<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * A change to a recorded deadline's date or status, written by the importer
 * when a re-import finds a deadline whose date differs from the one stored.
 * It is what lets a page say "originally 2 August 2026, now 2 December 2027".
 * Deadlines are replaced wholesale on import, so they are keyed by instrument
 * and title rather than by id.
 */
class DeadlineRevision extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['from_due_on' => 'date', 'to_due_on' => 'date', 'changed_at' => 'datetime'];
    }

    public static function keyFor(string $title): string
    {
        return Str::limit(Str::slug($title), 120, '');
    }

    public function policyInstrument(): BelongsTo
    {
        return $this->belongsTo(PolicyInstrument::class);
    }

    /** The first date ever recorded for this deadline, following the chain back. */
    public static function originalFor(int $policyId, string $title): ?self
    {
        return self::where('policy_instrument_id', $policyId)->where('deadline_key', self::keyFor($title))->orderBy('changed_at')->first();
    }
}
