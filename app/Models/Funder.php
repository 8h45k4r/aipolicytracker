<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * A funder disclosed on /funding. Kept by the owner in the admin; only published rows
 * are shown. Amounts are free text, written as agreed, and never computed.
 */
class Funder extends Model
{
    public const KINDS = ['grant' => 'Grant', 'sponsor' => 'Sponsor', 'subscription' => 'Subscription', 'other' => 'Other'];

    protected $fillable = ['name', 'kind', 'amount_display', 'period', 'purpose', 'url', 'starts_on', 'ends_on', 'published', 'sort'];

    protected function casts(): array
    {
        return ['starts_on' => 'date', 'ends_on' => 'date', 'published' => 'boolean', 'sort' => 'integer'];
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('published', true);
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort')->orderBy('id');
    }

    public function kindLabel(): string
    {
        return self::KINDS[$this->kind] ?? $this->kind;
    }
}
