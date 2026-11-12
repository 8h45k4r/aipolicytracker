<?php

namespace Tests\Feature;

use App\Http\Controllers\Site\SubscribeController;
use App\Mail\SubscriptionConfirmMail;
use App\Models\Subscriber;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Bots that solve the bot check signed up hundreds of made-up addresses, and the confirmation
 * emails to them used up the mail provider's daily quota, so emails people asked for (template
 * downloads) failed. The digest form now has a time trap, a per-client cap and a site-wide daily
 * budget of confirmation emails. All three are off by default in tests (phpunit.xml).
 */
class SubscribeLimitsTest extends TestCase
{
    use RefreshDatabase;

    private function signUp(string $email, ?string $stamp = null, string $ip = '203.0.113.7')
    {
        return $this->withServerVariables(['REMOTE_ADDR' => $ip])->from('/subscribe')
            ->post(route('subscribe.store'), ['email' => $email, 'terms' => '1', 't' => $stamp ?? Crypt::encryptString((string) (now()->getTimestamp() - 10))]);
    }

    public function test_a_form_sent_back_faster_than_a_person_types_is_ignored_without_saying_so(): void
    {
        config(['aipolicytracker.subscribe.min_fill_seconds' => 3]);
        Mail::fake();

        $this->signUp('fast@example.org', SubscribeController::formStamp())->assertSessionHas('success')->assertSessionHasNoErrors();
        $this->signUp('nostamp@example.org', 'not-a-stamp')->assertSessionHas('success');
        $this->assertSame(0, Subscriber::count());
        Mail::assertNothingSent();

        $this->signUp('person@example.org')->assertSessionHas('success');
        $this->assertSame(1, Subscriber::count());
        Mail::assertSent(SubscriptionConfirmMail::class, 1);

        // Every subscribe form carries the stamp.
        $this->assertStringContainsString('name="t" value="', $this->get('/subscribe')->getContent());
        $this->assertStringContainsString('name="t" value="', $this->get('/')->getContent());
    }

    public function test_one_client_address_gets_a_few_sign_ups_a_day(): void
    {
        config(['aipolicytracker.subscribe.per_ip_per_day' => 3]);
        Mail::fake();

        foreach (range(1, 5) as $n) {
            $this->signUp("reader{$n}@example.org")->assertSessionHas('success');
        }
        $this->assertSame(3, Subscriber::count(), 'the fourth and fifth are not stored');
        Mail::assertSent(SubscriptionConfirmMail::class, 3);

        $this->signUp('other@example.org', null, '198.51.100.9')->assertSessionHas('success');
        $this->assertSame(4, Subscriber::count(), 'another client address is not affected');
    }

    public function test_confirmation_emails_stop_at_the_daily_budget_and_a_person_is_told(): void
    {
        config(['aipolicytracker.subscribe.confirmations_per_day' => 2]);
        Mail::fake();

        $this->signUp('a@example.org', null, '192.0.2.1')->assertSessionHas('success');
        $this->signUp('b@example.org', null, '192.0.2.2')->assertSessionHas('success');
        $this->signUp('c@example.org', null, '192.0.2.3')
            ->assertSessionHasErrors(['email' => 'New sign-ups are paused for today. Please try again tomorrow.']);
        $this->signUp('d@example.org', null, '192.0.2.4')->assertSessionHasErrors('email');

        $this->assertSame(['a@example.org', 'b@example.org'], Subscriber::orderBy('email')->pluck('email')->all());
        Mail::assertSent(SubscriptionConfirmMail::class, 2);

        $this->travel(1)->days();
        $this->signUp('c@example.org', null, '192.0.2.3')->assertSessionHas('success');
        $this->assertSame(3, Subscriber::count(), 'the budget starts again the next day');
    }
}
