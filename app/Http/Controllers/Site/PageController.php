<?php

namespace App\Http\Controllers\Site;

use App\Enums\PolicyStatus;
use App\Enums\ReviewStatus;
use App\Http\Controllers\Controller;
use App\Models\SourceDocument;
use App\Models\TaxonomyTerm;
use App\Services\PolicyData\OpenDataExporter;
use App\Services\PolicyData\PolicyCatalog;
use App\Support\Seo;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\View\View;

class PageController extends Controller
{
    public function openData(PolicyCatalog $catalog): View
    {
        $stats = $catalog->stats();
        $lastUpdated = $stats['last_updated'] ? \Illuminate\Support\Carbon::parse($stats['last_updated']) : null;
        $seo = Seo::make(
            'Open AI policy dataset, schema and API',
            'Download the AIPolicyTracker dataset (CC BY 4.0): jurisdictions, policy instruments, obligations, deadlines and change events with official sources. JSON Schema, read-only API and citation guidance.',
            route('open-data')
        )->withBreadcrumbs([['Home', route('home')], ['Open data', route('open-data')]])
            ->withJsonLd([
                '@type' => 'Dataset',
                'name' => 'AIPolicyTracker open AI policy dataset',
                'description' => 'Structured, source-backed records of AI laws, regulations, standards, guidance, obligations, deadlines and change events across jurisdictions.',
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
            ]);

        return view('site.pages.open-data', ['seo' => $seo, 'stats' => $stats, 'lastUpdated' => $lastUpdated, 'taxonomies' => TaxonomyTerm::TAXONOMIES, 'schemaFiles' => array_map('basename', glob(base_path('data/schema/*.json')) ?: []), 'releases' => $this->releases()]);
    }

    public function openDataDownload(OpenDataExporter $exporter): JsonResponse
    {
        $bundle = Cache::remember('open-data.bundle', 900, fn () => $exporter->bundle());

        return response()->json($bundle, 200, ['Cache-Control' => 'public, max-age=900', 'Content-Disposition' => 'inline; filename="aipolicytracker-latest.json"'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    public function methodology(): View
    {
        $seo = Seo::make(
            'Methodology: how AIPolicyTracker verifies AI policy data',
            'Editorial and source-verification principles, the data model and taxonomy, review levels, policy status definitions, how changes are detected and reviewed, known limitations and AI-assistance disclosure.',
            route('methodology')
        )->withBreadcrumbs([['Home', route('home')], ['Methodology', route('methodology')]]);

        return view('site.pages.methodology', ['seo' => $seo, 'statuses' => PolicyStatus::cases(), 'reviewStatuses' => ReviewStatus::cases(), 'tiers' => SourceDocument::TIERS, 'taxonomies' => TaxonomyTerm::TAXONOMIES]);
    }

    public function about(): View
    {
        $faq = [
            ['question' => 'What is AIPolicyTracker?', 'answer' => 'An open, source-backed reference for AI laws, strategies, guidance and their obligations across jurisdictions, with a bias towards markets that global trackers cover thinly. Every record links to its official source and shows its verification state.'],
            ['question' => 'Is the data free to reuse?', 'answer' => 'Yes. The policy dataset is published under CC BY 4.0 and the code under Apache-2.0. Third-party datasets shown on the site (MIT AI Risk Repository, AI Incident Database) keep their own licences, which are stated on each page.'],
            ['question' => 'Is this legal advice?', 'answer' => 'No. The site is informational. Confirm every date and obligation in the linked official source and consult qualified counsel before acting.'],
            ['question' => 'How are records verified?', 'answer' => 'Records are created from official sources with a source URL and access date, then reviewed by a human who opens the source, confirms the fields and sets the verification date. Unverified records are labelled as source-linked rather than verified.'],
            ['question' => 'Who maintains it?', 'answer' => 'AIPolicyTracker is founded and maintained by Bhaskar Bhatt and built by Dignep Group Pvt. Ltd. Contributions are welcome through GitHub.'],
        ];
        $seo = Seo::make(
            'About AIPolicyTracker: open, source-backed AI policy intelligence',
            'Why AIPolicyTracker exists, how it is built and verified, how to use it, the datasets and research it builds on, and who maintains it.',
            route('about')
        )->withBreadcrumbs([['Home', route('home')], ['About', route('about')]])
            ->withJsonLd(['@type' => 'AboutPage', 'name' => 'About AIPolicyTracker', 'url' => route('about'), 'mainEntity' => ['@id' => url('/').'#organization']])
            ->withJsonLd(['@type' => 'FAQPage', 'mainEntity' => array_map(fn ($f) => ['@type' => 'Question', 'name' => $f['question'], 'acceptedAnswer' => ['@type' => 'Answer', 'text' => $f['answer']]], $faq)]);

        return view('site.pages.about', ['seo' => $seo, 'maintainers' => config('aipolicytracker.maintainers'), 'contacts' => config('aipolicytracker.contact_emails'), 'organization' => config('aipolicytracker.organization'), 'references' => config('aipolicytracker.references'), 'faq' => $faq]);
    }

    private function releases(): array
    {
        return [
            ['version' => config('aipolicytracker.data_schema_version'), 'date' => '2026-09-10', 'notes' => 'Initial structured dataset: 10 jurisdictions, 18 policy instruments, 57 obligations, dated change log. All records pending human verification.'],
        ];
    }
}
