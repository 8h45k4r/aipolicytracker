<?php

use App\Http\Controllers\Backend\Admin\AdminController;
use App\Http\Controllers\Backend\Admin\AlertsController;
use App\Http\Controllers\Backend\Admin\BillingController;
use App\Http\Controllers\Backend\Admin\FundingController;
use App\Http\Controllers\Backend\Admin\ToolController;
use App\Http\Controllers\Backend\Admin\UserController;
use App\Http\Controllers\Backend\Review\IndependentChecksController;
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
            Route::get('/submissions/export', 'submissionsExport')->name('submissions.export');
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
            Route::post('/subscribers/resend-many', 'subscribersResendMany')->name('subscribers.resend.many');
            Route::post('/subscribers/delete-many', 'subscribersDeleteMany')->name('subscribers.delete.many');
            Route::post('/subscribers/{subscriber}/resend', 'subscriberResend')->name('subscribers.resend');
            Route::delete('/subscribers/{subscriber}', 'subscriberDelete')->name('subscribers.delete');
        });
        // Every scheduled job, runnable now, with its last outcome. The ones that send
        // mail or rewrite data ask for the password first.
        Route::middleware('can:jobs.run')->group(function () {
            Route::get('/jobs', 'jobs')->name('jobs');
            Route::get('/jobs/export', 'jobsExport')->name('jobs.export');
            Route::post('/jobs/{job}', 'runJob')->where('job', '[a-z_]+')->name('jobs.run');
            Route::post('/jobs/{job}/confirmed', 'runJob')->where('job', '[a-z_]+')->middleware('password.confirm')->name('jobs.run.confirmed');
        });
        Route::middleware('can:external.sync')->group(function () {
            Route::get('/external', 'external')->name('external');
            Route::post('/external/sync', 'externalSync')->name('external.sync');
        });
        // Owner only: these change how the platform runs.
        Route::middleware('can:settings.manage')->group(function () {
            // Confirmed before the page opens rather than on save: a POST bounced to the
            // password prompt comes back to an empty form, and typed API keys were lost.
            Route::get('/settings', 'settings')->middleware('password.confirm')->name('settings');
            Route::post('/settings', 'settingsSave')->middleware('password.confirm')->name('settings.save');
            Route::post('/settings/test-mail', 'settingsTestMail')->name('settings.test');
            Route::post('/settings/turnstile-check', 'settingsTurnstileCheck')->middleware('throttle:10,1')->name('settings.turnstile');
        });
        Route::middleware('can:audit.view')->group(function () {
            Route::get('/audit', 'audit')->name('audit');
            Route::get('/audit/export', 'auditExport')->name('audit.export');
        });
    });
    // Accounts and roles. Owner only, and the controller additionally refuses to act on an
    // owner or on the signed-in account, so this page cannot be used to seize or lose control.
    Route::middleware('can:users.manage')->prefix('backend/admin/users')->as('backend.admin.users.')->controller(UserController::class)->group(function () {
        Route::get('/', 'index')->name('index');
        Route::get('/export', 'export')->name('export');
        Route::get('/invite', 'inviteForm')->name('invite.create');
        Route::post('/invite', 'invite')->middleware('throttle:20,1')->name('invite');
        Route::post('/bulk', 'bulk')->name('bulk');
        Route::post('/bulk-delete', 'bulkDelete')->middleware('password.confirm')->name('bulk.delete');
        // What each role may do. Changing it changes every holder at once, so it needs a
        // recently confirmed password, like the other owner-level settings.
        Route::get('/permissions', 'permissions')->middleware('password.confirm')->name('permissions');
        Route::post('/permissions', 'updatePermissions')->middleware('password.confirm')->name('permissions.update');
        Route::post('/permissions/reset', 'resetPermissions')->middleware('password.confirm')->name('permissions.reset');
        Route::get('/{user}', 'show')->name('show');
        Route::post('/{user}/invitation', 'resendInvitation')->middleware('throttle:10,1')->name('invitation');
        Route::post('/{user}/password-reset', 'sendPasswordReset')->middleware('throttle:10,1')->name('password-reset');
        Route::post('/{user}/verification', 'resendVerification')->middleware('throttle:10,1')->name('verification');
        Route::post('/{user}/role', 'updateRole')->name('role');
        Route::post('/{user}/suspend', 'suspend')->name('suspend');
        Route::post('/{user}/restore', 'restore')->name('restore');
        Route::post('/{user}/reset-two-factor', 'resetTwoFactor')->middleware('password.confirm')->name('two-factor.reset');
        Route::delete('/{user}', 'destroy')->middleware('password.confirm')->name('destroy');
    });

    // Billing: subscriptions, received webhooks and provider configuration check.
    Route::middleware('can:billing.manage')->prefix('backend/admin/billing')->as('backend.admin.billing.')->controller(BillingController::class)->group(function () {
        Route::get('/', 'index')->name('index');
        Route::get('/export', 'export')->name('export');
        Route::get('/payments/export', 'paymentsExport')->name('payments.export');
        // Runs a failed webhook again from its stored payload, under the same event id.
        Route::post('/events/{event}/reapply', 'reapply')->whereNumber('event')->name('events.reapply');
        Route::post('/check', 'check')->name('check');
        Route::post('/provision', 'provision')->middleware('password.confirm')->name('provision');
        Route::post('/probe', 'probe')->name('probe');
    });
    // Alerts: watches, channels, channel deliveries and consent. Reading is an audience
    // read; retrying a delivery is acting on the audience, like resending to a subscriber.
    Route::prefix('backend/admin/alerts')->as('backend.admin.alerts.')->controller(AlertsController::class)->group(function () {
        Route::middleware('can:audience.view')->group(function () {
            Route::get('/', 'index')->name('index');
            Route::get('/deliveries/export', 'export')->name('deliveries.export');
        });
        Route::middleware('can:subscribers.manage')->group(function () {
            Route::post('/deliveries/retry-many', 'retryMany')->name('deliveries.retry.many');
            Route::post('/deliveries/{delivery}/retry', 'retry')->whereNumber('delivery')->name('deliveries.retry');
        });
    });

    // Funders disclosed on /funding. Owner only, like settings: what the site says about its
    // money is a statement by the project.
    Route::middleware('can:settings.manage')->prefix('backend/admin/funding')->as('backend.admin.funding.')->controller(FundingController::class)->group(function () {
        Route::get('/', 'index')->name('index');
        Route::post('/', 'store')->name('store');
        Route::put('/{funder}', 'update')->name('update');
        Route::post('/{funder}/publish', 'publish')->name('publish');
        Route::post('/{funder}/move', 'move')->name('move');
        Route::delete('/{funder}', 'destroy')->name('destroy');
    });
    // Free-tool library CRUD (tools, files, status).
    Route::middleware('can:tools.manage')->prefix('backend/admin/tools')->as('backend.admin.tools.')->controller(ToolController::class)->group(function () {
        Route::get('/', 'index')->name('index');
        Route::get('/create', 'create')->name('create');
        Route::post('/', 'store')->name('store');
        Route::post('/status-many', 'statusMany')->name('status.many');
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
        Route::get('/export', [ReviewController::class, 'export'])->name('export');
        Route::post('/submissions/{submission}/decide', [ReviewController::class, 'decide'])->name('decide');
        Route::post('/submissions/decide-many', [ReviewController::class, 'decideMany'])->name('decide.many');
    });
    // {type} is any key of App\Services\Review\ReviewableTypes; the controller answers 404 to the rest.
    Route::post('/publish/{type}/{slug}', [ReviewController::class, 'publish'])->middleware('can:records.publish')->name('publish');
    Route::post('/publish-many/{type}', [ReviewController::class, 'publishMany'])->middleware('can:records.publish')->name('publish.many');
    Route::post('/verify/{type}/{slug}', [ReviewController::class, 'verify'])->middleware('can:records.verify')->name('verify');
    Route::post('/verify-many/{type}', [ReviewController::class, 'verifyMany'])->middleware('can:records.verify')->name('verify.many');
});

// Quarterly independent second checks: the sample, progress, agreement and disputes. Read-only;
// a second check is recorded in data/ by pull request.
Route::middleware(['auth', 'isAdmin', 'admin.2fa', 'admin.audit', 'can:records.verify'])->prefix('backend/review/independent-checks')->as('backend.checks.')->controller(IndependentChecksController::class)->group(function () {
    Route::get('/', 'index')->name('index');
    Route::get('/export', 'export')->name('export');
});
