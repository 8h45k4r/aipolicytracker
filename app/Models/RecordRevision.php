<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/** One revision of a record: the fields that changed and when. */
class RecordRevision extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['fields' => 'array', 'changed_at' => 'datetime'];
    }

    public function scopeFor(Builder $query, string $type, string $slug): Builder
    {
        return $query->where('record_type', $type)->where('record_slug', $slug)->orderByDesc('changed_at')->orderByDesc('id');
    }
}
