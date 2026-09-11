<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PolicyVersion extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['version_date' => 'date'];
    }

    public function policyInstrument(): BelongsTo
    {
        return $this->belongsTo(PolicyInstrument::class);
    }
}
