<?php

namespace App\Http\Controllers\Frontend\AiPolicyTracker;

use Carbon\Carbon;
use App\Models\News;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Illuminate\Http\Request;
use App\Models\AiPolicyTracker;
use App\Http\Controllers\Controller;

class SinglePolicyTackerControlle extends Controller
{
    public function index($id)
    {

        if (!Auth::check()) {
            return Inertia::render('Frontend/DeniedPermissionPage/DeniedPermission');

            // return to_route('page_access_denied');
        }
        if (! Auth::user()->hasVerifiedEmail() && ! Auth::user()->isAdmin()) {

            return Inertia::render('Auth/VerifyEmail');
        }

        $data['aiPolicyTrackerWithRelatedNews'] = AiPolicyTracker::query()
            ->with([
                'news.thumbnail',
                'country',
                'aIPolicyActivityLogs',
                'status',
            ])
            ->where('id', $id)
            ->first();

        $latestAIPolicyTracker = AiPolicyTracker::orderBy('updated_at', 'DESC')->first();
        $data['latestDateOfUpdateAiPolicyTracker'] = $latestAIPolicyTracker ? Carbon::parse($latestAIPolicyTracker->updated_at)->format('F Y') : '';

        return Inertia::render("Frontend/AiPolicyTracker/SingleAiPolicyTracker", $data);
    }

    public function aiPolicyBookMark(Request $request, $id)
    {
        $aiPolicyTracker = AiPolicyTracker::findOrFail($id);

        return response()->json(['id' => $aiPolicyTracker->id, 'status' => 'success']);
    }
}
