<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** One received provider webhook, stored exactly once (unique event_id) with its processing outcome. */
class BillingEvent extends Model
{
    public const OUTCOMES = ['applied', 'ignored', 'stale', 'error'];

    protected $guarded = [];

    protected function casts(): array
    {
        return ['payload' => 'array', 'event_at' => 'datetime', 'received_at' => 'datetime', 'processed_at' => 'datetime'];
    }
}
