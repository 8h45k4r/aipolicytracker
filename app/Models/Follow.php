<?php

namespace App\Models;

use App\Services\Alerts\WatchTypes;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A user follows a policy, jurisdiction or obligation; daily alerts are built from these rows. */
class Follow extends Model
{
    /** Record types; WatchTypes::all() adds sector, use case, framework, change type and saved search. */
    public const TYPES = ['policy', 'jurisdiction', 'obligation'];

    protected $guarded = [];

    protected function casts(): array
    {
        return ['params' => 'array'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** The followed record, or null when it no longer exists or is unpublished. */
    public function subject(): ?Model
    {
        return match ($this->subject_type) {
            'policy' => PolicyInstrument::published()->where('slug', $this->subject_slug)->first(),
            'jurisdiction' => Jurisdiction::published()->where('slug', $this->subject_slug)->first(),
            'obligation' => Obligation::published()->where('slug', $this->subject_slug)->first(),
            default => null,
        };
    }

    public static function typeLabel(string $type): string
    {
        return WatchTypes::typeLabel($type);
    }
}
