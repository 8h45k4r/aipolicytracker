<?php

namespace App\Services\Billing;

use App\Models\Subscription;
use App\Models\User;

/**
 * Decides what a user may do from the local subscription mirror. Access is
 * granted only for a subscription whose plan we sell and whose status (plus
 * the provider's dates) still covers today; nothing here calls the provider.
 */
class Entitlements
{
    public function __construct(private BillingConfig $config, private PlanCatalog $catalog) {}

    public function activeSubscription(User $user): ?Subscription
    {
        return Subscription::query()->forUser($user->id)->whereNotNull('plan_key')->orderByDesc('id')->get()
            ->first(fn (Subscription $s) => $this->covers($s));
    }

    /** Whether a subscription grants access right now. */
    public function covers(Subscription $s, ?\DateTimeInterface $now = null): bool
    {
        $now = $now ? \Carbon\Carbon::instance($now) : now();
        if ($s->plan_key === null || $this->catalog->plan($s->plan_key) === null) {
            return false;
        }
        if ($s->expires_at && $s->expires_at->lte($now)) {
            return false;
        }

        return match ($s->status) {
            'active' => true,
            // Renewal failed: keep access for the grace period so the customer can fix the payment method.
            'on_hold', 'past_due' => ($s->on_hold_at ?? $s->last_event_at ?? $s->updated_at)?->copy()->addDays($this->config->onHoldGraceDays())->gt($now) ?? false,
            // Cancelled at period end: paid until the period closes.
            'cancelled' => $s->current_period_end !== null && $s->current_period_end->gt($now),
            default => false,
        };
    }

    /** Capability lookup: plan entitlements for a covering subscription, otherwise the free tier. */
    public function allows(User $user, string $capability): bool
    {
        $value = $this->value($user, $capability);

        return is_bool($value) ? $value : (is_numeric($value) && $value > 0);
    }

    /** Raw entitlement value (bool or number), null when the capability is unknown. */
    public function value(User $user, string $capability): mixed
    {
        $sub = $this->activeSubscription($user);
        $entitlements = $sub ? ($this->catalog->plan($sub->plan_key)['entitlements'] ?? []) : ($this->catalog->free()['entitlements'] ?? []);

        return $entitlements[$capability] ?? null;
    }
}
