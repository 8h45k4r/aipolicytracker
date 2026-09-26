<?php

namespace App\Services\Records;

use App\Models\Deadline;
use App\Models\Jurisdiction;
use App\Models\Obligation;
use App\Models\PolicyInstrument;
use Illuminate\Support\Collection;

/**
 * The first paragraph of a record page: what the record is, in 40 to 60
 * words, composed from its structured fields and nothing else.
 *
 * A searcher, and an answer engine, read the first paragraph and decide. On
 * these pages that paragraph used to be the summary a contributor wrote, which
 * is good prose and answers a different question ("what does this instrument
 * do?") from the one asked ("what is this, is it in force, who does it bind?").
 * This composes the second answer from fields that are already checked against
 * the source, so it can never say more than the record does, and it changes
 * when the record changes.
 *
 * Sentences are ranked; they are added in order while the total stays within
 * the budget, and the paragraph is topped up from the record's own summary if
 * the facts alone fall short. Nothing is invented to fill the space.
 */
final class AnswerBox
{
    public const MIN_WORDS = 40;

    public const MAX_WORDS = 60;

    /**
     * True of every record on the site, so it can close any paragraph that the
     * record's own facts leave short, without saying anything about the record
     * that the record does not say.
     */
    private const CLOSING = 'Every record here links to its official source and states its verification status.';

    public static function policy(PolicyInstrument $policy): string
    {
        $name = ucfirst($policy->definiteName());
        $place = $policy->jurisdiction?->nameWithArticle();
        // "Act / statute" is a label for a filter; in a sentence it is "act".
        $type = mb_strtolower(trim(explode(' / ', $policy->typeEnum()->label())[0]));
        $status = $policy->statusEnum();

        $sentences = [
            trim("{$name} is a ".($policy->is_binding ? 'binding ' : 'non-binding ')."{$type}".($place ? " of {$place}" : '').($policy->issuing_body ? ', issued by '.self::withArticle($policy->issuing_body) : '').'.'),
            self::statusSentence($policy, $status->label()),
            self::firstSentence($policy->scope_summary ?? $policy->who_it_applies_to),
            self::dutiesSentence($policy),
            self::firstSentence($policy->penalties_summary, 'Penalties: '),
            self::firstSentence($policy->summary_plain),
            self::CLOSING,
        ];

        return self::compose($sentences, $policy->summary_plain);
    }

    public static function obligation(Obligation $obligation): string
    {
        $policy = $obligation->policyInstrument;
        $short = $policy ? $policy->definiteName() : null;
        $place = $policy?->jurisdiction?->nameWithArticle();
        $ref = $obligation->source_reference ? " ({$obligation->source_reference})" : '';

        $sentences = [
            trim(self::clean($obligation->title).' is '.($obligation->is_binding ? 'a legal requirement' : 'a voluntary measure').($short ? " under {$short}" : '').($place ? " in {$place}" : '').$ref.'.'),
            self::firstSentence($obligation->summary),
            $obligation->applies_from ? 'It applies from '.$obligation->applies_from->format('j F Y').'.' : null,
            self::recordedSentence($obligation),
            self::firstSentence($obligation->practical_action, 'In practice: '),
            self::CLOSING,
        ];

        return self::compose($sentences, $obligation->summary);
    }

    /**
     * @param  Collection<int,PolicyInstrument>  $policies  the jurisdiction's published instruments
     * @param  Collection<int,Deadline>  $deadlines  upcoming, soonest first
     */
    public static function jurisdiction(Jurisdiction $jurisdiction, Collection $policies, Collection $deadlines): string
    {
        $name = $jurisdiction->nameWithArticle();
        $Name = ucfirst($name);
        $n = $policies->count();
        $binding = $policies->where('is_binding', true)->count();
        $inForce = $policies->filter(fn ($p) => in_array($p->status, ['in_force', 'partially_applicable'], true))->count();
        $regulators = collect($jurisdiction->regulators ?? [])->pluck('name')->filter()->take(3);
        $next = $deadlines->first();

        $sentences = [
            $n > 0
                ? "{$Name} has {$n} recorded AI policy ".($n === 1 ? 'instrument' : 'instruments').($binding > 0 ? ", {$binding} of them binding" : ', none of them binding').($inForce > 0 ? " and {$inForce} in force" : '').'.'
                : "{$Name} has no AI policy instrument recorded here yet.",
            self::firstSentence($jurisdiction->regulatory_status_summary),
            $regulators->isNotEmpty() ? 'Regulators include '.$regulators->implode(', ').'.' : null,
            $next && $next->due_on ? 'Next dated milestone: '.self::clean($next->title).' on '.$next->due_on->format('j F Y').'.' : null,
            self::firstSentence($jurisdiction->overview),
            self::CLOSING,
        ];

        return self::compose($sentences, $jurisdiction->overview);
    }

    /** "Ministry of Digital Affairs" → "the Ministry of Digital Affairs"; "NIST" stays "NIST". */
    public static function withArticle(string $organisation): string
    {
        $o = trim($organisation);
        if (preg_match('/^(the|a|an)\s/i', $o) || preg_match('/^[A-Z0-9]{2,}(\b|$)/', $o) && ! preg_match('/^(EU|UK|US)\s/', $o)) {
            return $o;
        }
        $common = 'Ministry|Department|Office|Council|European|National|Federal|Government|Cabinet|Parliament|Commission|Commissioner|Presidency|State|Personal|Information|Executive|Prime|Secretary|Central|Supreme|Digital|Agency|Authority|Bureau|Committee|Board|Data|Privacy|Communications|Infocomm|Korean|Japanese|Chinese|Indian|United|White|Senate|House|Legislature|General|Attorney|Governor|President|King|Royal|Swiss|Dutch|French|German|Italian|Spanish|Australian|Canadian|Brazilian|African|Asian|Association|Organisation|Organization|Institute|Standards|EU|UK|US';

        // A proper name in title case that is not one of the common institutional
        // nouns above ("Infocomm Media Development Authority" is; "Meta" is not).
        return preg_match("/^({$common})\b/u", $o) ? 'the '.$o : $o;
    }

