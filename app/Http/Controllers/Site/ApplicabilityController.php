<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Models\ApplicabilityProfile;
use App\Models\Jurisdiction;
use App\Models\TaxonomyTerm;
use App\Services\Applicability\ApplicabilityScreener;
use App\Support\Seo;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Educational screening questionnaire. It never makes a legal determination:
 * it surfaces policies and obligations whose recorded scope overlaps the answers,
 * plus questions to investigate and a starter checklist. The screen itself lives
 * in ApplicabilityScreener so the paid change-impact alerts use the same rules.
 */
class ApplicabilityController extends Controller
{
    /** @deprecated Use ApplicabilityScreener::DOMAINS; kept so existing links and views keep working. */
    public const DOMAINS = ApplicabilityScreener::DOMAINS;

    public function show(Request $request, ApplicabilityScreener $screener): View
    {
        $jurisdictions = Jurisdiction::published()->orderBy('name')->get(['id', 'slug', 'name']);
        $actors = TaxonomyTerm::taxonomy('actor')->get();
        $useCases = TaxonomyTerm::taxonomy('use_case')->get();
        $sectors = TaxonomyTerm::taxonomy('sector')->get();

        $answers = $screener->normalise($request->query());
        $submitted = $request->has('jurisdictions') && $answers['jurisdictions'] !== [];
        $result = $submitted ? $screener->screen($answers) : null;
        $user = $request->user();
        $canSave = (bool) $user?->entitled('saved.server');
        $savedProfile = $submitted && $user ? ApplicabilityProfile::where('user_id', $user->id)->get()->first(fn ($p) => $p->matchesAnswers($answers)) : null;

        $seo = Seo::make(
            'AI regulation applicability check (educational screening)',
            'Answer a few questions about your markets, role, AI use case, sector and data to see which AI laws, standards and obligations may be relevant, with questions to investigate and official sources. Not legal advice.',
            route('tools.applicability'),
            ! $submitted
        )->withBreadcrumbs([['Home', route('home')], ['Tools', route('tools.applicability')], ['Applicability check', route('tools.applicability')]])
            ->withJsonLd(['@type' => 'WebApplication', 'name' => 'AI regulation applicability check', 'url' => route('tools.applicability'), 'applicationCategory' => 'BusinessApplication', 'operatingSystem' => 'Web', 'offers' => ['@type' => 'Offer', 'price' => '0', 'priceCurrency' => 'USD'], 'isPartOf' => ['@id' => url('/').'#website']]);

        return view('site.tools.applicability', compact('seo', 'jurisdictions', 'actors', 'useCases', 'sectors', 'answers', 'submitted', 'result', 'canSave', 'savedProfile', 'user') + ['domains' => ApplicabilityScreener::DOMAINS]);
    }
}
