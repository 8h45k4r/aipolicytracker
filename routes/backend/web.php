<?php
use App\Http\Controllers\Backend\AiPolicyTrackerController;
use App\Http\Controllers\Backend\CMS\HeaderMenuController;
use App\Http\Controllers\Backend\CountryController;
use App\Http\Controllers\Backend\DashboardController;
use App\Http\Controllers\Backend\NewsController;
use App\Http\Controllers\Backend\UserController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::middleware(['auth', 'isAdmin'])->group(function () {
    // New admin (Blade). The legacy React dashboard stays reachable as backend.legacy_dashboard.
    Route::get('/backend/dashboard', [\App\Http\Controllers\Backend\Admin\AdminController::class, 'dashboard'])->name('dashboard');
    Route::get('/backend/legacy-dashboard', [DashboardController::class, 'dashboard'])->name('backend.legacy_dashboard');
    Route::prefix('backend/admin')->as('backend.admin.')->controller(\App\Http\Controllers\Backend\Admin\AdminController::class)->group(function () {
        Route::get('/', 'dashboard')->name('dashboard');
        Route::get('/submissions', 'submissions')->name('submissions');
        Route::get('/subscribers', 'subscribers')->name('subscribers');
        Route::get('/subscribers/export', 'subscribersExport')->name('subscribers.export');
        Route::post('/subscribers/{subscriber}/resend', 'subscriberResend')->name('subscribers.resend');
        Route::delete('/subscribers/{subscriber}', 'subscriberDelete')->name('subscribers.delete');
        Route::get('/external', 'external')->name('external');
        Route::get('/settings', 'settings')->name('settings');
        Route::post('/settings', 'settingsSave')->name('settings.save');
        Route::post('/settings/test-mail', 'settingsTestMail')->name('settings.test');
    });
});


Route::middleware(['auth', 'isAdmin'])
    ->as("backend.")->group(function () {

        // ai policy tracker
        Route::controller(AiPolicyTrackerController::class)->as("ai_policy_tracker.")->group(function () {
            Route::get("/backend/aipolicytracker", "index")->name("index");
            Route::post("/backend/aipolicytracker", "store")->name("store");
            Route::post("/backend/aipolicytracker/update/{id}", "edit")->name("edit");
            Route::put("/backend/aipolicytracker/update/{id}", "update")->name("update");
            Route::get("/backend/aipolicytracker/search", "search")->name("search");
            Route::delete("/backend/aipolicytracker/delete/{id}", "delete")->name("delete");
        });
        /*********************** News Controller ******************************************* */
        Route::controller(NewsController::class)->as("news.")->group(function () {
            Route::get("/backend/news", "index")->name("index");
            Route::post("/backend/news", "store")->name("store");
            Route::post("/backend/news/edit/{id}", "updateData")->name("updateData");
            Route::post("/backend/news/update/{id}", "update")->name("update");
            Route::get("/backend/news/search", "search")->name("search");
            Route::delete("/backend/news/delete/{id}", "delete")->name("delete");


            // image upload after drop or choosen
            // Route::delete("/upload-image", "imgUpload")->name("imgUpload");

        });

        // Header menu
        Route::controller(HeaderMenuController::class)->as("header_menu.")->group(function () {
            Route::get("/backend/header-menu", "index")->name("index");
            Route::post("/backend/header-menu", "store")->name("store");
            // Route::post("/backend/header-menu/update/{id}", "updateData")->name("updateData");
            // Route::put("/backend/header-menu/update/{id}", "update")->name("update");

            Route::get("/backend/header-menu/contributin-org", "showContributingOrgIndex")->name("showContributingOrgIndex");
            Route::delete("/backend/header-menu/contributin-org/delete/{id}", "contributingOrgDelete")->name("contributingOrgDelete");



            // Route::delete("/backend/header-menu/delete/{id}", "delete")->name("delete");
        });

        // user controller
        Route::controller(UserController::class)->as("users.")->group(function () {
            Route::get("/backend/users", "index")->name("index");
            Route::post("/backend/users/views/{id}", "view")->name("view");
        });

        // user controller
        Route::controller(CountryController::class)->as("country.")->group(function () {
            Route::get("/backend/country", "index")->name("index");
            Route::post("/backend/country", "store")->name("store");
            Route::post("/backend/country/view/{id}", "view")->name("view");
            Route::get("/backend/country/search", "search")->name("search");
            Route::post("/backend/country/update-status", "updatedStatus")->name("updatedStatus");
            // Route::post("/backend/country/update/{id}", "edit")->name("edit");
            // Route::put("/backend/country/update/{id}", "update")->name("update");


            // Route::delete("/backend/country/delete/{id}", "delete")->name("delete");
        });

    });

// Reviewer queue and publishing controls (admin only).
Route::middleware(['auth', 'isAdmin'])->prefix('backend/review')->as('backend.review.')->group(function () {
    Route::get('/', [\App\Http\Controllers\Backend\Review\ReviewController::class, 'index'])->name('index');
    Route::post('/submissions/{submission}/decide', [\App\Http\Controllers\Backend\Review\ReviewController::class, 'decide'])->name('decide');
    Route::post('/publish/{type}/{slug}', [\App\Http\Controllers\Backend\Review\ReviewController::class, 'publish'])->name('publish');
});
