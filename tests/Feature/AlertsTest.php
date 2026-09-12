<?php

namespace Tests\Feature;

use App\Mail\DailyAlertMail;
use App\Models\AlertDelivery;
use App\Models\AppSetting;
use App\Models\ChangeEvent;
use App\Models\Deadline;
use App\Models\Follow;
use App\Models\Jurisdiction;
use App\Models\Obligation;
use App\Models\PolicyInstrument;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class AlertsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        $this->artisan('policy:import');
        config(['billing.plans.pro_monthly.product_id' => 'pdt_month']);
    }

    private function pro(array $attrs = []): User
    {
        $user = User::factory()->create($attrs + ['email_verified_at' => now()]);
        Subscription::create(['user_id' => $user->id, 'provider_subscription_id' => 'sub_'.$user->id, 'product_id' => 'pdt_month', 'plan_key' => 'pro_monthly', 'status' => 'active', 'current_period_end' => now()->addMonth(), 'last_event_at' => now()]);

        return $user;
    }

    private function free(): User
    {
        return User::factory()->create(['email_verified_at' => now()]);
    }

    public function test_following_is_a_pro_capability_and_validates_the_record(): void
    {
        $policy = PolicyInstrument::published()->firstOrFail();
        $this->get($policy->url())->assertOk()->assertSee('Follow with Pro')->assertDontSee('Follow for daily alerts');
        $this->post('/follow/policy/'.$policy->slug)->assertRedirect('/login');

        $free = $this->free();
        $this->actingAs($free)->get($policy->url())->assertOk()->assertSee('Follow with Pro');
        $this->actingAs($free)->post('/follow/policy/'.$policy->slug)->assertRedirect('/pricing')->assertSessionHas('error');
        $this->assertSame(0, Follow::count());

        $pro = $this->pro();
        $this->actingAs($pro)->get($policy->url())->assertOk()->assertSee('Follow for daily alerts');
        $this->actingAs($pro)->post('/follow/policy/'.$policy->slug)->assertRedirect($policy->url())->assertSessionHas('status', 'followed');
        $this->assertSame(1, Follow::where('user_id', $pro->id)->count());
        $this->actingAs($pro)->get($policy->url())->assertOk()->assertSee('Following ✓');
        // Toggle again removes it; unknown records and types are refused.
        $this->actingAs($pro)->post('/follow/policy/'.$policy->slug)->assertRedirect($policy->url())->assertSessionHas('status', 'unfollowed');
        $this->assertSame(0, Follow::count());
        $this->actingAs($pro)->post('/follow/policy/does-not-exist')->assertNotFound();
        $this->actingAs($pro)->post('/follow/incident/1')->assertNotFound();
        // Follows are per account: another Pro user does not see them.
        Follow::create(['user_id' => $pro->id, 'subject_type' => 'policy', 'subject_slug' => $policy->slug]);
        $other = $this->pro();
        $this->actingAs($other)->get('/following')->assertOk()->assertSee('not following anything yet');
        $this->actingAs($pro)->get('/following')->assertOk()->assertSee($policy->short_title ?: $policy->title)->assertSee('Unfollow');
        $this->actingAs($pro)->post('/follow/policy/'.$policy->slug, ['return' => '/following'])->assertRedirect('/following');
        $this->assertSame(0, Follow::count());
    }

    public function test_daily_alert_goes_only_to_entitled_followers_of_changed_records_once_per_day(): void
    {
        Mail::fake();
        $change = ChangeEvent::published()->whereNotNull('policy_instrument_id')->with(['policyInstrument', 'jurisdiction'])->firstOrFail();
        $change->forceFill(['occurred_on' => now()->toDateString()])->save();
        $otherJurisdiction = Jurisdiction::published()->where('id', '!=', $change->jurisdiction_id)->firstOrFail();

        $byPolicy = $this->pro(['email' => 'policy@example.org']);
        Follow::create(['user_id' => $byPolicy->id, 'subject_type' => 'policy', 'subject_slug' => $change->policyInstrument->slug]);
        $byJurisdiction = $this->pro(['email' => 'jurisdiction@example.org']);
        Follow::create(['user_id' => $byJurisdiction->id, 'subject_type' => 'jurisdiction', 'subject_slug' => $change->jurisdiction->slug]);
        $unrelated = $this->pro(['email' => 'unrelated@example.org']);
        Follow::create(['user_id' => $unrelated->id, 'subject_type' => 'jurisdiction', 'subject_slug' => $otherJurisdiction->slug]);
        $lapsed = $this->pro(['email' => 'lapsed@example.org']);
        Subscription::where('user_id', $lapsed->id)->update(['status' => 'expired']);
        Follow::create(['user_id' => $lapsed->id, 'subject_type' => 'policy', 'subject_slug' => $change->policyInstrument->slug]);
        $freeFollower = $this->free();
        Follow::create(['user_id' => $freeFollower->id, 'subject_type' => 'policy', 'subject_slug' => $change->policyInstrument->slug]);

        $this->artisan('alerts:send')->expectsOutputToContain('2 sent')->assertSuccessful();
        Mail::assertSent(DailyAlertMail::class, fn ($m) => $m->hasTo('policy@example.org') && $m->changes->contains('id', $change->id));
        Mail::assertSent(DailyAlertMail::class, fn ($m) => $m->hasTo('jurisdiction@example.org'));
        Mail::assertNotSent(DailyAlertMail::class, fn ($m) => $m->hasTo('unrelated@example.org'));
        Mail::assertNotSent(DailyAlertMail::class, fn ($m) => $m->hasTo('lapsed@example.org'));
        Mail::assertNotSent(DailyAlertMail::class, fn ($m) => $m->hasTo($freeFollower->email));
        $this->assertSame(2, AlertDelivery::count());
        $this->assertSame(1, AlertDelivery::where('user_id', $byPolicy->id)->value('changes_count'));

        // Re-running the same day sends nothing more.
        $this->artisan('alerts:send')->expectsOutputToContain('0 sent')->assertSuccessful();
        Mail::assertSent(DailyAlertMail::class, 2);

        // The next day, with nothing new in the window, nothing is sent; the window starts at the last delivery.
        $this->travelTo(now()->addDay());
        $this->artisan('alerts:send')->expectsOutputToContain('0 sent')->assertSuccessful();
        Mail::assertSent(DailyAlertMail::class, 2);
        $this->travelBack();

        $this->actingAs($byPolicy)->get('/following')->assertOk()->assertSee('Last alert sent');
    }

    public function test_deadline_milestone_alone_triggers_an_alert_and_obligation_follows_match_their_instrument(): void
    {
        Mail::fake();
        $obligation = Obligation::published()->whereNotNull('policy_instrument_id')->with('policyInstrument')->firstOrFail();
        DB::table('change_events')->update(['occurred_on' => '2001-01-01']);
        Deadline::create(['policy_instrument_id' => $obligation->policy_instrument_id, 'obligation_id' => $obligation->id, 'title' => 'Test application date', 'due_on' => now()->addDays(7)->toDateString(), 'date_precision' => 'day', 'deadline_status' => 'scheduled', 'confidence_level' => 'high', 'sort_order' => 99]);
        $user = $this->pro(['email' => 'deadline@example.org']);
        Follow::create(['user_id' => $user->id, 'subject_type' => 'obligation', 'subject_slug' => $obligation->slug]);

        $this->artisan('alerts:send')->expectsOutputToContain('1 sent')->assertSuccessful();
        Mail::assertSent(DailyAlertMail::class, function (DailyAlertMail $m) {
            return $m->hasTo('deadline@example.org') && $m->changes->isEmpty() && $m->deadlines->contains('title', 'Test application date')
                && str_contains($m->envelope()->subject, 'Application date approaching');
        });
        $rendered = (new DailyAlertMail($user, collect(), Deadline::where('title', 'Test application date')->get(), now()->subDay(), now()))->render();
        $this->assertStringContainsString('Test application date', $rendered);
        $this->assertStringContainsString('/following', $rendered);

        // Six days out is not a milestone and there are no changes: quiet day.
        $this->travelTo(now()->addDay());
        $this->artisan('alerts:send')->expectsOutputToContain('0 sent')->assertSuccessful();
        $this->travelBack();
    }

    public function test_cron_alerts_requires_the_token(): void
    {
        $this->postJson('/cron/alerts')->assertStatus(401);
        AppSetting::put('cron_token', str_repeat('a', 32));
        $this->postJson('/cron/alerts', [], ['Authorization' => 'Bearer '.str_repeat('a', 32)])->assertOk()->assertJsonStructure(['message']);
        $this->assertSame(0, AlertDelivery::count());
    }
}
