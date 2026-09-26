<?php

namespace App\Models;

use App\Models\Concerns\HasSourceQuality;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A proposal, bill, programme or law that responds to AI-driven economic
 * change. A record starts as a draft with every factual field null; a
 * reviewer fills it from the official source. Drafts are served (with a
 * visible notice) but never indexed and never counted in the index.
 */
class TransitionMeasure extends Model
{
    use HasSourceQuality;

    public const TYPES = [
        'ai_dividend' => 'AI dividend', 'universal_basic_income' => 'Universal basic income', 'ai_tax' => 'AI tax', 'sovereign_wealth_fund' => 'Sovereign wealth fund',
        'layoff_disclosure' => 'Layoff disclosure', 'retraining' => 'Retraining', 'worker_voice' => 'Worker voice', 'transition_benefit' => 'Transition benefit', 'other' => 'Other',
    ];

    public const STATUSES = [
        'unverified' => 'Unverified', 'proposed' => 'Proposed', 'introduced' => 'Introduced', 'in_committee' => 'In committee', 'passed_chamber' => 'Passed a chamber',
        'enacted' => 'Enacted', 'in_force' => 'In force', 'pilot' => 'Pilot', 'withdrawn' => 'Withdrawn', 'expired' => 'Expired',
    ];

    /** Which index dimension each type feeds. */
    public const DIMENSION = [
        'layoff_disclosure' => 'disclosure',
        'ai_dividend' => 'safety_net', 'universal_basic_income' => 'safety_net', 'transition_benefit' => 'safety_net',
        'ai_tax' => 'transition_funding', 'sovereign_wealth_fund' => 'transition_funding', 'retraining' => 'transition_funding',
        'worker_voice' => 'worker_voice',
    ];

    protected $guarded = [];

    protected function casts(): array
    {
        return ['sponsors' => 'array', 'arguments_for' => 'array', 'arguments_against' => 'array', 'sources' => 'array', 'introduced_on' => 'date', 'enacted_on' => 'date', 'in_force_on' => 'date', 'published_at' => 'datetime', 'last_checked_at' => 'datetime', 'last_verified_at' => 'datetime'];
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function jurisdiction(): BelongsTo
    {
        return $this->belongsTo(Jurisdiction::class);
    }

    public function isDraft(): bool
    {
        return $this->review_status === 'draft' || $this->status === 'unverified';
    }

    /** Indexable only once verified against an official source. */
    public function isIndexable(): bool
    {
        return $this->published_at !== null && ! $this->isDraft() && filled($this->official_source_url) && filled($this->summary);
    }

    public function typeLabel(): string
    {
        return self::TYPES[$this->measure_type] ?? ucfirst(str_replace('_', ' ', $this->measure_type));
    }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status] ?? ucfirst(str_replace('_', ' ', $this->status));
    }

    public function url(): string
    {
        return route('transition.show', $this->slug);
    }
}
