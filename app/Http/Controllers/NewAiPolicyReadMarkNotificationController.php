<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class NewAiPolicyReadMarkNotificationController extends Controller
{
    public function markAsRead(Request $request)
    {
        // Mark all unread notifications as read for the authenticated user
        auth()->user()->unreadNotifications->markAsRead();

        return response()->json(['message' => 'Notifications marked as read']);
    }
}
