<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One version of a profile's compliance workbook: the screened rows it was built
 * from and what changed against the version before. Stored only when the rows
 * change, so the history lists real changes, not daily rebuilds.
 */
class ProfileWorkbookVersion extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['snapshot' => 'array', 'record_changes' => 'array', 'notified_at' => 'datetime'];
    }

    public function profile(): BelongsTo
    {
        return $this->belongsTo(ApplicabilityProfile::class, 'applicability_profile_id');
    }

    /** Counts for a one-line summary: added, removed, changed. */
    public function changeCounts(): array
    {
        $c = $this->record_changes ?? [];

        return ['added' => count($c['added'] ?? []), 'removed' => count($c['removed'] ?? []), 'changed' => count($c['changed'] ?? [])];
    }

    public function hasRecordChanges(): bool
    {
        return array_sum($this->changeCounts()) > 0;
    }
}
