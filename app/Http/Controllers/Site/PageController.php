<?php

namespace App\Http\Controllers\Site;

use App\Enums\PolicyStatus;
use App\Enums\ReviewStatus;
use App\Http\Controllers\Controller;
use App\Models\SourceDocument;
use App\Models\TaxonomyTerm;
use App\Services\Billing\BillingConfig;
use App\Services\PolicyData\OpenDataExporter;
use App\Services\PolicyData\PolicyCatalog;
use App\Services\Verification\IndependentChecks;
use App\Support\ContentCache;
use App\Support\DatasetCitation;
use App\Support\Seo;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

class PageController extends Controller
{
    public function openData(PolicyCatalog $catalog): View
    {
        $stats = $catalog->stats();
        $lastUpdated = $stats['last_updated'] ? Carbon::parse($stats['last_updated']) : null;
        $seo = Seo::make(
            'Open AI policy dataset, schema and API',
            'Download the AIPolicyTracker dataset (CC BY 4.0): jurisdictions, policy instruments, obligations, controls, deadlines and change events with official sources. JSON Schema, read-only API and citation guidance.',
            route('open-data')
        )->withBreadcrumbs([['Home', route('home')], ['Open data', route('open-data')]])
            ->withJsonLd([
                '@type' => 'Dataset',
                '@id' => route('open-data').'#dataset',
                'name' => 'AIPolicyTracker open AI policy dataset',
                'description' => 'Structured, source-backed records of AI laws, regulations, standards, guidance, obligations, the controls that meet them, deadlines and change events across jurisdictions.',
                'url' => route('open-data'),
                'license' => config('aipolicytracker.data_license_url'),
                'isAccessibleForFree' => true,
                'creator' => ['@id' => url('/').'#organization'],
                'dateModified' => $lastUpdated?->toIso8601String(),
                'version' => config('aipolicytracker.data_schema_version'),
                'keywords' => ['AI regulation', 'AI policy', 'EU AI Act', 'AI governance', 'compliance'],
                'distribution' => [
                    ['@type' => 'DataDownload', 'encodingFormat' => 'application/json', 'contentUrl' => route('open-data.download')],
                    ['@type' => 'DataDownload', 'encodingFormat' => 'application/json', 'contentUrl' => url('/api/v1/policies')],
                ],
                // The Zenodo concept DOI, only once one has been minted (DATASET_DOI).
                ...DatasetCitation::jsonLdIdentifier(),
            ]);

        return view('site.pages.open-data', ['seo' => $seo, 'stats' => $stats, 'lastUpdated' => $lastUpdated, 'taxonomies' => TaxonomyTerm::TAXONOMIES, 'schemaFiles' => array_map('basename', glob(base_path('data/schema/*.json')) ?: []), 'releases' => $this->releases()]);
    }

