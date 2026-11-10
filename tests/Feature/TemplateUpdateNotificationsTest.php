<?php

namespace Tests\Feature;

use App\Mail\TemplateUpdateMail;
use App\Models\JobRun;
use App\Models\Subscriber;
use App\Models\TemplateDownloadRequest;
use App\Models\TemplateUpdateNotice;
use App\Models\TemplateUpdateOptout;
use App\Models\TemplateVersion;
use App\Services\Templates\TemplateBuilder;
use App\Services\Templates\TemplateUpdateNotices;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * "Email me when this template gets a new version": consent → new version → one email per
 * address → one-click unsubscribe, with retries, invalid addresses, duplicate builds and two
 * runs racing for the same notice.
 */
class TemplateUpdateNotificationsTest extends TestCase
{
    use RefreshDatabase;

    private const SLUG = 'ai-risk-register';

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        $this->artisan('policy:import');
        app(TemplateBuilder::class)->build(self::SLUG);
        Mail::fake();
    }

    private function request(string $email, bool $updates, string $slug = self::SLUG): TemplateDownloadRequest
    {
        return TemplateDownloadRequest::create([
            'template_slug' => $slug, 'name' => 'Reader', 'email' => $email, 'company' => 'Acme',
            'terms_accepted_at' => now(), 'marketing_consent_at' => $updates ? now() : null,
        ]);
    }

    /** A real content change: the stored hash no longer matches, so the next build is v+1. */
    private function newVersion(): TemplateVersion
    {
        TemplateVersion::latestFor(self::SLUG)->update(['content_hash' => str_repeat('0', 64)]);
        $result = app(TemplateBuilder::class)->build(self::SLUG);
        $this->assertTrue($result['changed']);

        return $result['version'];
    }

    public function test_only_readers_who_ticked_the_box_are_queued_and_mailed_once(): void
    {
        $this->request('Yes@Acme.com', true);
        $this->request('yes@acme.com', true); // same reader, second download
        $this->request('no@acme.com', false);
        $this->request('other@acme.com', true, 'ai-system-inventory'); // a different template
        $version = $this->newVersion();

        $notices = TemplateUpdateNotice::all();
        $this->assertSame(['yes@acme.com'], $notices->pluck('email')->all());
        $this->assertSame($version->id, $notices->first()->template_version_id);

        $this->artisan('templates:notify')->assertSuccessful();
        Mail::assertSent(TemplateUpdateMail::class, 1);
        Mail::assertSent(TemplateUpdateMail::class, fn (TemplateUpdateMail $m) => $m->hasTo('yes@acme.com'));
        $this->assertSame('sent', $notices->first()->fresh()->status);
        $this->assertNotNull($notices->first()->fresh()->sent_at);

        // A second run sends nothing more.
        $this->artisan('templates:notify')->assertSuccessful();
        Mail::assertSent(TemplateUpdateMail::class, 1);
    }

    public function test_the_email_names_the_template_version_changes_link_and_related_and_has_one_click_unsubscribe(): void
    {
        $this->request('reader@acme.com', true);
        $version = $this->newVersion();
        app(TemplateUpdateNotices::class)->send();

        Mail::assertSent(TemplateUpdateMail::class, function (TemplateUpdateMail $mail) use ($version) {
            $html = $mail->render();
            $headers = $mail->headers()->text;

            return str_contains($mail->envelope()->subject, $version->label())
                && str_contains($html, $version->label())
                && str_contains($html, e(route('templates.show', self::SLUG)))
                && str_contains($html, 'Related templates')
                && str_contains($html, 'What changed')
                && $headers['List-Unsubscribe-Post'] === 'List-Unsubscribe=One-Click'
                && str_contains($headers['List-Unsubscribe'], '/templates/updates/unsubscribe/')
                && ! str_contains($headers['List-Unsubscribe'], 'reader@acme.com');
        });
    }

    public function test_first_build_and_unchanged_or_forced_rebuilds_notify_nobody(): void
    {
        $this->request('reader@acme.com', true);
        app(TemplateBuilder::class)->build('ai-system-inventory'); // first build of another template
        app(TemplateBuilder::class)->build(self::SLUG); // unchanged
        app(TemplateBuilder::class)->build(self::SLUG, force: true); // same content, new files

        $this->assertSame(0, TemplateUpdateNotice::count());
    }

    public function test_queueing_the_same_version_twice_creates_no_duplicates(): void
    {
        $this->request('reader@acme.com', true);
        $version = $this->newVersion();

        $this->assertSame(0, app(TemplateUpdateNotices::class)->queue($version));
        $this->assertSame(1, TemplateUpdateNotice::count());
    }

    public function test_one_click_unsubscribe_stops_pending_and_future_emails(): void
    {
        $this->request('reader@acme.com', true);
        $this->newVersion();
        $notice = TemplateUpdateNotice::sole();
        $url = $notice->unsubscribeUrl();

        // Opening the link changes nothing; a scanner following it must not unsubscribe.
        $this->get($url)->assertOk()->assertSee('Stop these emails');
        $this->assertSame(0, TemplateUpdateOptout::count());

        // RFC 8058: the mail client POSTs to the List-Unsubscribe URL, with no CSRF token.
        $this->post($url)->assertRedirect();
        $this->assertSame('email', TemplateUpdateOptout::sole()->source);
        $this->assertSame('suppressed', $notice->fresh()->status);
        $this->get($url)->assertOk()->assertSee('Template update emails stopped');

        $this->artisan('templates:notify')->assertSuccessful();
        Mail::assertNothingSent();

        $this->newVersion();
        $this->assertSame(1, TemplateUpdateNotice::count(), 'an opted-out address is not queued again');
    }

    public function test_a_tampered_unsubscribe_link_is_refused(): void
    {
        $this->request('reader@acme.com', true);
        $this->newVersion();
        $notice = TemplateUpdateNotice::sole();

        $this->get('/templates/updates/unsubscribe/'.$notice->id)->assertForbidden();
        $this->post('/templates/updates/unsubscribe/'.$notice->id)->assertForbidden();
        $this->assertSame(0, TemplateUpdateOptout::count());
    }

    public function test_ticking_the_box_again_after_unsubscribing_opts_back_in(): void
    {
        $this->request('reader@acme.com', true);
        app(TemplateUpdateNotices::class)->unsubscribe('reader@acme.com', 'email');
        $this->travel(1)->minutes();
        $this->request('reader@acme.com', true);

        $this->newVersion();
        $this->assertSame(['reader@acme.com'], TemplateUpdateNotice::pluck('email')->all());
    }

    public function test_an_unsubscribe_after_queueing_suppresses_the_send(): void
    {
        $this->request('reader@acme.com', true);
        $this->newVersion();
        // Opted out by another route after the notice was queued, without touching it.
        TemplateUpdateOptout::create(['email' => 'reader@acme.com', 'unsubscribed_at' => now()->addSecond(), 'source' => 'admin']);

        $counts = app(TemplateUpdateNotices::class)->send();
        $this->assertSame(1, $counts['suppressed']);
        $this->assertSame('suppressed', TemplateUpdateNotice::sole()->status);
        Mail::assertNothingSent();
    }

    public function test_an_invalid_address_fails_at_once_and_is_never_retried(): void
    {
        $this->request('not an address', true);
        $this->newVersion();

        $counts = app(TemplateUpdateNotices::class)->send();
        $notice = TemplateUpdateNotice::sole();
        $this->assertSame(1, $counts['failed']);
        $this->assertSame('failed', $notice->status);
        $this->assertSame('Not a valid email address.', $notice->last_error);
        $this->assertNull($notice->next_attempt_at);
        Mail::assertNothingSent();
    }

    public function test_a_failing_send_is_retried_with_backoff_then_marked_failed(): void
    {
        $this->request('reader@acme.com', true);
        $this->newVersion();
        Mail::shouldReceive('to')->andThrow(new \RuntimeException('SMTP unavailable'));
        $notices = app(TemplateUpdateNotices::class);

        $this->assertSame(1, $notices->send()['retry']);
        $notice = TemplateUpdateNotice::sole();
        $this->assertSame(['pending', 1], [$notice->status, $notice->attempts]);
        $this->assertStringContainsString('SMTP unavailable', $notice->last_error);
        $this->assertTrue($notice->next_attempt_at->isFuture());

        // Not due yet: nothing happens.
        $this->assertSame(0, array_sum($notices->send()));

        $this->travel(31)->minutes();
        $this->assertSame(1, $notices->send()['retry']);
        $this->assertSame(2, $notice->fresh()->attempts);

        $this->travel(181)->minutes();
        $this->assertSame(1, $notices->send()['failed']);
        $notice->refresh();
        $this->assertSame(['failed', 3], [$notice->status, $notice->attempts]);
        $this->assertNull($notice->next_attempt_at);

        $this->travel(1)->days();
        $this->assertSame(0, array_sum($notices->send()), 'a failed notice is never retried');
    }

    public function test_a_retry_that_succeeds_is_sent_once(): void
    {
        $this->request('reader@acme.com', true);
        $this->newVersion();
        TemplateUpdateNotice::query()->update(['attempts' => 1, 'next_attempt_at' => now()->subMinute(), 'last_error' => 'earlier failure']);

        $this->assertSame(1, app(TemplateUpdateNotices::class)->send()['sent']);
        $notice = TemplateUpdateNotice::sole();
        $this->assertSame(['sent', 2, null], [$notice->status, $notice->attempts, $notice->last_error]);
        Mail::assertSent(TemplateUpdateMail::class, 1);
    }

    public function test_a_notice_claimed_by_another_run_is_not_sent_twice_unless_that_run_died(): void
    {
        $this->request('reader@acme.com', true);
        $this->newVersion();
        // Another run has just claimed it.
        TemplateUpdateNotice::query()->update(['status' => 'sending', 'updated_at' => now()]);

        $this->assertSame(0, array_sum(app(TemplateUpdateNotices::class)->send()));
        Mail::assertNothingSent();

        // That run died mid-send: after the stale window the notice is taken up again, once.
        $this->travel(TemplateUpdateNotice::STALE_MINUTES + 1)->minutes();
        $this->assertSame(1, app(TemplateUpdateNotices::class)->send()['sent']);
        $this->assertSame(0, array_sum(app(TemplateUpdateNotices::class)->send()));
        Mail::assertSent(TemplateUpdateMail::class, 1);
    }

    public function test_readers_are_not_added_to_the_digest_and_digest_subscribers_are_untouched(): void
    {
        $subscriber = Subscriber::create(['email' => 'digest@acme.com', 'token' => Subscriber::newToken(), 'topics' => ['all'], 'confirmed_at' => now()]);
        $this->request('reader@acme.com', true);
        $this->newVersion();
        app(TemplateUpdateNotices::class)->send();

        $notice = TemplateUpdateNotice::sole();
        $this->post($notice->unsubscribeUrl())->assertRedirect();

        $this->assertSame(['digest@acme.com'], Subscriber::pluck('email')->all());
        $this->assertNull($subscriber->fresh()->unsubscribed_at);
        $this->assertNotNull(TemplateDownloadRequest::sole()->marketing_consent_at, 'the stored request is not rewritten');
    }

    public function test_the_job_is_catalogued_and_scheduled_only_when_work_is_waiting(): void
    {
        $this->assertSame('templates:notify', JobRun::JOBS['template_notices']['command']);
        $this->assertFalse(TemplateUpdateNotice::due()->exists());

        $this->request('reader@acme.com', true);
        $this->newVersion();
        $this->assertTrue(TemplateUpdateNotice::due()->exists());
    }

    public function test_the_request_form_describes_exactly_what_the_box_does(): void
    {
        $html = $this->get(route('templates.show', self::SLUG))->assertOk()->getContent();
        $this->assertStringContainsString('Email me when this template gets a new version, with links to related templates', $html);
        $this->assertStringNotContainsString('about related templates and guides', $html, 'no promise of mail that is never sent');
    }
}
