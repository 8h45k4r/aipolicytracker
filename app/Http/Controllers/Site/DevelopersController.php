<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Providers\AppServiceProvider;
use App\Services\Billing\BillingConfig;
use App\Services\PolicyData\PolicyCatalog;
use App\Support\Seo;
use Illuminate\View\View;

/**
 * The two pages for people building on the data: the read-only API and bulk exports
 * (/developers) and the MCP server (/mcp). Both describe what exists today, from the
 * routes and the agent server, and say plainly what does not exist yet.
 */
class DevelopersController extends Controller
{
    /** The read-only endpoints, as registered in routes/api.php. */
    public const ENDPOINTS = [
        ['/api/v1/jurisdictions', 'Jurisdictions, with their status summary and regulators'],
        ['/api/v1/policies', 'Policy instruments: laws, regulations, strategies, guidance, with sources and review status'],
        ['/api/v1/obligations', 'Duties broken out from the instruments, with the provision each comes from'],
        ['/api/v1/deadlines', 'Application dates and deadlines, including those that moved'],
        ['/api/v1/changes', 'The dated change log'],
        ['/api/v1/controls', 'Controls that meet the duties, with the evidence they produce'],
        ['/api/v1/frameworks', 'Framework mappings (ISO/IEC 42001, NIST AI RMF and others)'],
        ['/api/v1/enforcement', 'Enforcement actions'],
        ['/api/v1/implementation-measures', 'What an instrument still depends on: guidelines, codes, standards'],
        ['/api/v1/incidents', 'AI incidents with the recorded laws that address the harm'],
        ['/api/v1/templates', 'The templates library, with download links'],
        ['/api/v1/taxonomies', 'The controlled vocabularies used across records'],
    ];

    /** The tools the MCP server lists (agent/server.mjs). */
    public const MCP_TOOLS = [
        ['corpus_health', 'How far the corpus can be trusted right now: records published, overdue for re-check, verified by a named reviewer'],
        ['search_policies', 'Instruments by keyword, jurisdiction or status'],
        ['resolve_record_id', 'A permanent id (AIPT-POL-0001) to the record and its current address'],
        ['get_policy', 'One instrument as a Markdown context file, provenance included'],
        ['get_jurisdiction', 'One jurisdiction profile as a Markdown context file'],
        ['list_obligations', 'Duties recorded against instruments, with the provision each comes from'],
        ['recent_changes', 'The dated change log, with a significance score'],
        ['search_incidents', 'AI incidents, with the laws on record that address the harm'],
        ['build_obligations_register', 'The applicability check as an obligations register, with XLSX, CSV and PDF links'],
        ['get_applicable_deadlines', 'Which dates apply to one organisation, and why'],
        ['list_transition_measures', 'AI economic transition measures'],
        ['get_displacement_index', 'The displacement policy index per jurisdiction'],
        ['list_enforcement_actions', 'Enforcement actions with regulator, respondent, legal basis and outcome'],
        ['list_implementation_measures', 'What an instrument still depends on, with due and actual dates'],
        ['list_templates', 'The templates library'],
        ['open_gaps', 'What the corpus is missing'],
    ];

    public function developers(PolicyCatalog $catalog, BillingConfig $billing): View
    {
        $stats = $catalog->stats();
        $publicRate = AppServiceProvider::PUBLIC_API_REQUESTS_PER_MINUTE;
        // The paid rate is stated only while it can be bought (see the public-api limiter).
        $paidRate = $billing->enabled() ? (int) (config('billing.plans.pro_monthly.entitlements')['api.rate_per_minute'] ?? 0) : 0;
        $paidLine = $paidRate > $publicRate ? " An account token on the Builder plan raises it to {$paidRate} a minute." : '';
        $licence = config('aipolicytracker.data_license');
        $seo = Seo::make(
            'AI regulation API and dataset for developers',
            "A free, read-only AI regulation API and bulk dataset: {$stats['jurisdictions']} jurisdictions, {$stats['policies']} instruments and {$stats['obligations']} obligations with official sources, under {$licence}. No key needed.",
            route('developers')
        )->withBreadcrumbs([['Home', route('home')], ['Developers', route('developers')]])
            ->withPageType('WebPage')
            ->withFaq([
                ['question' => 'Is there an API for AI regulations?', 'answer' => "Yes. AIPolicyTracker serves a read-only JSON API at /api/v1 covering jurisdictions, policy instruments, obligations, deadlines, changes, controls and enforcement actions. It needs no key, allows {$publicRate} requests a minute,{$paidLine} and is described by an OpenAPI document at /openapi.json."],
                ['question' => 'Can I use the AI regulation data in a commercial product?', 'answer' => "Yes. The data is published under {$licence}, which allows commercial use with attribution. Paid plans add service terms such as a freshness commitment and delta feeds; they do not change what the licence already allows."],
                ['question' => 'Is there a bulk download?', 'answer' => 'Yes: one JSON bundle of the whole dataset, and CSV and NDJSON exports per record type, each row carrying its official source, review status and the date it was last confirmed.'],
            ]);

        return view('site.pages.developers', ['seo' => $seo, 'stats' => $stats, 'endpoints' => self::ENDPOINTS, 'publicRate' => $publicRate, 'paidRate' => $paidRate > $publicRate ? $paidRate : 0]);
    }

    public function mcp(): View
    {
        $seo = Seo::make(
            'AI regulation MCP server for assistants',
            'An open-source Model Context Protocol server that lets an AI assistant look up AI laws, obligations, deadlines and enforcement from AIPolicyTracker, with sources and review status in every result.',
            route('mcp')
        )->withBreadcrumbs([['Home', route('home')], ['Developers', route('developers')], ['MCP server', route('mcp')]])
            ->withPageType('WebPage')
            ->withJsonLd([
                '@type' => 'SoftwareSourceCode',
                'name' => 'AIPolicyTracker MCP server',
                'description' => 'A read-only Model Context Protocol server (JSON-RPC 2.0 over stdio) for AI policy records.',
                'codeRepository' => config('aipolicytracker.github_url'),
                'programmingLanguage' => 'JavaScript',
                'runtimePlatform' => 'Node.js',
                'license' => 'https://www.apache.org/licenses/LICENSE-2.0',
                'url' => route('mcp'),
            ])
            ->withFaq([
                ['question' => 'Is there an MCP server for AI laws?', 'answer' => 'Yes. AIPolicyTracker publishes an open-source MCP server with '.count(self::MCP_TOOLS).' read-only tools for AI policy records: search instruments, read a jurisdiction, list obligations and deadlines, enforcement actions and changes. It runs on your machine over stdio and calls the public site.'],
                ['question' => 'Is there a hosted MCP endpoint?', 'answer' => 'Not yet. Today the server runs locally with Node.js and needs no account or key. A hosted endpoint with higher limits is planned for the paid plans.'],
                ['question' => 'Does the MCP server show whether a record is verified?', 'answer' => 'Yes. Its corpus_health tool reports how many records were confirmed by a named reviewer, and every tool result carries each record\'s official source and review status, so an assistant does not present a summary as settled law.'],
            ]);

        return view('site.pages.mcp', ['seo' => $seo, 'tools' => self::MCP_TOOLS]);
    }
}
