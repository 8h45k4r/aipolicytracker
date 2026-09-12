<?php

namespace Tests\Feature;

use App\Mail\SubscriptionPaymentFailedMail;
use App\Models\AppSetting;
use App\Models\BillingCheckout;
use App\Models\BillingEvent;
use App\Models\Subscription;
use App\Models\User;
use App\Services\Billing\Contracts\BillingGateway;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Route;
use StandardWebhooks\Webhook;
use Tests\TestCase;

class BillingTest extends TestCase
{
    use RefreshDatabase;

    private const SECRET = 'whsec_dGVzdHNlY3JldHRlc3RzZWNyZXR0ZXN0c2VjcmV0';

    private FakeGateway $gateway;

    protected function setUp(): void
    {
        parent::setUp();
        $this->gateway = new FakeGateway;
        $this->app->instance(BillingGateway::class, $this->gateway);
        config(['billing.webhook_secret' => self::SECRET, 'billing.api_key' => 'dodo_test_key_1234567890']);
    }

    private function enable(): void
    {
        config(['billing.enabled' => true, 'billing.plans.pro_monthly.product_id' => 'pdt_month', 'billing.plans.pro_yearly.product_id' => 'pdt_year']);
    }

    private function user(array $attrs = []): User
    {
        return User::factory()->create($attrs + ['email_verified_at' => now()]);
    }

    /** Signs a payload exactly as the provider does (Standard Webhooks) and posts it. */
    private function webhook(array $payload, ?string $eventId = null, ?int $timestamp = null, ?string $secret = null)
    {
        $eventId ??= 'msg_'.bin2hex(random_bytes(6));
        $timestamp ??= time();
        $body = json_encode($payload);
        $signature = (new Webhook($secret ?? self::SECRET))->sign($eventId, $timestamp, $body);

        return $this->call('POST', '/webhooks/dodo', [], [], [], [
            'HTTP_WEBHOOK_ID' => $eventId, 'HTTP_WEBHOOK_TIMESTAMP' => (string) $timestamp, 'HTTP_WEBHOOK_SIGNATURE' => $signature, 'CONTENT_TYPE' => 'application/json',
        ], $body);
    }

    private function subscriptionEvent(string $type, User $user, array $overrides = [], string $subId = 'sub_1'): array
    {
        return [
            'business_id' => 'bus_1',
            'type' => $type,
            'timestamp' => $overrides['timestamp'] ?? now()->toIso8601String(),
            'data' => array_merge([
                'payload_type' => 'Subscription',
                'subscription_id' => $subId,
                'product_id' => 'pdt_month',
                'status' => match ($type) {
                    'subscription.active', 'subscription.renewed' => 'active', 'subscription.on_hold' => 'on_hold', 'subscription.cancelled' => 'cancelled', 'subscription.expired' => 'expired', 'subscription.failed' => 'failed', default => 'active'
                },
                'next_billing_date' => now()->addMonth()->toIso8601String(),
                'cancel_at_next_billing_date' => false,
                'cancelled_at' => null,
                'customer' => ['customer_id' => 'cus_1', 'email' => $user->email, 'name' => $user->name],
                'metadata' => ['app_user_id' => (string) $user->id, 'plan_key' => 'pro_monthly'],
            ], array_diff_key($overrides, ['timestamp' => 1])),
        ];
    }

    public function test_pricing_page_is_noindex_and_sells_nothing_while_billing_is_disabled(): void
    {
        $res = $this->get('/pricing')->assertOk()->assertSee('Not yet available')->assertDontSee('Subscribe to Pro');
        $this->assertStringContainsString('noindex', $res->getContent());
        $this->actingAs($this->user())->post('/billing/checkout/pro_monthly')->assertNotFound();
        $this->actingAs($this->user())->post('/billing/portal')->assertNotFound();
        $this->get('/sitemap-static.xml')->assertOk()->assertDontSee('/pricing');
    }

    public function test_pricing_page_is_indexable_and_offers_checkout_when_enabled(): void
    {
        $this->enable();
        $res = $this->get('/pricing')->assertOk()->assertSee('$29')->assertSee('$290')->assertSee('Sign in to subscribe');
        $this->assertStringContainsString('index,follow', $res->getContent());
        $this->assertStringNotContainsString('noindex', $res->getContent());
        $this->actingAs($this->user())->get('/pricing')->assertOk()->assertSee('Subscribe to Pro');
        $this->get('/sitemap-static.xml')->assertOk()->assertSee('/pricing');
    }

