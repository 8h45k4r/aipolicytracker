<?php

use App\Http\Controllers\Site\AgentSurfaceController;
use App\Http\Controllers\Site\AlertChannelController;
use App\Http\Controllers\Site\ApplicabilityController;
use App\Http\Controllers\Site\ApplicabilityProfileController;
use App\Http\Controllers\Site\AudienceController;
use App\Http\Controllers\Site\BillingWebhookController;
use App\Http\Controllers\Site\CalendarController;
use App\Http\Controllers\Site\ChangeController;
use App\Http\Controllers\Site\CompareController;
use App\Http\Controllers\Site\ContributeController;
use App\Http\Controllers\Site\ControlController;
use App\Http\Controllers\Site\CorrectionsController;
use App\Http\Controllers\Site\CoverageController;
use App\Http\Controllers\Site\CronController;
use App\Http\Controllers\Site\DeadlineEngineController;
use App\Http\Controllers\Site\FollowController;
use App\Http\Controllers\Site\FrameworkController;
use App\Http\Controllers\Site\FreeToolController;
use App\Http\Controllers\Site\HomeController;
use App\Http\Controllers\Site\HubController;
use App\Http\Controllers\Site\JurisdictionController;
use App\Http\Controllers\Site\LandingController;
use App\Http\Controllers\Site\LegalController;
use App\Http\Controllers\Site\MachineReadableController;
use App\Http\Controllers\Site\NewsletterController;
use App\Http\Controllers\Site\ObligationController;
use App\Http\Controllers\Site\PageController;
use App\Http\Controllers\Site\PolicyController;
use App\Http\Controllers\Site\RegisterExportController;
use App\Http\Controllers\Site\ReviewersController;
use App\Http\Controllers\Site\RiskBrowseController;
use App\Http\Controllers\Site\RiskController;
use App\Http\Controllers\Site\SitemapController;
use App\Http\Controllers\Site\SocialCardController;
use App\Http\Controllers\Site\SubscribeController;
use App\Http\Controllers\Site\TemplateController;
use App\Http\Controllers\Site\TransitionController;
use App\Http\Controllers\Site\UpdatesController;
use App\Http\Controllers\Site\VerificationController;
use App\Services\Hubs\HubCatalog;
use App\Support\RiskTaxonomy;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Support\Facades\Route;

// Public, server-rendered policy-intelligence site.
Route::get('/', [HomeController::class, 'index'])->name('home');

Route::get('/policies', [PolicyController::class, 'index'])->name('policies.index');
Route::get('/policies/{policy}.json', [PolicyController::class, 'json'])->name('policies.json');
Route::get('/policies/{policy}.md', [AgentSurfaceController::class, 'policy'])->where('policy', '[a-z0-9-]+')->name('policies.context');
Route::get('/policies/{policy}', [PolicyController::class, 'show'])->name('policies.show');

Route::get('/jurisdictions', [JurisdictionController::class, 'index'])->name('jurisdictions.index');
Route::get('/jurisdictions/{jurisdiction}.md', [AgentSurfaceController::class, 'jurisdiction'])->where('jurisdiction', '[a-z0-9-]+')->name('jurisdictions.context');
Route::get('/jurisdictions/{jurisdiction}', [JurisdictionController::class, 'show'])->name('jurisdictions.show');

Route::get('/obligations', [ObligationController::class, 'index'])->name('obligations.index');
Route::get('/obligations/{obligation}.md', [AgentSurfaceController::class, 'obligation'])->where('obligation', '[a-z0-9-]+')->name('obligations.context');
Route::get('/obligations/{obligation}', [ObligationController::class, 'show'])->name('obligations.show');

// Controls: what an organisation operates to meet the duties above. One control
// serves many duties in many jurisdictions, which is the reuse a compliance lead
// is looking for.
Route::get('/controls', [ControlController::class, 'index'])->name('controls.index');
Route::get('/controls/{control}.md', [AgentSurfaceController::class, 'control'])->where('control', '[a-z0-9-]+')->name('controls.context');
Route::get('/controls/{control}', [ControlController::class, 'show'])->where('control', '[a-z0-9-]+')->name('controls.show');

