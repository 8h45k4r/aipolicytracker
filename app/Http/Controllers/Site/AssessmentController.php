<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Services\Assessments\AssessmentCatalog;
use App\Support\Seo;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** /self-assessments: the free AI self-assessments from Certifyi, filterable by type and region. */
class AssessmentController extends Controller
{
    public function index(Request $request): View
    {
        $type = array_key_exists((string) $request->query('type'), config('assessments.types')) ? (string) $request->query('type') : null;
        $regions = AssessmentCatalog::regions();
        $region = array_key_exists((string) $request->query('region'), $regions) ? (string) $request->query('region') : null;
        $q = mb_substr(trim((string) $request->query('q', '')), 0, 80);
        $items = AssessmentCatalog::filter(['type' => $type, 'region' => $region, 'q' => $q]);
        $all = AssessmentCatalog::all();
        $filtered = $type || $region || $q !== '';

        $faq = [
            ['question' => 'Are the AI self-assessments free?', 'answer' => 'Yes. Each is a short questionnaire answered in the browser, with a score for each domain at the end. No account is needed, and a PDF report can be requested by email.'],
            ['question' => 'How long does an AI self-assessment take?', 'answer' => 'Most take five to seven minutes: about 20 to 28 questions across five to seven domains. The list shows the question count and the expected time for each.'],
            ['question' => 'Which assessment should I start with?', 'answer' => 'If the EU AI Act may apply to you, the EU AI Act Readiness and AI System Risk Classification assessments. For a framework-neutral view, the AI Governance Maturity assessment. For a certification goal, ISO/IEC 42001 Readiness. For US state laws, the US State AI Law Exposure assessment.'],
            ['question' => 'Does a good score mean we are compliant?', 'answer' => 'No. A self-assessment shows where you stand against the questions it asks; it is not an audit, a certification or legal advice. Use the score to decide what to evidence next, and the templates and obligations on this site to do it.'],
            ['question' => 'Where do the self-assessments run?', 'answer' => 'On Certifyi, a related product. They are listed here because they cover the same laws and frameworks as the records; nothing on this site requires an account there.'],
        ];

        $seo = Seo::make(
            'Free AI Self-Assessments: EU AI Act, ISO 42001, NIST AI RMF',
            sprintf('%d free AI self-assessments: EU AI Act readiness, risk classification, FRIA, ISO/IEC 42001, NIST AI RMF, US state AI laws, LLM security and AI governance. Scores by domain, no account.', $all->count()),
            route('assessments.index'),
            ! $filtered,
        )->withBreadcrumbs([['Home', route('home')], ['Guides', route('guides.index')], ['Free self-assessments', route('assessments.index')]])
            ->withPageType('CollectionPage', [
                'name' => 'Free AI self-assessments',
                'mainEntity' => [
                    '@type' => 'ItemList',
                    'name' => 'Free AI self-assessments',
                    'numberOfItems' => $items->count(),
                    'itemListElement' => $items->values()->map(fn ($a, $i) => ['@type' => 'ListItem', 'position' => $i + 1, 'url' => $a['url'], 'name' => $a['title']])->all(),
                ],
            ])
            ->withFaq($faq);

        return view('site.assessments.index', compact('seo', 'items', 'all', 'type', 'region', 'regions', 'q', 'filtered'));
    }
}
