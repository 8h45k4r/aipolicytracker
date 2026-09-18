<?php

use Illuminate\Support\Facades\Route;

// Second-factor enrolment and challenge. Reachable by an admin who has not yet passed the
// factor, which is why these sit outside the gated groups below; still audited.
Route::middleware(['auth', 'isAdmin', 'admin.audit'])->prefix('backend/security')->as('admin.two-factor.')->controller(\App\Http\Controllers\Backend\Security\TwoFactorController::class)->group(function () {
    Route::get('/enrol', 'enrol')->name('enrol');
    Route::post('/enrol', 'confirm')->middleware('throttle:10,1')->name('confirm');
    Route::get('/challenge', 'challenge')->name('challenge');
    Route::post('/challenge', 'verify')->middleware('throttle:5,1')->name('verify');
});
// Recovery codes are shown once and regenerated only behind a fresh password.
Route::middleware(['auth', 'isAdmin', 'admin.2fa', 'admin.audit'])->prefix('backend/security')->as('admin.two-factor.')->controller(\App\Http\Controllers\Backend\Security\TwoFactorController::class)->group(function () {
    Route::get('/recovery-codes', 'recovery')->name('recovery');
    Route::post('/recovery-codes', 'regenerate')->middleware('password.confirm')->name('regenerate');
});

Route::middleware(['auth', 'isAdmin', 'admin.2fa', 'admin.audit'])->group(function () {
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
        Route::post('/external/sync', 'externalSync')->name('external.sync');
        Route::get('/downloads', 'downloads')->name('downloads');
        Route::get('/downloads/export', 'downloadsExport')->name('downloads.export');
        Route::get('/settings', 'settings')->name('settings');
        Route::post('/settings', 'settingsSave')->middleware('password.confirm')->name('settings.save');
        Route::get('/audit', 'audit')->name('audit');
        Route::post('/settings/test-mail', 'settingsTestMail')->name('settings.test');
    });
    // Billing: subscriptions, received webhooks and provider configuration check.
    Route::prefix('backend/admin/billing')->as('backend.admin.billing.')->controller(\App\Http\Controllers\Backend\Admin\BillingController::class)->group(function () {
        Route::get('/', 'index')->name('index');
        Route::post('/check', 'check')->name('check');
        Route::post('/provision', 'provision')->middleware('password.confirm')->name('provision');
        Route::post('/probe', 'probe')->name('probe');
    });
    // Free-tool library CRUD (tools, files, status).
    Route::prefix('backend/admin/tools')->as('backend.admin.tools.')->controller(\App\Http\Controllers\Backend\Admin\ToolController::class)->group(function () {
        Route::get('/', 'index')->name('index');
        Route::get('/create', 'create')->name('create');
        Route::post('/', 'store')->name('store');
        Route::get('/{tool}/edit', 'edit')->name('edit');
        Route::put('/{tool}', 'update')->name('update');
        Route::delete('/{tool}', 'destroy')->middleware('password.confirm')->name('destroy');
        Route::post('/{tool}/files', 'fileStore')->name('files.store');
        Route::post('/{tool}/files/{file}/toggle', 'fileToggle')->name('files.toggle');
        Route::delete('/{tool}/files/{file}', 'fileDestroy')->middleware('password.confirm')->name('files.destroy');
        Route::get('/{tool}/files/{file}', 'fileDownload')->name('files.download');
    });
});

// Reviewer queue and publishing controls (admin only).
Route::middleware(['auth', 'isAdmin', 'admin.2fa', 'admin.audit'])->prefix('backend/review')->as('backend.review.')->group(function () {
    Route::get('/', [\App\Http\Controllers\Backend\Review\ReviewController::class, 'index'])->name('index');
    Route::post('/submissions/{submission}/decide', [\App\Http\Controllers\Backend\Review\ReviewController::class, 'decide'])->name('decide');
    Route::post('/publish/{type}/{slug}', [\App\Http\Controllers\Backend\Review\ReviewController::class, 'publish'])->name('publish');
    Route::post('/verify/{type}/{slug}', [\App\Http\Controllers\Backend\Review\ReviewController::class, 'verify'])->name('verify');
});
