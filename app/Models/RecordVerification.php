<?php

namespace App\Models;

use App\Services\Review\ReviewableTypes;
use Illuminate\Database\Eloquent\Model;

/** A human verification decision for a reviewable record (see ReviewableTypes), kept across imports. */
class RecordVerification extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['last_verified_at' => 'date', 'exported' => 'boolean'];
    }

    /**
     * Re-apply stored verifications to the freshly imported rows.
     *
     * Only decisions not yet exported: an exported one has been written into the record's
     * YAML, which is the source of truth from then on. Re-applying it after every import
     * meant a later change in the file (a record marked needs_update, a date corrected)
     * was overwritten every night and the site kept calling a stale record verified.
     */
    public static function applyAll(): int
    {
        $n = 0;
        foreach (static::query()->where('exported', false)->cursor() as $v) {
            $model = ReviewableTypes::find($v->record_type, $v->record_slug);
            if ($model) {
                $model->forceFill(['review_status' => $v->review_status, 'confidence_level' => $v->confidence_level, 'last_verified_at' => $v->last_verified_at, 'reviewed_by' => $v->reviewed_by])->save();
                $n++;
            }
        }

        return $n;
    }
}
