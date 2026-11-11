<?php

namespace Tests\Feature;

use App\Mail\WorkbookUpdatedMail;
use App\Models\AlertChannel;
use App\Models\ApplicabilityProfile;
use App\Models\JobRun;
use App\Models\Obligation;
use App\Models\PolicyInstrument;
use App\Models\ProfileWorkbookVersion;
use App\Models\Subscription;
use App\Models\User;
use App\Services\Applicability\ObligationsRegister;
use App\Services\Workbooks\ComplianceWorkbook;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Tests\TestCase;

/**
 * The compliance workbook kept for a saved profile: built from the same screen as the free
 * register, versioned only when the screened records change, history and email saying what
 * changed, the owner's only, and the paid part gated once selling is on.
 */
class ProfileWorkbookTest extends TestCase
{
    use RefreshDatabase;

    private const ANSWERS = ['jurisdictions' => ['eu'], 'role' => null, 'use_case' => null, 'sector' => null, 'personal_data' => null, 'domains' => [], 'genai' => null];

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        $this->artisan('policy:import');
    }

    private function profile(?User $user = null): ApplicabilityProfile
    {
        $user ??= User::factory()->create();

        return ApplicabilityProfile::create(['user_id' => $user->id, 'name' => 'Support chatbot', 'answers' => self::ANSWERS]);
    }

    private function sheets(string $bytes): array
    {
        $path = tempnam(sys_get_temp_dir(), 'wbt').'.xlsx';
        file_put_contents($path, $bytes);
        try {
            $book = IOFactory::load($path);
            $out = [];
            foreach ($book->getAllSheets() as $sheet) {
                $out[$sheet->getTitle()] = $sheet->toArray(null, false, false, false);
            }

            return $out;
        } finally {
            @unlink($path);
        }
    }

    private function pro(User $user): void
    {
        config(['billing.enabled' => true, 'billing.plans.pro_monthly.product_id' => 'pdt_month']);
        Subscription::create(['user_id' => $user->id, 'provider' => 'dodo', 'provider_subscription_id' => 'sub_'.$user->id, 'product_id' => 'pdt_month', 'plan_key' => 'pro_monthly', 'status' => 'active', 'current_period_end' => now()->addMonth(), 'last_event_at' => now()]);
    }

    public function test_the_workbook_holds_every_screened_duty_with_dates_controls_evidence_and_sources(): void
    {
        $profile = $this->profile();
        $screened = app(ComplianceWorkbook::class)->snapshot($profile);
        $this->assertNotEmpty($screened);

        $response = $this->actingAs($profile->user)->get(route('profiles.workbook', $profile))->assertOk();
        $this->assertStringContainsString('compliance-workbook-support-chatbot-v1.xlsx', $response->headers->get('Content-Disposition'));
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
        $this->assertStringContainsString('private', $response->headers->get('Cache-Control'));
        $sheets = $this->sheets($response->getContent());

        foreach (['Gap analysis', 'Deadlines', 'Controls', 'Evidence', 'Sources', 'Version history', 'System'] as $name) {
            $this->assertArrayHasKey($name, $sheets, $name);
        }
        $gap = $sheets['Gap analysis'];
        $header = $gap[0];
        foreach (['Duty', 'Applies from', 'Recorded controls', 'Evidence a reviewer expects', 'Record review status', 'Status', 'Gap', 'Owner', 'Record'] as $col) {
            $this->assertContains($col, $header, $col);
        }
        $duties = array_filter(array_column(array_slice($gap, 1), array_search('Duty', $header, true)));
        foreach ($screened as $row) {
            $this->assertContains($row['title'], $duties, 'every screened duty is a row');
        }
        // Every row links its record, and a pending record says so instead of looking settled.
        $records = array_column(array_slice($gap, 1), array_search('Record', $header, true));
        $this->assertContains(route('obligations.show', array_key_first($screened)), $records);
        $states = implode('|', array_column(array_slice($gap, 1), array_search('Record review status', $header, true)));
        $this->assertDoesNotMatchRegularExpression('/\bMet\b/', $states, 'the workbook never marks a duty as met');

        $sourceUrls = array_column(array_slice($sheets['Sources'], 1), 5);
        $this->assertContains(PolicyInstrument::where('slug', 'eu-ai-act')->value('official_source_url'), $sourceUrls);
        $this->assertSame('First version', $sheets['Version history'][1][2]);
        $this->assertContains('Support chatbot', array_column($sheets['System'], 1));
    }

    public function test_a_version_is_stored_only_when_the_screened_records_change(): void
    {
        $profile = $this->profile();
        $workbook = app(ComplianceWorkbook::class);

        $first = $workbook->current($profile);
        $this->assertSame(1, $first->version);
        $this->assertNull($workbook->refresh($profile), 'the same records make no new version');
        $this->artisan('policy:import');
        $this->assertNull($workbook->refresh($profile), 'nor does a re-import of unchanged data');

        $slug = array_key_first($first->snapshot);
        Obligation::where('slug', $slug)->update(['applies_from' => '2031-03-01']);
        $second = $workbook->refresh($profile);
        $this->assertSame(2, $second->version);
        $this->assertSame([], $second->record_changes['added']);
        $this->assertSame([], $second->record_changes['removed']);
        $this->assertSame($slug, $second->record_changes['changed'][0]['slug']);
        $this->assertContains('applies_from', $second->record_changes['changed'][0]['fields']);

        // The new file carries the change in its history; the old version is still there as it was.
        $sheets = $this->sheets($workbook->xlsx($profile, $second));
        $history = array_slice($sheets['Version history'], 1);
        $this->assertContains('Changed', array_column($history, 2));
        $this->assertStringContainsString('application date', implode('|', array_column($history, 4)));
        $this->actingAs($profile->user)->get(route('profiles.workbook.version', [$profile, 1]))->assertOk();
        $this->actingAs($profile->user)->get(route('profiles.workbook.version', [$profile, 9]))->assertNotFound();
    }

    public function test_the_daily_job_emails_what_changed_once_and_respects_email_off(): void
    {
        Mail::fake();
        $profile = $this->profile();
        $quiet = $this->profile();
        AlertChannel::create(['user_id' => $quiet->user_id, 'kind' => 'email', 'enabled' => false]);

        $this->artisan('profiles:workbooks')->assertSuccessful();
        Mail::assertNothingSent();
        $this->assertSame(1, $profile->workbookVersions()->count(), 'the first run stores the baseline without email');

        $slug = array_key_first($profile->latestWorkbook()->snapshot);
        Obligation::where('slug', $slug)->update(['summary' => 'A revised summary of what the duty requires.']);
        $this->artisan('profiles:workbooks')->expectsOutputToContain('emails sent: 1')->assertSuccessful();

        Mail::assertSent(WorkbookUpdatedMail::class, 1);
        Mail::assertSent(WorkbookUpdatedMail::class, function (WorkbookUpdatedMail $mail) use ($profile, $slug) {
            $html = $mail->render();

            return $mail->hasTo($profile->user->email)
                && str_contains($html, 'version 2')
                && str_contains($html, e(route('obligations.show', $slug)))
                && str_contains($html, 'what it requires')
                && str_contains($mail->headers()->text['List-Unsubscribe'], '/alerts/unsubscribe/');
        });
        $this->assertNotNull($profile->latestWorkbook()->notified_at);
        $this->assertSame(2, $quiet->workbookVersions()->count(), 'the version is kept for an account with email off');

        $this->artisan('profiles:workbooks')->assertSuccessful();
        Mail::assertSent(WorkbookUpdatedMail::class, 1);
    }

    public function test_only_the_owner_can_see_change_or_download_a_system(): void
    {
        $profile = $this->profile();
        $other = User::factory()->create();

        $this->get(route('profiles.show', $profile))->assertRedirect(route('login'));
        $this->actingAs($other)->get(route('profiles.show', $profile))->assertNotFound();
        $this->actingAs($other)->get(route('profiles.workbook', $profile))->assertNotFound();
        $this->actingAs($other)->patch(route('profiles.update', $profile), ['name' => 'Taken'])->assertNotFound();
        $this->assertSame('Support chatbot', $profile->fresh()->name);
    }

    public function test_the_owner_describes_the_system_and_the_page_is_not_indexed(): void
    {
        $profile = $this->profile();
        $user = $profile->user;

        $html = $this->actingAs($user)->get(route('profiles.show', $profile))->assertOk()->getContent();
        $this->assertStringContainsString('<meta name="robots" content="noindex', $html);
        $this->assertStringContainsString('Download version 1 (XLSX)', $html);

        $this->actingAs($user)->patch(route('profiles.update', $profile), ['name' => 'Support chatbot EU', 'system_description' => 'Answers customer questions.', 'system_owner' => 'Customer operations', 'lifecycle' => 'in_use'])
            ->assertRedirect(route('profiles.show', $profile));
        $this->assertSame(['Support chatbot EU', 'Answers customer questions.', 'Customer operations', 'in_use'], [$profile->fresh()->name, $profile->fresh()->system_description, $profile->fresh()->system_owner, $profile->fresh()->lifecycle]);
        $this->actingAs($user)->patch(route('profiles.update', $profile), ['name' => 'X', 'lifecycle' => 'sold'])->assertSessionHasErrors('lifecycle');

        $sheets = $this->sheets($this->actingAs($user)->get(route('profiles.workbook', $profile))->getContent());
        $this->assertContains('Customer operations', array_column($sheets['System'], 1));
        $this->assertContains('In use', array_column($sheets['System'], 1));
        $this->assertSame(1, ProfileWorkbookVersion::count(), 'describing the system is not a change in the records');
    }

    public function test_with_selling_on_the_workbook_is_part_of_pro_and_the_free_register_stays_free(): void
    {
        config(['billing.enabled' => true, 'billing.plans.pro_monthly.product_id' => 'pdt_month']);
        $profile = $this->profile();
        $user = $profile->user;

        $this->actingAs($user)->get(route('profiles.workbook', $profile))->assertRedirect(route('pricing'));
        $html = $this->actingAs($user)->get(route('profiles.show', $profile))->assertOk()->getContent();
        $this->assertStringContainsString('data-track="workbook_upsell"', $html);
        $this->assertSame(0, ProfileWorkbookVersion::count(), 'nothing is built for an account without the workbook');
        $this->actingAs($user)->get(app(ObligationsRegister::class)->url('xlsx', $profile->answers))->assertOk();
        $this->artisan('profiles:workbooks')->expectsOutputToContain('Profiles checked: 0')->assertSuccessful();

        $this->pro($user);
        $this->actingAs($user)->get(route('profiles.workbook', $profile))->assertOk();
    }

    public function test_the_job_is_catalogued_and_scheduled(): void
    {
        $this->assertSame('profiles:workbooks', JobRun::JOBS['profile_workbooks']['command']);
        $this->artisan('schedule:list')->expectsOutputToContain('Compliance workbook updates')->assertSuccessful();
    }
}
