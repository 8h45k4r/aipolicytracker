<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SourceDocument extends Model
{
    protected $guarded = [];

    public const TIERS = [
        1 => 'Official primary source (government, legislature, regulator, court, standards body, intergovernmental)',
        2 => 'Official consultation, guidance, enforcement, procurement or agency source',
        3 => 'Reputable institutional secondary source (clearly labelled)',
        4 => 'Community submission awaiting review',
    ];

    protected function casts(): array
    {
        return [
            'document_date' => 'date',
            'retrieved_at' => 'datetime',
        ];
    }

    public function jurisdiction(): BelongsTo
    {
        return $this->belongsTo(Jurisdiction::class);
    }

    public function policyInstrument(): BelongsTo
    {
        return $this->belongsTo(PolicyInstrument::class);
    }

    public function tierLabel(): string
    {
        return self::TIERS[$this->source_tier] ?? 'Unclassified';
    }
}
