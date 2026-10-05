<?php

namespace App\Services\Hubs;

use App\Models\Jurisdiction;
use App\Models\PolicyInstrument;
use App\Services\Applicability\ApplicabilityScreener;
use App\Support\ContentCache;

/**
 * The data behind the world map: one entry per country, keyed by its ISO 3166-1
 * alpha-2 code, with the strongest AI rule on record there.
 *
 * Levels, strongest first: binding AI law in force; binding AI law adopted but not
 * yet applying; strategy, guidance or existing law only; nothing recorded. An EU
 * member state takes the EU AI Act's level when its own records are weaker, and
 * says so, because the Regulation applies there directly.
 */
final class WorldMap
{
    public const LEVELS = [
        'in_force' => 'Binding AI law in force',
        'binding' => 'Binding AI law adopted',
        'guidance' => 'Strategy, guidance or existing law',
        'none' => 'Nothing recorded yet',
    ];

    /** Legend colours; resources/js/world-map.js uses the same values. */
    public const COLOURS = ['in_force' => '#002147', 'binding' => '#006aac', 'guidance' => '#8fb8d8', 'none' => '#e3e8ee'];

    /** The 27 EU member states (ISO 3166-1 alpha-2). */
    public const EU_MEMBERS = ['AT', 'BE', 'BG', 'HR', 'CY', 'CZ', 'DK', 'EE', 'FI', 'FR', 'DE', 'GR', 'HU', 'IE', 'IT', 'LV', 'LT', 'LU', 'MT', 'NL', 'PL', 'PT', 'RO', 'SK', 'SI', 'ES', 'SE'];

    /** @return list<array{id: string, name: string, level: string, label: string, via: ?string, instruments: int, url: string}> */
    public static function countries(): array
    {
        return ContentCache::remember('hubs.world-map', 900, function () {
            $levels = self::levels();
            $eu = Jurisdiction::published()->where('slug', 'eu')->first();
            $euLevel = $eu ? ($levels[$eu->id]['level'] ?? 'none') : 'none';
            $rank = array_flip(array_keys(self::LEVELS));

            return Jurisdiction::published()->whereRaw('LENGTH(iso_code) = 2')->where('iso_code', '!=', 'EU')->orderBy('name')->get()
                ->map(function (Jurisdiction $j) use ($levels, $euLevel, $rank) {
                    $own = $levels[$j->id] ?? ['level' => 'none', 'instruments' => 0];
                    $level = $own['level'];
                    $via = null;
                    if (in_array(strtoupper($j->iso_code), self::EU_MEMBERS, true) && $rank[$euLevel] < $rank[$level]) {
                        [$level, $via] = [$euLevel, 'EU AI Act'];
                    }

                    return ['id' => strtoupper($j->iso_code), 'name' => $j->name, 'level' => $level, 'label' => self::LEVELS[$level], 'via' => $via, 'instruments' => $own['instruments'], 'url' => $j->url()];
                })->values()->all();
        });
    }

    /** @return array<string, int> countries per level, for the legend */
    public static function totals(): array
    {
        return array_merge(array_fill_keys(array_keys(self::LEVELS), 0), collect(self::countries())->countBy('level')->all());
    }

    /** @return array<int, array{level: string, instruments: int}> by jurisdiction id */
    private static function levels(): array
    {
        $out = [];
        $policies = PolicyInstrument::published()->whereNotIn('status', ApplicabilityScreener::NOT_IN_FORCE)->get(['jurisdiction_id', 'is_binding', 'status']);
        foreach ($policies->groupBy('jurisdiction_id') as $id => $group) {
            $binding = $group->where('is_binding', true);
            $level = $binding->whereIn('status', ['in_force', 'partially_applicable'])->isNotEmpty() ? 'in_force' : ($binding->isNotEmpty() ? 'binding' : 'guidance');
            $out[$id] = ['level' => $level, 'instruments' => $group->count()];
        }

        return $out;
    }
}
