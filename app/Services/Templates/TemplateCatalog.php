<?php

namespace App\Services\Templates;

use App\Models\Obligation;
use App\Services\Templates\Definitions\Assessments;
use App\Services\Templates\Definitions\Kits;
use App\Services\Templates\Definitions\PoliciesProcedures;
use App\Services\Templates\Definitions\Registers;
use Illuminate\Support\Collection;

/**
 * The catalogue in config/templates.php, answered: which templates exist,
 * which one covers a duty, which old free-tool address a template replaces,
 * and the definition that turns the records into a template's content.
 */
final class TemplateCatalog
{
    /** @return Collection<string, array<string,mixed>> keyed by slug, each with its slug inside */
    public static function all(): Collection
    {
        return collect(config('templates.items', []))->map(fn ($meta, $slug) => $meta + ['slug' => $slug]);
    }

    public static function find(string $slug): ?array
    {
        $meta = config("templates.items.{$slug}");

        return $meta ? $meta + ['slug' => $slug] : null;
    }

    public static function definition(string $slug): ?Definition
    {
        $meta = self::find($slug);
        if (! $meta) {
            return null;
        }

        return Registers::make($slug, $meta)
            ?? Assessments::make($slug, $meta)
            ?? PoliciesProcedures::make($slug, $meta)
            ?? Kits::make($slug, $meta);
    }

    public static function url(string $slug): string
    {
        return route('templates.show', $slug);
    }

    /**
     * The templates whose `covers` reaches this duty: by its category, by its
     * instrument, or by a framework it is mapped to.
     *
     * @return Collection<string, array<string,mixed>>
     */
    public static function forObligation(Obligation $obligation): Collection
    {
        $policy = $obligation->policyInstrument?->slug;
        $frameworks = $obligation->relationLoaded('frameworkMappings') ? $obligation->frameworkMappings->pluck('framework')->all() : [];

        return self::all()->filter(function ($meta) use ($obligation, $policy, $frameworks) {
            $c = $meta['covers'] ?? [];

            return in_array($obligation->category, $c['categories'] ?? [], true)
                || ($policy && in_array($policy, $c['policies'] ?? [], true))
                || array_intersect($frameworks, array_map(fn ($f) => Records::frameworkKey($f), $c['frameworks'] ?? [])) !== [];
        });
    }

    /** The filter the covering duties are read with, for counts and the covered-duties list. */
    public static function obligationFilter(array $meta): array
    {
        $c = $meta['covers'] ?? [];

        return array_filter(['categories' => $c['categories'] ?? [], 'policies' => $c['policies'] ?? []]);
    }

    /** Old free-tool slug => template slug. */
    public static function redirects(): array
    {
        $map = [];
        foreach (self::all() as $slug => $meta) {
            if (! empty($meta['replaces'])) {
                $map[$meta['replaces']] = $slug;
            }
        }

        return $map;
    }

    public static function redirectFor(string $oldSlug): ?string
    {
        return self::redirects()[$oldSlug] ?? null;
    }

    /** @param  array{type?:?string, topic?:?string, framework?:?string}  $filters */
    public static function filter(Collection $items, array $filters): Collection
    {
        return $items->filter(fn ($m) => (empty($filters['type']) || $m['type'] === $filters['type'])
            && (empty($filters['topic']) || in_array($filters['topic'], $m['topics'] ?? [], true))
            && (empty($filters['framework']) || in_array($filters['framework'], $m['frameworks'] ?? [], true)));
    }

    public static function typeLabel(string $type): string
    {
        return config('templates.types')[$type] ?? ucfirst($type);
    }

    public static function frameworkLabel(string $key): string
    {
        return config('templates.frameworks')[$key] ?? config("frameworks.{$key}.name") ?? $key;
    }

    public static function topicLabel(string $key): string
    {
        return config('templates.topics')[$key] ?? ucfirst($key);
    }

    /** The formats a template ships, as "XLSX and DOCX". */
    public static function formatList(array $meta): string
    {
        $f = array_map('strtoupper', $meta['formats'] ?? []);

        return count($f) > 1 ? implode(' and ', $f) : ($f[0] ?? '');
    }
}
