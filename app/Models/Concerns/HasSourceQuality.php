<?php

namespace App\Models\Concerns;

use App\Enums\ConfidenceLevel;
use App\Enums\ReviewStatus;

/**
 * Shared helpers for records that carry the source-quality columns
 * (official_source_url, last_verified_at, review_status, confidence_level, ...).
 */
trait HasSourceQuality
{
    public function reviewStatusEnum(): ReviewStatus
    {
        return ReviewStatus::tryFrom((string) $this->review_status) ?? ReviewStatus::PendingReview;
    }

    public function confidenceEnum(): ConfidenceLevel
    {
        return ConfidenceLevel::tryFrom((string) $this->confidence_level) ?? ConfidenceLevel::Medium;
    }

    public function isVerified(): bool
    {
        return $this->reviewStatusEnum() === ReviewStatus::Verified && $this->last_verified_at !== null;
    }

    /** Human-readable verification line shown next to factual content. */
    public function verificationLabel(): string
    {
        if ($this->isVerified()) {
            return 'Verified against the official source '.$this->last_verified_at->format('j M Y');
        }
        if (! empty($this->last_checked_at)) {
            return 'Source-linked · checked '.$this->last_checked_at->format('j M Y');
        }

        return 'Source-linked';
    }

    /** Records are considered stale when not verified in the last 180 days. */
    public function isStale(): bool
    {
        return $this->last_verified_at === null || $this->last_verified_at->lt(now()->subDays(180));
    }

    public function scopePublished($query)
    {
        return $query->whereNotNull($query->getModel()->getTable().'.published_at')
            ->where($query->getModel()->getTable().'.published_at', '<=', now());
    }
}
