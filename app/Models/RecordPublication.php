<?php

namespace App\Models;

use App\Services\Review\ReviewableTypes;
use Illuminate\Database\Eloquent\Model;

/**
 * An admin's publish decision for a reviewable record (see ReviewableTypes), kept across
 * imports. Only a takedown overrides the data files: an import leaves a taken-down record
 * unpublished until an admin republishes it. Republishing clears the takedown and restores
 * the date the record first went public.
 */
class RecordPublication extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['published' => 'boolean', 'first_published_at' => 'datetime'];
    }

    /**
     * Every decision, keyed "type:slug", for an import to consult without a query per record.
     *
     * @return array<string, self>
     */
    public static function byKey(): array
    {
        return static::query()->get()->keyBy(fn (self $p) => $p->record_type.':'.$p->record_slug)->all();
    }

    /** The review type for a model class, or null when records of that class cannot be published from the admin. */
    public static function typeFor(string $modelClass): ?string
    {
        foreach (ReviewableTypes::TYPES as $type => $meta) {
            if ($meta['model'] === $modelClass) {
                return $type;
            }
        }

        return null;
    }
}
