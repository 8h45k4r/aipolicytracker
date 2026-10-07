<?php

namespace App\Services\Subscribers;

use App\Models\ConsentEvent;
use App\Models\Subscriber;
use App\Models\User;

/**
 * Joins an account's "policy-update emails" choice to the weekly digest, which
 * sends only to subscriber rows. Before this, ticking the box at sign-up or on
 * the account page recorded consent and nothing ever arrived.
 *
 * A verified account address needs no second confirmation email: verifying the
 * address is the opt-in. An unverified one is synced when it is verified.
 */
class AccountDigest
{
    /** Whether the account's address currently receives the digest. */
    public function active(User $user): bool
    {
        return Subscriber::where('email', strtolower((string) $user->email))->active()->exists();
    }

    /** Subscribes or unsubscribes the account's address to match $wanted, and records the decision. */
    public function set(User $user, bool $wanted, string $source = 'account'): void
    {
        $user->forceFill(['marketing_consent_at' => $wanted ? ($user->marketing_consent_at ?? now()) : null])->save();
        if (! $user->hasVerifiedEmail() || $this->active($user) === $wanted) {
            return;
        }

        $subscriber = Subscriber::firstOrNew(['email' => strtolower((string) $user->email)]);
        if ($wanted) {
            $subscriber->token ??= Subscriber::newToken();
            $subscriber->topics ??= ['all'];
            $subscriber->source ??= $source;
            $subscriber->fill(['confirmed_at' => now(), 'unsubscribed_at' => null])->save();
        } elseif ($subscriber->exists) {
            $subscriber->update(['unsubscribed_at' => now()]);
        }
        ConsentEvent::record($user, 'digest.email', $wanted, $source);
    }

    /** Called once the address is verified: honours a choice made at sign-up. */
    public function afterVerification(User $user): void
    {
        if ($user->marketing_consent_at) {
            $this->set($user, true, 'registration');
        }
    }
}
