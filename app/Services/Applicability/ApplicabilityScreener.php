<?php

namespace App\Services\Applicability;

use App\Models\Jurisdiction;
use App\Models\Obligation;
use App\Models\PolicyInstrument;
use App\Models\TaxonomyTerm;
use Illuminate\Support\Collection;

/**
 * Educational screening shared by the free applicability check and the paid
 * change-impact alerts. It never makes a legal determination: it surfaces
 * instruments and obligations whose recorded scope overlaps the answers, with
 * the reason for each match, so a human can verify against the official source.
 *
 * One implementation on purpose: an alert that claims a change is relevant must
 * use exactly the screen the reader saw on the tool page.
 */
class ApplicabilityScreener
{
    /** Sensitive decision domains offered by the questionnaire. */
    public const DOMAINS = [
        'hiring_and_hr' => 'Employment, recruitment or worker management',
        'finance_and_credit' => 'Credit, lending, insurance or financial services',
        'health' => 'Health, medical or care decisions',
        'education' => 'Education or exam assessment',
        'public_services' => 'Access to public services or benefits',
        'law_enforcement' => 'Law enforcement, justice or migration',
        'biometrics' => 'Biometric identification, categorisation or emotion recognition',
        'safety_critical' => 'Safety of products, infrastructure or vehicles',
    ];

    /** Answers reduced to the keys the screen understands, with unknown values dropped. */
    public function normalise(array $input): array
    {
        $slugs = fn (string $taxonomy) => TaxonomyTerm::taxonomy($taxonomy)->pluck('slug')->all();
        // Only flat lists of strings: `jurisdictions[][]=x` used to reach array_intersect
        // as a nested array and end in a 500.
        $list = fn (mixed $v) => array_values(array_unique(array_filter((array) $v, 'is_string')));

        return [
            'jurisdictions' => array_values(array_intersect($list($input['jurisdictions'] ?? []), Jurisdiction::published()->pluck('slug')->all())),
            'role' => in_array($input['role'] ?? null, $slugs('actor'), true) ? $input['role'] : null,
            'use_case' => in_array($input['use_case'] ?? null, $slugs('use_case'), true) ? $input['use_case'] : null,
            'sector' => in_array($input['sector'] ?? null, $slugs('sector'), true) ? $input['sector'] : null,
            'personal_data' => in_array($input['personal_data'] ?? null, ['yes', 'no', 'unsure'], true) ? $input['personal_data'] : null,
            'domains' => array_values(array_intersect($list($input['domains'] ?? []), array_keys(self::DOMAINS))),
            'genai' => in_array($input['genai'] ?? null, ['yes', 'no', 'unsure'], true) ? $input['genai'] : null,
        ];
    }

