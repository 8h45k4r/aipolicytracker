<?php

namespace Tests\Feature;

use App\Support\ErrorAlert;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Mail\Transport\ResendTransport;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * The audit's reliability findings: mail with no time limit, no alert on production errors,
 * day-long scheduler locks after a killed job, and background processes nobody restarted.
 */
class RuntimeReliabilityTest extends TestCase
{
    public function test_the_resend_transport_and_smtp_give_up_after_the_configured_time(): void
    {
        config(['mail.mailers.resend.key' => 're_test_key_placeholder', 'mail.mailers.resend.timeout' => 7]);
        $transport = Mail::mailer('resend')->getSymfonyTransport();
        $this->assertInstanceOf(ResendTransport::class, $transport);

        $client = (fn () => $this->resend)->call($transport);
        $transporter = (fn () => $this->transporter)->call($client);
        $guzzle = (fn () => $this->client)->call($transporter);
        $this->assertSame(7.0, (float) $guzzle->getConfig('timeout'));
        $this->assertSame(5.0, (float) $guzzle->getConfig('connect_timeout'));

        $this->assertSame(10, config('mail.mailers.smtp.timeout'));
    }

    private function arrayMessages(): array
    {
        return iterator_to_array(Mail::mailer('array')->getSymfonyTransport()->messages());
    }

    public function test_a_production_error_emails_the_admins_once_per_hour_and_carries_no_request_data(): void
    {
        $this->app['env'] = 'production';
        config(['mail.default' => 'array', 'aipolicytracker.admin_emails' => ['ops@example.org'], 'aipolicytracker.error_alerts' => true]);
        $e = new \RuntimeException('Database went away');

        ErrorAlert::report($e);
        ErrorAlert::report($e);
        $messages = $this->arrayMessages();
        $this->assertCount(1, $messages, 'the same error is held for an hour');
        $body = $messages[0]->getOriginalMessage()->getTextBody();
        $this->assertStringContainsString('RuntimeException', $body);
        $this->assertStringContainsString('Database went away', $body);
        $this->assertStringContainsString('tests/Feature/RuntimeReliabilityTest.php:', $body);
        $this->assertSame('ops@example.org', $messages[0]->getOriginalMessage()->getTo()[0]->getAddress());

        ErrorAlert::report(new \LogicException('Another'));
        $this->assertCount(2, $this->arrayMessages(), 'a different error is its own alert');
    }

    public function test_alerts_stop_at_the_daily_cap(): void
    {
        $this->app['env'] = 'production';
        config(['mail.default' => 'array', 'aipolicytracker.admin_emails' => ['ops@example.org']]);
        // The day's allowance already used up by other errors.
        Cache::put('error-alert-count:'.now()->toDateString(), ErrorAlert::DAILY_CAP, now()->addDay());
        ErrorAlert::report(new \RuntimeException('one more'));
        $this->assertCount(0, $this->arrayMessages());
    }

    public function test_no_alert_outside_production_without_admins_or_when_switched_off(): void
    {
        config(['mail.default' => 'array', 'aipolicytracker.admin_emails' => ['ops@example.org']]);
        ErrorAlert::report(new \RuntimeException('local'));
        $this->app['env'] = 'production';
        config(['aipolicytracker.error_alerts' => false]);
        ErrorAlert::report(new \RuntimeException('off'));
        config(['aipolicytracker.error_alerts' => true, 'aipolicytracker.admin_emails' => []]);
        ErrorAlert::report(new \RuntimeException('nobody'));
        $this->assertCount(0, $this->arrayMessages());
    }

    public function test_a_killed_job_frees_its_scheduler_lock_within_an_hour(): void
    {
        $events = collect(app(Schedule::class)->events())->filter(fn ($e) => str_starts_with((string) $e->description, '') && $e->withoutOverlapping);
        $this->assertNotEmpty($events);
        foreach ($events as $event) {
            $this->assertSame(60, $event->expiresAt, ($event->description ?? 'job').' keeps its lock too long');
        }
    }

    public function test_the_container_supervises_and_stops_its_background_processes(): void
    {
        $entrypoint = file_get_contents(base_path('docker/entrypoint.sh'));
        $this->assertStringContainsString('supervise queue php artisan queue:work', $entrypoint);
        $this->assertStringContainsString('supervise scheduler', $entrypoint);
        $this->assertMatchesRegularExpression('/for pid in \$queue \$scheduler \$imports; do kill -TERM/', $entrypoint);
        $this->assertStringContainsString('stop_grace_period: 30s', file_get_contents(base_path('deploy/compose.yml')));
        $this->assertStringContainsString('docker tag aip-app:rollback aip-app:latest', file_get_contents(base_path('deploy/remote-deploy.sh')));
    }
}
