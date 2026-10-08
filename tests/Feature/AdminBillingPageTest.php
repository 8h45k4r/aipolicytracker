<?php

namespace Tests\Feature;

use App\Enums\AdminRole;
use App\Models\AdminAuditLog;
use App\Models\BillingEvent;
use App\Models\BillingPayment;
use App\Models\Follow;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Admin → Billing: summary figures, payments, subscriptions, webhooks with re-apply, free-tier quota. */
class AdminBillingPageTest extends TestCase
{
    use RefreshDatabase;

    private function owner(): User
    {
        config(['aipolicytracker.admin_emails' => ['owner@example.org']]);

        return User::factory()->create(['email' => 'owner@example.org']);
    }

    private function sub(string $id, array $attrs = []): Subscription
    {
        return Subscription::forceCreate($attrs + [
            'user_id' => User::factory()->create()->id, 'provider' => 'dodo', 'provider_subscription_id' => $id,
            'product_id' => 'pdt_month', 'plan_key' => 'pro_monthly', 'status' => 'active',
            'current_period_end' => now()->addMonth(), 'last_event_at' => now()->subDay(), 'last_event_type' => 'subscription.active',
        ]);
    }

    private function payment(string $id, string $subId, array $attrs = []): BillingPayment
    {
        return BillingPayment::forceCreate($attrs + ['provider' => 'dodo', 'provider_payment_id' => $id, 'provider_subscription_id' => $subId, 'total_amount' => 2900, 'currency' => 'USD', 'status' => 'succeeded', 'paid_at' => now()->subDays(2)]);
    }

    private function event(string $id, string $type, ?string $outcome, array $attrs = []): BillingEvent
    {
        return BillingEvent::forceCreate($attrs + ['provider' => 'dodo', 'event_id' => $id, 'event_type' => $type, 'payload' => ['type' => $type, 'data' => []], 'received_at' => now()->subHour(), 'outcome' => $outcome, 'processed_at' => $outcome ? now() : null]);
    }

    /** @return list<array<int,string>> */
    private function csv(string $url, User $as): array
    {
        $body = $this->actingAs($as)->get($url)->assertOk()->assertHeader('Content-Type', 'text/csv; charset=UTF-8')->streamedContent();
        $this->assertStringStartsWith("\xEF\xBB\xBF", $body);

        return array_map('str_getcsv', array_values(array_filter(explode("\n", trim(substr($body, 3))))));
    }

    public function test_only_an_account_holding_billing_can_open_or_act_on_billing(): void
    {
        $owner = $this->owner();
        $member = User::factory()->create();
        $editor = User::factory()->create(['admin_role' => AdminRole::Editor]);
        $event = $this->event('msg_err', 'subscription.active', 'error');

        $this->actingAs($member)->get(route('backend.admin.billing.index'))->assertRedirect('/');
        $this->actingAs($member)->post(route('backend.admin.billing.events.reapply', $event))->assertRedirect('/');
        $this->actingAs($editor)->get(route('backend.admin.billing.index'))->assertForbidden();
        $this->actingAs($editor)->get(route('backend.admin.billing.payments.export'))->assertForbidden();
        $this->actingAs($editor)->post(route('backend.admin.billing.events.reapply', $event))->assertForbidden();
        foreach (['subscriptions', 'payments', 'webhooks', 'quota'] as $tab) {
            $this->actingAs($owner)->get(route('backend.admin.billing.index', ['tab' => $tab]))->assertOk();
        }
    }

    public function test_summary_figures_come_from_the_rows_that_grant_access_and_the_payments_received(): void
    {
        $this->sub('sub_m1');
        $this->sub('sub_y1', ['product_id' => 'pdt_year', 'plan_key' => 'pro_yearly']);
        $this->sub('sub_cancel', ['status' => 'cancelled', 'cancel_at_period_end' => true]);
        $this->sub('sub_grace', ['status' => 'on_hold', 'on_hold_at' => now()->subDays(2)]);
        $this->sub('sub_lapsed', ['status' => 'on_hold', 'on_hold_at' => now()->subDays(30)]);
        $this->sub('sub_refunded', ['revoked_at' => now()->subDay(), 'revoked_reason' => 'refund']);
        $this->sub('sub_unknown', ['plan_key' => null, 'product_id' => 'pdt_other']);
        $this->payment('pay_1', 'sub_m1');
        $this->payment('pay_2', 'sub_y1', ['total_amount' => 29000]);
        $this->payment('pay_old', 'sub_m1', ['paid_at' => now()->subDays(40)]);
        $this->event('msg_ref', 'refund.succeeded', 'applied');
        $this->event('msg_dsp', 'dispute.lost', 'applied');
        $this->event('msg_dsp_open', 'dispute.opened', 'applied');
        $this->event('msg_err', 'subscription.renewed', 'error', ['error' => 'Deadlock found']);

        $response = $this->actingAs($this->owner())->get(route('backend.admin.billing.index'))->assertOk();
        $summary = $response->viewData('summary');

        $this->assertSame(3, $summary['monthly'], 'active, cancelling at period end and on hold within grace');
        $this->assertSame(1, $summary['annual']);
        $this->assertSame(['USD' => 3 * 2900 + (int) round(29000 / 12)], $summary['mrr']);
        $this->assertSame(2, $summary['payments_count']);
        $this->assertSame(['USD' => 31900], $summary['payments_total']);
        $this->assertSame(1, $summary['refunds']);
        $this->assertSame(1, $summary['disputes_lost']);
        $this->assertSame(1, $summary['revoked']);
        $this->assertSame(1, $summary['grace']);
        $this->assertSame(1, $summary['errors']);
        $response->assertSee('$111.17 USD')->assertSee('Monthly recurring revenue');
    }

