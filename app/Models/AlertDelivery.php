<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One daily alert email sent to a user, with the window of changes it covered. */
class AlertDelivery extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['sent_on' => 'date', 'window_start' => 'datetime', 'window_end' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
