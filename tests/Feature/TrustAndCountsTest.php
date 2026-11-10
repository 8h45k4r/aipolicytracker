<?php

namespace Tests\Feature;

use App\Enums\AdminRole;
use App\Mail\AdminInvitationMail;
use App\Models\Jurisdiction;
use App\Models\PolicyInstrument;
use App\Models\User;
use App\Services\Hubs\HubCatalog;
use App\Services\PolicyData\PolicyDataRepository;
use App\Services\PolicyData\PolicyDataValidator;
use App\Services\PolicyData\SchemaValidator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Findings from the October 2026 SEO audit that a compliance reader would catch first:
 * pages contradicting their own records, counts that disagree, and the reviewer roster.
 */
class TrustAndCountsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('policy:import');
    }

    public function test_a_privacy_law_is_not_counted_as_binding_ai_law(): void
    {
        $this->assertFalse(PolicyInstrument::where('slug', 'india-dpdp-act')->value('ai_specific'));
        $india = Jurisdiction::where('slug', 'india')->firstOrFail();
        $html = $this->followingRedirects()->get($india->url())->assertOk()->getContent();

        // The audit's contradiction: "Is there a federal AI law in India?" answered "Yes" from
        // the data protection act while the page said India has no AI law. The answer now
        // counts AI-specific records and names the privacy law separately.
        $this->assertStringNotContainsString('Binding national AI legislation is recorded: India DPDP Act', $html);
        if (PolicyInstrument::published()->where('jurisdiction_id', $india->id)->bindingAiLaw()->doesntExist()) {
            $this->assertStringContainsString('No binding national AI instrument is recorded for India', $html);
            $this->assertStringContainsString('not AI-specific but also apply to AI: India DPDP Act', $html);
        }
    }

    public function test_the_region_hub_counts_only_adopted_ai_specific_law(): void
    {
        $html = $this->get(route('hubs.show', 'ai-regulation-asia'))->assertOk()->getContent();
        preg_match('#Which countries in Asia have binding AI law\?.*?"text":"([^"]*)"#s', $html, $m);
        $this->assertNotEmpty($m);
        foreach (Jurisdiction::where('region', 'Asia')->get() as $j) {
            $counts = $j->policyInstruments()->published()->bindingAiLaw()->whereIn('status', ['adopted', 'in_force', 'partially_applicable'])->exists();
            if (! $counts) {
                $this->assertStringNotContainsString($j->name.',', $m[1], $j->name.' has no adopted AI-specific binding law');
            }
        }
    }

    public function test_a_binding_law_whose_name_does_not_mention_ai_must_say_whether_it_is_about_ai(): void
    {
        $dir = storage_path('framework/testing/data-'.uniqid());
        File::copyDirectory(base_path('data'), $dir);
        try {
            $file = $dir.'/policies/india/india-dpdp-act.yaml';
            File::put($file, preg_replace('/^ai_specific: false\n/m', '', File::get($file)));
            $repository = new PolicyDataRepository($dir);
            $errors = (new PolicyDataValidator($repository, new SchemaValidator($repository->schemaDir())))->run();
            $this->assertStringContainsString('$.ai_specific', implode("\n", $errors['policies/india/india-dpdp-act.yaml'] ?? []));
        } finally {
            File::deleteDirectory($dir);
        }
    }

    public function test_the_release_history_states_the_live_counts(): void
    {
        $html = $this->get(route('open-data'))->assertOk()->getContent();
        $this->assertStringContainsString(number_format(PolicyInstrument::published()->count()).' policy instruments', $html);
        $this->assertStringContainsString('Historical figures: 10 jurisdictions', $html);
        $this->assertStringNotContainsString('212', json_decode(File::get(public_path('site.webmanifest')), true)['description']);
    }

    public function test_a_timeline_shows_each_milestone_once(): void
    {
        $nepal = Jurisdiction::where('slug', 'nepal')->firstOrFail();
        $events = HubCatalog::timeline(PolicyInstrument::published()->with('deadlines')->where('jurisdiction_id', $nepal->id)->get());
        $perDay = collect($events)->countBy(fn ($e) => $e['policy']->id.'|'.$e['date']->toDateString());
        $this->assertSame([], $perDay->filter(fn ($n) => $n > 1)->keys()->all(), 'one line per instrument per day');
        $this->assertFalse(collect($events)->contains(fn ($e) => str_contains($e['label'], 'approved by the Council of Ministers')));
    }

    public function test_the_nepal_concept_paper_is_not_shown_as_adopted(): void
    {
        $paper = PolicyInstrument::where('slug', 'nepal-concept-paper-on-the-use-and-practice-of-artificial-intelligence-in-nepal')->firstOrFail();
        $this->assertNull($paper->adopted_on);
        $this->assertSame('2024-07-05', $paper->published_on->toDateString());
        $this->assertSame('superseded', $paper->status);
    }

    public function test_reviewers_without_a_declaration_stay_off_the_public_roster(): void
    {
        $html = $this->get(route('reviewers'))->assertOk()->getContent();
        $this->assertStringNotContainsString('Joyce Loksee Ho', $html);
        $this->assertStringNotContainsString('Kailash Bohara', $html);
    }

    public function test_admin_invite_creates_the_account_with_its_role_and_sends_the_link(): void
    {
        Mail::fake();
        config(['aipolicytracker.admin_emails' => ['owner@example.test']]);
        User::factory()->create(['email' => 'owner@example.test', 'email_verified_at' => now()]);

        $this->artisan('admin:invite', ['email' => 'Joyce@AIPolicyTracker.org', 'name' => 'Joyce Loksee Ho'])->assertExitCode(0);
        $joyce = User::where('email', 'joyce@aipolicytracker.org')->firstOrFail();
        $this->assertSame(AdminRole::Reviewer, $joyce->adminRole());
        $this->assertSame('Joyce Loksee Ho', $joyce->name);
        Mail::assertSent(AdminInvitationMail::class, fn ($m) => $m->hasTo('joyce@aipolicytracker.org'));

        // An existing account is left as it is.
        $this->artisan('admin:invite', ['email' => 'joyce@aipolicytracker.org', 'name' => 'Someone Else', '--role' => 'editor'])->assertExitCode(0);
        $this->assertSame('Joyce Loksee Ho', $joyce->fresh()->name);
        $this->assertSame(AdminRole::Reviewer, $joyce->fresh()->adminRole());

        $this->artisan('admin:invite', ['email' => 'kailash@aipolicytracker.org', 'name' => 'Kailash Bohara', '--role' => 'owner'])->assertExitCode(1);
        $this->assertNull(User::where('email', 'kailash@aipolicytracker.org')->first());
    }
}
