<?php

namespace App\Support;

use App\Models\Control;
use App\Models\Deadline;
use App\Models\Jurisdiction;
use App\Models\Obligation;
use App\Models\PolicyInstrument;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Meta descriptions for record pages, composed from the record's fields.
 *
 * The pages used to pass their 40-to-60-word answer paragraph (AnswerBox) as the
 * description, and Seo::make() cut it at 155 characters, so a search result showed
 * a sentence that stopped mid-way ("…sets duties for providers of…"). These say the
 * few facts a searcher decides on, in a fixed shape per record type, sized to fit.
 *
 * Each builder lists its forms from the fullest to the shortest. The first that
 * fits the budget is used; every form ends on a complete sentence. If none fits (a
 * very long name), the shortest goes through PageTitle::description(), which cuts
 * at the last full sentence. Nothing is said that the record does not say.
 */
final class MetaDescription
{
    private const SOURCES = 'Official sources linked.';

    /** "EU AI Act (European Union): in force since 1 Aug 2024; 113 duties, next deadline 2 Aug 2026. Official sources linked." */
    public static function policy(PolicyInstrument $policy): string
    {
        $name = PageTitle::clean($policy->short_title ?: $policy->title);
        $place = $policy->jurisdiction?->short_name ?: $policy->jurisdiction?->name;
        // A place is added only when the name does not already carry it, and only a plain
        // name: "International organisations (OECD, UNESCO, …)" is a bucket, not a place.
        $named = $place && ! str_contains($place, '(') && mb_stripos($name, $place) === false && mb_stripos($name, (string) $policy->jurisdiction?->name) === false
            ? [$name.' ('.$place.')', $name] : [$name];
        $status = self::policyStatus($policy);
        $duties = $policy->obligations()->published()->count();
        $next = Deadline::query()->where('policy_instrument_id', $policy->id)->where('deadline_status', 'scheduled')
            ->whereNotNull('due_on')->where('due_on', '>=', now()->toDateString())->min('due_on');

        $dutyText = $duties > 0 ? $duties.' '.Str::plural('duty', $duties) : null;
        $nextText = $next ? 'next deadline '.self::date($next) : null;
        $forms = [];
        foreach ($named as $n) {
            foreach ([[$dutyText, $nextText], [$dutyText, null], [null, $nextText], [null, null]] as [$d, $x]) {
                $facts = implode(', ', array_filter([$d, $x]));
                $sentence = $n.': '.$status.($facts !== '' ? '; '.$facts : '').'.';
                $forms[] = $sentence.' '.self::SOURCES;
                $forms[] = $sentence;
            }
        }

        return self::first($forms);
    }

    /** "Japan AI regulation: 12 instruments, 3 binding. Status, regulators and deadlines." */
    public static function jurisdiction(Jurisdiction $jurisdiction, Collection $policies): string
    {
        $n = $policies->count();
        $binding = $policies->where('is_binding', true)->count();
        $head = $jurisdiction->name.' AI regulation: ';
        $counts = $n > 0
            ? $n.' '.Str::plural('instrument', $n).', '.($binding > 0 ? $binding.' binding' : 'none binding').'.'
            : 'no instruments recorded yet.';

        return self::first([
            $head.$counts.' Status, regulators and deadlines, with official sources.',
            $head.$counts.' Status, regulators and deadlines.',
            $head.$counts,
        ]);
    }

    /** "Asia AI regulation: 33 jurisdictions, 6 with binding AI law; 120 instruments. Status, deadlines and official sources." */
    public static function region(string $region, int $jurisdictions, int $binding, int $instruments): string
    {
        $head = $region.' AI regulation: '.$jurisdictions.' '.Str::plural('jurisdiction', $jurisdictions).', '
            .($binding > 0 ? $binding.' with binding AI law' : 'none with binding AI law').'; '.$instruments.' '.Str::plural('instrument', $instruments).'.';

        return self::first([
            $head.' Country by country, with deadlines and official sources.',
            $head.' Status, deadlines and official sources.',
            $head,
        ]);
    }

