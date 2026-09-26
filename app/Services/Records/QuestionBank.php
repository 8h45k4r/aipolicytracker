<?php

namespace App\Services\Records;

use App\Models\Deadline;
use App\Models\Jurisdiction;
use App\Models\Obligation;
use App\Models\PolicyInstrument;
use Illuminate\Support\Collection;

/**
 * The questions a record page answers, from a bank rather than a blank.
 *
 * Each entry pairs the question people ask ("Is the EU AI Act in force?") with
 * the field that answers it. A question is rendered only when its answer is
 * non-null: a page never asks something it cannot answer, and never answers
 * with a placeholder. Hand-written questions on the record (the `faq` field in
 * the data) come first and win on a duplicate.
 *
 * @phpstan-type Item array{question:string, answer:string}
 */
final class QuestionBank
{
    /** @return list<Item> */
    public static function policy(PolicyInstrument $p): array
    {
        $name = $p->definiteName();
        $Name = ucfirst($name);
        $status = $p->statusEnum();

        return self::merge($p->faq ?? [], [
            ["Is {$name} in force?", self::inForceAnswer($p, $status->label())],
            ["Is {$name} legally binding?", $p->is_binding
                ? "Yes. {$Name} is a binding ".mb_strtolower($p->typeEnum()->label()).'; the obligations it creates are legal requirements for the actors it covers.'
                : "No. {$Name} is ".self::article($p->typeEnum()->label()).' '.mb_strtolower($p->typeEnum()->label()).'; it sets expectations rather than legal requirements, unless another instrument gives it force.'],
            ["Who does {$name} apply to?", self::text($p->scope_summary) ?? self::text($p->who_it_applies_to)],
            ["When does {$name} apply?", self::text($p->key_dates_summary) ?? ($p->applies_from ? "{$Name} applies from ".$p->applies_from->format('j F Y').'.' : null)],
            ["What must organisations do under {$name}?", self::text($p->what_organizations_must_do)],
            ["What are the penalties under {$name}?", self::text($p->penalties_summary)],
            ["Where is the official text of {$name}?", $p->official_source_url ? 'The official text is published by '.($p->source_publisher ?: $p->issuing_body ?: 'the issuing authority').' at '.$p->official_source_url.($p->source_title ? " ({$p->source_title})" : '').'. This record links to it and is checked against it.' : null],
        ]);
    }

    /** @return list<Item> */
    public static function obligation(Obligation $o): array
    {
        $policy = $o->policyInstrument;
        $short = $policy ? ($policy->short_title ?: $policy->title) : 'the instrument';
        $rules = $o->relationLoaded('applicabilityRules') ? $o->applicabilityRules : $o->applicabilityRules()->get();
        $evidence = $o->relationLoaded('evidenceArtifacts') ? $o->evidenceArtifacts : $o->evidenceArtifacts()->get();
        $mappings = $o->relationLoaded('frameworkMappings') ? $o->frameworkMappings : $o->frameworkMappings()->get();
        $controls = $o->relationLoaded('controls') ? $o->controls->filter(fn ($c) => $c->published_at) : collect();

        return self::merge([], [
            ['Is this a legal requirement?', $o->is_binding
                ? "Yes. It is a legal requirement under {$short}".($o->source_reference ? " ({$o->source_reference})" : '').'.'
                : "No. It is voluntary guidance under {$short}; following it is expected or recommended rather than required by law."],
            ['Who must comply with this obligation?', $rules->isNotEmpty() ? $rules->map(fn ($r) => trim($r->description.($r->conditions ? " ({$r->conditions})" : '')))->implode(' ') : null],
            ['When does this obligation apply?', $o->applies_from ? 'It applies from '.$o->applies_from->format('j F Y').'.' : null],
            ['What evidence shows compliance?', $evidence->isNotEmpty() ? 'A reviewer would expect: '.$evidence->pluck('title')->take(6)->implode('; ').'.' : null],
            ['Which frameworks map to this obligation?', $mappings->isNotEmpty() ? 'It is mapped to '.$mappings->map(fn ($m) => (config("frameworks.{$m->framework}.name") ?? $m->framework).($m->reference ? " {$m->reference}" : ''))->unique()->implode(', ').'. These are editorial crosswalks with a stated confidence, not official mappings.' : null],
            ['Which controls satisfy this obligation?', $controls->isNotEmpty() ? $controls->pluck('title')->take(6)->implode('; ').'.' : null],
            ['What should an organisation do in practice?', self::text($o->practical_action)],
        ]);
    }

