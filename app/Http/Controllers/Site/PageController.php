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
        $seo = Seo::make(
            'About AIPolicyTracker',
            'AIPolicyTracker is an open, source-backed AI policy and regulatory intelligence platform. Mission, maintainers, reviewer invitation, contact and the relationship to Certifyi.',
            route('about')
        )->withBreadcrumbs([['Home', route('home')], ['About', route('about')]])
            ->withJsonLd(['@type' => 'AboutPage', 'name' => 'About AIPolicyTracker', 'url' => route('about'), 'mainEntity' => ['@id' => url('/').'#organization']]);

        return view('site.pages.about', ['seo' => $seo, 'maintainers' => config('aipolicytracker.maintainers'), 'contacts' => config('aipolicytracker.contact_emails')]);
    }

    private function releases(): array
    {
        return [
            ['version' => config('aipolicytracker.data_schema_version'), 'date' => '2026-09-10', 'notes' => 'Initial structured dataset: 10 jurisdictions, 18 policy instruments, 57 obligations, dated change log. All records pending human verification.'],
        ];
    }
}
