<?php

namespace App\Enums;

enum ReviewStatus: string
{
    case Draft = 'draft';
    case PendingReview = 'pending_review';
    case Verified = 'verified';
    case NeedsUpdate = 'needs_update';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::PendingReview => 'Pending human review',
            self::Verified => 'Verified against primary source',
            self::NeedsUpdate => 'Needs update',
        };
    }

    public static function values(): array
    {
        return array_map(fn (self $s) => $s->value, self::cases());
    }
}
