<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EvidenceArtifact extends Model
{
    protected $guarded = [];

    public function obligation(): BelongsTo
    {
        return $this->belongsTo(Obligation::class);
    }
}
