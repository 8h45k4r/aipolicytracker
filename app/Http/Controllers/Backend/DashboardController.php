<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Models\AiPolicyTracker;
use App\Models\BookMark;
use App\Models\News;
use App\Models\User;
use Carbon\Carbon;
use Inertia\Inertia;

class DashboardController extends Controller
{
    public function dashboard()
    {

        $user = User::query()->nonAdmin();

        $data['usersCount'] = $user->count();

        $data['notVerifiedUsersCount'] = (clone $user)
            ->where('email_verified_at', null)
            ->count();

        $data['verifiedUsersCount'] = (clone $user)
            ->whereNotNull('email_verified_at')
            ->count();


        $data['aiPolicyCount'] = AiPolicyTracker::count();
        $data['newsCount'] = News::count();
        $data['bookMarkCount'] = BookMark::count();


        $currentYear = Carbon::now()->year;
        $upcomingYear = $currentYear + 1;



        // Get all months
        $months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];

        // Get the data and group by month
        // $graphCounts = AiPolicyTracker::get()->groupBy('created_month');
        $graphCounts = AiPolicyTracker::whereYear('created_at', $currentYear)
            ->orWhereYear('created_at', $upcomingYear)
            ->get()
            ->groupBy('created_month');

        $monthlyCounts = [];

        foreach ($months as $month) {

            $monthlyCounts[] = [
                'month' => $month,
                'value' => isset($graphCounts[$month]) ? $graphCounts[$month]->count() : 0,
            ];
        }

        $data['currentYear'] = $currentYear;
        $data['upcomingYear'] = $upcomingYear;

        return Inertia::render('Backend/Dashboard', [
            "data" => $data,
            "graphData" => $monthlyCounts,
        ]);
    }
}
