<?php

use App\Http\Controllers\NewAiPolicyReadMarkNotificationController;
use Illuminate\Support\Facades\Route;

require __DIR__ . '/frontend/web.php';
require __DIR__ . '/auth.php';
require __DIR__ . '/backend/web.php';

Route::post('/notifications/mark-as-read', [NewAiPolicyReadMarkNotificationController::class, 'markAsRead'])
    ->middleware(['auth', 'throttle:30,1'])
    ->name('notifications.markAsRead');
