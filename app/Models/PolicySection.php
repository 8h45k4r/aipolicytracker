<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PolicySection extends Model
{
    protected $guarded = [];

    public function policyInstrument(): BelongsTo
    {
        return $this->belongsTo(PolicyInstrument::class);
    }

    public function obligations(): HasMany
    {
        return $this->hasMany(Obligation::class);
    }
}
