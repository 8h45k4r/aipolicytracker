<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One attempt-tracked delivery of an alert to a Slack or webhook channel. */
class ChannelDelivery extends Model
{
    public const MAX_ATTEMPTS = 5;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['payload' => 'array', 'next_attempt_at' => 'datetime', 'sent_at' => 'datetime', 'attempts' => 'integer'];
    }

    public function channel(): BelongsTo
    {
        return $this->belongsTo(AlertChannel::class, 'alert_channel_id');
    }

    public function scopeDue($q)
    {
        return $q->where('status', 'pending')->where('attempts', '<', self::MAX_ATTEMPTS)->where(fn ($w) => $w->whereNull('next_attempt_at')->orWhere('next_attempt_at', '<=', now()));
    }

    /** Minutes before the next try: 15, 60, 240, 960 (then it is given up). */
    public static function backoffMinutes(int $attempts): int
    {
        return 15 * (4 ** max(0, $attempts - 1));
    }
}
