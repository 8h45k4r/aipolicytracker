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

    /** Re-apply every stored verification to the freshly imported rows. */
    public static function applyAll(): int
    {
        $n = 0;
        foreach (static::query()->cursor() as $v) {
            $model = ReviewableTypes::find($v->record_type, $v->record_slug);
            if ($model) {
                $model->forceFill(['review_status' => $v->review_status, 'confidence_level' => $v->confidence_level, 'last_verified_at' => $v->last_verified_at, 'reviewed_by' => $v->reviewed_by])->save();
                $n++;
            }
        }

        return $n;
    }
}
