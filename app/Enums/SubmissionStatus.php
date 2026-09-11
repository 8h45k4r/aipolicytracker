<?php

namespace App\Enums;

enum SubmissionStatus: string
{
    case PendingReview = 'pending_review';
    case NeedsInformation = 'needs_information';
    case Approved = 'approved';
    case Rejected = 'rejected';

    public function label(): string
    {
        return match ($this) {
            self::PendingReview => 'Pending review',
            self::NeedsInformation => 'Needs information',
            self::Approved => 'Approved',
            self::Rejected => 'Rejected',
        };
    }

    public static function values(): array
    {
        return array_map(fn (self $s) => $s->value, self::cases());
    }
}
