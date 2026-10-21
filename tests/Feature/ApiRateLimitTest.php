<?php

namespace Tests\Feature;

use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** The read-only API's limit: the public rate without a token, the plan's rate with one once selling is on. */
class ApiRateLimitTest extends TestCase
{
    use RefreshDatabase;

    private function enable(): void
    {
        config(['billing.enabled' => true, 'billing.plans.pro_monthly.product_id' => 'pdt_month', 'billing.plans.pro_yearly.product_id' => 'pdt_year']);
    }

    private function pro(User $user): void
    {
        Subscription::forceCreate([
            'user_id' => $user->id, 'provider' => 'dodo', 'provider_subscription_id' => 'sub_'.$user->id,
            'product_id' => 'pdt_month', 'plan_key' => 'pro_monthly', 'status' => 'active',
            'current_period_end' => now()->addMonth(), 'last_event_at' => now()->subDay(), 'last_event_type' => 'subscription.active',
        ]);
    }

    private function limit(?string $token = null): string
    {
        $headers = $token ? ['Authorization' => 'Bearer '.$token] : [];

        return (string) $this->getJson('/api/v1/', $headers)->assertOk()->headers->get('X-RateLimit-Limit');
    }

    public function test_the_public_api_keeps_its_rate_without_a_token(): void
    {
        $this->assertSame('120', $this->limit());
        $this->enable();
        $this->assertSame('120', $this->limit());
        $this->assertSame('120', $this->limit('not-a-real-token'), 'a bad token is treated as no token, not refused');
    }

    public function test_a_paid_account_token_raises_the_limit_once_selling_is_on(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('watches', ['watches'])->plainTextToken;
        $this->pro($user);

        $this->assertSame('120', $this->limit($token), 'selling off: nobody holds the paid rate, so turning selling on takes nothing away');
        $this->enable();
        $this->assertSame('600', $this->limit($token));
        $this->assertSame((int) config('billing.plans.pro_monthly.entitlements')['api.rate_per_minute'], 600);
    }

    public function test_a_free_account_token_gets_the_public_rate_counted_per_account(): void
    {
        $this->enable();
        $user = User::factory()->create();
        $token = $user->createToken('watches', ['watches'])->plainTextToken;
        $this->assertSame('120', $this->limit($token));
        $this->getJson('/api/v1/', ['Authorization' => 'Bearer '.$token])->assertHeader('X-RateLimit-Remaining', '118');
    }

    public function test_the_pricing_page_and_developer_page_state_the_rates(): void
    {
        $this->get(route('developers'))->assertOk()->assertSee('120 requests a minute')->assertDontSee('600 a minute');
        $this->enable();
        $this->get(route('pricing'))->assertOk()->assertSee('600 requests a minute with your account token');
        $this->get(route('developers'))->assertOk()->assertSee('120 requests a minute')->assertSee('600');
    }
}
