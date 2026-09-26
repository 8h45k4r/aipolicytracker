<?php

namespace App\Models;

use App\Services\Templates\TemplateBuilder;
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

    /** Whether a change event matches the subscriber's topics (jurisdiction slug, policy slug, "templates" or "all"). */
    public function wants(ChangeEvent $change): bool
    {
        $topics = $this->topics ?: ['all'];

        return in_array('all', $topics, true)
            || (in_array('templates', $topics, true) && str_starts_with((string) $change->slug, TemplateBuilder::CHANGE_SLUG_PREFIX))
            || in_array($change->jurisdiction?->slug, $topics, true)
            || ($change->policyInstrument && in_array($change->policyInstrument->slug, $topics, true));
    }
}
