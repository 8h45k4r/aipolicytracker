<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ControlEvidence extends Model
{
    protected $table = 'control_evidence';

    protected $guarded = [];

    public function control(): BelongsTo
    {
        return $this->belongsTo(Control::class);
    }

    /** The taxonomy name for the evidence type, falling back to the slug. */
    public function typeName(): string
    {
        return TaxonomyTerm::query()->where('taxonomy', 'evidence_type')->where('slug', $this->evidence_type)->value('name') ?? str_replace('_', ' ', (string) $this->evidence_type);
    }
}
