<?php

namespace Tests\Feature;

use App\Models\ResourceDownload;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * A template download is a lead. Sign-up left the organisation optional, so the
 * download record could exist with no way to follow it up. Name and organisation
 * are now confirmed at the point of download and kept on the account, and the
 * admin export can list one row per download with them.
 */
class DownloadLeadCaptureTest extends TestCase
{
    use RefreshDatabase;

    private const TOOL = '/guides/tools/ai-system-inventory-template/download';

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        Mail::fake();
    }

    public function test_a_download_is_refused_until_an_organisation_is_given(): void
    {
        $user = User::factory()->create(['organization_name' => null]);

        $this->actingAs($user)->post(self::TOOL, ['terms' => 1, 'name' => $user->name])
            ->assertSessionHasErrors('organization_name');

        $this->assertSame(0, ResourceDownload::count(), 'no download is recorded without the organisation');
        $this->assertNull($user->fresh()->organization_name);
    }

    public function test_the_confirmed_name_and_organisation_are_kept_on_the_account(): void
    {
        $user = User::factory()->create(['name' => 'Old Name', 'organization_name' => null]);

        $this->actingAs($user)->post(self::TOOL, ['terms' => 1, 'name' => 'Ada Example', 'organization_name' => 'Example Health Ltd'])
            ->assertRedirect();

        $fresh = $user->fresh();
        $this->assertSame('Ada Example', $fresh->name);
        $this->assertSame('Example Health Ltd', $fresh->organization_name);
        $this->assertSame(1, ResourceDownload::where('user_id', $user->id)->count());
    }

    public function test_the_form_is_prefilled_from_the_account_so_a_returning_reader_confirms_rather_than_retypes(): void
    {
        $user = User::factory()->create(['name' => 'Grace Example', 'organization_name' => 'Example Agency']);

        $html = $this->actingAs($user)->get('/guides/tools/ai-system-inventory-template')->getContent();

        $this->assertStringContainsString('value="Grace Example"', $html);
        $this->assertStringContainsString('value="Example Agency"', $html);
        $this->assertStringContainsString('name="organization_name"', $html);
        $this->assertStringContainsString('required', $html);
    }

    public function test_registration_requires_an_organisation_only_when_the_reader_came_for_a_template(): void
    {
        // Exactly what the registration form sends: it has no phone field, which is why the
        // user_infos.phone_no column had to become nullable for this path to work at all.
        $payload = [
            'name' => 'Test User', 'email' => 'test@example.com', 'password' => 'Password!123',
            'password_confirmation' => 'Password!123', 'terms_condition' => true,
        ];

        // Ordinary sign-up: organisation stays optional.
        $this->post('/register', $payload)->assertRedirect();
        $this->assertAuthenticated();
        auth()->logout();

        // Sign-up that began at a template's download gate: organisation is required.
        $this->get('/guides/tools/ai-system-inventory-template/download')->assertSessionHas('url.intended');
        $this->get('/register')->assertOk()->assertDontSee('Organisation <span class="text-brand-muted font-normal">(optional)</span>', false);
        $this->post('/register', ['email' => 'second@example.com'] + $payload)->assertSessionHasErrors('organization_name');
        $this->assertGuest();

        $this->post('/register', ['email' => 'second@example.com', 'organization_name' => 'Example Ltd'] + $payload)->assertRedirect();
        $this->assertSame('free-tool', User::where('email', 'second@example.com')->value('signup_source'));
        $this->assertSame('Example Ltd', User::where('email', 'second@example.com')->value('organization_name'));
    }

    public function test_the_admin_can_export_one_row_per_download_with_the_lead_details(): void
    {
        config(['aipolicytracker.admin_emails' => ['editor@example.test']]);
        $admin = User::factory()->create(['email' => 'editor@example.test']);
        $reader = User::factory()->create(['name' => 'Ada Example', 'email' => 'ada@example.com', 'organization_name' => 'Example Health Ltd']);
        $this->actingAs($reader)->post(self::TOOL, ['terms' => 1, 'name' => $reader->name, 'organization_name' => $reader->organization_name])->assertRedirect();

        $csv = $this->actingAs($admin)->get(route('backend.admin.downloads.export', ['rows' => 'downloads']))
            ->assertOk()
            ->assertHeader('Content-Type', 'text/csv; charset=UTF-8')
            ->streamedContent();

        $lines = array_values(array_filter(explode("\n", trim($csv))));
        $this->assertSame(['downloaded_at', 'tool', 'version', 'file', 'name', 'email', 'organization', 'signup_source', 'marketing_consent', 'referrer'], str_getcsv($lines[0]));
        $this->assertCount(2, $lines, 'one header and one download');
        $row = str_getcsv($lines[1]);
        $this->assertSame('ai-system-inventory-template', $row[1]);
        $this->assertSame('Ada Example', $row[4]);
        $this->assertSame('ada@example.com', $row[5]);
        $this->assertSame('Example Health Ltd', $row[6]);

        // The per-user export is unchanged and still carries the organisation.
        $users = $this->actingAs($admin)->get(route('backend.admin.downloads.export'))->assertOk()->streamedContent();
        $this->assertStringContainsString('Example Health Ltd', $users);
    }

    public function test_the_export_is_admin_only(): void
    {
        $reader = User::factory()->create();
        $this->actingAs($reader)->get(route('backend.admin.downloads.export', ['rows' => 'downloads']))->assertRedirect(route('home'));
        // A guest is turned away before any CSV is written; the app's own tests assert the
        // redirect without pinning its target, and so does this one.
        $guest = $this->get(route('backend.admin.downloads.export'));
        $guest->assertRedirect();
        $this->assertStringNotContainsString('downloaded_at', (string) $guest->getContent());
    }
}