    public function test_subscriptions_filter_by_revoked_and_the_export_carries_the_revocation(): void
    {
        $owner = $this->owner();
        $kept = $this->sub('sub_kept');
        $gone = $this->sub('sub_gone', ['revoked_at' => now()->subDay(), 'revoked_reason' => 'chargeback']);

        $html = $this->actingAs($owner)->get(route('backend.admin.billing.index', ['status' => 'revoked']))->assertOk()->getContent();
        $this->assertStringContainsString($gone->user->email, $html);
        $this->assertStringNotContainsString($kept->user->email, $html);
        $this->assertStringContainsString('access revoked', $html);
        $this->assertStringContainsString('chargeback, '.now()->subDay()->format('j M Y'), $html);

        $rows = $this->csv(route('backend.admin.billing.export', ['status' => 'revoked']), $owner);
        $this->assertSame(['email', 'plan', 'status', 'cancel_at_period_end', 'current_period_end', 'cancelled_at', 'revoked_at', 'revoked_reason', 'last_event', 'last_event_at', 'provider_subscription_id', 'created_at'], $rows[0]);
        $this->assertCount(2, $rows);
        $this->assertSame('chargeback', $rows[1][7]);
    }

    public function test_payments_filter_by_status_and_date_link_to_the_account_and_export_safely(): void
    {
        $owner = $this->owner();
        $sub = $this->sub('sub_1');
        $this->payment('pay_ok', 'sub_1');
        $this->payment('pay_refunded', 'sub_1', ['status' => 'refunded', 'refunded_amount' => 2900, 'paid_at' => now()->subDays(20)]);
        $this->payment('=HYPERLINK("http://evil.example")', 'sub_1', ['paid_at' => now()->subDays(60)]);

        $html = $this->actingAs($owner)->get(route('backend.admin.billing.index', ['tab' => 'payments', 'status' => 'refunded']))->assertOk()->getContent();
        $this->assertStringContainsString('pay_refunded', $html);
        $this->assertStringNotContainsString('pay_ok', $html);
        $this->assertStringContainsString(route('backend.admin.users.show', $sub->user), $html, 'the payment links to the account it paid for');

        $html = $this->actingAs($owner)->get(route('backend.admin.billing.index', ['tab' => 'payments', 'from' => now()->subDays(30)->toDateString()]))->assertOk()->getContent();
        $this->assertStringContainsString('pay_ok', $html);
        $this->assertStringNotContainsString('HYPERLINK', $html);

        $rows = $this->csv(route('backend.admin.billing.payments.export'), $owner);
        $this->assertSame(['paid_at', 'provider_payment_id', 'provider_subscription_id', 'email', 'total_amount', 'currency', 'refunded_amount', 'status', 'dispute_status'], $rows[0]);
        $this->assertCount(4, $rows);
        $this->assertSame($sub->user->email, $rows[1][3]);
        $this->assertContains("'=HYPERLINK(\"http://evil.example\")", array_column($rows, 1), 'a formula-looking cell is neutralised');
        $this->assertCount(2, $this->csv(route('backend.admin.billing.payments.export', ['status' => 'refunded']), $owner), 'the export applies the filters');
    }

    public function test_webhooks_filter_by_outcome_and_show_the_error(): void
    {
        $owner = $this->owner();
        $this->event('msg_ok', 'subscription.renewed', 'applied');
        $this->event('msg_bad', 'subscription.cancelled', 'error', ['error' => 'SQLSTATE[40001]: Deadlock found']);

        $html = $this->actingAs($owner)->get(route('backend.admin.billing.index', ['tab' => 'webhooks', 'outcome' => 'error']))->assertOk()->getContent();
        $this->assertStringContainsString('msg_bad', $html);
        $this->assertStringContainsString('Deadlock found', $html);
        $this->assertStringNotContainsString('msg_ok', $html);
        $this->assertStringContainsString(route('backend.admin.billing.events.reapply', BillingEvent::where('event_id', 'msg_bad')->first()), $html);
        $this->assertStringContainsString('data-confirm="Apply subscription.cancelled again', $html, 'the confirmation runs under the CSP');
        $this->assertStringNotContainsString('onsubmit=', $html);
    }

