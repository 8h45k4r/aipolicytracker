<?php

namespace App\Http\Controllers\Frontend\WatchList;

use App\Helpers\GovAiIndexHelper;
use App\Models\BookMark;
use App\Models\Country;
use App\Models\Status;
use Auth;
use Inertia\Inertia;
use Illuminate\Http\Request;
use App\Models\AiPolicyTracker;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\Controller;
use Carbon\Carbon;

class WatchListController extends Controller
{

    public function index()
    {
        $data['tableData'] = AiPolicyTracker::whereHas('bookmark')
            ->with(['country', 'status', 'bookmark'])
            ->orderBy('created_at', 'desc')
            ->paginate(10);

        $data['aiPolicies'] = AiPolicyTracker::get()
            ->map(function ($aiPolicy) {
                return [
                    'value' => $aiPolicy->id,
                    'label' => $aiPolicy->ai_policy_name,
                ];
            });

        $data['countries'] = Country::select('id as value', 'name as label')
            ->where('status', 1)
            ->orderBy('name', 'asc')
            ->get();

        $data['statuses'] = Status::get()
            ->map(function ($status) {
                return [
                    'value' => $status->id,
                    'label' => $status->name,
                ];
            });
        $data['govAiIndex'] = GovAiIndexHelper::$govAiIndex;

        return Inertia::render("Frontend/WatchList/WatchList", $data);
    }

    public function add($id, $isBooked)
    {
        try {

            if (!Auth::check()) {
                return Inertia::render('Frontend/DeniedPermissionPage/DeniedPermission');
            }

            if (!$id) {
                return to_route('home')->with('error', 'Woops! not booked');
            }

            if ($isBooked == "true") {
                $bookmark = BookMark::where('ai_policy_tracker_id', $id)
                    ->where('user_id', Auth::id())
                    ->first();
                $bookmark->delete();
            } else {

                BookMark::updateOrCreate([
                    'ai_policy_tracker_id' => $id,
                    'user_id' => Auth::id()
                ], [
                    'user_id' => Auth::user()->id,
                    'ai_policy_tracker_id' => $id,
                ]);
            }

            return back();
        } catch (\Throwable $th) {
            report($th);
            return to_route('home')->with('error', 'Oops! Something went wrong.');
        }

    }

    public function getFilteredData(Request $request)
    {
        $query = AiPolicyTracker::whereHas('bookmark');

        // Apply filters based on the request parameters
        if ($request->has('AI_Policy_Name') && !empty($request->AI_Policy_Name)) {
            $aiPolicyIds = explode(',', $request->AI_Policy_Name);
            $query->whereIn('id', $aiPolicyIds);
        }

        if ($request->has('country_id') && !empty($request->country_id)) {
            $countryIds = explode(',', $request->country_id);
            $query->whereIn('country_id', $countryIds);
        }

        if ($request->has('status_id') && !empty($request->status_id)) {
            $statusIds = explode(',', $request->status_id);
            $query->whereIn('status_id', $statusIds);
        }
        if ($request->has('Gov_ai_index') && !empty($request->Gov_ai_index)) {
            $Gov_ai_index = explode(',', $request->Gov_ai_index);
            $query->whereIn('Gov_ai_index', $Gov_ai_index);
        }

        // if ($request->has('announcement_year') && !empty($request->announcement_year)) {
        //     $query->where('announcement_year', $request->announcement_year);
        // }

        // Apply pagination
        $perPage = min(max((int) $request->get('per_page', 10), 1), config('aipolicytracker.max_per_page'));
        $filteredData = $query->paginate($perPage);

        // Format the data as needed
        $tableData = $filteredData->getCollection()->map(function ($policy) {
            return [
                'id' => $policy->id,
                'ai_policy_name' => $policy->ai_policy_name,
                'country' => $policy->country,
                'bookmark' => $policy->bookmark,
                'governing_body' => $policy->governing_body,
                'formatted_created_at' => \Carbon\Carbon::parse($policy->announcement_date)->format('M d, Y'),
                'status' => $policy->status,
                'technology_partners' => $policy->technology_partners,
                'governance_structure' => $policy->governance_structure,
                'main_motivation' => $policy->main_motivation,
                'description' => $policy->description,
            ];
        });

        // Return the filtered and paginated data as JSON
        return response()->json([
            'current_page' => $filteredData->currentPage(),
            'data' => $tableData,
            'first_page_url' => $filteredData->url(1),
            'last_page' => $filteredData->lastPage(),
            'last_page_url' => $filteredData->url($filteredData->lastPage()),
            'next_page_url' => $filteredData->nextPageUrl(),
            'path' => $filteredData->path(),
            'per_page' => $filteredData->perPage(),
            'prev_page_url' => $filteredData->previousPageUrl(),
            'to' => $filteredData->lastItem(),
            'total' => $filteredData->total(),
        ]);
    }
}
