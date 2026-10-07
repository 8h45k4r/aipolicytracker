<?php

namespace App\Services\Search;

use App\Enums\PolicyStatus;
use App\Models\PolicyInstrument;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Finds and orders the laws and policies for a site search, so the record a reader is
 * naming comes first. A record is a candidate when the phrase appears in its text, when
 * every word of the query appears in its names (title, short title, slug, the name given
 * in brackets in the title, its jurisdiction), or when one of its published duties
 * mentions the phrase. Candidates are ordered by how well the query names them, then by
 * legal force (in force before adopted before guidance before proposals; repealed,
 * superseded and archived last), then by binding force, then by the most recent date,
 * and finally by title so equal records always come out in the same order.
 */
class PolicyRanker
{
    /**
     * How well the query names the record: the whole name, then the query as whole words
     * inside a name ("ai act" in "EU AI Act", not in "AI Action Plan"), then every word
     * of the query at the start of a word in the names, then its duties, then its text.
     */
    public const EXACT = 5;

    public const PHRASE = 4;

    public const WORDS = 3;

    public const DUTIES = 2;

    public const TEXT = 1;

    /**
     * Ranked matches, best first. Each item is ['policy' => PolicyInstrument, 'tier' => int].
     *
     * @return Collection<int, array{policy: PolicyInstrument, tier: int}>
     */
    public function rank(string $q): Collection
    {
        $needle = self::normalize($q);
        if ($needle === '') {
            return collect();
        }

        $phrase = self::like($q);
        $words = array_values(array_filter(explode(' ', $needle), fn ($w) => $w !== ''));

        $policies = PolicyInstrument::published()
            ->with('jurisdiction')
            ->withCount(['obligations as matching_duties_count' => fn (Builder $o) => $o->published()->where(fn ($w) => $this->obligationMatches($w, $phrase))])
            ->where(function (Builder $w) use ($phrase, $words) {
                foreach (['title', 'short_title', 'summary_plain', 'issuing_body', 'who_it_applies_to', 'what_organizations_must_do'] as $column) {
                    $w->orWhereRaw("LOWER(policy_instruments.{$column}) LIKE ? ESCAPE ?", [$phrase, '\\']);
                }
                $w->orWhere(function (Builder $all) use ($words) {
                    foreach ($words as $word) {
                        $like = self::like($word);
                        $all->where(fn (Builder $any) => $any
                            ->whereRaw('LOWER(policy_instruments.title) LIKE ? ESCAPE ?', [$like, '\\'])
                            ->orWhereRaw('LOWER(policy_instruments.short_title) LIKE ? ESCAPE ?', [$like, '\\'])
                            ->orWhereRaw('LOWER(policy_instruments.slug) LIKE ? ESCAPE ?', [$like, '\\'])
                            ->orWhereHas('jurisdiction', fn (Builder $j) => $j->whereRaw('LOWER(name) LIKE ? ESCAPE ?', [$like, '\\'])->orWhereRaw('LOWER(short_name) LIKE ? ESCAPE ?', [$like, '\\'])));
                    }
                });
                $w->orWhereHas('obligations', fn (Builder $o) => $o->published()->where(fn ($m) => $this->obligationMatches($m, $phrase)));
            })
            ->get();

        return $policies
            ->map(fn (PolicyInstrument $p) => ['policy' => $p, 'tier' => $this->tier($p, $needle, $words)])
            ->sort(fn (array $a, array $b) => $this->compare($a, $b))
            ->values();
    }

    /** Whether the best match is named so plainly that it can be shown on its own above the groups. */
    public static function isClearWinner(Collection $ranked): bool
    {
        $first = $ranked->first();
        if (! $first) {
            return false;
        }
        $second = $ranked->get(1);

        return $first['tier'] === self::EXACT
            || ($first['tier'] >= self::PHRASE && (! $second || $second['tier'] < $first['tier']));
    }