// The corpus cut by role, sector and use case, generated from the taxonomy terms
// every obligation carries.
Route::get('/for', [AudienceController::class, 'index'])->name('audiences.index');
Route::get('/for/{audience}', [AudienceController::class, 'show'])->where('audience', '[a-z0-9-]+')->name('audiences.show');

Route::get('/compare', [CompareController::class, 'index'])->name('compare.index');
Route::get('/compare/{comparison}', [CompareController::class, 'show'])->name('compare.show');

// Crosswalks between legal duties and the standards organisations are audited against.
Route::get('/frameworks', [FrameworkController::class, 'index'])->name('frameworks.index');
Route::get('/frameworks/compare', [FrameworkController::class, 'compare'])->name('frameworks.compare');
Route::get('/frameworks/{framework}', [FrameworkController::class, 'show'])->where('framework', '[a-z0-9-]+')->name('frameworks.show');
Route::get('/frameworks/{framework}/{jurisdiction}', [FrameworkController::class, 'crosswalk'])->where(['framework' => '[a-z0-9-]+', 'jurisdiction' => '[a-z0-9-]+'])->name('frameworks.crosswalk');

// The updates hub: the change log arranged the way people search for it.
// Month and day archives are dates, so a jurisdiction slug (which never starts
// with a digit) cannot collide with them.
Route::get('/updates', [UpdatesController::class, 'index'])->name('updates.index');
Route::get('/updates/{month}', [UpdatesController::class, 'month'])->where('month', '20[0-9]{2}-(0[1-9]|1[0-2])')->name('updates.month');
Route::get('/updates/{day}', [UpdatesController::class, 'day'])->where('day', '20[0-9]{2}-(0[1-9]|1[0-2])-(0[1-9]|[12][0-9]|3[01])')->name('updates.day');
Route::get('/updates/{jurisdiction}/feed', [UpdatesController::class, 'jurisdictionFeed'])->where('jurisdiction', '[a-z][a-z0-9-]*')->name('updates.jurisdiction.feed');
Route::get('/updates/{jurisdiction}', [UpdatesController::class, 'jurisdiction'])->where('jurisdiction', '[a-z][a-z0-9-]*')->name('updates.jurisdiction');
Route::get('/newsletter', [NewsletterController::class, 'index'])->name('newsletter.index');
Route::get('/newsletter/{issue}', [NewsletterController::class, 'show'])->where('issue', '20[0-9]{2}-[0-9]{2}-[0-9]{2}')->name('newsletter.show');

Route::get('/changes', [ChangeController::class, 'index'])->name('changes.index');
Route::get('/changes/feed', [ChangeController::class, 'feed'])->name('changes.feed');
Route::get('/changes/{change}.md', [AgentSurfaceController::class, 'change'])->where('change', '[a-z0-9-]+')->name('changes.context');
Route::get('/calendar', [CalendarController::class, 'show'])->name('calendar');
// The deadline engine: five plain-form steps, a personal timeline from recorded dates, .ics and PDF of the same rows.
Route::get('/deadlines/which-date-applies', [DeadlineEngineController::class, 'show'])->name('deadlines.engine');
Route::get('/deadlines/which-date-applies.ics', [DeadlineEngineController::class, 'ics'])->name('deadlines.engine.ics');
Route::get('/deadlines/which-date-applies.pdf', [DeadlineEngineController::class, 'pdf'])->middleware('throttle:30,1')->name('deadlines.engine.pdf');
Route::get('/calendar/ai-policy-deadlines.ics', [CalendarController::class, 'feed'])->name('calendar.feed');
Route::get('/calendar/{jurisdiction}.ics', [CalendarController::class, 'feed'])->where('jurisdiction', '[a-z0-9-]+')->name('calendar.feed.jurisdiction');
Route::get('/changes/{year}', [ChangeController::class, 'year'])->where('year', '20[0-9]{2}')->name('changes.year');
// One page per change. A development that only existed as an anchor on a list
// could not be shared, cited or returned as an answer on its own.
Route::get('/changes/{change}', [ChangeController::class, 'show'])->where('change', '[a-z0-9][a-z0-9-]*')->name('changes.show');