    public function test_checkout_requires_verified_account_records_attempt_and_hands_off_to_provider(): void
    {
        $this->enable();
        $this->post('/billing/checkout/pro_monthly')->assertRedirect('/login');
        $unverified = User::factory()->create(['email_verified_at' => null]);
        $this->actingAs($unverified)->post('/billing/checkout/pro_monthly')->assertRedirect(route('verification.notice'));
        $this->actingAs($this->user())->post('/billing/checkout/no_such_plan')->assertNotFound();

        $user = $this->user();
        $res = $this->actingAs($user)->post('/billing/checkout/pro_monthly')->assertOk()->assertSee('https://checkout.example.test/cs_1');
        $checkout = BillingCheckout::where('user_id', $user->id)->firstOrFail();
        $this->assertSame('pdt_month', $checkout->product_id);
        $this->assertSame('cs_1', $checkout->provider_session_id);
        $this->assertSame((string) $user->id, $this->gateway->lastCheckout['metadata']['app_user_id']);
        $this->assertStringContainsString('/billing/return/'.$checkout->id, $this->gateway->lastCheckout['return_url']);
        // Return page: no subscription yet, so the page says it is still confirming, never "active".
        $this->actingAs($user)->get('/billing/return/'.$checkout->id)->assertOk()->assertSee('Confirming your subscription');
        $this->assertSame('returned', $checkout->fresh()->status);
        $this->actingAs($this->user())->get('/billing/return/'.$checkout->id)->assertNotFound();
        $this->assertNull($user->fresh()->activeSubscription());
    }

    public function test_checkout_failure_at_provider_is_reported_not_faked(): void
    {
        $this->enable();
        $this->gateway->fail = true;
        $user = $this->user();
        $this->actingAs($user)->post('/billing/checkout/pro_monthly')->assertRedirect('/pricing')->assertSessionHas('error');
        $attempt = BillingCheckout::where('user_id', $user->id)->firstOrFail();
        $this->assertSame('abandoned', $attempt->status);
        $this->assertSame('provider down', $attempt->error, 'provider answer kept for diagnosis');
        config(['aipolicytracker.admin_emails' => ['admin@example.org']]);
        $this->actingAs($this->user(['email' => 'admin@example.org']))->get('/backend/admin/billing')->assertOk()->assertSee('provider down')->assertSee('abandoned');
    }

    public function test_webhook_rejects_missing_or_invalid_signatures_and_stores_nothing(): void
    {
        $user = $this->user();
        $this->postJson('/webhooks/dodo', ['type' => 'subscription.active'])->assertStatus(400);
        $this->webhook($this->subscriptionEvent('subscription.active', $user), secret: 'whsec_b3RoZXJzZWNyZXRvdGhlcnNlY3JldA==')->assertStatus(400);
        $this->webhook($this->subscriptionEvent('subscription.active', $user), timestamp: time() - 3600)->assertStatus(400);
        $this->assertSame(0, BillingEvent::count());
        $this->assertSame(0, Subscription::count());
        // Without a configured secret nothing is accepted either.
        config(['billing.webhook_secret' => null]);
        $this->webhook($this->subscriptionEvent('subscription.active', $user))->assertStatus(400);
    }