    public function test_re_applying_an_errored_webhook_applies_it_under_the_same_event_id_and_is_audited(): void
    {
        config(['billing.plans.pro_monthly.product_id' => 'pdt_month', 'billing.enabled' => true]);
        $owner = $this->owner();
        $member = User::factory()->create(['email_verified_at' => now()]);
        $payload = ['business_id' => 'bus_1', 'type' => 'subscription.active', 'timestamp' => now()->subMinutes(5)->toIso8601String(), 'data' => [
            'subscription_id' => 'sub_retry', 'product_id' => 'pdt_month', 'status' => 'active', 'next_billing_date' => now()->addMonth()->toIso8601String(),
            'customer' => ['customer_id' => 'cus_retry', 'email' => $member->email], 'metadata' => ['app_user_id' => (string) $member->id],
        ]];
        $event = $this->event('msg_failed', 'subscription.active', 'error', ['payload' => $payload, 'provider_subscription_id' => 'sub_retry', 'error' => 'Deadlock found']);
        $applied = $this->event('msg_applied', 'subscription.renewed', 'applied');
        $this->assertFalse($member->fresh()->entitled('alerts.channels'));

        $this->actingAs($owner)->from(route('backend.admin.billing.index', ['tab' => 'webhooks']))->post(route('backend.admin.billing.events.reapply', $event))
            ->assertRedirect(route('backend.admin.billing.index', ['tab' => 'webhooks']))->assertSessionHas('success');

        $event->refresh();
        $this->assertSame('applied', $event->outcome);
        $this->assertNull($event->error);
        $this->assertSame(1, BillingEvent::where('event_id', 'msg_failed')->count(), 'the same row, not a second delivery');
        $this->assertSame('active', Subscription::where('provider_subscription_id', 'sub_retry')->value('status'));
        $this->assertTrue($member->fresh()->entitled('alerts.channels'));
        $this->assertTrue(AdminAuditLog::where('route_name', 'backend.admin.billing.events.reapply')->where('user_id', $owner->id)->exists());

        // An event that did not fail cannot be replayed from here.
        $this->actingAs($owner)->post(route('backend.admin.billing.events.reapply', $applied))->assertSessionHas('error');
        $this->actingAs($owner)->post(route('backend.admin.billing.events.reapply', $event))->assertSessionHas('error');
    }

    public function test_the_quota_view_counts_accounts_by_watches_and_lists_free_accounts_over_the_limit(): void
    {
        config(['billing.enabled' => true]);
        $owner = $this->owner();
        $follow = fn (User $u, int $n) => collect(range(1, $n))->each(fn ($i) => Follow::create(['user_id' => $u->id, 'subject_type' => 'policy', 'subject_slug' => 'p-'.$u->id.'-'.$i]));
        $follow(User::factory()->create(), 2);
        $follow($over = User::factory()->create(['email' => 'over@example.org']), 5);
        $paid = $this->sub('sub_paid');
        $follow($paid->user, 6);
        User::factory()->create(); // no watches

        $response = $this->actingAs($owner)->get(route('backend.admin.billing.index', ['tab' => 'quota']))->assertOk();
        $quota = $response->viewData('quota');
        $this->assertSame(3, $quota['limit']);
        $this->assertSame(['none' => 2, 'within' => 1, 'over' => 2], $quota['buckets'], 'the owner and the empty account have none');
        $this->assertSame(1, $quota['over_free_count'], 'the Pro account is not over its limit');
        $response->assertSee('over@example.org')->assertSee(route('backend.admin.users.show', $over))->assertSee('Above the free limit now')->assertDontSee($paid->user->email);
    }

    public function test_the_dashboard_raises_failed_webhooks_and_recent_refunds(): void
    {
        $this->event('msg_err', 'subscription.renewed', 'error', ['error' => 'boom']);
        $this->event('msg_ref', 'refund.succeeded', 'applied', ['received_at' => now()->subDays(2)]);

        $html = $this->actingAs($this->owner())->get(route('backend.admin.dashboard'))->assertOk()->getContent();
        $this->assertStringContainsString('1 billing webhook failed to apply', $html);
        $this->assertStringContainsString(e(route('backend.admin.billing.index', ['tab' => 'webhooks', 'outcome' => 'error'])), $html);
        $this->assertStringContainsString('1 refund or lost dispute in the last 7 days', $html);

        $editor = User::factory()->create(['admin_role' => AdminRole::Editor]);
        $this->assertStringNotContainsString('billing webhook failed', $this->actingAs($editor)->get(route('backend.admin.dashboard'))->assertOk()->getContent(), 'only billing holders are told');
    }
}
