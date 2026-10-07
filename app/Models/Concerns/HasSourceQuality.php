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

    /**
     * One of three public states, the same everywhere a record's review standing
     * is shown (x-site.verified, key facts, machine-readable text):
     *  - verified: a named reviewer opened the official source and confirmed it;
     *  - pending_review: linked to the source and waiting in the review queue;
     *  - source_linked: linked to the source, not confirmed by a reviewer and not
     *    queued (draft, needs update, or verified without a date).
     */
    public function verificationState(): string
    {
        if ($this->isVerified()) {
            return 'verified';
        }

        return (string) $this->review_status === ReviewStatus::PendingReview->value ? 'pending_review' : 'source_linked';
    }

    /** Human-readable verification line shown next to factual content. */
    public function verificationLabel(): string
    {
        if ($this->isVerified()) {
            return 'Verified against the official source '.$this->last_verified_at->format('j M Y');
        }
        $checked = ! empty($this->last_checked_at) ? ', checked '.$this->last_checked_at->format('j M Y') : '';

        return $this->verificationState() === 'pending_review'
            ? 'Pending review · source-linked'.$checked
            : 'Source-linked'.($checked ? ' ·'.substr($checked, 1) : '');
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