    public function test_signed_subscription_active_event_grants_entitlements_and_is_idempotent(): void
    {
        $this->enable();
        $user = $this->user();
        BillingCheckout::create(['user_id' => $user->id, 'plan_key' => 'pro_monthly', 'product_id' => 'pdt_month', 'status' => 'returned']);
        $this->assertFalse($user->entitled('alerts.daily'));
        $this->assertTrue($user->entitled('alerts.weekly'));

        $this->webhook($this->subscriptionEvent('subscription.active', $user), 'msg_a')->assertOk()->assertJson(['outcome' => 'applied']);
        $sub = Subscription::where('provider_subscription_id', 'sub_1')->firstOrFail();
        $this->assertSame('active', $sub->status);
        $this->assertSame('pro_monthly', $sub->plan_key);
        $this->assertSame($user->id, $sub->user_id);
        $this->assertSame('cus_1', $user->fresh()->billingCustomer->provider_customer_id);
        $this->assertSame('completed', BillingCheckout::where('user_id', $user->id)->value('status'));
        $user = $user->fresh();
        $this->assertSame('pro_monthly', $user->planKey());
        $this->assertTrue($user->entitled('alerts.daily'));
        $this->assertTrue($user->entitled('saved.server'));

        // Same webhook id delivered again: recorded once, applied once.
        $this->webhook($this->subscriptionEvent('subscription.active', $user), 'msg_a')->assertOk()->assertJson(['outcome' => 'duplicate']);
        $this->assertSame(1, BillingEvent::count());
        $this->assertSame(1, Subscription::count());
        $this->assertSame('applied', BillingEvent::first()->outcome);

        $this->actingAs($user)->get('/profile')->assertOk()->assertSee('Pro')->assertSee('Manage billing');
        $this->actingAs($user)->get('/pricing')->assertOk()->assertSee('Your current plan');
        $this->actingAs($user)->post('/billing/checkout/pro_monthly')->assertRedirect('/profile');
    }

    public function test_unknown_product_never_grants_access_and_payment_events_are_recorded_but_ignored(): void
    {
        $this->enable();
        $user = $this->user();
        $this->webhook($this->subscriptionEvent('subscription.active', $user, ['product_id' => 'pdt_not_ours']))->assertOk()->assertJson(['outcome' => 'applied']);
        $this->assertNull(Subscription::first()->plan_key);
        $this->assertNull($user->fresh()->activeSubscription());
        $this->assertFalse($user->fresh()->entitled('alerts.daily'));

        $this->webhook(['business_id' => 'bus_1', 'type' => 'payment.succeeded', 'timestamp' => now()->toIso8601String(), 'data' => ['payload_type' => 'Payment', 'payment_id' => 'pay_1', 'subscription_id' => 'sub_1']])->assertOk()->assertJson(['outcome' => 'ignored']);
        $this->assertSame(2, BillingEvent::count());
    }

    public function test_cancellation_keeps_access_until_period_end_and_expiry_revokes_it(): void
    {
        $this->enable();
        $user = $this->user();
        $this->webhook($this->subscriptionEvent('subscription.active', $user, ['timestamp' => now()->subMinutes(5)->toIso8601String()]))->assertOk();
        $end = now()->addDays(10);
        $this->webhook($this->subscriptionEvent('subscription.cancelled', $user, ['cancel_at_next_billing_date' => true, 'cancelled_at' => now()->toIso8601String(), 'next_billing_date' => $end->toIso8601String(), 'timestamp' => now()->subMinutes(4)->toIso8601String()]))->assertOk()->assertJson(['outcome' => 'applied']);
        $sub = Subscription::first();
        $this->assertSame('cancelled', $sub->status);
        $this->assertTrue($sub->cancel_at_period_end);
        $this->assertTrue($user->fresh()->entitled('alerts.daily'), 'paid period still running');

        $this->travelTo($end->copy()->addDay());
        $this->assertFalse($user->fresh()->entitled('alerts.daily'), 'period over');
        $this->travelBack();

        $this->webhook($this->subscriptionEvent('subscription.expired', $user, ['timestamp' => now()->subMinutes(3)->toIso8601String()]))->assertOk()->assertJson(['outcome' => 'applied']);
        $this->assertSame('expired', Subscription::first()->status);
        $this->assertFalse($user->fresh()->entitled('alerts.daily'));
        $this->assertSame('free', $user->fresh()->planKey());
    }

