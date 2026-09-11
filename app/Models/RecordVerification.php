<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** A human verification decision for a policy or jurisdiction record, kept across imports. */
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
            $model = $v->record_type === 'policy' ? PolicyInstrument::where('slug', $v->record_slug)->first() : Jurisdiction::where('slug', $v->record_slug)->first();
            if ($model) {
                $model->forceFill(['review_status' => $v->review_status, 'confidence_level' => $v->confidence_level, 'last_verified_at' => $v->last_verified_at, 'reviewed_by' => $v->reviewed_by])->save();
                $n++;
            }
        }

        return $n;
    }
}