// Full-table exports and generated corpora are throttled: each request reads a whole table.
Route::get('/ai-risk', [RiskController::class, 'index'])->name('risk.index');
Route::get('/ai-risk/incidents', [RiskController::class, 'incidents'])->name('risk.incidents');
Route::get('/ai-risk/incidents/browse', [RiskBrowseController::class, 'incidentsBrowse'])->name('risk.incidents.browse');
Route::get('/ai-risk/incidents/export.{format}', [RiskBrowseController::class, 'incidentsExport'])->where('format', 'csv|json')->middleware('throttle:30,1')->name('risk.incidents.export');
Route::get('/ai-risk/incidents/{incident}', [RiskBrowseController::class, 'incidentShow'])->where('incident', '[a-z0-9][a-z0-9-]*')->name('risk.incidents.show');
Route::get('/ai-risk/risks', [RiskBrowseController::class, 'risks'])->name('risk.risks');
Route::get('/ai-risk/risks/export.{format}', [RiskBrowseController::class, 'risksExport'])->where('format', 'csv|json')->middleware('throttle:30,1')->name('risk.risks.export');
Route::get('/ai-risk/frameworks', [RiskBrowseController::class, 'frameworks'])->name('risk.frameworks');
Route::get('/ai-risk/risks/{ev}', [RiskBrowseController::class, 'riskShow'])->where('ev', '(?!export\\.)[A-Za-z0-9_.-]+')->name('risk.risks.show');
Route::get('/ai-risk/{domain}', [RiskController::class, 'domain'])->where('domain', RiskTaxonomy::domainPattern())->name('risk.domain');
Route::get('/ai-risk/{domain}/{sub}', [RiskController::class, 'subdomain'])->where(['domain' => RiskTaxonomy::domainPattern(), 'sub' => RiskTaxonomy::subdomainPattern()])->name('risk.subdomain');

Route::get('/tools/applicability-check', [ApplicabilityController::class, 'show'])->name('tools.applicability');

Route::get('/open-data', [PageController::class, 'openData'])->name('open-data');
Route::get('/open-data/aipolicytracker-latest.json', [PageController::class, 'openDataDownload'])->middleware('throttle:30,1')->name('open-data.download');
Route::get('/open-data/health.json', [AgentSurfaceController::class, 'health'])->name('open-data.health');
Route::get('/open-data/{dataset}.csv', [AgentSurfaceController::class, 'exportCsv'])->where('dataset', '[a-z]+')->middleware('throttle:30,1')->name('open-data.csv');
Route::get('/open-data/{dataset}.ndjson', [AgentSurfaceController::class, 'exportNdjson'])->where('dataset', '[a-z]+')->middleware('throttle:30,1')->name('open-data.ndjson');
Route::get('/schema/{name}.schema.json', [AgentSurfaceController::class, 'schema'])->where('name', '[a-z-]+')->name('schema.show');
Route::get('/methodology', [PageController::class, 'methodology'])->name('methodology');
Route::get('/verification', [VerificationController::class, 'show'])->name('verification');
Route::get('/coverage', [CoverageController::class, 'show'])->name('coverage');
Route::get('/gaps', [CoverageController::class, 'gaps'])->name('gaps');
Route::get('/corrections', [CorrectionsController::class, 'show'])->name('corrections');
Route::get('/reviewers', [ReviewersController::class, 'show'])->name('reviewers');
Route::get('/about', [PageController::class, 'about'])->name('about');
// The sign-up form asks readers to accept these and the download gate records the
// acceptance, so they have to be real pages rather than a configurable link that
// fell back to /about when unset.
Route::get('/privacy', [LegalController::class, 'privacy'])->name('privacy');
Route::get('/terms', [LegalController::class, 'terms'])->name('terms');
Route::get('/contribute', [ContributeController::class, 'show'])->name('contribute');
Route::post('/contribute', [ContributeController::class, 'store'])->middleware('throttle:10,1')->name('contribute.store');

