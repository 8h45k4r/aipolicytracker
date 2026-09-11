<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Subscriber extends Model
{
    protected $fillable = ['email', 'token', 'topics', 'frequency', 'source', 'confirmed_at', 'unsubscribed_at', 'last_sent_at'];

    protected function casts(): array
    {
        return ['topics' => 'array', 'confirmed_at' => 'datetime', 'unsubscribed_at' => 'datetime', 'last_sent_at' => 'datetime'];
    }

    public static function newToken(): string
    {
        return Str::random(48);
    }

    public function scopeActive($query)
    {
        return $query->whereNotNull('confirmed_at')->whereNull('unsubscribed_at');
    }

    public function isActive(): bool
    {
        return $this->confirmed_at !== null && $this->unsubscribed_at === null;
    }

    /** Whether a change event matches the subscriber's topics (jurisdiction slug or "all"). */
    public function wants(ChangeEvent $change): bool
    {
        $topics = $this->topics ?: ['all'];

        return in_array('all', $topics, true) || in_array($change->jurisdiction?->slug, $topics, true);
    }
}
