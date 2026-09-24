<?php

use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;

// Account profile (Inertia). The legacy map site was removed; its URLs redirect to the new pages.
// admin.2fa is a no-op for ordinary accounts. For an admin it means a session holding only
// the password cannot change the email or delete the account: either would free an owner
// address for re-registration and so bypass the second factor.
Route::middleware(['auth', 'admin.2fa'])->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

Route::redirect('/map', '/', 301);
Route::redirect('/news', '/changes', 301);
Route::redirect('/timeline', '/changes', 301);
Route::redirect('/bookmarks', '/changes', 301);
Route::redirect('/gov-ai-index/assesment', '/tools/applicability-check', 301);
Route::redirect('/legacy/about-ai-policy', '/about', 301);
