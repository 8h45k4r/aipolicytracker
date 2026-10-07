<?php

namespace App\Services\Implementation;

use App\Models\EnforcementEvent;
use App\Models\ImplementationMeasure;
use App\Models\PolicyInstrument;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Shared reads for the enforcement, implementation and standards trackers, so the
 * pages, the API, the exports and the sitemap apply one rule each: what is listed,
 * how a row is shaped, and when a page has enough recorded entries to be indexed.
 *
 * "Recorded" means published and not a draft. Drafts are listed (the gap is the
 * point) but never count towards a threshold.
 */
final class Trackers
{
    /** A hub with fewer recorded entries than this is served noindex and left out of the sitemap. */
    public const MIN_INDEXABLE_ENFORCEMENT = 5;

    public const MIN_INDEXABLE_IMPLEMENTATION = 5;

    public const MIN_INDEXABLE_STANDARDS = 5;

    /** @return Builder<EnforcementEvent> */
    public static function enforcementQuery(array $filters = []): Builder
    {
        return EnforcementEvent::query()->published()
            ->whereHas('policyInstrument', fn ($q) => $q->published())
            ->with(['jurisdiction', 'policyInstrument'])
            ->when($filters['jurisdiction'] ?? null, fn ($q, $j) => $q->whereHas('jurisdiction', fn ($w) => $w->where('slug', $j)))
            ->when($filters['kind'] ?? null, fn ($q, $k) => $q->where('kind', $k))
            ->when($filters['policy'] ?? null, fn ($q, $p) => $q->whereHas('policyInstrument', fn ($w) => $w->where('slug', $p)))
            ->when($filters['year'] ?? null, fn ($q, $y) => $q->whereBetween('occurred_on', [$y.'-01-01', $y.'-12-31']))
            ->orderByDesc('occurred_on')->orderBy('title');
    }

    public static function recordedEnforcementCount(): int
    {
        return self::enforcementQuery()->where('review_status', '!=', 'draft')->count();
    }

    public static function enforcementIndexable(): bool
    {
        return self::recordedEnforcementCount() >= self::MIN_INDEXABLE_ENFORCEMENT;
    }

    /** @return Builder<ImplementationMeasure> */
    public static function measureQuery(array $filters = []): Builder
    {
        return ImplementationMeasure::query()->published()
            ->with(['policyInstrument', 'relatedPolicy'])
            ->when($filters['instrument'] ?? null, fn ($q, $s) => $q->whereHas('policyInstrument', fn ($w) => $w->where('slug', $s)))
            ->when($filters['kind'] ?? null, fn ($q, $k) => $q->where('kind', $k))
            ->when($filters['body'] ?? null, fn ($q, $b) => $q->where('body', $b))
            ->when($filters['standards'] ?? false, fn ($q) => $q->whereIn('kind', ImplementationMeasure::STANDARD_KINDS))
            ->orderByRaw('case when review_status = ? then 1 else 0 end', ['draft'])
            ->orderBy('kind')->orderBy('title');
    }

    /** Measures, with the derived status applied as a filter after the read (it depends on today). */
    public static function measures(array $filters = []): Collection
    {
        $rows = self::measureQuery($filters)->get();
        if (! empty($filters['status'])) {
            $rows = $rows->filter(fn (ImplementationMeasure $m) => $m->effectiveStatus() === $filters['status'])->values();
        }

        return $rows;
    }

    public static function implementationIndexable(PolicyInstrument $policy): bool
    {
        return self::measureQuery(['instrument' => $policy->slug])->whereNotIn('kind', ImplementationMeasure::STANDARD_KINDS)->where('review_status', '!=', 'draft')->count() >= self::MIN_INDEXABLE_IMPLEMENTATION;
    }

    public static function standardsIndexable(): bool
    {
        return self::measureQuery(['standards' => true])->where('review_status', '!=', 'draft')->count() >= self::MIN_INDEXABLE_STANDARDS;
    }

    /** Instruments with at least one published non-standard measure: the implementation pages that exist. */
    public static function instrumentsWithMeasures(): Collection
    {
        return PolicyInstrument::published()->whereHas('implementationMeasures', fn ($q) => $q->published()->whereNotIn('kind', ImplementationMeasure::STANDARD_KINDS))->orderBy('slug')->get();
    }

    /** One enforcement event as the API, the NDJSON and the CSV export carry it. */
    public static function enforcementRow(EnforcementEvent $e): array
    {
        return [
            'slug' => $e->slug,
            'title' => $e->title,
            'kind' => $e->kind,
            'occurred_on' => $e->occurred_on?->toDateString(),
            'jurisdiction' => $e->jurisdiction?->slug,
            'jurisdiction_name' => $e->jurisdiction?->name,
            'policy' => $e->policyInstrument?->slug,
            'regulator' => $e->regulatorName(),
            'respondent' => $e->respondent,
            'amount' => $e->amount === null ? null : (float) $e->amount,
            'currency' => $e->currency,
            'legal_basis' => $e->legal_basis,
            'summary' => $e->summary,
            'outcome' => $e->outcome,
            'appeal_status' => $e->appeal_status,
            'official_source_url' => $e->official_source_url,
            'source_title' => $e->source_title,
            'source_publisher' => $e->source_publisher,
            'review_status' => $e->review_status,
            'confidence_level' => $e->confidence_level,
            'last_verified_at' => $e->last_verified_at?->toDateString(),
            'reviewed_by' => $e->reviewed_by,
            'url' => $e->exists && $e->slug ? $e->url() : null,
        ];
    }

    /** One implementation measure as the API, the NDJSON and the CSV export carry it. */
    public static function measureRow(ImplementationMeasure $m): array
    {
        return [
            'slug' => $m->slug,
            'title' => $m->title,
            'kind' => $m->kind,
            'instrument' => $m->policyInstrument?->slug,
            'related_policy' => $m->relatedPolicy?->slug,
            'declared_status' => $m->status,
            'status' => $m->exists ? $m->effectiveStatus() : null,
            'legal_basis' => $m->legal_basis,
            'summary' => $m->summary,
            'due_on' => $m->due_on?->toDateString(),
            'adopted_on' => $m->adopted_on?->toDateString(),
            'published_on' => $m->published_on?->toDateString(),
            'published_on_precision' => $m->published_on_precision,
            'body' => $m->body,
            'reference' => $m->reference,
            'stage' => $m->stage,
            'oj_citation_expected_on' => $m->oj_citation_expected_on?->toDateString(),
            'oj_citation_on' => $m->oj_citation_on?->toDateString(),
            'official_source_url' => $m->official_source_url,
            'source_publisher' => $m->source_publisher,
            'source_tier' => $m->source_tier,
            'review_status' => $m->review_status,
            'confidence_level' => $m->confidence_level,
            'last_verified_at' => $m->last_verified_at?->toDateString(),
            'reviewed_by' => $m->reviewed_by,
            'draft' => $m->isDraft(),
            'url' => $m->exists ? $m->url() : null,
        ];
    }
}