    /**
     * @param  Collection<int,PolicyInstrument>  $policies
     * @param  Collection<int,Deadline>  $deadlines
     * @return list<Item>
     */
    public static function jurisdiction(Jurisdiction $j, Collection $policies, Collection $deadlines): array
    {
        $name = $j->nameWithArticle();
        $Name = ucfirst($name);
        $binding = $policies->where('is_binding', true);
        $inForce = $binding->filter(fn ($p) => in_array($p->status, ['in_force', 'partially_applicable'], true));
        $strategies = $policies->filter(fn ($p) => in_array($p->instrument_type, ['strategy', 'national_strategy', 'plan', 'action_plan'], true));
        $regulators = collect($j->regulators ?? [])->filter(fn ($r) => ! empty($r['name']));

        return self::merge($j->faq ?? [], [
            ["Does {$name} have an AI law?", $policies->isEmpty() ? null : ($inForce->isNotEmpty()
                ? "Yes. {$inForce->count()} binding ".($inForce->count() === 1 ? 'instrument is' : 'instruments are').' in force: '.$inForce->map(fn ($p) => $p->short_title ?: $p->title)->take(4)->implode('; ').'.'
                : ($binding->isNotEmpty()
                    ? 'Binding instruments are recorded but not yet in force: '.$binding->map(fn ($p) => $p->short_title ?: $p->title)->take(4)->implode('; ').'.'
                    : "No binding AI law is recorded for {$name}. The instruments recorded are non-binding: strategies, guidance or standards."))],
            ["Who regulates AI in {$name}?", $regulators->isNotEmpty() ? $regulators->map(fn ($r) => $r['name'].(! empty($r['role']) ? " ({$r['role']})" : ''))->implode('; ').'.' : null],
            ["What is the status of AI regulation in {$name}?", self::text($j->regulatory_status_summary)],
            ["Is there a national AI strategy in {$name}?", $strategies->isNotEmpty() ? 'Yes: '.$strategies->map(fn ($p) => ($p->short_title ?: $p->title).($p->adopted_on ? ' ('.$p->adopted_on->format('Y').')' : ''))->take(3)->implode('; ').'.' : null],
            ["What AI compliance deadlines are coming up in {$name}?", $deadlines->isNotEmpty() ? $deadlines->take(4)->map(fn ($d) => $d->due_on->format('j F Y').': '.trim($d->title))->implode('; ').'.' : null],
            ["What is binding and what is only guidance in {$name}?", self::text($j->binding_vs_guidance)],
        ]);
    }

    private static function inForceAnswer(PolicyInstrument $p, string $label): string
    {
        $Name = ucfirst($p->definiteName());

        return match ($p->status) {
            'in_force' => "Yes. {$Name} is in force".($p->in_force_on ? ' since '.$p->in_force_on->format('j F Y') : '').'.',
            'partially_applicable' => "In part. {$Name} applies in stages".($p->applies_from ? ', with application from '.$p->applies_from->format('j F Y') : '').'; see the dates on this page for which provisions apply now.',
            'adopted' => "Not yet. {$Name} has been adopted".($p->adopted_on ? ' ('.$p->adopted_on->format('j F Y').')' : '').($p->applies_from ? ' and applies from '.$p->applies_from->format('j F Y') : '').'.',
            'proposed', 'under_consultation' => "No. {$Name} is ".mb_strtolower($label).' and has not been adopted.',
            'repealed', 'superseded', 'archived' => "No. {$Name} is ".mb_strtolower($label).'.',
            default => "{$Name} has the status \"".mb_strtolower($label).'"; it is not a binding law in force.',
        };
    }

    /**
     * Hand-written questions first; generated ones added unless the same
     * question is already asked; nothing with an empty answer.
     *
     * @param  list<array{question:string, answer:string}>  $written
     * @param  list<array{0:string,1:?string}>  $generated
     * @return list<Item>
     */
    private static function merge(array $written, array $generated): array
    {
        $out = [];
        $seen = [];
        foreach ($written as $item) {
            if (! empty($item['question']) && ! empty($item['answer'])) {
                $out[] = ['question' => trim($item['question']), 'answer' => trim($item['answer'])];
                $seen[self::key($item['question'])] = true;
            }
        }
        foreach ($generated as [$question, $answer]) {
            if ($answer === null || trim($answer) === '' || isset($seen[self::key($question)])) {
                continue;
            }
            $out[] = ['question' => $question, 'answer' => trim($answer)];
            $seen[self::key($question)] = true;
        }

        return $out;
    }

    private static function key(string $q): string
    {
        return preg_replace('/[^a-z0-9]+/', ' ', mb_strtolower($q));
    }

    private static function text(?string $s): ?string
    {
        $s = trim(preg_replace('/\s+/u', ' ', (string) $s));

        return $s === '' ? null : $s;
    }

    private static function article(string $word): string
    {
        return preg_match('/^[aeiou]/i', $word) ? 'an' : 'a';
    }
}
