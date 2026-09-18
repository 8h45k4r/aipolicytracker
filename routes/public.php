<?php

use App\Http\Controllers\Site\ApplicabilityController;
use App\Http\Controllers\Site\ApplicabilityProfileController;
use App\Http\Controllers\Site\BillingController;
use App\Http\Controllers\Site\BillingWebhookController;
use App\Http\Controllers\Site\ChangeController;
use App\Http\Controllers\Site\CompareController;
use App\Http\Controllers\Site\ContributeController;
use App\Http\Controllers\Site\CronController;
use App\Http\Controllers\Site\FollowController;
use App\Http\Controllers\Site\FreeToolController;
use App\Http\Controllers\Site\HomeController;
use App\Http\Controllers\Site\JurisdictionController;
use App\Http\Controllers\Site\LandingController;
use App\Http\Controllers\Site\MachineReadableController;
use App\Http\Controllers\Site\ObligationController;
use App\Http\Controllers\Site\PageController;
use App\Http\Controllers\Site\PolicyController;
use App\Http\Controllers\Site\RiskBrowseController;
use App\Http\Controllers\Site\RiskController;
use App\Http\Controllers\Site\SitemapController;
use App\Http\Controllers\Site\SubscribeController;
use Illuminate\Support\Facades\Route;

// Public, server-rendered policy-intelligence site.
Route::get('/', [HomeController::class, 'index'])->name('home');

Route::get('/policies', [PolicyController::class, 'index'])->name('policies.index');
Route::get('/policies/{policy}.json', [PolicyController::class, 'json'])->name('policies.json');
Route::get('/policies/{policy}.md', [\App\Http\Controllers\Site\AgentSurfaceController::class, 'policy'])->where('policy', '[a-z0-9-]+')->name('policies.context');
Route::get('/policies/{policy}', [PolicyController::class, 'show'])->name('policies.show');

Route::get('/jurisdictions', [JurisdictionController::class, 'index'])->name('jurisdictions.index');
Route::get('/jurisdictions/{jurisdiction}.md', [\App\Http\Controllers\Site\AgentSurfaceController::class, 'jurisdiction'])->where('jurisdiction', '[a-z0-9-]+')->name('jurisdictions.context');
Route::get('/jurisdictions/{jurisdiction}', [JurisdictionController::class, 'show'])->name('jurisdictions.show');

Route::get('/obligations', [ObligationController::class, 'index'])->name('obligations.index');
Route::get('/obligations/{obligation}.md', [\App\Http\Controllers\Site\AgentSurfaceController::class, 'obligation'])->where('obligation', '[a-z0-9-]+')->name('obligations.context');
Route::get('/obligations/{obligation}', [ObligationController::class, 'show'])->name('obligations.show');

Route::get('/compare', [CompareController::class, 'index'])->name('compare.index');
Route::get('/compare/{comparison}', [CompareController::class, 'show'])->name('compare.show');

Route::get('/changes', [ChangeController::class, 'index'])->name('changes.index');
Route::get('/changes/feed', [ChangeController::class, 'feed'])->name('changes.feed');
Route::get('/changes/{change}.md', [\App\Http\Controllers\Site\AgentSurfaceController::class, 'change'])->where('change', '[a-z0-9-]+')->name('changes.context');
Route::get('/calendar', [\App\Http\Controllers\Site\CalendarController::class, 'show'])->name('calendar');
Route::get('/calendar/ai-policy-deadlines.ics', [\App\Http\Controllers\Site\CalendarController::class, 'feed'])->name('calendar.feed');
Route::get('/calendar/{jurisdiction}.ics', [\App\Http\Controllers\Site\CalendarController::class, 'feed'])->where('jurisdiction', '[a-z0-9-]+')->name('calendar.feed.jurisdiction');
Route::get('/changes/{year}', [ChangeController::class, 'year'])->where('year', '20[0-9]{2}')->name('changes.year');

Route::get('/ai-risk', [RiskController::class, 'index'])->name('risk.index');
Route::get('/ai-risk/incidents', [RiskController::class, 'incidents'])->name('risk.incidents');
Route::get('/ai-risk/incidents/browse', [RiskBrowseController::class, 'incidentsBrowse'])->name('risk.incidents.browse');
Route::get('/ai-risk/incidents/export.{format}', [RiskBrowseController::class, 'incidentsExport'])->where('format', 'csv|json')->name('risk.incidents.export');
Route::get('/ai-risk/incidents/{incident}', [RiskBrowseController::class, 'incidentShow'])->where('incident', '[0-9]+')->name('risk.incidents.show');
Route::get('/ai-risk/risks', [RiskBrowseController::class, 'risks'])->name('risk.risks');
Route::get('/ai-risk/risks/export.{format}', [RiskBrowseController::class, 'risksExport'])->where('format', 'csv|json')->name('risk.risks.export');
Route::get('/ai-risk/frameworks', [RiskBrowseController::class, 'frameworks'])->name('risk.frameworks');
Route::get('/ai-risk/risks/{ev}', [RiskBrowseController::class, 'riskShow'])->where('ev', '(?!export\\.)[A-Za-z0-9_.-]+')->name('risk.risks.show');
Route::get('/ai-risk/{domain}', [RiskController::class, 'domain'])->where('domain', '[1-7]')->name('risk.domain');
Route::get('/ai-risk/{domain}/{sub}', [RiskController::class, 'subdomain'])->where(['domain' => '[1-7]', 'sub' => '[1-7]\\.[0-9]{1,2}'])->name('risk.subdomain');

