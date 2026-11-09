<?php

namespace Tests\Feature;

use App\Http\Controllers\Auth\PasswordResetLinkController;
use App\Mail\EmailChangedMail;
use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * The pre-launch audit's auth findings: password spraying from one address, unthrottled
 * password and account endpoints, account enumeration through the reset form, an email
 * change without the password, and a deletion error the form never showed.
 */
class AuthHardeningTest extends TestCase
{
    use RefreshDatabase;

    public function test_one_address_trying_many_emails_is_stopped(): void
    {
        // Spraying: a different email each time, so the per-email counter never fills.
        for ($i = 0; $i < 20; $i++) {
            $this->post('/login', ['email' => "person{$i}@example.org", 'password' => 'Winter2026!'])
                ->assertSessionHasErrors('email');
        }
        $this->post('/login', ['email' => 'person99@example.org', 'password' => 'Winter2026!'])
            ->assertSessionHasErrors('email');
        $this->assertStringContainsString('Too many login attempts', session('errors')->first('email'));

        // Even the right password is refused from that address until the window passes.
        $user = User::factory()->create(['email' => 'real@example.org']);
        $this->post('/login', ['email' => 'real@example.org', 'password' => 'password']);
        $this->assertGuest();

        $this->travel(11)->minutes();
        $this->post('/login', ['email' => 'real@example.org', 'password' => 'password'])->assertRedirect();
        $this->assertAuthenticatedAs($user);
    }

    public function test_a_different_address_is_not_affected(): void
    {
        for ($i = 0; $i < 21; $i++) {
            $this->post('/login', ['email' => "person{$i}@example.org", 'password' => 'x']);
        }
        $user = User::factory()->create();
        $this->withServerVariables(['REMOTE_ADDR' => '198.51.100.7'])
            ->post('/login', ['email' => $user->email, 'password' => 'password'])->assertRedirect();
        $this->assertAuthenticatedAs($user);
    }

    public function test_password_and_account_endpoints_are_throttled(): void
    {
        $user = User::factory()->create();
        for ($i = 0; $i < 5; $i++) {
            $this->post('/reset-password', ['token' => str_repeat('a', 64), 'email' => 'x@example.org', 'password' => 'Long-enough-1', 'password_confirmation' => 'Long-enough-1'])->assertRedirect();
        }
        $this->post('/reset-password', ['token' => str_repeat('a', 64), 'email' => 'x@example.org', 'password' => 'Long-enough-1', 'password_confirmation' => 'Long-enough-1'])->assertStatus(429);

        for ($i = 0; $i < 5; $i++) {
            $this->actingAs($user)->from('/profile')->delete('/profile', ['password' => 'wrong'])->assertRedirect('/profile');
        }
        $this->actingAs($user)->delete('/profile', ['password' => 'wrong'])->assertStatus(429);

        // The password change shares the account's current-password budget with deletion.
        $this->travel(2)->minutes();
        for ($i = 0; $i < 5; $i++) {
            $this->actingAs($user)->from('/profile')->put('/password', ['current_password' => 'wrong', 'password' => 'Long-enough-1', 'password_confirmation' => 'Long-enough-1'])->assertRedirect('/profile');
        }
        $this->actingAs($user)->put('/password', ['current_password' => 'wrong', 'password' => 'Long-enough-1', 'password_confirmation' => 'Long-enough-1'])->assertStatus(429);
        $this->assertNotNull($user->fresh());
    }

    public function test_an_invalid_reset_token_changes_nothing(): void
    {
        $user = User::factory()->create();
        $hash = $user->password;
        $this->post('/reset-password', ['token' => str_repeat('b', 64), 'email' => $user->email, 'password' => 'Long-enough-1', 'password_confirmation' => 'Long-enough-1'])
            ->assertSessionHasErrors('email');
        $this->assertSame($hash, $user->fresh()->password);
    }

    public function test_the_reset_form_answers_the_same_whether_or_not_an_account_exists(): void
    {
        Notification::fake();
        $user = User::factory()->create(['email' => 'known@example.org']);

        $known = $this->from('/forgot-password')->post('/forgot-password', ['email' => 'known@example.org']);
        $unknown = $this->from('/forgot-password')->post('/forgot-password', ['email' => 'nobody@example.org']);

        foreach ([$known, $unknown] as $response) {
            $response->assertRedirect('/forgot-password')->assertSessionHasNoErrors()->assertSessionHas('status', PasswordResetLinkController::SENT);
        }
        Notification::assertSentTo($user, ResetPassword::class);
        $this->get('/forgot-password')->assertDontSee("can't find", false);
    }

    public function test_changing_the_email_needs_the_current_password_and_tells_the_old_address(): void
    {
        Mail::fake();
        $user = User::factory()->create(['email' => 'old@example.org', 'name' => 'Ana']);

        $this->actingAs($user)->from('/profile')->patch('/profile', ['name' => 'Ana', 'email' => 'new@example.org'])
            ->assertSessionHasErrors('current_password');
        $this->actingAs($user)->from('/profile')->patch('/profile', ['name' => 'Ana', 'email' => 'new@example.org', 'current_password' => 'wrong'])
            ->assertSessionHasErrors('current_password');
        $this->assertSame('old@example.org', $user->fresh()->email);
        Mail::assertNothingSent();

        $this->actingAs($user)->patch('/profile', ['name' => 'Ana', 'email' => 'new@example.org', 'current_password' => 'password'])
            ->assertSessionHasNoErrors()->assertRedirect('/profile');
        $this->assertSame('new@example.org', $user->fresh()->email);
        Mail::assertSent(EmailChangedMail::class, fn (EmailChangedMail $m) => $m->hasTo('old@example.org') && str_contains($m->render(), 'n***@example.org'));

        // Other profile edits need no password.
        $this->actingAs($user->fresh())->patch('/profile', ['name' => 'Ana B', 'email' => 'new@example.org'])->assertSessionHasNoErrors();
        $this->assertSame('Ana B', $user->fresh()->name);
    }

    public function test_the_deletion_form_shows_a_wrong_password_and_confirms_without_an_inline_handler(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->from('/profile')->delete('/profile', ['password' => 'wrong']);
        $html = $this->actingAs($user)->get('/profile')->assertOk()->getContent();

        $this->assertStringContainsString(__('validation.current_password'), $html);
        $this->assertStringContainsString('data-confirm="Delete your account permanently?', $html);
        $this->assertStringNotContainsString('onsubmit=', $html);
    }

    public function test_no_view_relies_on_an_inline_event_handler_the_csp_blocks(): void
    {
        $offenders = [];
        foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(resource_path('views'))) as $file) {
            if ($file->isFile() && preg_match('/\son(?:click|change|submit|input|load|error|keyup|keydown)\s*=/i', file_get_contents($file->getPathname()))) {
                $offenders[] = str_replace(resource_path('views').'/', '', $file->getPathname());
            }
        }
        // Mail views are not served under the CSP.
        $offenders = array_values(array_filter($offenders, fn ($f) => ! str_starts_with($f, 'emails/')));
        $this->assertSame([], $offenders, 'inline handlers never run under the CSP');
    }
}
