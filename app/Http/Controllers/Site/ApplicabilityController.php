<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Models\Jurisdiction;
use App\Models\Obligation;
use App\Models\PolicyInstrument;
use App\Models\TaxonomyTerm;
use App\Support\Seo;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Educational screening questionnaire. It never makes a legal determination:
 * it surfaces policies and obligations whose recorded scope overlaps the answers,
 * plus questions to investigate and a starter checklist.
 */
class ApplicabilityController extends Controller
{
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

    public function show(Request $request): View
    {
        $jurisdictions = Jurisdiction::published()->orderBy('name')->get(['id', 'slug', 'name']);
        $actors = TaxonomyTerm::taxonomy('actor')->get();
        $useCases = TaxonomyTerm::taxonomy('use_case')->get();
        $sectors = TaxonomyTerm::taxonomy('sector')->get();

        $answers = [
            'jurisdictions' => array_values(array_intersect((array) $request->query('jurisdictions', []), $jurisdictions->pluck('slug')->all())),
            'role' => in_array($request->query('role'), $actors->pluck('slug')->all(), true) ? $request->query('role') : null,
            'use_case' => in_array($request->query('use_case'), $useCases->pluck('slug')->all(), true) ? $request->query('use_case') : null,
            'sector' => in_array($request->query('sector'), $sectors->pluck('slug')->all(), true) ? $request->query('sector') : null,
            'personal_data' => in_array($request->query('personal_data'), ['yes', 'no', 'unsure'], true) ? $request->query('personal_data') : null,
            'domains' => array_values(array_intersect((array) $request->query('domains', []), array_keys(self::DOMAINS))),
            'genai' => in_array($request->query('genai'), ['yes', 'no', 'unsure'], true) ? $request->query('genai') : null,
        ];
        $submitted = $request->has('jurisdictions') && $answers['jurisdictions'] !== [];
        $result = $submitted ? $this->screen($answers) : null;

        $seo = Seo::make(
            'AI regulation applicability check (educational screening)',
            'Answer a few questions about your markets, role, AI use case, sector and data to see which AI laws, standards and obligations may be relevant, with questions to investigate and official sources. Not legal advice.',
            route('tools.applicability'),
            ! $submitted
        )->withBreadcrumbs([['Home', route('home')], ['Tools', route('tools.applicability')], ['Applicability check', route('tools.applicability')]])
            ->withJsonLd(['@type' => 'WebApplication', 'name' => 'AI regulation applicability check', 'url' => route('tools.applicability'), 'applicationCategory' => 'BusinessApplication', 'operatingSystem' => 'Web', 'offers' => ['@type' => 'Offer', 'price' => '0', 'priceCurrency' => 'USD'], 'isPartOf' => ['@id' => url('/').'#website']]);

        return view('site.tools.applicability', compact('seo', 'jurisdictions', 'actors', 'useCases', 'sectors', 'answers', 'submitted', 'result') + ['domains' => self::DOMAINS]);
    }

    private function screen(array $a): array
    {
        $jurisdictionIds = Jurisdiction::whereIn('slug', $a['jurisdictions'])->pluck('id');
        $policies = PolicyInstrument::published()->with(['jurisdiction', 'terms'])->whereIn('jurisdiction_id', $jurisdictionIds)->get();
        $wantedUseCases = array_values(array_unique(array_filter(array_merge([$a['use_case']], $a['domains'], $a['genai'] === 'yes' ? ['generative_ai'] : []))));

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

        $policyIds = $scored->pluck('policy.id');
        $obligations = Obligation::published()->with(['policyInstrument.jurisdiction', 'terms'])->whereIn('policy_instrument_id', $policyIds)->get()
            ->filter(function (Obligation $o) use ($a, $wantedUseCases) {
                $roleOk = ! $a['role'] || $o->terms->where('taxonomy', 'actor')->isEmpty() || $o->terms->contains(fn ($t) => $t->taxonomy === 'actor' && $t->slug === $a['role']);
                $useOk = $wantedUseCases === [] || $o->terms->contains(fn ($t) => $t->taxonomy === 'use_case' && in_array($t->slug, $wantedUseCases, true));

                return $roleOk && $useOk;
            })->sortByDesc('is_binding')->values();

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

        $checklist = [
            'Create an AI system inventory with owner, purpose, users, jurisdictions and data types.',
            'Assign a role (provider, deployer, importer, distributor) for each system and market.',
            'Screen each system against prohibited practices and high-risk categories in the relevant instruments.',
            'Record the official source and date for every conclusion; set a review date.',
            'Prepare core evidence: risk assessment, data documentation, human-oversight procedure, user notices, incident process.',
            'Have qualified counsel review classifications and confirm applicable dates before relying on them.',
        ];

        return ['policies' => $scored, 'obligations' => $obligations, 'questions' => $questions, 'checklist' => $checklist];
    }
}