// Email digest subscriptions (double opt-in) and the scheduled-send trigger.
Route::get('/subscribe', [SubscribeController::class, 'show'])->name('subscribe.show');
Route::get('/saved', [PageController::class, 'saved'])->name('saved');
Route::post('/subscribe', [SubscribeController::class, 'store'])->middleware('throttle:10,1')->name('subscribe.store');
Route::get('/subscribe/confirm/{token}', [SubscribeController::class, 'confirm'])->where('token', '[A-Za-z0-9]{48}')->name('subscribe.confirm');
Route::post('/subscribe/confirm/{token}', [SubscribeController::class, 'confirmPost'])->where('token', '[A-Za-z0-9]{48}')->middleware('throttle:10,1')->name('subscribe.confirm.post');
Route::get('/subscribe/unsubscribe/{token}', [SubscribeController::class, 'unsubscribe'])->where('token', '[A-Za-z0-9]{48}')->name('subscribe.unsubscribe');
Route::post('/subscribe/unsubscribe/{token}', [SubscribeController::class, 'unsubscribePost'])->where('token', '[A-Za-z0-9]{48}')->withoutMiddleware([ValidateCsrfToken::class])->name('subscribe.unsubscribe.post');
Route::post('/cron/digest', [CronController::class, 'digest'])->middleware('throttle:5,1')->withoutMiddleware([ValidateCsrfToken::class])->name('cron.digest');
Route::post('/cron/external-sync', [CronController::class, 'externalSync'])->middleware('throttle:5,1')->withoutMiddleware([ValidateCsrfToken::class])->name('cron.external-sync');

// Selling is retired: there is no pricing page, checkout or portal. The
// webhook stays mounted and signature-authenticated so that events for any
// subscription created before this change are still recorded rather than
// silently dropped. See debt #41.
Route::middleware(['auth', 'verified'])->group(function () {});
// Follows and daily alerts (Pro): the toggle needs the saved.server entitlement; the list page needs an account.
Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/following', [FollowController::class, 'index'])->name('following.index');
    Route::post('/profiles', [ApplicabilityProfileController::class, 'store'])->middleware(['subscribed:saved.server', 'throttle:30,1'])->name('profiles.store');
    Route::delete('/profiles/{profile}', [ApplicabilityProfileController::class, 'destroy'])->whereNumber('profile')->name('profiles.destroy');
    Route::post('/alerts/channels', [AlertChannelController::class, 'store'])->middleware('throttle:20,1')->name('alerts.channels.store');
    Route::post('/alerts/channels/email-off', [AlertChannelController::class, 'emailOff'])->name('alerts.channels.email-off');
    Route::post('/alerts/channels/{channel}/toggle', [AlertChannelController::class, 'toggle'])->whereNumber('channel')->name('alerts.channels.toggle');
    Route::post('/alerts/channels/{channel}/test', [AlertChannelController::class, 'test'])->whereNumber('channel')->middleware('throttle:10,1')->name('alerts.channels.test');
    Route::delete('/alerts/channels/{channel}', [AlertChannelController::class, 'destroy'])->whereNumber('channel')->name('alerts.channels.destroy');
    Route::get('/account/export.json', [AlertChannelController::class, 'export'])->name('account.export');
    Route::post('/profile/api-token', [AlertChannelController::class, 'apiToken'])->middleware('throttle:10,1')->name('profile.api-token');
    Route::post('/follow/{type}/{slug}', [FollowController::class, 'toggle'])->where(['type' => '[a-z_]+', 'slug' => '[A-Za-z0-9._-]{1,160}'])->middleware(['subscribed:saved.server', 'throttle:60,1'])->name('follow.toggle');
});
// Private feed by token, and the unsubscribe link from every alert email (signed; no sign-in).
Route::get('/alerts/feed/{token}.rss', [AlertChannelController::class, 'feed'])->where('token', '[A-Za-z0-9]{48}')->name('alerts.feed');
Route::match(['get', 'post'], '/alerts/unsubscribe/{user}', [AlertChannelController::class, 'unsubscribe'])->whereNumber('user')->middleware('signed')->name('alerts.unsubscribe');
// The applicability check's obligations register as a file: the answers are the state.
Route::get('/tools/applicability-check/register.{format}', [RegisterExportController::class, 'export'])->where('format', 'xlsx|csv|json|pdf')->middleware('throttle:30,1')->name('tools.applicability.register');
Route::post('/cron/alerts', [CronController::class, 'alerts'])->middleware('throttle:5,1')->withoutMiddleware([ValidateCsrfToken::class])->name('cron.alerts');
Route::post('/webhooks/dodo', BillingWebhookController::class)->middleware('throttle:120,1')->withoutMiddleware([ValidateCsrfToken::class])->name('billing.webhook');

