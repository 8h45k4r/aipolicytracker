<?php

namespace App\Models;

use App\Enums\SubmissionStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ContributorSubmission extends Model
{
    protected $guarded = [];

    public const TYPES = [
        'correction' => 'Report an error or correction',
        'new_source' => 'Propose an official source',
        'new_policy' => 'Submit a new policy record',
        'reviewer_application' => 'Volunteer as a reviewer',
    ];

    protected function casts(): array
    {
        return ['payload' => 'array'];
    }

    public function statusEnum(): SubmissionStatus
    {
        return SubmissionStatus::tryFrom((string) $this->status) ?? SubmissionStatus::PendingReview;
    }

    public function decisions(): HasMany
    {
        return $this->hasMany(ReviewerDecision::class)->orderByDesc('decided_at');
    }
}
