<?php

namespace App\Services\Billing;

use App\Mail\SubscriptionPaymentFailedMail;
use App\Models\BillingCheckout;
use App\Models\BillingCustomer;
use App\Models\BillingEvent;
use App\Models\Subscription;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Applies an already-verified provider webhook to the local subscription
 * mirror. Every delivery is stored once by its webhook id, so provider retries
 * are idempotent; events older than the last applied one for a subscription
 * are recorded as stale and skipped, so out-of-order delivery cannot
 * downgrade or upgrade access incorrectly.
 */
class WebhookProcessor
{
    public const OUTCOME_APPLIED = 'applied';

    public const OUTCOME_IGNORED = 'ignored';

    public const OUTCOME_STALE = 'stale';

    public const OUTCOME_DUPLICATE = 'duplicate';

    public const OUTCOME_ERROR = 'error';

    public function __construct(private PlanCatalog $catalog) {}

    /**
     * @param  array  $payload  decoded, signature-verified body
     * @param  string  $eventId  the webhook-id header (unique per event, stable across retries)
     * @return string one of the OUTCOME_* constants
     *
     * @throws \Throwable when applying the event fails; the event row keeps the error
     */
    public function handle(array $payload, string $eventId): string
    {
        $type = (string) ($payload['type'] ?? '');
        $data = is_array($payload['data'] ?? null) ? $payload['data'] : [];
        $eventAt = $this->parseDate($payload['timestamp'] ?? null) ?? now();

        try {
            // Wrapped so the insert has its own transaction, or a SAVEPOINT when one
            // is already open. Idempotency is the unique index on `event_id`, so the
            // duplicate path runs *after* a failed insert — and PostgreSQL aborts the
            // whole transaction on a constraint violation, refusing every later
            // statement with SQLSTATE 25P02 until it is rolled back. Without the
            // savepoint the existence check inside the catch is itself refused, and a
            // provider's ordinary retry gets a 500 instead of an acknowledgement,
            // which makes it retry harder. SQLite does not poison the transaction, so
            // the suite never saw it; PostgreSQL in CI is what found it.
            $event = DB::transaction(fn () => BillingEvent::create([
                'provider' => 'dodo',
                'event_id' => $eventId,
                'event_type' => $type !== '' ? $type : 'unknown',
                'provider_subscription_id' => $data['subscription_id'] ?? null,
                'event_at' => $eventAt,
                'payload' => $payload,
                'received_at' => now(),
            ]));
        } catch (QueryException $e) {
            $event = BillingEvent::where('event_id', $eventId)->first();
            if (! $event) {
                throw $e;
            }
            // A retry of an event that failed while being applied is applied again.
            // Acknowledging it as a duplicate would lose it for good: a cancellation
            // that hit a deadlock would leave Pro access in place indefinitely.
            if ($event->outcome !== self::OUTCOME_ERROR) {
                return self::OUTCOME_DUPLICATE;
            }
            $event->forceFill(['error' => null])->save();
        }

        try {
            $outcome = str_starts_with($type, 'subscription.') && ! empty($data['subscription_id'])
                ? $this->applySubscription($type, $data, $eventAt)
                : self::OUTCOME_IGNORED;
            $event->forceFill(['processed_at' => now(), 'outcome' => $outcome])->save();

            return $outcome;
        } catch (\Throwable $e) {
            $event->forceFill(['processed_at' => now(), 'outcome' => self::OUTCOME_ERROR, 'error' => mb_substr($e->getMessage(), 0, 2000)])->save();
            Log::error('billing.webhook.error', ['event_id' => $eventId, 'type' => $type, 'error' => $e->getMessage()]);
            throw $e;
        }
    }

