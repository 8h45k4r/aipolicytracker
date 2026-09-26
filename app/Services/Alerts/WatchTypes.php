<?php

namespace App\Services\Alerts;

use App\Models\Follow;
use App\Models\Jurisdiction;
use App\Models\Obligation;
use App\Models\PolicyInstrument;
use App\Models\TaxonomyTerm;
use App\Services\Templates\Records;
use Illuminate\Support\Collection;

/**
 * What a watch can point at beyond a record: a sector, a use case, a
 * framework, a change type or a saved search over the updates hub. Each
 * type knows how to validate a subject, label it, link it, and resolve to
 * the instruments or change filters the alert builder needs.
 */
final class WatchTypes
{
    public const RECORD = ['policy', 'jurisdiction', 'obligation'];

    public const EXTENDED = ['sector', 'use_case', 'framework', 'change_type', 'search'];

    public const CHANGE_TYPES = ['urgent' => 'Urgent changes', 'high' => 'High-impact changes', 'routine' => 'Routine changes'];

    public const SEARCH_KEYS = ['jurisdiction', 'impact', 'q'];

    public static function all(): array
    {
        return array_merge(self::RECORD, self::EXTENDED);
    }

    /**
     * Validate a subject for a type; returns the canonical slug, a label and a
     * URL, or null when the subject does not exist.
     *
     * @return array{slug:string, label:string, url:?string, params:?array}|null
     */
    public static function resolve(string $type, string $slug, array $params = []): ?array
    {
        return match ($type) {
            'policy' => ($p = PolicyInstrument::published()->where('slug', $slug)->first()) ? ['slug' => $slug, 'label' => $p->short_title ?: $p->title, 'url' => $p->url(), 'params' => null] : null,
            'jurisdiction' => ($j = Jurisdiction::published()->where('slug', $slug)->first()) ? ['slug' => $slug, 'label' => $j->name, 'url' => $j->url(), 'params' => null] : null,
            'obligation' => ($o = Obligation::published()->where('slug', $slug)->first()) ? ['slug' => $slug, 'label' => $o->title, 'url' => $o->url(), 'params' => null] : null,
            'sector', 'use_case' => ($t = TaxonomyTerm::where('taxonomy', $type)->where('slug', $slug)->first()) ? ['slug' => $slug, 'label' => $t->name, 'url' => route('policies.index', [$type => $slug]), 'params' => null] : null,
            'framework' => ($key = self::frameworkKey($slug)) ? ['slug' => $slug, 'label' => config("frameworks.{$key}.name", $slug), 'url' => route('frameworks.show', $slug), 'params' => null] : null,
            'change_type' => isset(self::CHANGE_TYPES[$slug]) ? ['slug' => $slug, 'label' => self::CHANGE_TYPES[$slug], 'url' => route('updates.index', ['impact' => $slug]), 'params' => null] : null,
            'search' => self::search($params),
            default => null,
        };
    }

    /** A saved search is the updates hub's filters; the slug is a hash of them so the same search is one watch. */
    private static function search(array $params): ?array
    {
        $clean = [];
        foreach (self::SEARCH_KEYS as $k) {
            $v = $params[$k] ?? null;
            if (is_string($v) && trim($v) !== '' && mb_strlen($v) <= 80 && preg_match('/^[\p{L}\p{N} _\-]+$/u', $v)) {
                $clean[$k] = trim($v);
            }
        }
        if ($clean === []) {
            return null;
        }
        if (isset($clean['impact']) && ! isset(self::CHANGE_TYPES[$clean['impact']])) {
            return null;
        }
        if (isset($clean['jurisdiction']) && ! Jurisdiction::published()->where('slug', $clean['jurisdiction'])->exists()) {
            return null;
        }
        ksort($clean);
        $parts = [];
        foreach ($clean as $k => $v) {
            $parts[] = match ($k) {
                'jurisdiction' => Jurisdiction::where('slug', $v)->value('name') ?? $v,
                'impact' => mb_strtolower(self::CHANGE_TYPES[$v]),
                'q' => '“'.$v.'”',
            };
        }

        return ['slug' => 'search-'.substr(hash('sha256', json_encode($clean)), 0, 16), 'label' => 'Updates: '.implode(', ', $parts), 'url' => route('updates.index', $clean), 'params' => $clean];
    }

    public static function frameworkKey(string $slug): ?string
    {
        $key = Records::frameworkKey($slug);

        return config("frameworks.{$key}") ? $key : null;
    }

    public static function typeLabel(string $type): string
    {
        return match ($type) {
            'policy' => 'Policy', 'jurisdiction' => 'Jurisdiction', 'obligation' => 'Obligation', 'sector' => 'Sector', 'use_case' => 'Use case',
            'framework' => 'Framework', 'change_type' => 'Change type', 'search' => 'Saved search', default => ucfirst($type),
        };
    }

    /**
     * What a set of watches resolves to for the alert builder: instruments
     * (for sector, use case and framework watches) and change filters (for
     * change types and saved searches).
     *
     * @param  Collection<int,Follow>  $follows
     * @return array{instrument_ids:list<int>, impact_levels:list<string>, searches:list<array>}
     */
    public static function scope(Collection $follows): array
    {
        $instrumentIds = [];
        $impacts = [];
        $searches = [];
        foreach ($follows as $f) {
            match ($f->subject_type) {
                'sector', 'use_case' => $instrumentIds = array_merge($instrumentIds, PolicyInstrument::published()->withTerm($f->subject_type, $f->subject_slug)->pluck('id')->all()),
                'framework' => $instrumentIds = array_merge($instrumentIds, ($key = self::frameworkKey($f->subject_slug)) ? Obligation::published()->whereHas('frameworkMappings', fn ($m) => $m->where('framework', $key))->distinct()->pluck('policy_instrument_id')->all() : []),
                'change_type' => $impacts[] = $f->subject_slug,
                'search' => $searches[] = (array) ($f->params ?? []),
                default => null,
            };
        }

        return ['instrument_ids' => array_values(array_unique($instrumentIds)), 'impact_levels' => array_values(array_unique($impacts)), 'searches' => $searches];
    }
}
