<?php

namespace App\Services\PolicyData;

use App\Enums\InstrumentType;
use App\Enums\PolicyStatus;
use App\Models\ChangeEvent;
use App\Models\Deadline;
use App\Models\Jurisdiction;
use App\Models\Obligation;
use App\Models\PolicyInstrument;
use App\Models\TaxonomyTerm;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

/**
 * Shared read queries and filter handling for the public site and API.
 */
class PolicyCatalog
{
    public const SORTS = ['updated' => 'Recently updated', 'effective' => 'Effective date', 'relevance' => 'Relevance', 'jurisdiction' => 'Jurisdiction'];

    public const FILTER_KEYS = ['q', 'jurisdiction', 'region', 'status', 'type', 'sector', 'use_case', 'risk', 'actor', 'from', 'to', 'sort', 'category', 'binding'];

    public function filterOptions(): array
    {
        return Cache::remember('catalog.filter_options', 600, function () {
            $terms = TaxonomyTerm::orderBy('sort_order')->orderBy('name')->get()->groupBy('taxonomy');

            return [
                'jurisdictions' => Jurisdiction::published()->orderBy('name')->get(['slug', 'name', 'short_name', 'region']),
                'regions' => Jurisdiction::published()->whereNotNull('region')->distinct()->orderBy('region')->pluck('region')->all(),
                'statuses' => PolicyStatus::cases(),
                'types' => InstrumentType::cases(),
                'sectors' => $terms->get('sector', collect()),
                'use_cases' => $terms->get('use_case', collect()),
                'risks' => $terms->get('risk_category', collect()),
                'actors' => $terms->get('actor', collect()),
                'categories' => $terms->get('obligation_category', collect()),
            ];
        });
    }

    /** @return array<string, mixed> sanitised filters from the request */
    public function filtersFromRequest(Request $request): array
    {
        $f = [];
        foreach (self::FILTER_KEYS as $key) {
            $value = $request->query($key);
            if (is_array($value)) {
                $value = implode(',', array_map('strval', $value));
            }
            $value = is_string($value) ? trim($value) : null;
            if ($value !== null && $value !== '') {
                $f[$key] = mb_substr($value, 0, 200);
            }
        }

        return $f;
    }

    public function policyQuery(array $filters): Builder
    {
        $q = PolicyInstrument::query()->published()->with(['jurisdiction', 'terms']);

        if (! empty($filters['q'])) {
            $q->search($filters['q']);
        }
        if (! empty($filters['jurisdiction'])) {
            $slugs = $this->list($filters['jurisdiction']);
            $q->whereHas('jurisdiction', fn ($j) => $j->whereIn('slug', $slugs));
        }
        if (! empty($filters['region'])) {
            $q->whereHas('jurisdiction', fn ($j) => $j->where('region', $filters['region']));
        }
        if (! empty($filters['status'])) {
            $q->whereIn('status', array_intersect($this->list($filters['status']), PolicyStatus::values()));
        }
        if (! empty($filters['type'])) {
            $q->whereIn('instrument_type', array_intersect($this->list($filters['type']), InstrumentType::values()));
        }
        if (! empty($filters['binding'])) {
            $q->where('is_binding', $filters['binding'] === 'yes');
        }
        foreach (['sector' => 'sector', 'use_case' => 'use_case', 'risk' => 'risk_category', 'actor' => 'actor'] as $key => $taxonomy) {
            if (! empty($filters[$key])) {
                $q->withTerm($taxonomy, $this->list($filters[$key]));
            }
        }
        if (! empty($filters['from']) && $this->isDate($filters['from'])) {
            $q->where(fn ($w) => $w->where('applies_from', '>=', $filters['from'])->orWhere('in_force_on', '>=', $filters['from']));
        }
        if (! empty($filters['to']) && $this->isDate($filters['to'])) {
            $q->where(fn ($w) => $w->where('applies_from', '<=', $filters['to'])->orWhere('in_force_on', '<=', $filters['to']));
        }

        match ($filters['sort'] ?? 'updated') {
            'effective' => $q->orderByRaw('COALESCE(applies_from, in_force_on, adopted_on) DESC'),
            'jurisdiction' => $q->join('jurisdictions as jsort', 'jsort.id', '=', 'policy_instruments.jurisdiction_id')->orderBy('jsort.name')->orderBy('policy_instruments.title')->select('policy_instruments.*'),
            'relevance' => $q->orderByDesc('featured')->orderByDesc('is_binding')->orderByDesc('updated_at'),
            default => $q->orderByDesc('policy_instruments.updated_at'),
        };

        return $q;
    }