    public function test_failed_renewal_keeps_access_for_the_grace_period_and_emails_once(): void
    {
        Mail::fake();
        $this->enable();
        config(['billing.on_hold_grace_days' => 7]);
        $user = $this->user();
        $this->webhook($this->subscriptionEvent('subscription.active', $user, ['timestamp' => now()->subMinutes(5)->toIso8601String()]))->assertOk();
        $this->webhook($this->subscriptionEvent('subscription.on_hold', $user, ['timestamp' => now()->subMinutes(4)->toIso8601String()]))->assertOk()->assertJson(['outcome' => 'applied']);
        Mail::assertSent(SubscriptionPaymentFailedMail::class, fn ($m) => $m->hasTo($user->email));
        $this->assertTrue($user->fresh()->entitled('alerts.daily'), 'inside grace period');

        $this->travelTo(now()->addDays(8));
        $this->assertFalse($user->fresh()->entitled('alerts.daily'), 'grace period over');
        $this->travelBack();

        // A renewal after the customer fixed the card restores access without a new email.
        $this->webhook($this->subscriptionEvent('subscription.renewed', $user, ['timestamp' => now()->subMinutes(3)->toIso8601String()]))->assertOk();
        $this->assertSame('active', Subscription::first()->status);
        $this->assertTrue($user->fresh()->entitled('alerts.daily'));
        Mail::assertSent(SubscriptionPaymentFailedMail::class, 1);
    }

    public function test_out_of_order_events_cannot_reactivate_a_cancelled_subscription(): void
    {
        $this->enable();
        $user = $this->user();
        $this->webhook($this->subscriptionEvent('subscription.cancelled', $user, ['timestamp' => now()->toIso8601String(), 'next_billing_date' => now()->subDay()->toIso8601String()]))->assertOk();
        $this->assertFalse($user->fresh()->entitled('alerts.daily'));
        $this->webhook($this->subscriptionEvent('subscription.active', $user, ['timestamp' => now()->subHour()->toIso8601String()]))->assertOk()->assertJson(['outcome' => 'stale']);
        $this->assertSame('cancelled', Subscription::first()->status);
        $this->assertFalse($user->fresh()->entitled('alerts.daily'));
        $this->assertSame('stale', BillingEvent::orderByDesc('id')->first()->outcome);
    }

    public function test_event_for_an_unknown_customer_is_recorded_and_ignored(): void
    {
        $this->enable();
        $payload = $this->subscriptionEvent('subscription.active', $this->user());
        $payload['data']['metadata'] = [];
        $payload['data']['customer'] = ['customer_id' => 'cus_ghost', 'email' => 'nobody@example.org'];
        $this->webhook($payload)->assertOk()->assertJson(['outcome' => 'ignored']);
        $this->assertSame(0, Subscription::count());
        $this->assertSame('ignored', BillingEvent::first()->outcome);
    }

    public function test_customer_resolved_by_email_when_metadata_is_missing(): void
    {
        $this->enable();
        $user = $this->user(['email' => 'Mixed.Case@Example.org']);
        $payload = $this->subscriptionEvent('subscription.active', $user);
        $payload['data']['metadata'] = [];
        $payload['data']['customer']['email'] = 'mixed.case@example.org';
        $this->webhook($payload)->assertOk()->assertJson(['outcome' => 'applied']);
        $this->assertSame($user->id, Subscription::first()->user_id);
    }

    public function test_portal_redirects_subscribed_users_to_the_provider(): void
    {
        $this->enable();
        $user = $this->user();
        $this->actingAs($user)->post('/billing/portal')->assertRedirect('/profile')->assertSessionHas('error');
        $this->webhook($this->subscriptionEvent('subscription.active', $user))->assertOk();
        $this->actingAs($user->fresh())->post('/billing/portal')->assertOk()->assertSee('https://portal.example.test/cus_1');
        $this->assertSame('cus_1', $this->gateway->lastPortalCustomer);
    }

    public function test_subscribed_middleware_guards_paid_routes(): void
    {
        $this->enable();
        Route::middleware(['web', 'subscribed:alerts.daily'])->get('/_test/pro', fn () => 'pro-only');
        $this->get('/_test/pro')->assertRedirect('/login');
        $user = $this->user();
        $this->actingAs($user)->get('/_test/pro')->assertRedirect('/pricing');
        $this->webhook($this->subscriptionEvent('subscription.active', $user))->assertOk();
        $this->actingAs($user->fresh())->get('/_test/pro')->assertOk()->assertSee('pro-only');
    }