    /** Lower case, every run of punctuation or spacing turned into a single space. */
    public static function normalize(string $text): string
    {
        return trim((string) preg_replace('/[^\p{L}\p{N}]+/u', ' ', mb_strtolower($text)));
    }

    /** A LIKE pattern that treats % and _ in the query as literal characters. */
    public static function like(string $q): string
    {
        return '%'.str_replace(['%', '_'], ['\\%', '\\_'], mb_strtolower(trim($q))).'%';
    }

    /**
     * The names a reader might use for a record: its short title, title, slug, any name
     * given in brackets in the title ("... (Colorado AI Act)"), and the short title
     * prefixed with the jurisdiction ("EU" + "AI Act").
     *
     * @return list<string>
     */
    public static function names(PolicyInstrument $p): array
    {
        $names = [$p->short_title, $p->title, str_replace('-', ' ', (string) $p->slug)];
        if (preg_match_all('/\(([^()]+)\)/u', (string) $p->title, $m)) {
            array_push($names, ...$m[1]);
        }
        $jurisdiction = $p->jurisdiction?->short_name ?: $p->jurisdiction?->name;
        if ($jurisdiction && $p->short_title) {
            $names[] = $jurisdiction.' '.$p->short_title;
        }

        return array_values(array_unique(array_filter(array_map(fn ($n) => self::normalize((string) $n), $names))));
    }

    private function obligationMatches(Builder $query, string $phrase): void
    {
        $query->whereRaw('LOWER(obligations.title) LIKE ? ESCAPE ?', [$phrase, '\\'])
            ->orWhereRaw('LOWER(obligations.summary) LIKE ? ESCAPE ?', [$phrase, '\\']);
    }

    /** @param list<string> $words */
    private function tier(PolicyInstrument $p, string $needle, array $words): int
    {
        $names = self::names($p);
        $pattern = '/(^| )'.preg_quote($needle, '/').'( |$)/u';

        if (in_array($needle, $names, true)) {
            return self::EXACT;
        }
        if (collect($names)->contains(fn ($n) => preg_match($pattern, $n) === 1)) {
            return self::PHRASE;
        }
        $haystack = ' '.implode(' ', $names).' '.self::normalize((string) $p->jurisdiction?->name).' '.self::normalize((string) $p->jurisdiction?->short_name);
        if ($words && collect($words)->every(fn ($w) => str_contains($haystack, ' '.$w))) {
            return self::WORDS;
        }

        return $p->matching_duties_count > 0 ? self::DUTIES : self::TEXT;
    }

    /** In force first, repealed and superseded last. */
    private static function forceRank(PolicyInstrument $p): int
    {
        return match ($p->statusEnum()) {
            PolicyStatus::InForce, PolicyStatus::PartiallyApplicable => 0,
            PolicyStatus::Adopted, PolicyStatus::EnforcementAction => 1,
            PolicyStatus::Guidance, PolicyStatus::VoluntaryStandard => 2,
            PolicyStatus::Proposed, PolicyStatus::UnderConsultation => 3,
            PolicyStatus::Superseded, PolicyStatus::Repealed, PolicyStatus::Archived => 4,
        };
    }

    private static function latestDate(PolicyInstrument $p): string
    {
        return (string) collect([$p->in_force_on, $p->adopted_on, $p->published_on])->filter()->map(fn ($d) => $d->format('Y-m-d'))->max();
    }

    /** @param array{policy: PolicyInstrument, tier: int} $a @param array{policy: PolicyInstrument, tier: int} $b */
    private function compare(array $a, array $b): int
    {
        [$pa, $pb] = [$a['policy'], $b['policy']];

        return [$b['tier'], self::forceRank($pa), $pb->is_binding ? 1 : 0, $pb->matching_duties_count, self::latestDate($pb), mb_strtolower((string) ($pa->short_title ?: $pa->title)), $pa->id]
            <=> [$a['tier'], self::forceRank($pb), $pa->is_binding ? 1 : 0, $pa->matching_duties_count, self::latestDate($pa), mb_strtolower((string) ($pb->short_title ?: $pb->title)), $pb->id];
    }
}