    /** "Risk management system: binding duty under the EU AI Act (European Union), applies from 2 Aug 2026. 4 controls and evidence listed." */
    public static function obligation(Obligation $obligation): string
    {
        $policy = $obligation->policyInstrument;
        $title = PageTitle::clean($obligation->title);
        $kind = $obligation->is_binding ? 'binding duty' : 'voluntary guidance';
        $under = $policy ? ' under '.$policy->definiteName() : '';
        $place = $policy?->jurisdiction ? ' ('.$policy->jurisdiction->name.')' : '';
        $applies = $obligation->applies_from ? ', applies from '.self::date($obligation->applies_from) : '';
        $controls = $obligation->relationLoaded('controls') ? $obligation->controls->count() : $obligation->controls()->count();
        $tail = $controls > 0 ? $controls.' '.Str::plural('control', $controls).' and evidence listed.' : 'Evidence examples and source reference.';

        return self::first([
            "{$title}: {$kind}{$under}{$place}{$applies}. {$tail}",
            "{$title}: {$kind}{$under}{$place}{$applies}.",
            "{$title}: {$kind}{$under}{$applies}.",
            "{$title}: {$kind}{$under}.",
            "{$title}: {$kind}.",
        ]);
    }

    /** "AI impact assessment: a control that serves 14 duties in 6 jurisdictions. Evidence, risks and framework mappings." */
    public static function control(Control $control, int $duties, int $jurisdictions): string
    {
        $title = PageTitle::clean($control->title);
        $serves = $duties > 0
            ? 'a control that serves '.$duties.' recorded '.Str::plural('duty', $duties).' in '.$jurisdictions.' '.Str::plural('jurisdiction', $jurisdictions).'.'
            : 'an AI governance control.';

        return self::first([
            "{$title}: {$serves} Evidence, risks and ISO/IEC 42001 and NIST AI RMF mappings.",
            "{$title}: {$serves} Evidence, risks and framework mappings.",
            "{$title}: {$serves}",
        ]);
    }

    /**
     * A guide: its own heading, what it is built from and when that last changed.
     *
     * @param  array{records:int, verified:int, changed:?CarbonInterface}  $provenance
     */
    public static function guide(string $heading, array $provenance): string
    {
        $h = rtrim(PageTitle::clean($heading), '.?!:');
        $n = $provenance['records'];
        $built = $n > 0 ? 'Built from '.$n.' source-linked '.Str::plural('record', $n).($provenance['verified'] > 0 ? ', '.$provenance['verified'].' reviewer-verified' : '').'.' : null;
        $changed = $provenance['changed'] ? 'Updated '.self::date($provenance['changed']).'.' : null;

        return self::first(array_values(array_filter([
            $built ? trim("{$h}. {$built} {$changed} Not legal advice.") : null,
            $built ? trim("{$h}. {$built} {$changed}") : null,
            $built ? "{$h}. {$built}" : null,
            "{$h}. Free guide with official sources; not legal advice.",
            "{$h}.",
        ])));
    }

    /** The first form that fits, or the last one cut at a sentence. */
    public static function first(array $forms): string
    {
        foreach ($forms as $form) {
            $form = PageTitle::clean($form);
            if (mb_strlen($form) <= PageTitle::MAX_DESCRIPTION) {
                return $form;
            }
        }

        return PageTitle::description((string) end($forms));
    }

    /** "in force since 1 Aug 2024", "adopted on 21 May 2024", "proposed". */
    private static function policyStatus(PolicyInstrument $policy): string
    {
        $label = mb_strtolower($policy->statusEnum()->label());
        [$verb, $date] = match ($policy->status) {
            'in_force', 'partially_applicable' => [$label.' since', $policy->in_force_on ?? $policy->applies_from],
            'under_consultation' => [$label.' since', $policy->published_on],
            'adopted' => ['adopted on', $policy->adopted_on],
            'proposed' => ['proposed on', $policy->published_on ?? $policy->adopted_on],
            'guidance', 'voluntary_standard' => [$label.', issued', $policy->published_on ?? $policy->adopted_on],
            default => [$label, null],
        };

        return $date ? $verb.' '.self::date($date) : $label;
    }

    private static function date(mixed $date): string
    {
        return ($date instanceof CarbonInterface ? $date : Carbon::parse($date))->format('j M Y');
    }
}