    public function test_admin_provisioning_creates_endpoint_and_products_once_and_stores_keys(): void
    {
        config(['aipolicytracker.admin_emails' => ['admin@example.org']]);
        $admin = $this->user(['email' => 'admin@example.org']);
        // Without an API key nothing is created.
        config(['billing.api_key' => null]);
        $this->actingAs($admin)->post('/backend/admin/billing/provision')->assertRedirect()->assertSessionHas('error');
        $this->assertSame([], $this->gateway->webhooks);

        AppSetting::put('dodo_api_key', 'dodo_test_abcdefghijklmnop');
        $this->actingAs($admin)->post('/backend/admin/billing/provision')->assertRedirect()->assertSessionHas('success');
        $webhook = $this->gateway->webhooks[route('billing.webhook')] ?? null;
        $this->assertNotNull($webhook, 'endpoint registered for this site');
        $this->assertContains('subscription.active', $webhook['events']);
        $this->assertContains('payment.failed', $webhook['events']);
        $this->assertSame($webhook['secret'], AppSetting::get('dodo_webhook_secret'));
        $this->assertSame(1, (int) AppSetting::find('dodo_webhook_secret')->secret, 'stored as a secret');
        $monthly = $this->gateway->createdProducts['AIPolicyTracker Pro'];
        $yearly = $this->gateway->createdProducts['AIPolicyTracker Pro (annual)'];
        $this->assertSame([2900, 'USD', 'Month'], [$monthly['price'], $monthly['currency'], $monthly['interval']]);
        $this->assertSame([29000, 'USD', 'Year'], [$yearly['price'], $yearly['currency'], $yearly['interval']]);
        $this->assertSame($monthly['product_id'], AppSetting::get('dodo_product_pro_monthly'));
        $this->assertSame($yearly['product_id'], app(\App\Services\Billing\PlanCatalog::class)->plan('pro_yearly')['product_id']);

        // The stored secret now verifies real webhooks and the stored ids resolve plans.
        $member = $this->user();
        config(['billing.webhook_secret' => null]);
        $this->webhook($this->subscriptionEvent('subscription.active', $member, ['product_id' => $yearly['product_id']]), secret: $webhook['secret'])->assertOk()->assertJson(['outcome' => 'applied']);
        $this->assertSame('pro_yearly', Subscription::first()->plan_key);

        // Running it again reuses everything.
        $this->actingAs($admin)->post('/backend/admin/billing/provision')->assertRedirect()->assertSessionHas('success');
        $this->assertCount(1, $this->gateway->webhooks);
        $this->assertCount(2, $this->gateway->createdProducts);
        $this->actingAs($this->user())->post('/backend/admin/billing/provision')->assertRedirect('/');
    }

    public function test_checkout_switch_in_settings_overrides_the_environment(): void
    {
        config(['aipolicytracker.admin_emails' => ['admin@example.org'], 'billing.plans.pro_monthly.product_id' => 'pdt_month', 'billing.plans.pro_yearly.product_id' => 'pdt_year']);
        $admin = $this->user(['email' => 'admin@example.org']);
        $this->get('/pricing')->assertOk()->assertSee('Not yet available');
        $this->actingAs($admin)->post('/backend/admin/settings', ['billing_enabled' => 'on'])->assertRedirect();
        $this->get('/pricing')->assertOk()->assertDontSee('Not yet available')->assertSee('Subscribe to Pro');
        $this->assertStringNotContainsString('noindex', $this->get('/pricing')->getContent());
        config(['billing.enabled' => true]);
        $this->actingAs($admin)->post('/backend/admin/settings', ['billing_enabled' => 'off'])->assertRedirect();
        $this->get('/pricing')->assertOk()->assertSee('Not yet available');
        $this->actingAs($admin)->post('/backend/admin/settings', ['billing_enabled' => 'maybe'])->assertSessionHasErrors('billing_enabled');
        $this->actingAs($admin)->get('/backend/admin/billing')->assertOk()->assertSee('from Settings');
    }

