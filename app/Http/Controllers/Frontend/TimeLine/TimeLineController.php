<?php

namespace App\Http\Controllers\Frontend\TimeLine;

use Carbon\Carbon;
use Inertia\Inertia;
use Illuminate\Http\Request;
use App\Models\AiPolicyTracker;
use App\Http\Controllers\Controller;

class TimeLineController extends Controller
{
    public function index()
    {
        // Fetch the most recent record and format the date
        $lastUpdateDate = AiPolicyTracker::query()
            ->withTrashed()
            ->latest('created_at')
            ->first();

        $formattedLastDate = $lastUpdateDate
            ? Carbon::parse($lastUpdateDate->updated_at)->format('M Y')
            : '';

        // Group data by announcement_year
        $groupedData = AiPolicyTracker::query()
            ->withTrashed()
            ->with('aIPolicyActivityLogs')
            ->get()
            ->groupBy('announcement_year')
            ->toArray();

        // ksort($groupedData);
        krsort($groupedData);

        // Prepare data for Inertia view
        $data = [
            'timeLines' => $groupedData,
            'lastUpdateDate' => $formattedLastDate,
        ];



        return Inertia::render("Frontend/TimeLine/TimeLine", $data);
    }
}
