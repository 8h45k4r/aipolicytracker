<?php

namespace App\Services\Assessments;

use Illuminate\Support\Collection;

/**
 * The free self-assessments in config/assessments.php: the full list for /self-assessments,
 * and the few that fit a given page (a law, a jurisdiction, a framework, a template, an
 * audience or a guide), in the order the config lists them.
 */
final class AssessmentCatalog
{
    /** The kinds of page an assessment can be attached to, and the config key that lists them. */
    public const KINDS = ['policy' => 'policies', 'jurisdiction' => 'jurisdictions', 'framework' => 'frameworks', 'template' => 'templates', 'audience' => 'audiences', 'guide' => 'guides'];

    /** @return Collection<string, array<string,mixed>> keyed by slug, each with its slug and outbound url */
    public static function all(): Collection
    {
        return collect(config('assessments.items', []))->map(fn (array $a, string $slug) => $a + ['slug' => $slug, 'url' => self::url($slug)]);
    }

    public static function url(string $slug): string
    {
        return config('assessments.provider.assessment_url').config("assessments.items.{$slug}.path", $slug).'?'.config('assessments.provider.utm');
    }

    public static function listUrl(): string
    {
        return config('assessments.provider.url').'?'.config('assessments.provider.utm');
    }

    public static function typeLabel(string $type): string
    {
        return config('assessments.types.'.$type, ucfirst($type));
    }

    /** @return Collection<string, array<string,mixed>> */
    public static function for(string $kind, string $key, int $limit = 3): Collection
    {
        $field = self::KINDS[$kind] ?? null;

        return $field === null ? collect() : self::all()->filter(fn (array $a) => in_array($key, $a[$field] ?? [], true))->take($limit);
    }

    /**
     * Assessments that name at least one template, each with the first of those templates
     * that exists, in the order the config lists them: what the templates hub shows as
     * "score yourself, then pick up the template".
     *
     * @param  callable(string):(?array<string,mixed>)  $findTemplate
     * @return Collection<string, array<string,mixed>>
     */
    public static function pairedWithTemplates(callable $findTemplate, int $limit = 6): Collection
    {
        $paired = self::all()
            ->map(fn (array $a) => $a + ['template' => collect($a['templates'] ?? [])->map($findTemplate)->filter()->first()])
            ->filter(fn (array $a) => $a['template'] !== null);

        // One of each type first, so the six shown are not all about one law.
        return $paired->unique('type')->union($paired)->take($limit);
    }

    /**
     * Assessments that name any of the given templates, those naming the most first:
     * what a page listing several templates (a framework or a type) offers beside them.
     *
     * @param  list<string>  $templates
     * @return Collection<string, array<string,mixed>>
     */
    public static function forTemplates(array $templates, int $limit = 3): Collection
    {
        return self::all()
            ->map(fn (array $a) => $a + ['matches' => count(array_intersect($a['templates'] ?? [], $templates))])
            ->filter(fn (array $a) => $a['matches'] > 0)
            ->sortByDesc('matches')
            ->take($limit);
    }

    /**
     * @param  array{type?:?string, region?:?string, q?:?string}  $filters
     * @return Collection<string, array<string,mixed>>
     */
    public static function filter(array $filters): Collection
    {
        $q = mb_strtolower(trim((string) ($filters['q'] ?? '')));

        return self::all()
            ->when(! empty($filters['type']), fn ($c) => $c->where('type', $filters['type']))
            ->when(! empty($filters['region']), fn ($c) => $c->where('region', $filters['region']))
            ->when($q !== '', fn ($c) => $c->filter(fn ($a) => str_contains(mb_strtolower($a['title'].' '.$a['summary']), $q)));
    }

    /** @return array<string,int> region => count, Global first */
    public static function regions(): array
    {
        return self::all()->countBy('region')->sortKeys()->sortByDesc(fn ($n, $r) => $r === 'Global' ? PHP_INT_MAX : $n)->all();
    }
}
