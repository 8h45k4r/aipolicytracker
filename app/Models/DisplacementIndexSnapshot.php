<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One computed value of the displacement policy index for a jurisdiction and quarter, with the inputs it was computed from. */
class DisplacementIndexSnapshot extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['subscores' => 'array', 'inputs' => 'array', 'computed_at' => 'datetime', 'score' => 'integer'];
    }

    public function jurisdiction(): BelongsTo
    {
        return $this->belongsTo(Jurisdiction::class);
    }
}