    public function obligationQuery(array $filters): Builder
    {
        $q = Obligation::query()->published()->with(['policyInstrument.jurisdiction', 'terms']);
        if (! empty($filters['q'])) {
            $q->search($filters['q']);
        }
        if (! empty($filters['category'])) {
            $q->whereIn('category', $this->list($filters['category']));
        }
        if (! empty($filters['binding'])) {
            $q->where('is_binding', $filters['binding'] === 'yes');
        }
        if (! empty($filters['jurisdiction'])) {
            $slugs = $this->list($filters['jurisdiction']);
            $q->whereHas('policyInstrument.jurisdiction', fn ($j) => $j->whereIn('slug', $slugs));
        }
        foreach (['sector' => 'sector', 'use_case' => 'use_case', 'actor' => 'actor'] as $key => $taxonomy) {
            if (! empty($filters[$key])) {
                $q->withTerm($taxonomy, $this->list($filters[$key]));
            }
        }

        return $q->orderByDesc('is_binding')->orderBy('category')->orderBy('title');
    }

    public function latestChanges(int $limit = 6): Collection
    {
        return ChangeEvent::published()->with(['jurisdiction', 'policyInstrument'])->orderByDesc('occurred_on')->limit($limit)->get();
    }

    public function upcomingDeadlines(int $limit = 6, ?int $jurisdictionId = null): Collection
    {
        $q = Deadline::query()->with('policyInstrument.jurisdiction')
            ->whereHas('policyInstrument', fn ($p) => $p->published()->when($jurisdictionId, fn ($w) => $w->where('jurisdiction_id', $jurisdictionId)))
            ->where('deadline_status', 'scheduled')
            ->whereNotNull('due_on')
            ->where('due_on', '>=', now()->toDateString())
            ->orderBy('due_on');

        return $q->limit($limit)->get();
    }

    public function featuredJurisdictions(): Collection
    {
        return Jurisdiction::published()->where('featured', true)->withCount(['policyInstruments' => fn ($q) => $q->published()])->orderBy('name')->get();
    }

    public function stats(): array
    {
        return Cache::remember('catalog.stats', 600, fn () => [
            'jurisdictions' => Jurisdiction::published()->count(),
            'policies' => PolicyInstrument::published()->count(),
            'obligations' => Obligation::published()->count(),
            'changes' => ChangeEvent::published()->count(),
            'verified' => PolicyInstrument::published()->where('review_status', 'verified')->count(),
            'last_updated' => PolicyInstrument::published()->max('updated_at'),
        ]);
    }

    /**
     * Indexation rule for filtered listing URLs: only a bare listing, a single
     * jurisdiction filter, or a single status filter is indexable. Everything
     * else (search, multi-filter, sort, date ranges) is noindex,follow.
     */
    public function isIndexableFilterSet(array $filters): bool
    {
        $keys = array_keys(array_filter($filters, fn ($v) => $v !== null && $v !== ''));
        if ($keys === []) {
            return true;
        }
        if (count($keys) === 1 && in_array($keys[0], ['jurisdiction', 'status', 'category'], true) && ! str_contains($filters[$keys[0]], ',')) {
            return true;
        }

        return false;
    }

    /** Canonical URL for a listing: strips non-indexable parameters. */
    public function canonicalFor(string $base, array $filters): string
    {
        if ($this->isIndexableFilterSet($filters)) {
            $keep = array_intersect_key($filters, array_flip(['jurisdiction', 'status', 'category']));

            return $keep ? $base.'?'.http_build_query($keep) : $base;
        }

        return $base;
    }

    private function list(string $value): array
    {
        return array_values(array_filter(array_map('trim', explode(',', $value))));
    }

    private function isDate(string $value): bool
    {
        return (bool) preg_match('/^\d{4}-\d{2}-\d{2}$/', $value);
    }
}
