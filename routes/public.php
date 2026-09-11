<?php

use App\Http\Controllers\Site\ApplicabilityController;
use App\Http\Controllers\Site\ChangeController;
use App\Http\Controllers\Site\CompareController;
use App\Http\Controllers\Site\ContributeController;
use App\Http\Controllers\Site\CronController;
use App\Http\Controllers\Site\SubscribeController;
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
use Illuminate\Support\Facades\Route;

// Public, server-rendered policy-intelligence site.
Route::get('/', [HomeController::class, 'index'])->name('home');

Route::get('/policies', [PolicyController::class, 'index'])->name('policies.index');
Route::get('/policies/{policy}.json', [PolicyController::class, 'json'])->name('policies.json');
Route::get('/policies/{policy}', [PolicyController::class, 'show'])->name('policies.show');

Route::get('/jurisdictions', [JurisdictionController::class, 'index'])->name('jurisdictions.index');
Route::get('/jurisdictions/{jurisdiction}', [JurisdictionController::class, 'show'])->name('jurisdictions.show');

Route::get('/obligations', [ObligationController::class, 'index'])->name('obligations.index');
Route::get('/obligations/{obligation}', [ObligationController::class, 'show'])->name('obligations.show');

Route::get('/compare', [CompareController::class, 'index'])->name('compare.index');
Route::get('/compare/{comparison}', [CompareController::class, 'show'])->name('compare.show');

Route::get('/changes', [ChangeController::class, 'index'])->name('changes.index');
Route::get('/changes/feed', [ChangeController::class, 'feed'])->name('changes.feed');
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

Route::get('/tools/applicability-check', [ApplicabilityController::class, 'show'])->name('tools.applicability');

Route::get('/open-data', [PageController::class, 'openData'])->name('open-data');
Route::get('/open-data/aipolicytracker-latest.json', [PageController::class, 'openDataDownload'])->name('open-data.download');
Route::get('/methodology', [PageController::class, 'methodology'])->name('methodology');
Route::get('/about', [PageController::class, 'about'])->name('about');
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

// Editorial landing pages and guides generated from verified data plus editorial content.
Route::get('/guides', [LandingController::class, 'guides'])->name('guides.index');
Route::get('/guides/{slug}', [LandingController::class, 'guide'])->name('guides.show');
Route::get('/{landing}', [LandingController::class, 'landing'])
    ->where('landing', 'eu-ai-act|ai-regulation-india|ai-policy-nepal|ai-governance-singapore|ai-regulation-australia|ai-regulation-uk|ai-regulation-usa|ai-governance-uae|ai-regulation-south-asia')
    ->name('landing');

// Machine-readable assets.
Route::get('/sitemap.xml', [SitemapController::class, 'index'])->name('sitemap.index');
Route::get('/sitemap-{section}.xml', [SitemapController::class, 'section'])->where('section', 'static|jurisdictions|policies|obligations|changes|resources')->name('sitemap.section');
Route::get('/llms.txt', [MachineReadableController::class, 'llms'])->name('llms');
Route::get('/llms-full.txt', [MachineReadableController::class, 'llmsFull'])->name('llms.full');
Route::get('/openapi.json', [MachineReadableController::class, 'openapi'])->name('openapi');

// Legacy URL redirects.
Route::redirect('/about-ai-policy', '/about', 301);
Route::redirect('/dashboard', '/', 301);
