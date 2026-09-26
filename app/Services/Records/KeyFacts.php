<?php

namespace App\Services\Records;

use App\Models\Deadline;
use App\Models\Jurisdiction;
use App\Models\Obligation;
use App\Models\PolicyInstrument;
use Illuminate\Support\Collection;

/**
 * The key-facts table under the answer box: the fields a reader scans for,
 * as label and value, with a link where the value is a record. A fact with no
 * value is left out rather than shown as "—", because an absent row says
 * "not recorded" and a dash says "recorded as nothing".
 *
 * @phpstan-type Fact array{label:string, value:string, href?:string, note?:string}
 */
final class KeyFacts
{
    /** @return list<Fact> */
    public static function policy(PolicyInstrument $p): array
    {
        $obligations = $p->relationLoaded('obligations') ? $p->obligations->count() : $p->obligations()->published()->count();

        return self::rows([
            ['Jurisdiction', $p->jurisdiction?->name, $p->jurisdiction?->url()],
            ['Instrument type', $p->typeEnum()->label()],
            ['Status', $p->statusEnum()->label()],
            ['Legal force', $p->is_binding ? 'Binding' : 'Non-binding (guidance, strategy or standard)'],
            ['Issuing body', $p->issuing_body],
            ['Adopted', $p->adopted_on?->format('j F Y')],
            ['Published', $p->published_on?->format('j F Y')],
            ['In force', $p->in_force_on?->format('j F Y')],
            ['Applies from', $p->applies_from?->format('j F Y')],
            ['Obligations recorded', $obligations > 0 ? (string) $obligations : null, $obligations > 0 ? $p->url().'#obligations-heading' : null],
            ['Source reference', $p->source_reference],
            ['Official source', $p->official_source_url ? ($p->source_title ?: 'Open official text') : null, $p->official_source_url],
            ['Verification', $p->verificationLabel()],
        ]);
    }

    /** @return list<Fact> */
    public static function obligation(Obligation $o): array
    {
        $policy = $o->policyInstrument;
        $frameworks = $o->relationLoaded('frameworkMappings') ? $o->frameworkMappings->pluck('framework')->unique() : $o->frameworkMappings()->pluck('framework')->unique();
        $names = $frameworks->map(fn ($f) => config("frameworks.{$f}.name") ?? $f)->implode(', ');
        $evidence = $o->relationLoaded('evidenceArtifacts') ? $o->evidenceArtifacts->count() : $o->evidenceArtifacts()->count();
        $controls = $o->relationLoaded('controls') ? $o->controls->filter(fn ($c) => $c->published_at)->count() : null;
        $actors = $o->relationLoaded('terms') ? $o->termsOf('actor')->pluck('name')->implode(', ') : null;

        return self::rows([
            ['Instrument', $policy ? ($policy->short_title ?: $policy->title) : null, $policy?->url()],
            ['Jurisdiction', $policy?->jurisdiction?->name, $policy?->jurisdiction?->url()],
            ['Nature', $o->is_binding ? 'Legal requirement' : 'Voluntary guidance'],
            ['Source reference', $o->source_reference],
            ['Applies from', $o->applies_from?->format('j F Y')],
            ['Who it binds', $actors ?: null],
            ['Evidence examples', $evidence > 0 ? (string) $evidence : null],
            ['Framework mappings', $names ?: null],
            ['Controls that meet it', $controls !== null && $controls > 0 ? (string) $controls : null],
            ['Official source', $o->official_source_url ? 'Open official text' : ($policy?->official_source_url ? 'Open the instrument\'s official text' : null), $o->official_source_url ?: $policy?->official_source_url],
            ['Verification', $o->verificationLabel()],
        ]);
    }

    /**
     * @param  Collection<int,PolicyInstrument>  $policies
     * @param  Collection<int,Deadline>  $deadlines
     * @return list<Fact>
     */
    public static function jurisdiction(Jurisdiction $j, Collection $policies, Collection $deadlines, int $obligations): array
    {
        $regulators = collect($j->regulators ?? [])->pluck('name')->filter();
        $next = $deadlines->first();

        return self::rows([
            ['Type', ucfirst((string) $j->jurisdiction_type)],
            ['Region', $j->region],
            ['Instruments recorded', (string) $policies->count(), route('policies.index', ['jurisdiction' => $j->slug])],
            ['Binding instruments', (string) $policies->where('is_binding', true)->count()],
            ['In force', (string) $policies->filter(fn ($p) => in_array($p->status, ['in_force', 'partially_applicable'], true))->count()],
            ['Obligations recorded', $obligations > 0 ? (string) $obligations : null],
            ['Regulators', $regulators->isNotEmpty() ? $regulators->implode(', ') : null],
            ['Next dated milestone', $next?->due_on ? self::clean($next->title).', '.$next->due_on->format('j F Y') : null, $next?->policyInstrument?->url()],
            ['Updates and RSS', 'AI policy updates for '.($j->short_name ?: $j->name), route('updates.jurisdiction', $j->slug)],
            ['Verification', $j->verificationLabel()],
        ]);
    }

    /** @param list<array{0:string,1:?string,2?:?string}> $pairs @return list<Fact> */
    private static function rows(array $pairs): array
    {
        $out = [];
        foreach ($pairs as $pair) {
            [$label, $value] = $pair;
            if ($value === null || trim((string) $value) === '') {
                continue;
            }
            $fact = ['label' => $label, 'value' => self::clean($value)];
            if (! empty($pair[2])) {
                $fact['href'] = $pair[2];
            }
            $out[] = $fact;
        }

        return $out;
    }

    private static function clean(string $text): string
    {
        return trim(preg_replace('/\s+/u', ' ', $text));
    }
}