    /**
     * @return array{policies: Collection, obligations: Collection, questions: list<string>, checklist: list<string>}
     */
    public function screen(array $a): array
    {
        $jurisdictionIds = Jurisdiction::whereIn('slug', $a['jurisdictions'])->pluck('id');
        $policies = PolicyInstrument::published()->with(['jurisdiction', 'terms'])->whereIn('jurisdiction_id', $jurisdictionIds)->get();
        $wantedUseCases = $this->useCases($a);

        $scored = $policies->map(function (PolicyInstrument $p) use ($a, $wantedUseCases) {
            $score = 0;
            $why = [];
            $terms = $p->terms;
            if ($a['role'] && $terms->contains(fn ($t) => $t->taxonomy === 'actor' && $t->slug === $a['role'])) {
                $score += 2;
                $why[] = 'covers your role';
            }
            $matchedUses = $terms->filter(fn ($t) => $t->taxonomy === 'use_case' && in_array($t->slug, $wantedUseCases, true));
            if ($matchedUses->isNotEmpty()) {
                $score += 2 * $matchedUses->count();
                $why[] = 'mentions '.$matchedUses->pluck('name')->map(fn ($n) => mb_strtolower($n))->implode(', ');
            }
            if ($a['sector'] && $terms->contains(fn ($t) => $t->taxonomy === 'sector' && in_array($t->slug, [$a['sector'], 'cross_sector'], true))) {
                $score += 1;
                $why[] = 'applies to your sector or to all sectors';
            }
            if ($a['personal_data'] === 'yes' && $terms->contains(fn ($t) => $t->taxonomy === 'ai_system_type' && $t->slug === 'automated_decision_system')) {
                $score += 1;
            }
            if ($p->is_binding) {
                $score += 1;
                $why[] = 'binding instrument';
            }

            return ['policy' => $p, 'score' => $score, 'why' => $why];
        })->sortByDesc('score')->values();

        $obligations = Obligation::published()->with(['policyInstrument.jurisdiction', 'terms', 'controls'])->whereIn('policy_instrument_id', $scored->pluck('policy.id'))->get()
            ->filter(function (Obligation $o) use ($a, $wantedUseCases) {
                $roleOk = ! $a['role'] || $o->terms->where('taxonomy', 'actor')->isEmpty() || $o->terms->contains(fn ($t) => $t->taxonomy === 'actor' && $t->slug === $a['role']);
                $useOk = $wantedUseCases === [] || $o->terms->contains(fn ($t) => $t->taxonomy === 'use_case' && in_array($t->slug, $wantedUseCases, true));

                return $roleOk && $useOk;
            })->sortByDesc('is_binding')->values();

        return ['policies' => $scored, 'obligations' => $obligations, 'questions' => $this->questions($a), 'checklist' => $this->checklist()];
    }

    /**
     * Instruments a profile is watching: those the screen scored above zero.
     * A zero score means nothing in the record overlapped the answers, so a
     * change to it would not be reported as relevant.
     *
     * @return array{jurisdiction_ids: list<int>, instrument_ids: list<int>}
     */
    public function watchedIds(array $a): array
    {
        $result = $this->screen($a);

        return [
            'jurisdiction_ids' => Jurisdiction::published()->whereIn('slug', $a['jurisdictions'])->pluck('id')->all(),
            'instrument_ids' => $result['policies']->filter(fn ($row) => $row['score'] > 0)->pluck('policy.id')->all(),
        ];
    }

    /** @return list<string> */
    private function useCases(array $a): array
    {
        return array_values(array_unique(array_filter(array_merge([$a['use_case']], $a['domains'], ($a['genai'] ?? null) === 'yes' ? ['generative_ai'] : []))));
    }

    /** @return list<string> */
    private function questions(array $a): array
    {
        $questions = ['Which entity in your group places the AI system on the market or uses it, and in which country is it established?'];
        if ($a['personal_data'] !== 'no') {
            $questions[] = 'Does the system process personal data, and is there a lawful basis and impact assessment under the applicable data-protection law?';
        }
        if ($a['domains'] !== []) {
            $questions[] = 'Does the system make or materially influence decisions about people in the sensitive areas you selected? If so, check high-risk classification rules and notice, explanation and appeal duties.';
        }
        if ($a['genai'] !== 'no') {
            $questions[] = 'Do you provide a general-purpose or generative model, or only integrate one? Provider and deployer duties differ, including content labelling and training-data transparency.';
        }
        $questions[] = 'Which official sources set the application dates that matter for your launch plan, and have any been amended?';
        $questions[] = 'Are you subject to sector rules (financial, health, telecoms, employment) that already regulate automated decisions?';

        return $questions;
    }

    /** @return list<string> */
    private function checklist(): array
    {
        return [
            'Create an AI system inventory with owner, purpose, users, jurisdictions and data types.',
            'Assign a role (provider, deployer, importer, distributor) for each system and market.',
            'Screen each system against prohibited practices and high-risk categories in the relevant instruments.',
            'Record the official source and date for every conclusion; set a review date.',
            'Prepare core evidence: risk assessment, data documentation, human-oversight procedure, user notices, incident process.',
            'Have qualified counsel review classifications and confirm applicable dates before relying on them.',
        ];
    }
}