Route::get('/tools/applicability-check', [ApplicabilityController::class, 'show'])->name('tools.applicability');

Route::get('/open-data', [PageController::class, 'openData'])->name('open-data');
Route::get('/open-data/aipolicytracker-latest.json', [PageController::class, 'openDataDownload'])->name('open-data.download');
Route::get('/open-data/health.json', [\App\Http\Controllers\Site\AgentSurfaceController::class, 'health'])->name('open-data.health');
Route::get('/open-data/{dataset}.csv', [\App\Http\Controllers\Site\AgentSurfaceController::class, 'exportCsv'])->where('dataset', '[a-z]+')->name('open-data.csv');
Route::get('/open-data/{dataset}.ndjson', [\App\Http\Controllers\Site\AgentSurfaceController::class, 'exportNdjson'])->where('dataset', '[a-z]+')->name('open-data.ndjson');
Route::get('/schema/{name}.schema.json', [\App\Http\Controllers\Site\AgentSurfaceController::class, 'schema'])->where('name', '[a-z]+')->name('schema.show');
Route::get('/methodology', [PageController::class, 'methodology'])->name('methodology');
Route::get('/verification', [\App\Http\Controllers\Site\VerificationController::class, 'show'])->name('verification');
Route::get('/coverage', [\App\Http\Controllers\Site\CoverageController::class, 'show'])->name('coverage');
Route::get('/gaps', [\App\Http\Controllers\Site\CoverageController::class, 'gaps'])->name('gaps');
Route::get('/corrections', [\App\Http\Controllers\Site\CorrectionsController::class, 'show'])->name('corrections');
Route::get('/reviewers', [\App\Http\Controllers\Site\ReviewersController::class, 'show'])->name('reviewers');
Route::get('/about', [PageController::class, 'about'])->name('about');
// The sign-up form asks readers to accept these and the download gate records the
// acceptance, so they have to be real pages rather than a configurable link that
// fell back to /about when unset.
Route::get('/privacy', [\App\Http\Controllers\Site\LegalController::class, 'privacy'])->name('privacy');
Route::get('/terms', [\App\Http\Controllers\Site\LegalController::class, 'terms'])->name('terms');
Route::get('/contribute', [ContributeController::class, 'show'])->name('contribute');
Route::post('/contribute', [ContributeController::class, 'store'])->middleware('throttle:10,1')->name('contribute.store');

// Email digest subscriptions (double opt-in) and the scheduled-send trigger.
Route::get('/subscribe', [SubscribeController::class, 'show'])->name('subscribe.show');
Route::get('/saved', [PageController::class, 'saved'])->name('saved');
Route::post('/subscribe', [SubscribeController::class, 'store'])->middleware('throttle:10,1')->name('subscribe.store');
Route::get('/subscribe/confirm/{token}', [SubscribeController::class, 'confirm'])->where('token', '[A-Za-z0-9]{48}')->name('subscribe.confirm');
Route::get('/subscribe/unsubscribe/{token}', [SubscribeController::class, 'unsubscribe'])->where('token', '[A-Za-z0-9]{48}')->name('subscribe.unsubscribe');
Route::post('/subscribe/unsubscribe/{token}', [SubscribeController::class, 'unsubscribePost'])->where('token', '[A-Za-z0-9]{48}')->withoutMiddleware([\Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class])->name('subscribe.unsubscribe.post');
Route::post('/cron/digest', [CronController::class, 'digest'])->middleware('throttle:5,1')->withoutMiddleware([\Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class])->name('cron.digest');
Route::post('/cron/external-sync', [CronController::class, 'externalSync'])->middleware('throttle:5,1')->withoutMiddleware([\Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class])->name('cron.external-sync');

