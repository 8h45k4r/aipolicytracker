<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One look at an instrument's official source by the change desk, and what it found. */
class SourceCheck extends Model
{
    protected $guarded = [];

    public const OUTCOMES = [
        'no_change' => 'No change',
        'change_logged' => 'Change found and logged',
        'source_unreachable' => 'Source unreachable',
    ];

    protected function casts(): array
    {
        return ['checked_at' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public static function latestFor(string $type, string $slug): ?self
    {
        return self::where('record_type', $type)->where('record_slug', $slug)->latest('checked_at')->first();
    }
}
