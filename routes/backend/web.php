<?php
use App\Http\Controllers\Backend\AiPolicyTrackerController;
use App\Http\Controllers\Backend\CMS\HeaderMenuController;
use App\Http\Controllers\Backend\CountryController;
use App\Http\Controllers\Backend\DashboardController;
use App\Http\Controllers\Backend\NewsController;
use App\Http\Controllers\Backend\UserController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'isAdmin'])->group(function () {
    // Admin (Blade).
    Route::get('/backend/dashboard', [\App\Http\Controllers\Backend\Admin\AdminController::class, 'dashboard'])->name('dashboard');
    Route::prefix('backend/admin')->as('backend.admin.')->controller(\App\Http\Controllers\Backend\Admin\AdminController::class)->group(function () {
        Route::get('/', 'dashboard')->name('dashboard');
        Route::get('/submissions', 'submissions')->name('submissions');
        Route::get('/subscribers', 'subscribers')->name('subscribers');
        Route::get('/subscribers/export', 'subscribersExport')->name('subscribers.export');
        Route::post('/subscribers/{subscriber}/resend', 'subscriberResend')->name('subscribers.resend');
        Route::delete('/subscribers/{subscriber}', 'subscriberDelete')->name('subscribers.delete');
        Route::get('/external', 'external')->name('external');
        Route::get('/downloads', 'downloads')->name('downloads');
        Route::get('/downloads/export', 'downloadsExport')->name('downloads.export');
        Route::get('/settings', 'settings')->name('settings');
        Route::post('/settings', 'settingsSave')->name('settings.save');
        Route::post('/settings/test-mail', 'settingsTestMail')->name('settings.test');
    });
    // Free-tool library CRUD (tools, files, status).
    Route::prefix('backend/admin/tools')->as('backend.admin.tools.')->controller(\App\Http\Controllers\Backend\Admin\ToolController::class)->group(function () {
        Route::get('/', 'index')->name('index');
        Route::get('/create', 'create')->name('create');
        Route::post('/', 'store')->name('store');
        Route::get('/{tool}/edit', 'edit')->name('edit');
        Route::put('/{tool}', 'update')->name('update');
        Route::delete('/{tool}', 'destroy')->name('destroy');
        Route::post('/{tool}/files', 'fileStore')->name('files.store');
        Route::post('/{tool}/files/{file}/toggle', 'fileToggle')->name('files.toggle');
        Route::delete('/{tool}/files/{file}', 'fileDestroy')->name('files.destroy');
        Route::get('/{tool}/files/{file}', 'fileDownload')->name('files.download');
    });
});


// Reviewer queue and publishing controls (admin only).
Route::middleware(['auth', 'isAdmin'])->prefix('backend/review')->as('backend.review.')->group(function () {
    Route::get('/', [\App\Http\Controllers\Backend\Review\ReviewController::class, 'index'])->name('index');
    Route::post('/submissions/{submission}/decide', [\App\Http\Controllers\Backend\Review\ReviewController::class, 'decide'])->name('decide');
    Route::post('/publish/{type}/{slug}', [\App\Http\Controllers\Backend\Review\ReviewController::class, 'publish'])->name('publish');
});