// Billing: pricing is public; checkout and portal need a verified account; the webhook is signature-authenticated.
Route::get('/pricing', [BillingController::class, 'pricing'])->name('pricing');
Route::middleware(['auth', 'verified'])->group(function () {
    Route::post('/billing/checkout/{plan}', [BillingController::class, 'checkout'])->where('plan', '[a-z0-9_]+')->middleware('throttle:10,1')->name('billing.checkout');
    Route::get('/billing/return/{checkout}', [BillingController::class, 'returned'])->where('checkout', '[0-9]+')->name('billing.return');
    Route::post('/billing/portal', [BillingController::class, 'portal'])->middleware('throttle:10,1')->name('billing.portal');
});
// Follows and daily alerts (Pro): the toggle needs the saved.server entitlement; the list page needs an account.
Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/following', [FollowController::class, 'index'])->name('following.index');
    Route::post('/profiles', [ApplicabilityProfileController::class, 'store'])->middleware(['subscribed:saved.server', 'throttle:30,1'])->name('profiles.store');
    Route::delete('/profiles/{profile}', [ApplicabilityProfileController::class, 'destroy'])->whereNumber('profile')->name('profiles.destroy');
    Route::post('/follow/{type}/{slug}', [FollowController::class, 'toggle'])->where(['type' => '[a-z]+', 'slug' => '[A-Za-z0-9._-]{1,160}'])->middleware(['subscribed:saved.server', 'throttle:60,1'])->name('follow.toggle');
});
Route::post('/cron/alerts', [CronController::class, 'alerts'])->middleware('throttle:5,1')->withoutMiddleware([\Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class])->name('cron.alerts');
Route::post('/webhooks/dodo', BillingWebhookController::class)->middleware('throttle:120,1')->withoutMiddleware([\Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class])->name('billing.webhook');

// Editorial landing pages and guides generated from verified data plus editorial content.
Route::get('/guides', [LandingController::class, 'guides'])->name('guides.index');
// Free tools: public preview, sign-in gate on Download, signed file delivery for the owner.
Route::get('/guides/tools/{slug}', [FreeToolController::class, 'show'])->where('slug', '[a-z0-9-]+')->name('tools.show');
Route::get('/guides/tools/{slug}/download', [FreeToolController::class, 'gate'])->where('slug', '[a-z0-9-]+')->name('tools.gate');
Route::middleware('auth')->group(function () {
    Route::post('/guides/tools/{slug}/download', [FreeToolController::class, 'download'])->where('slug', '[a-z0-9-]+')->middleware('throttle:20,1')->name('tools.download');
    Route::get('/guides/tools/{slug}/ready/{download}', [FreeToolController::class, 'ready'])->where('slug', '[a-z0-9-]+')->name('tools.ready');
    Route::get('/guides/tools/{slug}/file/{download}/{file}', [FreeToolController::class, 'file'])->where(['slug' => '[a-z0-9-]+', 'file' => '[a-z0-9.-]+'])->middleware('signed')->name('tools.file');
});
Route::get('/guides/{slug}', [LandingController::class, 'guide'])->name('guides.show');
Route::get('/{landing}', [LandingController::class, 'landing'])
    ->where('landing', 'eu-ai-act|ai-regulation-india|ai-policy-nepal|ai-governance-singapore|ai-regulation-australia|ai-regulation-uk|ai-regulation-usa|ai-governance-uae|ai-regulation-south-asia')
    ->name('landing');

// Machine-readable assets.
// The image a platform shows when a page is shared, drawn from the record. The
// `v` query parameter is a version token from the record itself: it is what makes
// a platform fetch a new card after a retitle, and it is ignored when rendering.
Route::get('/og/{kind}/{slug}.png', \App\Http\Controllers\Site\SocialCardController::class)
    ->where(['kind' => 'policy|jurisdiction|obligation|site', 'slug' => '[a-z0-9-]+'])
    ->name('social.card');

Route::get('/sitemap.xml', [SitemapController::class, 'index'])->name('sitemap.index');
Route::get('/sitemap-{section}.xml', [SitemapController::class, 'section'])->where('section', 'static|jurisdictions|policies|obligations|changes|resources|incidents|risks')->name('sitemap.section');
Route::get('/llms.txt', [MachineReadableController::class, 'llms'])->name('llms');
Route::get('/llms-full.txt', [MachineReadableController::class, 'llmsFull'])->name('llms.full');
Route::get('/openapi.json', [MachineReadableController::class, 'openapi'])->name('openapi');

// Legacy URL redirects.
Route::redirect('/about-ai-policy', '/about', 301);
Route::redirect('/dashboard', '/', 301);
// Pre-2026 record URLs still crawled by search engines (Search Console lists them as 5xx/404).
Route::get('/news/{any}', fn () => redirect('/changes', 301))->where('any', '.*');
Route::get('/aipolicytracker/{any?}', fn () => redirect('/policies', 301))->where('any', '.*');
