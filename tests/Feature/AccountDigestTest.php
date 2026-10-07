<?php

namespace Tests\Feature;

use App\Models\ConsentEvent;
use App\Models\Subscriber;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

/**
 * The public account journey: sign up, verify, receive the digest, manage it from
 * the account page or any issue's unsubscribe link, and never reach the admin.
 */
class AccountDigestTest extends TestCase
{
    use RefreshDatabase;

    private function register(bool $digest): User
    {
        $this->post('/register', [
            'name' => 'Reader', 'email' => 'reader@example.com', 'phone_no' => '9800000000',
            'password' => 'Password!123', 'password_confirmation' => 'Password!123', 'terms_condition' => true,
            'marketing_consent' => $digest ? '1' : null,
        ])->assertSessionHasNoErrors();

        return User::where('email', 'reader@example.com')->firstOrFail();
    }

    private function verify(User $user): void
    {
        $url = URL::temporarySignedRoute('verification.verify', now()->addHour(), ['id' => $user->id, 'hash' => sha1($user->getEmailForVerification())]);
        $this->actingAs($user)->get($url)->assertRedirect();
    }

    public function test_choosing_the_digest_at_sign_up_subscribes_the_address_once_it_is_verified(): void
    {
        $user = $this->register(digest: true);
        $this->assertSame(0, Subscriber::count(), 'an unproven address is not mailed');
        $this->actingAs($user)->get('/profile')->assertOk()->assertSee('starts once you verify your address');

        $this->verify($user);
        $subscriber = Subscriber::sole();
        $this->assertSame('reader@example.com', $subscriber->email);
        $this->assertTrue($subscriber->isActive(), 'verification is the opt-in; no second confirmation email');
        $this->assertSame(['all'], $subscriber->topics);
        $this->assertTrue(ConsentEvent::where('user_id', $user->id)->where('kind', 'digest.email')->where('granted', true)->exists());
        $this->actingAs($user->fresh())->get('/profile')->assertOk()->assertSee('receiving');
    }

    public function test_not_choosing_the_digest_subscribes_nothing(): void
    {
        $user = $this->register(digest: false);
        $this->verify($user);
        $this->assertSame(0, Subscriber::count());
    }

    public function test_the_account_page_turns_the_digest_on_and_off_and_saving_unchanged_changes_nothing(): void
    {
        $user = User::factory()->create(['email' => 'pat@example.org', 'email_verified_at' => now()]);
        $profile = fn (bool $digest) => $this->actingAs($user)->patch('/profile', ['name' => 'Pat', 'email' => 'pat@example.org', 'marketing_consent' => $digest ? '1' : null])->assertRedirect('/profile');

        $profile(true);
        $this->assertTrue(Subscriber::where('email', 'pat@example.org')->sole()->isActive());

        $profile(false);
        $this->assertFalse(Subscriber::where('email', 'pat@example.org')->sole()->isActive());
        $this->assertNull($user->fresh()->marketing_consent_at);

        // Someone who subscribed from the public form is shown as subscribed, so a name change keeps them.
        Subscriber::where('email', 'pat@example.org')->update(['unsubscribed_at' => null]);
        $this->actingAs($user)->get('/profile')->assertOk()->assertSee('name="marketing_consent" value="1" checked', false);
        $profile(true);
        $this->assertTrue(Subscriber::where('email', 'pat@example.org')->sole()->isActive());
    }

    public function test_unsubscribing_from_an_issue_also_turns_the_account_choice_off(): void
    {
        $user = User::factory()->create(['email' => 'sam@example.org', 'email_verified_at' => now(), 'marketing_consent_at' => now()]);
        $subscriber = Subscriber::create(['email' => 'sam@example.org', 'token' => Subscriber::newToken(), 'topics' => ['all'], 'confirmed_at' => now()]);

        $this->post('/subscribe/unsubscribe/'.$subscriber->token)->assertRedirect();
        $this->assertFalse($subscriber->fresh()->isActive());
        $this->assertNull($user->fresh()->marketing_consent_at);
    }

    public function test_the_header_offers_sign_in_or_the_account_and_members_never_reach_the_admin(): void
    {
        $this->get('/')->assertOk()->assertSee('data-track="header_signin_click"', false)->assertDontSee('data-track="header_account_click"', false);

        $member = User::factory()->create(['email_verified_at' => now()]);
        $this->actingAs($member)->get('/')->assertOk()->assertSee('data-track="header_account_click"', false);
        $this->actingAs($member)->get('/profile')->assertOk()->assertDontSee(route('backend.admin.dashboard'));
        $this->actingAs($member)->get('/backend/admin')->assertRedirect(route('home'));
        $this->actingAs($member)->get('/backend/admin/billing')->assertRedirect(route('home'));
    }
}
