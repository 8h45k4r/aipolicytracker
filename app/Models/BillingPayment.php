<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A payment the provider reported as succeeded, kept so that a later refund or
 * dispute (which names only the payment) can be traced to the subscription it
 * paid for. Written only by verified webhooks.
 */
class BillingPayment extends Model
{
    public const STATUSES = ['succeeded', 'partially_refunded', 'refunded', 'disputed', 'dispute_lost', 'dispute_won'];

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'total_amount' => 'integer',
            'refunded_amount' => 'integer',
            'paid_at' => 'datetime',
        ];
    }
}
