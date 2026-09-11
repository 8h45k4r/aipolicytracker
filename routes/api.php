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
    Route::get('/changes', [PublicApiController::class, 'changes'])->name('changes');
    Route::get('/taxonomies', [PublicApiController::class, 'taxonomies'])->name('taxonomies');
});
