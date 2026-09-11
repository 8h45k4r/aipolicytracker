<?php

use App\Http\Controllers\Frontend\AiPolicyTracker\SinglePolicyTackerControlle;
use App\Http\Controllers\Frontend\DashboardController;
use App\Http\Controllers\Frontend\GovAiAssesmentController;
use App\Http\Controllers\Frontend\News\NewsController;
use App\Http\Controllers\Frontend\TimeLine\TimeLineController;
use App\Http\Controllers\Frontend\WatchList\WatchListController;
use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/denied', function () {
    return Inertia::render('Frontend/DeniedPermissionPage/DeniedPermission');
})->name('page_access_denied');

Route::get('/legacy/about-ai-policy', function () {
    return Inertia::render('Frontend/AboutUs/AboutAiPolicy');
})->name('aboutus.aipolicy');



Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

Route::controller(NewsController::class)->group(function () {
    Route::get("/news", "index")->name("news.index");
    Route::get("/news/{id}", "singleNews")->name("news.single");
    Route::post('/news/filtered', [NewsController::class, 'getFilteredData'])->name('frontend.news.filtered');

    Route::post('/news/show-advanced-info', 'showAdvancedInfoPaginate')->name('frontend.showAdvancedInfoPaginate');


});

// Route::post('/news/filtered', [NewsController::class, 'getFilteredData'])->name('frontend.news.filtered');

Route:: as('frontend.')->group(function () {
    Route::controller(DashboardController::class)->group(function () {
        // Legacy map dashboard (kept for existing data; noindex). The homepage now lives in routes/public.php.
        Route::get('/map', 'dashboard')->name('map');
        Route::get('/dashboard/filtered', 'getFilteredData')->name('dashboard.filtered');

        Route::get('/dashboard/updateStatus', 'updateStatus')->name('dashboard.updateStatus');
    });
    Route::controller(WatchListController::class)
        ->as('watch_list.')
        ->group(function () {
            Route::get('/bookmarks', 'index')->name('index');
            Route::get('/bookmarks/add/{id}/{isBooked}', 'add')->name('add')->middleware('verified');
            Route::get('/bookmarks/filtered', 'getFilteredData')->name('filtered');
        });

    // time line controller
    Route::controller(TimeLineController::class)->as('time_line.')->group(function () {
        Route::get('/timeline', 'index')->name('index');
    });

    // single AI policy tracker
    Route::controller(SinglePolicyTackerControlle::class)->as('single_ai_policy_tracker.')->group(function () {
        Route::get("/aipolicytracker/single-view/{id}", "index")->name('index');
        Route::post("/aipolicytracker/bookmark/{id}", "aiPolicyBookMark")->name('bookmark');
    });
    // Govai Assesment
    Route::controller(GovAiAssesmentController::class)->as('gov_ai_assesment.')->group(function () {
        Route::get("/gov-ai-index/assesment", "index")->name('index');
    });
});
