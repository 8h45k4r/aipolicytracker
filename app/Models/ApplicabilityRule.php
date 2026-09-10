<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ApplicabilityRule extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'actors' => 'array',
            'ai_system_types' => 'array',
            'sectors' => 'array',
            'risk_categories' => 'array',
            'use_cases' => 'array',
        ];
    }

    public function policyInstrument(): BelongsTo
    {
        return $this->belongsTo(PolicyInstrument::class);
    }

    public function obligation(): BelongsTo
    {
        return $this->belongsTo(Obligation::class);
    }
}
