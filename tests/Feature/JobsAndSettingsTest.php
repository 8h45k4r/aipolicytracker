<?php

namespace Tests\Feature;

use App\Http\Middleware\EnsureAdminSecondFactor;
use App\Models\AppSetting;
use App\Models\JobRun;
use App\Models\User;
use App\Providers\AppSettingsServiceProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * The platform is operable from the browser: every scheduled job has a page,
 * a recorded outcome and a run button, and the settings that decide how the
 * site behaves are stored rather than baked into a deploy.
 */
class JobsAndSettingsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['aipolicytracker.admin_emails' => ['admin@example.com']]);
        $this->seed();
    }

    private ?User $owner = null;

    /** The seeded administrator, created once per test. */
    private function owner(): User
    {
        return $this->owner ??= User::firstWhere('email', 'admin@example.com') ?? User::factory()->create(['email' => 'admin@example.com']);
    }

    private function asOwner(): static
    {
        $owner = $this->owner();

        return $this->actingAs($owner)->withSession([EnsureAdminSecondFactor::SESSION_KEY => $owner->id, 'auth.password_confirmed_at' => time()]);
    }

    public function test_every_scheduled_job_is_listed_with_its_timetable(): void
    {
        $page = $this->asOwner()->get('/backend/admin/jobs')->assertOk();
        foreach (JobRun::JOBS as $job) {
            $page->assertSee($job['label'])->assertSee($job['command']);
        }
        $page->assertSee('Mondays 07:00 UTC')->assertSee('Recent runs');
    }

    public function test_a_job_runs_from_the_browser_and_records_how_it_ended(): void
    {
        Mail::fake();
        $this->asOwner()->post('/backend/admin/jobs/policy_validate')->assertRedirect(route('backend.admin.jobs'));

        $run = JobRun::where('job', 'policy_validate')->firstOrFail();
        $this->assertSame('admin', $run->trigger);
        $this->assertNotNull($run->finished_at);
        $this->assertTrue($run->succeeded(), (string) $run->output);
        $this->assertStringContainsString('validated', (string) $run->output);
        $this->asOwner()->get('/backend/admin/jobs')->assertOk()->assertSee('ok');
    }

    /**
     * The scheduler runs jobs in the same method the HTTP entry points use. A time
     * limit belongs to the request, not to the job: external:import rebuilds
     * thousands of rows and used to be killed part-way through a scheduled run,
     * then recorded as a fatal. This asserts the limit is left alone under CLI,
     * which is where the scheduler and `php artisan` live.
     */
    public function test_a_scheduled_run_does_not_impose_a_request_time_limit(): void
    {
        $this->assertSame('cli', PHP_SAPI, 'the suite must run under CLI for this to mean anything');
        @set_time_limit(0); // CLI's own default: no limit
        $before = ini_get('max_execution_time');

        $run = JobRun::run('policy_validate', 'schedule');

        $this->assertTrue($run->succeeded(), (string) $run->output);
        $this->assertSame($before, ini_get('max_execution_time'), 'a scheduled run must not cap its own execution time');
        $this->assertSame('0', (string) ini_get('max_execution_time'));
    }

    public function test_a_job_that_sends_mail_is_only_reachable_behind_a_password_confirmation(): void
    {
        $owner = $this->owner();
        // No confirmation in the session: the unconfirmed route bounces to the password page.
        $this->actingAs($owner)->withSession([EnsureAdminSecondFactor::SESSION_KEY => $owner->id])
            ->post('/backend/admin/jobs/digest')->assertRedirect(route('password.confirm'));
        $this->assertSame(0, JobRun::where('job', 'digest')->count());
    }

    public function test_the_cron_endpoint_records_its_run(): void
    {
        Mail::fake();
        AppSetting::put('cron_token', str_repeat('k', 32));

        $this->withHeaders(['Authorization' => 'Bearer '.str_repeat('k', 32)])->postJson('/cron/alerts')->assertOk();
        $run = JobRun::where('job', 'alerts')->firstOrFail();
        $this->assertSame('cron', $run->trigger);
        $this->assertNull($run->user_id);
    }

    public function test_stored_settings_override_the_environment_for_site_behaviour(): void
    {
        AppSetting::put('contact_email', 'ops@example.org');
        AppSetting::put('social_cards_enabled', 'off');
        AppSetting::put('stale_after_days', '90');
        AppSetting::put('x_handle', '@example');
        (new AppSettingsServiceProvider($this->app))->boot();

        $this->assertSame('ops@example.org', config('aipolicytracker.contact_email'));
        $this->assertFalse(config('social.cards'));
        $this->assertSame(90, config('aipolicytracker.stale_after_days'));
        $this->assertSame('@example', config('aipolicytracker.x_handle'));

        $this->get('/.well-known/security.txt')->assertOk()->assertSee('ops@example.org');
        $this->get('/')->assertOk()->assertSee('content="@example"', false);
    }

    public function test_the_settings_page_offers_the_site_switches(): void
    {
        $page = $this->asOwner()->get('/backend/admin/settings')->assertOk();
        foreach (['contact_email', 'google_analytics_id', 'social_cards_enabled', 'email_domain_enforcement', 'stale_after_days'] as $key) {
            $page->assertSee('name="'.$key.'"', false);
        }
        $this->asOwner()->post('/backend/admin/settings', ['contact_email' => 'hello@example.org', 'social_cards_enabled' => 'off'])->assertRedirect();
        $this->assertSame('hello@example.org', AppSetting::get('contact_email'));
        $this->assertSame('off', AppSetting::get('social_cards_enabled'));
    }
}
