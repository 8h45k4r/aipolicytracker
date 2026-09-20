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

    /**
     * Raw entitlement value (bool or number), null when the capability is unknown.
     *
     * With selling switched off there is no route by which anyone can hold a
     * subscription, so gating on one would not make a capability paid -- it would
     * make it unreachable. Following records for daily alerts, and saving an
     * applicability profile, would become dead code behind a door with no key.
     *
     * So while billing is disabled the most permissive value any plan defines is
     * granted to every signed-in account. Sign-in is still required: these
     * capabilities write rows owned by a user. Turning billing back on restores
     * the subscription check with no further change here.
     */
    public function value(User $user, string $capability): mixed
    {
        if (! $this->config->enabled()) {
            return $this->mostPermissive($capability);
        }

        $sub = $this->activeSubscription($user);
        $entitlements = $sub ? ($this->catalog->plan($sub->plan_key)['entitlements'] ?? []) : ($this->catalog->free()['entitlements'] ?? []);

        return $entitlements[$capability] ?? null;
    }

    /**
     * The strongest value any tier defines for a capability: true over false, and
     * the largest number over smaller ones. Reads the same configuration the paid
     * tiers are built from, so a capability that no tier defines stays unknown
     * rather than being invented here.
     */
    private function mostPermissive(string $capability): mixed
    {
        $best = $this->catalog->free()['entitlements'][$capability] ?? null;

        foreach ($this->catalog->plans() as $plan) {
            $value = $plan['entitlements'][$capability] ?? null;
            if ($value === null) {
                continue;
            }
            if ($best === null || ($value === true && $best !== true) || (is_numeric($value) && is_numeric($best) && $value > $best)) {
                $best = $value;
            }
        }

        return $best;
    }
}