// The templates library: generated files, versioned, no account needed. The old free-tool
// addresses under /guides/tools redirect here (FreeToolController).
Route::get('/templates', [TemplateController::class, 'index'])->name('templates.index');
Route::get('/templates/feed', [TemplateController::class, 'feed'])->name('templates.feed');
Route::get('/templates/{slug}', [TemplateController::class, 'show'])->where('slug', '[a-z0-9-]+')->name('templates.show');
Route::get('/templates/{slug}/download', [TemplateController::class, 'download'])->where('slug', '[a-z0-9-]+')->middleware('throttle:60,1')->name('templates.download');

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
// Country and regional hubs: /ai-regulation-<country> is the jurisdiction page at its
// canonical address (the /jurisdictions/<slug> address redirects); /ai-regulation-<region>
// is computed over the region. Registered before the editorial landings, which share the prefix.
// The AI economic transition tracker: measures, indicators, the displacement policy index, and four
// themed landings served before the editorial landings, which share the root.
Route::get('/ai-economic-transition', [TransitionController::class, 'index'])->name('transition.index');
Route::get('/ai-economic-transition/methodology', [TransitionController::class, 'methodology'])->name('transition.methodology');
Route::get('/ai-economic-transition/measures/{measure}', [TransitionController::class, 'show'])->where('measure', '[a-z0-9-]+')->name('transition.show');
Route::get('/{theme}', [TransitionController::class, 'landing'])->where('theme', implode('|', array_keys(TransitionController::LANDINGS)))->name('transition.landing');
Route::get('/{hub}', [HubController::class, 'show'])->where('hub', HubCatalog::pattern())->name('hubs.show');
Route::get('/{landing}', [LandingController::class, 'landing'])
    ->where('landing', 'eu-ai-act|ai-regulation-india|ai-policy-nepal|ai-governance-singapore|ai-regulation-australia|ai-regulation-uk|ai-regulation-usa|ai-governance-uae|ai-regulation-south-asia')
    ->name('landing');

// Machine-readable assets.
// The image a platform shows when a page is shared, drawn from the record. The
// `v` query parameter is a version token from the record itself: it is what makes
// a platform fetch a new card after a retitle, and it is ignored when rendering.
Route::get('/og/{kind}/{slug}.png', SocialCardController::class)
    ->where(['kind' => 'policy|jurisdiction|obligation|site', 'slug' => '[a-z0-9-]{1,160}'])
    ->middleware('throttle:120,1')
    ->name('social.card');

Route::get('/sitemap.xml', [SitemapController::class, 'index'])->name('sitemap.index');
Route::get('/sitemap-{section}.xml', [SitemapController::class, 'section'])->where('section', 'static|jurisdictions|policies|obligations|controls|changes|updates|templates|resources|incidents|risks')->name('sitemap.section');
// Google News: entries first published in the last two days, in the news namespace.
Route::get('/sitemap-news.xml', [SitemapController::class, 'news'])->name('sitemap.news');
Route::get('/llms.txt', [MachineReadableController::class, 'llms'])->name('llms');
Route::get('/llms-full.txt', [MachineReadableController::class, 'llmsFull'])->middleware('throttle:30,1')->name('llms.full');
Route::get('/openapi.json', [MachineReadableController::class, 'openapi'])->name('openapi');
// RFC 9116: where a security researcher should write. Generated rather than a
// static file so the mandatory Expires field can never fall into the past.
Route::get('/.well-known/security.txt', [MachineReadableController::class, 'securityTxt'])->name('security.txt');
Route::redirect('/security.txt', '/.well-known/security.txt', 301);

// Legacy URL redirects.
Route::redirect('/about-ai-policy', '/about', 301);
Route::redirect('/dashboard', '/', 301);
// Pre-2026 record URLs still crawled by search engines (Search Console lists them as 5xx/404).
Route::get('/news/{any}', fn () => redirect('/changes', 301))->where('any', '.*');
Route::get('/aipolicytracker/{any?}', fn () => redirect('/policies', 301))->where('any', '.*');