    public static function wordCount(string $text): int
    {
        return count(preg_split('/\s+/u', trim($text), -1, PREG_SPLIT_NO_EMPTY));
    }

    /**
     * Add ranked sentences while they fit; top up from the fallback text if the
     * facts alone are short; never exceed the budget.
     *
     * @param  list<?string>  $sentences
     */
    private static function compose(array $sentences, ?string $fallback): string
    {
        $out = [];
        $words = 0;
        foreach (array_filter($sentences) as $sentence) {
            if ($sentence === self::CLOSING && $words >= self::MIN_WORDS) {
                continue;
            }
            $n = self::wordCount($sentence);
            if ($words + $n > self::MAX_WORDS) {
                continue;
            }
            if (in_array($sentence, $out, true)) {
                continue;
            }
            $out[] = $sentence;
            $words += $n;
        }
        if ($words < self::MIN_WORDS && $fallback) {
            // Take further sentences of the record's own summary, in order.
            foreach (self::sentences($fallback) as $sentence) {
                if (in_array($sentence, $out, true)) {
                    continue;
                }
                $n = self::wordCount($sentence);
                if ($words + $n <= self::MAX_WORDS) {
                    $out[] = $sentence;
                    $words += $n;
                } elseif ($words < self::MIN_WORDS) {
                    // A long sentence that would overflow: cut it at a word, once.
                    $room = self::MAX_WORDS - $words;
                    $out[] = implode(' ', array_slice(preg_split('/\s+/u', $sentence), 0, $room)).'…';
                    break;
                }
                if ($words >= self::MIN_WORDS) {
                    break;
                }
            }
        }

        if ($words < self::MIN_WORDS && ! in_array(self::CLOSING, $out, true) && $words + self::wordCount(self::CLOSING) <= self::MAX_WORDS) {
            $out[] = self::CLOSING;
        }

        return ucfirst(implode(' ', $out));
    }

    private static function statusSentence(PolicyInstrument $policy, string $label): ?string
    {
        $status = $policy->status;
        $since = match ($status) {
            'in_force', 'partially_applicable' => $policy->in_force_on ?? $policy->applies_from,
            'adopted' => $policy->adopted_on,
            'proposed', 'under_consultation' => $policy->published_on ?? $policy->adopted_on,
            default => $policy->adopted_on ?? $policy->published_on,
        };
        $verb = match ($status) {
            'in_force' => 'It has been in force since',
            'partially_applicable' => 'It has applied in part since',
            'adopted' => 'It was adopted on',
            'proposed' => 'It was proposed on',
            'under_consultation' => 'It has been under consultation since',
            'repealed' => 'It was repealed',
            'superseded' => 'It was superseded',
            default => null,
        };
        if ($verb && $since) {
            $s = "{$verb} {$since->format('j F Y')}";
            if (in_array($status, ['adopted', 'proposed'], true) && $policy->applies_from && $policy->applies_from->gt($since)) {
                $s .= ' and applies from '.$policy->applies_from->format('j F Y');
            }

            return $s.'.';
        }

        return 'Its current status is '.mb_strtolower($label).'.';
    }

    private static function dutiesSentence(PolicyInstrument $policy): ?string
    {
        $n = $policy->relationLoaded('obligations') ? $policy->obligations->count() : $policy->obligations()->published()->count();
        if ($n === 0) {
            return null;
        }

        return "{$n} ".($n === 1 ? 'obligation is' : 'obligations are').' recorded against it, each with its source reference.';
    }

    private static function recordedSentence(Obligation $o): ?string
    {
        $evidence = $o->relationLoaded('evidenceArtifacts') ? $o->evidenceArtifacts->count() : $o->evidenceArtifacts()->count();
        $mappings = $o->relationLoaded('frameworkMappings') ? $o->frameworkMappings->pluck('framework')->unique()->count() : $o->frameworkMappings()->distinct('framework')->count('framework');
        $parts = array_filter([
            $evidence > 0 ? "{$evidence} evidence ".($evidence === 1 ? 'example' : 'examples') : null,
            $mappings > 0 ? "mappings to {$mappings} ".($mappings === 1 ? 'framework' : 'frameworks') : null,
        ]);

        return $parts === [] ? null : 'The record carries '.implode(' and ', $parts).'.';
    }

    /** The first sentence of a text, cleaned, ending in a full stop. */
    public static function firstSentence(?string $text, string $prefix = ''): ?string
    {
        $s = self::sentences($text)[0] ?? null;

        return $s === null ? null : $prefix.$s;
    }

    /** @return list<string> */
    private static function sentences(?string $text): array
    {
        $text = self::clean($text ?? '');
        if ($text === '') {
            return [];
        }
        $parts = preg_split('/(?<=[.!?])\s+(?=[A-Z"“(])/u', $text) ?: [];

        return array_values(array_filter(array_map(fn ($p) => rtrim(trim($p), ';:,') === '' ? '' : (preg_match('/[.!?]$/u', trim($p)) ? trim($p) : trim($p).'.'), $parts)));
    }

    private static function clean(?string $text): string
    {
        return trim(preg_replace('/\s+/u', ' ', (string) $text));
    }
}