    public function test_admin_billing_page_and_settings_are_admin_only_and_keys_are_stored_encrypted(): void
    {
        $this->enable();
        config(['aipolicytracker.admin_emails' => ['admin@example.org']]);
        $admin = $this->user(['email' => 'admin@example.org']);
        $member = $this->user();
        $this->webhook($this->subscriptionEvent('subscription.active', $member))->assertOk();

        $this->actingAs($member)->get('/backend/admin/billing')->assertRedirect('/');
        $this->actingAs($admin)->get('/backend/admin/billing')->assertOk()->assertSee($member->email)->assertSee('subscription.active')->assertSee('/webhooks/dodo');

        $this->actingAs($admin)->post('/backend/admin/settings', ['dodo_api_key' => 'dodo_test_abcdefghijklmnop', 'dodo_webhook_secret' => 'whsec_bmV3c2VjcmV0bmV3c2VjcmV0', 'dodo_environment' => 'live_mode', 'dodo_product_pro_monthly' => 'pdt_live_month'])->assertRedirect();
        $this->assertSame('dodo_test_abcdefghijklmnop', AppSetting::get('dodo_api_key'));
        $this->assertNotSame('dodo_test_abcdefghijklmnop', AppSetting::find('dodo_api_key')->value, 'stored encrypted');
        $this->assertSame(1, (int) AppSetting::find('dodo_api_key')->secret);
        $this->assertSame('live_mode', app(\App\Services\Billing\BillingConfig::class)->environment());
        $this->assertSame('https://live.dodopayments.com', app(\App\Services\Billing\BillingConfig::class)->baseUrl());
        $this->assertSame('pdt_live_month', app(\App\Services\Billing\PlanCatalog::class)->plan('pro_monthly')['product_id']);
        $this->actingAs($admin)->get('/backend/admin/settings')->assertOk()->assertDontSee('dodo_test_abcdefghijklmnop')->assertSee('dodo');
        $this->actingAs($admin)->post('/backend/admin/settings', ['dodo_environment' => 'staging'])->assertSessionHasErrors('dodo_environment');

        // Product check compares the provider price with config and flags a mismatch.
        $this->gateway->products['pdt_live_month'] = ['product_id' => 'pdt_live_month', 'name' => 'Pro', 'price' => 1900, 'currency' => 'USD', 'interval' => 'Month'];
        $this->gateway->products['pdt_year'] = ['product_id' => 'pdt_year', 'name' => 'Pro annual', 'price' => 29000, 'currency' => 'USD', 'interval' => 'Year'];
        $this->actingAs($admin)->post('/backend/admin/billing/check')->assertRedirect();
        $this->actingAs($admin)->get('/backend/admin/billing')->assertOk()->assertSee('Price differs')->assertSee('Price matches');
    }
}

/** In-memory gateway: records calls and returns deterministic ids; never talks to the network. */
class FakeGateway implements BillingGateway
{
    public bool $fail = false;

    public ?array $lastCheckout = null;

    public ?string $lastPortalCustomer = null;

    public array $products = [];

    public array $webhooks = [];

    public array $createdProducts = [];

    public function provisionWebhook(string $url, array $events): array
    {
        $created = ! isset($this->webhooks[$url]);
        $this->webhooks[$url] ??= ['id' => 'wh_'.count($this->webhooks), 'secret' => 'whsec_cHJvdmlzaW9uZWRzZWNyZXRwcm92aXNpb25lZA==', 'events' => $events];

        return ['id' => $this->webhooks[$url]['id'], 'secret' => $this->webhooks[$url]['secret'], 'created' => $created];
    }

    public function provisionProduct(string $name, int $price, string $currency, string $interval, string $description): array
    {
        $created = ! isset($this->createdProducts[$name]);
        $this->createdProducts[$name] ??= ['product_id' => 'pdt_'.strtolower(preg_replace('/[^a-z]+/i', '_', $name)), 'price' => $price, 'currency' => $currency, 'interval' => $interval];

        return ['product_id' => $this->createdProducts[$name]['product_id'], 'created' => $created];
    }

    public function createCheckout(User $user, string $productId, array $metadata, string $returnUrl): array
    {
        if ($this->fail) {
            throw new \RuntimeException('provider down');
        }
        $this->lastCheckout = ['user_id' => $user->id, 'product_id' => $productId, 'metadata' => $metadata, 'return_url' => $returnUrl];

        return ['session_id' => 'cs_1', 'url' => 'https://checkout.example.test/cs_1'];
    }

    public function createPortalLink(string $providerCustomerId, string $returnUrl): string
    {
        $this->lastPortalCustomer = $providerCustomerId;

        return 'https://portal.example.test/'.$providerCustomerId;
    }

    public function retrieveProduct(string $productId): array
    {
        if (! isset($this->products[$productId])) {
            throw new \RuntimeException('not found');
        }

        return $this->products[$productId];
    }
}