    public function openDataDownload(OpenDataExporter $exporter): JsonResponse
    {
        $bundle = ContentCache::remember('open-data.bundle', 900, fn () => $exporter->bundle());

        return response()->json($bundle, 200, ['Cache-Control' => 'public, max-age=900', 'Content-Disposition' => 'inline; filename="aipolicytracker-latest.json"'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    public function methodology(IndependentChecks $independentChecks): View
    {
        $seo = Seo::make(
            'Methodology: how AIPolicyTracker verifies AI policy data',
            'Editorial and source-verification principles, the data model and taxonomy, review levels, policy status definitions, how changes are detected and reviewed, known limitations and AI-assistance disclosure.',
            route('methodology')
        )->withBreadcrumbs([['Home', route('home')], ['Methodology', route('methodology')]]);

        return view('site.pages.methodology', ['seo' => $seo, 'statuses' => PolicyStatus::cases(), 'reviewStatuses' => ReviewStatus::cases(), 'tiers' => SourceDocument::TIERS, 'taxonomies' => TaxonomyTerm::TAXONOMIES, 'checks' => $independentChecks->summary()]);
    }

    /** Per-browser reading list; the list itself lives in localStorage and is rendered client-side. */
    public function saved(): View
    {
        $seo = Seo::make(
            'Saved records',
            'Policies, jurisdictions and obligations you saved for later. Stored only in this browser; nothing is sent to the server.',
            route('saved'),
            false
        )->noindex()->withBreadcrumbs([['Home', route('home')], ['Saved', route('saved')]]);

        return view('site.pages.saved', ['seo' => $seo]);
    }

    public function about(): View
    {
        $faq = [
            ['question' => 'What is AIPolicyTracker?', 'answer' => 'An open, source-backed reference for AI laws, strategies, guidance and their obligations across jurisdictions, with a bias towards markets that global trackers cover thinly. Every record links to its official source and shows its verification state.'],
            ['question' => 'Is the data free to reuse?', 'answer' => 'Yes. The policy dataset is published under CC BY 4.0 and the code under Apache-2.0. Third-party datasets shown on the site (MIT AI Risk Repository, AI Incident Database) keep their own licences, which are stated on each page.'],
            ['question' => 'Is this legal advice?', 'answer' => 'No. The site is informational. Confirm every date and obligation in the linked official source and consult qualified counsel before acting.'],
            ['question' => 'How are records verified?', 'answer' => 'Records are created from official sources with a source URL and access date, then reviewed by a human who opens the source, confirms the fields and sets the verification date. Unverified records are labelled as source-linked rather than verified.'],
            ['question' => 'Who maintains it?', 'answer' => 'AIPolicyTracker is founded and maintained by Bhaskar Bhatt, with a named reviewer behind every verified record and contributions welcome through GitHub.'],
        ];
        $seo = Seo::make(
            'About AIPolicyTracker: open, source-backed AI policy intelligence',
            'Why AIPolicyTracker exists, how it is built and verified, how to use it, the datasets and research it builds on, and who maintains it.',
            route('about')
        )->withBreadcrumbs([['Home', route('home')], ['About', route('about')]])
            ->withPageType('AboutPage', ['name' => 'About AIPolicyTracker', 'mainEntity' => ['@id' => url('/').'#organization']])
            ->withJsonLd(['@type' => 'FAQPage', 'mainEntity' => array_map(fn ($f) => ['@type' => 'Question', 'name' => $f['question'], 'acceptedAnswer' => ['@type' => 'Answer', 'text' => $f['answer']]], $faq)]);

        return view('site.pages.about', ['seo' => $seo, 'maintainers' => config('aipolicytracker.maintainers'), 'contacts' => config('aipolicytracker.contact_emails'), 'organization' => config('aipolicytracker.organization'), 'references' => config('aipolicytracker.references'), 'faq' => $faq]);
    }

    /**
     * How the project is paid for and the rules that keep money away from the
     * records. Linked from Organization.ownershipFundingInfo, so it must stay a
     * statement of fact: funders come from config/funding.php and nowhere else.
     */
    public function funding(BillingConfig $billing): View
    {
        $seo = Seo::make(
            'Funding and independence: how AIPolicyTracker is paid for',
            'Who funds AIPolicyTracker, what each funder pays for, and the rules that keep funders, subscribers and related products out of what the records say.',
            route('funding')
        )->withBreadcrumbs([['Home', route('home')], ['About', route('about')], ['Funding and independence', route('funding')]])
            ->withPageType('AboutPage', ['name' => 'Funding and independence', 'mainEntity' => ['@id' => url('/').'#organization']]);

        return view('site.pages.funding', [
            'seo' => $seo,
            'funders' => (array) config('funding.funders'),
            'threshold' => (int) config('funding.disclosure_threshold'),
            'sponsorUrl' => config('funding.sponsor_url'),
            'selling' => $billing->enabled(),
            'certifyiUrl' => config('aipolicytracker.certifyi_url'),
        ]);
    }

    /**
     * Who maintains, researches for and advises the project. The list lives in
     * config/team.php; each person is also published as schema.org Person so a
     * search engine or assistant can tell the maintainer from a contributor.
     */
    public function team(): View
    {
        $team = config('team');
        $organization = config('aipolicytracker.organization');
        $seo = Seo::make(
            'People behind AIPolicyTracker: maintainer, research contributors and advisors',
            'Who maintains AIPolicyTracker, the independent researchers who contribute to it, and how affiliations are listed: for identification, never as endorsement.',
            route('team')
        )->withBreadcrumbs([['Home', route('home')], ['About', route('about')], ['People', route('team')]])
            ->withPageType('AboutPage', ['name' => 'People behind AIPolicyTracker', 'mainEntity' => ['@id' => url('/').'#organization']])
            // The card names who is listed, so its URL must move when the list does.
            ->withCard('page', 'team', hash('crc32b', json_encode($team)));

        foreach (['core', 'contributors', 'advisors'] as $group) {
            foreach ($team[$group] as $person) {
                $id = ! empty($person['reviewer']) ? route('reviewers.show', $person['reviewer']).'#person' : route('team').'#'.$person['slug'];
                $seo->withJsonLd(array_filter([
                    '@type' => 'Person',
                    '@id' => $id,
                    'name' => $person['name'],
                    'url' => route('team').'#'.$person['slug'],
                    'jobTitle' => $person['role'] ?? ('Advisor, '.($person['specialism'] ?? '')),
                    'description' => $person['bio'] ?? $person['scope'] ?? null,
                    'image' => ! empty($person['photo']) ? asset('images/team/'.$person['photo']) : null,
                    'sameAs' => array_values(array_map(fn ($l) => $l['url'], $person['links'] ?? [])) ?: null,
                    'memberOf' => $group === 'core' ? ['@id' => url('/').'#organization'] : null,
                ]));
            }
        }

        return view('site.pages.team', compact('seo', 'team', 'organization'));
    }

    private function releases(): array
    {
        return [
            ['version' => config('aipolicytracker.data_schema_version'), 'date' => '2026-09-10', 'notes' => 'Initial structured dataset: 10 jurisdictions, 18 policy instruments, 57 obligations, dated change log. All records pending human verification.'],
        ];
    }
}
