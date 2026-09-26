<?php

use App\Http\Controllers\Api\V1\PublicApiController;
use Illuminate\Support\Facades\Route;

// Read-only public API. No authentication; rate limited; cached.
Route::prefix('v1')->middleware('throttle:120,1')->name('api.v1.')->group(function () {
    Route::get('/', [PublicApiController::class, 'root'])->name('root');
    Route::get('/jurisdictions', [PublicApiController::class, 'jurisdictions'])->name('jurisdictions');
    Route::get('/jurisdictions/{slug}', [PublicApiController::class, 'jurisdiction'])->name('jurisdiction');
    Route::get('/policies', [PublicApiController::class, 'policies'])->name('policies');
    Route::get('/policies/{slug}', [PublicApiController::class, 'policy'])->name('policy');
    Route::get('/obligations', [PublicApiController::class, 'obligations'])->name('obligations');
    Route::get('/obligations/{slug}', [PublicApiController::class, 'obligation'])->name('obligation');
    Route::get('/controls', [PublicApiController::class, 'controls'])->name('controls');
    Route::get('/controls/{slug}', [PublicApiController::class, 'control'])->name('control');
    Route::get('/changes', [PublicApiController::class, 'changes'])->name('changes');
    Route::get('/taxonomies', [PublicApiController::class, 'taxonomies'])->name('taxonomies');

    // Crosswalks between legal duties and the standards organisations are audited against.
    Route::get('/frameworks', [PublicApiController::class, 'frameworks'])->name('frameworks');
    Route::get('/frameworks/{framework}', [PublicApiController::class, 'framework'])->where('framework', '[a-z0-9-]+')->name('framework');
    Route::get('/frameworks/{framework}/{jurisdiction}', [PublicApiController::class, 'frameworkCrosswalk'])->where(['framework' => '[a-z0-9-]+', 'jurisdiction' => '[a-z0-9-]+'])->name('framework.crosswalk');

    Route::get('/deadlines', [PublicApiController::class, 'deadlines'])->name('deadlines');

    // Mirrored external datasets. Responses carry their source, licence and citation.
    Route::get('/incidents', [PublicApiController::class, 'incidents'])->name('incidents');
    Route::get('/risks', [PublicApiController::class, 'risks'])->name('risks');
    Route::get('/templates', [PublicApiController::class, 'templates'])->name('templates');
    Route::get('/templates/{slug}', [PublicApiController::class, 'template'])->where('slug', '[a-z0-9-]+')->name('template');
    Route::get('/templates/{slug}/download', [PublicApiController::class, 'templateDownload'])->where('slug', '[a-z0-9-]+')->name('template.download');
});
