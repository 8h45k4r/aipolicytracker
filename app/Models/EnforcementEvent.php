<?php

namespace App\Models;

use App\Models\Concerns\HasSourceQuality;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * An enforcement action under a recorded instrument: a fine, order, warning,
 * settlement, court decision or annulment. Recorded inside the instrument's YAML
 * file (enforcement_events) and listed across instruments at /enforcement.
 */
class EnforcementEvent extends Model
{
    use HasSourceQuality;

    public const KINDS = [
        'fine' => 'Fine', 'order' => 'Order', 'warning' => 'Warning', 'settlement' => 'Settlement',
        'court_decision' => 'Court decision', 'annulment' => 'Annulment',
    ];

    public const APPEAL_STATUSES = [
        'not_appealed' => 'Not appealed', 'appeal_pending' => 'Appeal pending', 'upheld_on_appeal' => 'Upheld on appeal',
        'overturned_on_appeal' => 'Overturned on appeal', 'unknown' => 'Unknown',
    ];

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'occurred_on' => 'date',
            'amount' => 'decimal:2',
            'source_document_date' => 'date',
            'last_checked_at' => 'datetime',
            'last_verified_at' => 'datetime',
            'published_at' => 'datetime',
        ];
    }

    public function jurisdiction(): BelongsTo
    {
        return $this->belongsTo(Jurisdiction::class);
    }

    public function policyInstrument(): BelongsTo
    {
        return $this->belongsTo(PolicyInstrument::class);
    }

    public function isDraft(): bool
    {
        return $this->review_status === 'draft';
    }

    public function kindLabel(): ?string
    {
        return $this->kind ? (self::KINDS[$this->kind] ?? $this->kind) : null;
    }

    public function appealLabel(): ?string
    {
        return $this->appeal_status ? (self::APPEAL_STATUSES[$this->appeal_status] ?? $this->appeal_status) : null;
    }

    /** The amount as published, with its currency; null when none was published. */
    public function amountLabel(): ?string
    {
        if ($this->amount === null) {
            return null;
        }
        $value = (float) $this->amount;

        return trim(($this->currency ?? '').' '.number_format($value, floor($value) == $value ? 0 : 2));
    }

    /** Who acted: the regulator, or the older authority field. */
    public function regulatorName(): ?string
    {
        return $this->regulator ?: $this->authority;
    }

    /** The event's slug as recorded, or one derived from its instrument, date and title. Used by the validator and the importer alike. */
    public static function slugFor(string $policySlug, array $event): string
    {
        if (! empty($event['slug'])) {
            return (string) $event['slug'];
        }

        return trim(substr(Str::slug($policySlug.'-'.($event['occurred_on'] ?? 'undated').'-'.($event['title'] ?? 'event')), 0, 160), '-');
    }

    public function url(): string
    {
        return route('enforcement.index').'#'.$this->slug;
    }
}
