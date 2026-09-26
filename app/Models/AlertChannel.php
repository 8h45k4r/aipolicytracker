<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * Where an account's alerts go besides its inbox: a private RSS feed (the
 * secret is the feed token), a Slack incoming webhook, or a generic webhook
 * signed with HMAC-SHA256 over the body using the secret shown once.
 */
class AlertChannel extends Model
{
    public const KINDS = ['email' => 'Email', 'rss' => 'Private RSS feed', 'slack' => 'Slack', 'webhook' => 'Webhook (HMAC-signed)'];

    public const MAX_PER_USER = 6;

    protected $guarded = [];

    protected $hidden = ['secret'];

    protected function casts(): array
    {
        return ['enabled' => 'boolean', 'confirmed_at' => 'datetime', 'last_delivered_at' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function deliveries(): HasMany
    {
        return $this->hasMany(ChannelDelivery::class);
    }

    public static function newSecret(): string
    {
        return Str::random(48);
    }

    public function kindLabel(): string
    {
        return self::KINDS[$this->kind] ?? ucfirst($this->kind);
    }

    /** The webhook signature for a body: sha256=<hex hmac>. */
    public function sign(string $body): string
    {
        return 'sha256='.hash_hmac('sha256', $body, (string) $this->secret);
    }

    public function feedUrl(): ?string
    {
        return $this->kind === 'rss' && $this->secret ? route('alerts.feed', $this->secret) : null;
    }

    /** The endpoint with its path shortened, for display. */
    public function endpointLabel(): string
    {
        if (! $this->endpoint) {
            return '';
        }
        $host = parse_url($this->endpoint, PHP_URL_HOST) ?: $this->endpoint;
        $path = parse_url($this->endpoint, PHP_URL_PATH) ?: '';

        return $host.(strlen($path) > 18 ? substr($path, 0, 15).'…' : $path);
    }
}
