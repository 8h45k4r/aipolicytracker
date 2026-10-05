<?php

namespace App\Services\Glossary;

use App\Models\Obligation;
use App\Models\PolicyInstrument;
use App\Support\ContentCache;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Route;

/**
 * The curated glossary terms (config/glossary.php), each with its own page at
 * /glossary/{id}, and where the records use them. "Uses" is a plain phrase match
 * on the record text: the term without its parenthetical, its abbreviation when
 * the parenthetical is one (GPAI, FRIA, QMS), and any extra `match` phrases.
 * Nothing here is content; the definitions are the config's.
 */
final class GlossaryTerms
{
    /** @return array<string, array{id:string, term:string, short:string, definition:string, source:?array, see:list<array{0:string,1:string}>, phrases:list<string>}> */
    public static function all(): array
    {
        $out = [];
        foreach (config('glossary.terms', []) as $id => $t) {
            $see = [];
            foreach ($t['see'] ?? [] as [$label, $name, $params]) {
                // A link to a route that does not exist is left out rather than shipped as a 404.
                if (Route::has($name)) {
                    $see[] = [$label, route($name, $params)];
                }
            }
            $out[$id] = [
                'id' => $id,
                'term' => $t['term'],
                'short' => self::short($t['term']),
                'definition' => $t['definition'],
                'source' => $t['source'] ?? null,
                'see' => $see,
                'phrases' => self::phrases($t),
            ];
        }

        return $out;
    }

    public static function find(string $id): ?array
    {
        return self::all()[$id] ?? null;
    }

    public static function url(string $id): string
    {
        return route('glossary.show', $id);
    }

    /** The term without its parenthetical: "Fundamental rights impact assessment". */
    public static function short(string $term): string
    {
        return trim((string) preg_replace('/\s*\([^)]*\)\s*$/', '', $term));
    }

    /** @return list<string> lower-case phrases that count as a use of the term */
    private static function phrases(array $t): array
    {
        $phrases = [mb_strtolower(self::short($t['term']))];
        if (preg_match('/\(([^)]*)\)\s*$/', $t['term'], $m) && preg_match('#^[A-Z0-9][A-Z0-9 /:-]+$#', $m[1])) {
            $phrases[] = mb_strtolower($m[1]);
        }
        foreach ($t['match'] ?? [] as $p) {
            $phrases[] = mb_strtolower($p);
        }

        return array_values(array_unique($phrases));
    }

    /** Whether the text uses any of the term's phrases, as whole words (a plural counts). */
    public static function mentions(string $text, array $term): bool
    {
        foreach ($term['phrases'] as $p) {
            if (preg_match('/(?<![\pL\pN])'.preg_quote($p, '/').'(?:s|es)?(?![\pL\pN])/iu', $text)) {
                return true;
            }
        }

        return false;
    }

    /**
     * The terms a piece of text uses, in glossary order.
     *
     * @return list<array>
     */
    public static function foundIn(string $text, int $limit = 6, ?string $except = null): array
    {
        return collect(self::all())
            ->reject(fn ($t) => $t['id'] === $except)
            ->filter(fn ($t) => self::mentions($text, $t))
            ->take($limit)->values()->all();
    }

    /**
     * Other terms this one is bound up with: those its definition uses, then those
     * whose definitions use it.
     *
     * @return list<array>
     */
    public static function related(array $term, int $limit = 6): array
    {
        $used = collect(self::foundIn($term['definition'], 50, $term['id']));
        $usedBy = collect(self::all())->reject(fn ($t) => $t['id'] === $term['id'] || $used->contains('id', $t['id']))
            ->filter(fn ($t) => self::mentions($t['definition'], $term));

        return $used->concat($usedBy)->take($limit)->values()->all();
    }

    /**
     * Published laws and policies that use the term, in their own text or in one
     * of their duties; binding instruments first.
     *
     * @return Collection<int, PolicyInstrument>
     */
    public static function policiesUsing(array $term, int $limit = 8): Collection
    {
        $ids = ContentCache::remember('glossary.policies.'.$term['id'], 3600, function () use ($term) {
            $viaDuties = Obligation::published()->whereIn('id', self::obligationIds($term))->pluck('policy_instrument_id')->all();

            return PolicyInstrument::published()
                ->get(['id', 'title', 'short_title', 'summary_plain', 'scope_summary', 'who_it_applies_to', 'what_organizations_must_do', 'is_binding', 'featured', 'adopted_on'])
                ->filter(fn ($p) => in_array($p->id, $viaDuties, true) || self::mentions(implode(' ', [$p->title, $p->short_title, $p->summary_plain, $p->scope_summary, $p->who_it_applies_to, $p->what_organizations_must_do]), $term))
                ->sortBy([['is_binding', 'desc'], ['featured', 'desc'], ['adopted_on', 'desc']])
                ->pluck('id')->all();
        });

        return PolicyInstrument::with('jurisdiction')->whereIn('id', array_slice($ids, 0, $limit))->get()
            ->sortBy(fn ($p) => array_search($p->id, $ids, true))->values();
    }

    /**
     * Published duties whose title, summary or practical action uses the term.
     *
     * @return Collection<int, Obligation>
     */
    public static function obligationsUsing(array $term, int $limit = 8): Collection
    {
        $ids = self::obligationIds($term);

        return Obligation::with('policyInstrument.jurisdiction')->whereIn('id', array_slice($ids, 0, $limit))->get()
            ->sortBy(fn ($o) => array_search($o->id, $ids, true))->values();
    }

    /** @return list<int> */
    private static function obligationIds(array $term): array
    {
        return ContentCache::remember('glossary.obligations.'.$term['id'], 3600, fn () => Obligation::published()
            ->whereHas('policyInstrument', fn ($q) => $q->published())
            ->orderBy('policy_instrument_id')->orderBy('sort_order')
            ->get(['id', 'title', 'summary', 'practical_action'])
            ->filter(fn ($o) => self::mentions(implode(' ', [$o->title, $o->summary, $o->practical_action]), $term))
            ->pluck('id')->values()->all());
    }
}
