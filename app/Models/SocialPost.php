<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A change event as a post on a social network, and how sending it went. */
class SocialPost extends Model
{
    protected $guarded = [];

    public const STATUSES = [
        'draft' => 'Awaiting approval',
        'queued' => 'Queued',
        'posted' => 'Posted',
        'failed' => 'Failed',
        'skipped' => 'Skipped',
    ];

    /** Attempts before a post that keeps meeting rate limits or outages is given up. */
    public const MAX_ATTEMPTS = 5;

    protected function casts(): array
    {
        return ['posted_at' => 'datetime'];
    }

    public function changeEvent(): BelongsTo
    {
        return $this->belongsTo(ChangeEvent::class);
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function scopePostedThisMonth(Builder $query): Builder
    {
        return $query->where('status', 'posted')->where('posted_at', '>=', now()->startOfMonth());
    }

    /** The post on x.com, once it exists. The /i/web/ form needs no handle. */
    public function externalUrl(): ?string
    {
        return $this->external_id ? 'https://x.com/i/web/status/'.$this->external_id : null;
    }

    public function editable(): bool
    {
        return in_array($this->status, ['draft', 'queued', 'failed'], true);
    }
}