    private function applySubscription(string $type, array $data, Carbon $eventAt): string
    {
        $providerSubId = (string) $data['subscription_id'];
        $customer = is_array($data['customer'] ?? null) ? $data['customer'] : [];
        $metadata = is_array($data['metadata'] ?? null) ? $data['metadata'] : [];

        return DB::transaction(function () use ($type, $data, $eventAt, $providerSubId, $customer, $metadata) {
            $existing = Subscription::where('provider_subscription_id', $providerSubId)->lockForUpdate()->first();
            if ($existing && $existing->last_event_at && $existing->last_event_at->gt($eventAt)) {
                return self::OUTCOME_STALE;
            }

            $user = $existing?->user ?? $this->resolveUser($metadata, $customer);
            if (! $user) {
                Log::warning('billing.webhook.unresolved_user', ['subscription_id' => $providerSubId, 'type' => $type]);

                return self::OUTCOME_IGNORED;
            }

            $billingCustomer = $this->syncCustomer($user, $customer);
            $status = $this->status($type, $data, $existing);
            $wasCovering = $existing !== null && in_array($existing->status, ['active', 'cancelled'], true);

            $sub = $existing ?? new Subscription(['provider' => 'dodo', 'provider_subscription_id' => $providerSubId, 'user_id' => $user->id]);
            $productId = $data['product_id'] ?? $sub->product_id;
            $sub->fill([
                'billing_customer_id' => $billingCustomer?->id ?? $sub->billing_customer_id,
                'product_id' => $productId,
                'plan_key' => $this->catalog->keyForProduct($productId) ?? ($existing?->plan_key),
                'status' => $status,
                'current_period_end' => $this->parseDate($data['next_billing_date'] ?? null) ?? $sub->current_period_end,
                'cancel_at_period_end' => (bool) ($data['cancel_at_next_billing_date'] ?? $sub->cancel_at_period_end ?? false),
                'cancelled_at' => $this->parseDate($data['cancelled_at'] ?? null) ?? ($status === 'cancelled' ? ($sub->cancelled_at ?? $eventAt) : $sub->cancelled_at),
                'on_hold_at' => $status === 'on_hold' ? ($existing?->status === 'on_hold' ? $sub->on_hold_at : $eventAt) : null,
                'expires_at' => $this->parseDate($data['expires_at'] ?? null) ?? ($status === 'expired' ? ($sub->expires_at ?? $eventAt) : $sub->expires_at),
                'last_event_at' => $eventAt,
                'last_event_type' => $type,
                'metadata' => $metadata ?: $sub->metadata,
            ]);
            $sub->save();

            if ($status === 'active' && $sub->plan_key) {
                BillingCheckout::where('user_id', $user->id)->where('plan_key', $sub->plan_key)->whereIn('status', ['created', 'returned'])->update(['status' => 'completed']);
            }
            if ($status === 'on_hold' && $wasCovering) {
                $this->notifyPaymentFailed($user, $sub);
            }

            return self::OUTCOME_APPLIED;
        });
    }

    /** Status from the payload when valid, otherwise derived from the event name. */
    private function status(string $type, array $data, ?Subscription $existing): string
    {
        $given = strtolower((string) ($data['status'] ?? ''));
        if (in_array($given, Subscription::STATUSES, true)) {
            return $given;
        }

        return match ($type) {
            'subscription.active', 'subscription.renewed', 'subscription.plan_changed' => 'active',
            'subscription.on_hold' => 'on_hold',
            'subscription.cancelled' => 'cancelled',
            'subscription.failed' => 'failed',
            'subscription.expired' => 'expired',
            'subscription.paused' => 'paused',
            default => $existing?->status ?? 'pending',
        };
    }

    /** Who the subscription belongs to: our metadata first, then the provider customer id, then the email. */
    private function resolveUser(array $metadata, array $customer): ?User
    {
        if (! empty($metadata['app_user_id']) && ctype_digit((string) $metadata['app_user_id'])) {
            if ($user = User::find((int) $metadata['app_user_id'])) {
                return $user;
            }
        }
        if (! empty($customer['customer_id'])) {
            $bc = BillingCustomer::where('provider_customer_id', (string) $customer['customer_id'])->first();
            if ($bc?->user) {
                return $bc->user;
            }
        }
        if (! empty($customer['email'])) {
            return User::whereRaw('LOWER(email) = ?', [strtolower((string) $customer['email'])])->first();
        }

        return null;
    }

    private function syncCustomer(User $user, array $customer): ?BillingCustomer
    {
        $providerId = isset($customer['customer_id']) ? (string) $customer['customer_id'] : null;
        $existing = BillingCustomer::where('user_id', $user->id)->first();
        if ($existing) {
            if ($providerId && $existing->provider_customer_id !== $providerId && ! BillingCustomer::where('provider_customer_id', $providerId)->exists()) {
                $existing->update(['provider_customer_id' => $providerId, 'email' => $customer['email'] ?? $existing->email]);
            }

            return $existing;
        }
        if (! $providerId || BillingCustomer::where('provider_customer_id', $providerId)->exists()) {
            return null;
        }

        return BillingCustomer::create(['user_id' => $user->id, 'provider' => 'dodo', 'provider_customer_id' => $providerId, 'email' => $customer['email'] ?? $user->email]);
    }

    private function notifyPaymentFailed(User $user, Subscription $sub): void
    {
        try {
            Mail::to($user->email)->send(new SubscriptionPaymentFailedMail($user, $sub));
        } catch (\Throwable $e) {
            Log::warning('billing.mail.failed', ['user_id' => $user->id, 'error' => $e->getMessage()]);
        }
    }

    private function parseDate(mixed $value): ?Carbon
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }
        try {
            return Carbon::parse($value)->utc();
        } catch (\Throwable) {
            return null;
        }
    }
}
