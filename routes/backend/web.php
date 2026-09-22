<?php

use App\Http\Controllers\Backend\Admin\AdminController;
use App\Http\Controllers\Backend\Admin\BillingController;
use App\Http\Controllers\Backend\Admin\ToolController;
use App\Http\Controllers\Backend\Admin\UserController;
use App\Http\Controllers\Backend\Review\ReviewController;
use App\Http\Controllers\Backend\Security\TwoFactorController;
use Illuminate\Support\Facades\Route;

// Second-factor enrolment and challenge. Reachable by an admin who has not yet passed the
// factor, which is why these sit outside the gated groups below; still audited.
Route::middleware(['auth', 'isAdmin', 'admin.audit'])->prefix('backend/security')->as('admin.two-factor.')->controller(TwoFactorController::class)->group(function () {
    Route::get('/enrol', 'enrol')->name('enrol');
    Route::post('/enrol', 'confirm')->middleware('throttle:10,1')->name('confirm');
    Route::get('/challenge', 'challenge')->name('challenge');
    Route::post('/challenge', 'verify')->middleware('throttle:5,1')->name('verify');
});
// Recovery codes are shown once and regenerated only behind a fresh password.
Route::middleware(['auth', 'isAdmin', 'admin.2fa', 'admin.audit'])->prefix('backend/security')->as('admin.two-factor.')->controller(TwoFactorController::class)->group(function () {
    Route::get('/recovery-codes', 'recovery')->name('recovery');
    Route::post('/recovery-codes', 'regenerate')->middleware('password.confirm')->name('regenerate');
});

Route::middleware(['auth', 'isAdmin', 'admin.2fa', 'admin.audit'])->group(function () {
    // Admin (Blade).
    Route::get('/backend/dashboard', [AdminController::class, 'dashboard'])->middleware('can:dashboard.view')->name('dashboard');
    // Grouped by the capability each page needs rather than by controller, so a granted role
    // cannot reach a page its role does not name. `can:` resolves the Gate abilities defined in
    // AppServiceProvider from App\Enums\AdminCapability.
    Route::prefix('backend/admin')->as('backend.admin.')->controller(AdminController::class)->group(function () {
        Route::middleware('can:dashboard.view')->group(function () {
            Route::get('/', 'dashboard')->name('dashboard');
        });
        Route::middleware('can:submissions.decide')->group(function () {
            Route::get('/submissions', 'submissions')->name('submissions');
        });
        // Reading the audience is separated from acting on it: an analyst may see the list,
        // only an editor may resend to or delete from it.
        Route::middleware('can:audience.view')->group(function () {
            Route::get('/subscribers', 'subscribers')->name('subscribers');
            Route::get('/subscribers/export', 'subscribersExport')->name('subscribers.export');
            Route::get('/downloads', 'downloads')->name('downloads');
            Route::get('/downloads/export', 'downloadsExport')->name('downloads.export');
        });
        Route::middleware('can:subscribers.manage')->group(function () {
            Route::post('/subscribers/{subscriber}/resend', 'subscriberResend')->name('subscribers.resend');
            Route::delete('/subscribers/{subscriber}', 'subscriberDelete')->name('subscribers.delete');
        });
        // Every scheduled job, runnable now, with its last outcome. The ones that send
        // mail or rewrite data ask for the password first.
        Route::middleware('can:jobs.run')->group(function () {
            Route::get('/jobs', 'jobs')->name('jobs');
            Route::post('/jobs/{job}', 'runJob')->where('job', '[a-z_]+')->name('jobs.run');
            Route::post('/jobs/{job}/confirmed', 'runJob')->where('job', '[a-z_]+')->middleware('password.confirm')->name('jobs.run.confirmed');
        });
        Route::middleware('can:external.sync')->group(function () {
            Route::get('/external', 'external')->name('external');
            Route::post('/external/sync', 'externalSync')->name('external.sync');
        });
        // Owner only: these change how the platform runs.
        Route::middleware('can:settings.manage')->group(function () {
            Route::get('/settings', 'settings')->name('settings');
            Route::post('/settings', 'settingsSave')->middleware('password.confirm')->name('settings.save');
            Route::post('/settings/test-mail', 'settingsTestMail')->name('settings.test');
        });
        Route::middleware('can:audit.view')->group(function () {
            Route::get('/audit', 'audit')->name('audit');
        });
    });
    // Accounts and roles. Owner only, and the controller additionally refuses to act on an
    // owner or on the signed-in account, so this page cannot be used to seize or lose control.
    Route::middleware('can:users.manage')->prefix('backend/admin/users')->as('backend.admin.users.')->controller(UserController::class)->group(function () {
        Route::get('/', 'index')->name('index');
        Route::post('/{user}/role', 'updateRole')->name('role');
        Route::post('/{user}/suspend', 'suspend')->name('suspend');
        Route::post('/{user}/restore', 'restore')->name('restore');
        Route::post('/{user}/reset-two-factor', 'resetTwoFactor')->middleware('password.confirm')->name('two-factor.reset');
        Route::delete('/{user}', 'destroy')->middleware('password.confirm')->name('destroy');
    });

    // Billing: subscriptions, received webhooks and provider configuration check.
    Route::middleware('can:billing.manage')->prefix('backend/admin/billing')->as('backend.admin.billing.')->controller(BillingController::class)->group(function () {
        Route::get('/', 'index')->name('index');
        Route::post('/check', 'check')->name('check');
        Route::post('/provision', 'provision')->middleware('password.confirm')->name('provision');
        Route::post('/probe', 'probe')->name('probe');
    });
    // Free-tool library CRUD (tools, files, status).
    Route::middleware('can:tools.manage')->prefix('backend/admin/tools')->as('backend.admin.tools.')->controller(ToolController::class)->group(function () {
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
    // A reviewer confirms records against their source and decides submissions; publishing is
    // a separate capability, because making a record public is a different act from agreeing
    // that it is accurate.
    Route::middleware('can:submissions.decide')->group(function () {
        Route::get('/', [ReviewController::class, 'index'])->name('index');
        Route::post('/submissions/{submission}/decide', [ReviewController::class, 'decide'])->name('decide');
    });
    Route::post('/publish/{type}/{slug}', [ReviewController::class, 'publish'])->middleware('can:records.publish')->name('publish');
    Route::post('/verify/{type}/{slug}', [ReviewController::class, 'verify'])->middleware('can:records.verify')->name('verify');
    Route::post('/verify-many/{type}', [ReviewController::class, 'verifyMany'])->where('type', 'policy|jurisdiction|control')->middleware('can:records.verify')->name('verify.many');
});
