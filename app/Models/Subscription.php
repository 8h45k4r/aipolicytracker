<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Local mirror of a provider subscription. Rows are written only by verified
 * webhooks (see WebhookProcessor); the application never marks a subscription
 * active on its own.
 */
class Subscription extends Model
{
    public const STATUSES = ['pending', 'active', 'on_hold', 'paused', 'past_due', 'cancelled', 'failed', 'expired'];

    /** Statuses that never grant access, whatever the dates say. */
    public const DEAD = ['failed', 'expired', 'pending', 'paused'];

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'current_period_end' => 'datetime',
            'cancel_at_period_end' => 'boolean',
            'cancelled_at' => 'datetime',
            'on_hold_at' => 'datetime',
            'expires_at' => 'datetime',
            'last_event_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function billingCustomer(): BelongsTo
    {
        return $this->belongsTo(BillingCustomer::class);
    }

    public function scopeForUser(Builder $query, int $userId): Builder
    {
        return $query->where('user_id', $userId);
    }

    /** Plan definition from config/billing.php, or null when the product is no longer sold. */
    public function plan(): ?array
    {
        return $this->plan_key ? (config('billing.plans.'.$this->plan_key) ?: null) : null;
    }

    public function planName(): string
    {
        return $this->plan()['name'] ?? ($this->plan_key ?: 'Unknown plan');
    }
}
