<?php

namespace Tests\Feature;

use App\Enums\AdminRole;
use App\Models\Subscriber;
use App\Models\User;
use App\Services\Admin\DashboardGrowth;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** The dashboard's direction: headline figures against the 30 days before, and weekly growth. */
class AdminDashboardGrowthTest extends TestCase
{
    use RefreshDatabase;

    private function subscriber(string $email, $confirmed, $unsubscribed = null): void
    {
        (new Subscriber)->forceFill(['email' => $email, 'confirmed_at' => $confirmed, 'unsubscribed_at' => $unsubscribed, 'created_at' => $confirmed, 'updated_at' => $confirmed, 'token' => str_repeat('a', 30).$email])->save();
    }

    public function test_deltas_compare_the_last_30_days_with_the_30_before(): void
    {
        $this->subscriber('a@example.org', now()->subDays(5));
        $this->subscriber('b@example.org', now()->subDays(10));
        $this->subscriber('c@example.org', now()->subDays(40));
        $this->subscriber('d@example.org', now()->subDays(90));

        $d = app(DashboardGrowth::class)->deltas()['subscribers'];
        $this->assertSame(['now' => 2, 'before' => 1, 'change' => 100.0], $d);
    }

    public function test_weekly_counts_active_subscribers_at_each_week_end(): void
    {
        $this->subscriber('a@example.org', now()->subWeeks(10));
        $this->subscriber('b@example.org', now()->subWeeks(3), now()->subWeek());
        $this->subscriber('c@example.org', now()->subDays(1));

        $weekly = app(DashboardGrowth::class)->weekly()['subscribers'];
        $this->assertCount(DashboardGrowth::WEEKS, $weekly);
        $values = array_values($weekly);
        $this->assertSame(0, $values[0], 'thirteen weeks ago nobody had confirmed');
        $this->assertSame(2, $values[DashboardGrowth::WEEKS - 3], 'two weeks ago: a and b');
        $this->assertSame(2, end($values), 'now: a and c; b has unsubscribed');
    }

    public function test_the_dashboard_shows_growth_to_roles_that_see_the_audience_only(): void
    {
        $this->subscriber('a@example.org', now()->subDays(3));
        $owner = User::factory()->create(['email' => 'owner@example.org']);
        config(['aipolicytracker.admin_emails' => ['owner@example.org']]);

        $html = $this->actingAs($owner)->get(route('backend.admin.dashboard'))->assertOk()->getContent();
        $this->assertStringContainsString('data-growth', $html);
        $this->assertStringContainsString('data-delta="subscribers"', $html);
        $this->assertStringContainsString('id="chart-subscribers-title"', $html);
        $this->assertStringContainsString('Show as a table', $html, 'every chart has a table view');
        $this->assertMatchesRegularExpression('#role="img" aria-label="Active subscribers: 0 to 1 over 13 weeks"#', $html);

        $reviewer = User::factory()->create(['admin_role' => AdminRole::Reviewer, 'two_factor_secret' => 'JBSWY3DPEHPK3PXP', 'two_factor_confirmed_at' => now()]);
        $html = $this->actingAs($reviewer)->get(route('backend.admin.dashboard'))->assertOk()->getContent();
        $this->assertStringNotContainsString('data-growth', $html, 'a reviewer does not see audience figures');
    }
}
