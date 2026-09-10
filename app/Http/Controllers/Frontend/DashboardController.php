<?php

namespace App\Http\Controllers\Frontend;

use App\Helpers\GovAiIndexHelper;
use App\Models\BookMark;
use App\Models\ContributingOrg;
use Carbon\Carbon;
use App\Models\News;
use Inertia\Inertia;
use App\Models\Status;
use App\Models\Country;
use Illuminate\Http\Request;
use App\Models\AiPolicyTracker;
use App\Http\Controllers\Controller;

class DashboardController extends Controller
{
    public function dashboard()
    {
        $aiPolicyTracker = AiPolicyTracker::with(['country', 'status', 'bookmark'])
            ->latest();

        $data['authUserBookmarkCount'] = BookMark::authUserBookmarkCount();

        $data['tableData'] = $aiPolicyTracker
            ->paginate(10);

        $data['news'] = News::query()
            ->with(['thumbnail', 'status'])
            ->latest()
            ->get();

        $latestNews = News::query()
            ->latest()->first();
        $data['newsLastUpdate'] = $latestNews ? Carbon::parse($latestNews->updated_at)->format('d M Y') : '';

        $latestAiPolicy = $aiPolicyTracker->first();
        $data['aiPolicyLastUpdate'] = $latestAiPolicy ? Carbon::parse($latestAiPolicy->updated_at)->format('d M Y') : '';

        //-- Ai Policy tracker country with status and Link
        $data['aiPolicyTrackerWithStatus'] = AiPolicyTracker::query()
            ->get();

        $URL_MAP = [];
        $STATUS_MAP = [];
        foreach ($data['aiPolicyTrackerWithStatus'] as $tracker) {
            $countrySymbol = null;
            if ($tracker->country->status == 1) {
                $countrySymbol = $tracker->country->symbol;
            }
            $URL_MAP[$countrySymbol] = $tracker->whitepaper_document_link;
            $STATUS_MAP[$countrySymbol] = $tracker->status->name;
        }
        $data['countrywithStatus'] = $STATUS_MAP;

        //-- get individual country
        $data['countrywiseAiPolicyTracker'] = Country::where('status', 1)
            ->withWhereHas('aiPolicyTrackers')
            ->latest()
            ->get();

        $countryWithAiPolicyTrackerMap = [];
        foreach ($data['countrywiseAiPolicyTracker'] as $country) {
            $countrySymbol = $country->symbol;
            foreach ($country->aiPolicyTrackers as $tracker) {
                $countryWithAiPolicyTrackerMap[$countrySymbol][] = [
                    'name' => $tracker->ai_policy_name,
                    'url' => route('frontend.single_ai_policy_tracker.index', $tracker->id),
                ];
            }
        }
        $data['countryWithAiPolicies'] = $countryWithAiPolicyTrackerMap;

        //-- End Ai Policy tracker country with status
        $data['aiPolicies'] = AiPolicyTracker::select('id as value', 'ai_policy_name as label')->get();

        $data['countries'] = Country::select('id as value', 'name as label')
            ->where('status', 1)
            ->orderBy('name', 'asc')
            ->get();

        $data['statuses'] = Status::select('id as value', 'name as label')->get();

        $data['govAiIndex'] = GovAiIndexHelper::$govAiIndex;

        $data['organizationLogo'] = ContributingOrg::get();

        return Inertia::render('Frontend/Dashboard/Dashboard', $data);
    }

    public function getFilteredData(Request $request)
    {
        $query = AiPolicyTracker::query();

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
        $perPage = min(max((int) $request->get('per_page', 15), 1), config('aipolicytracker.max_per_page'));
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


    // In your Controller
    public function updateStatus(Request $request)
    {

        // $statusIds = $request->input('status_state');
        // if (is_string($statusIds)) {
        //     $statusIds = explode(',', $statusIds);
        // }

        $statusIds = $request->input('status_state', []);

        // If $statusIds is a string, convert it to an array
        if (!is_array($statusIds)) {
            $statusIds = json_decode($statusIds, true); // Decodes JSON array string to PHP array
            if (json_last_error() !== JSON_ERROR_NONE) {
                $statusIds = explode(',', $statusIds); // Fallback: Split by comma if it's not a valid JSON string
            }
        }




        // Ensure $statusIds is an array and not empty
        if (is_array($statusIds) && count($statusIds) > 0) {
            //-- Ai Policy tracker country with status and Link
            // $data['aiPolicyTrackerWithStatus'] = AiPolicyTracker::whereIn('status_id', $statusIds)->with(['country', 'status'])->get();


            $data['aiPolicyTrackerWithStatus'] = AiPolicyTracker::whereIn('status_id', $statusIds)
                ->with(['country', 'status'])
                ->get();

            $URL_MAP = [];
            $STATUS_MAP = [];
            foreach ($data['aiPolicyTrackerWithStatus'] as $tracker) {
                $countrySymbol = null;
                if ($tracker->country->status == 1) {
                    $countrySymbol = $tracker->country->symbol;
                }
                $URL_MAP[$countrySymbol] = $tracker->whitepaper_document_link;
                $STATUS_MAP[$countrySymbol] = $tracker->status->name;
            }
            $data['countrywithStatus'] = $STATUS_MAP;

            return response()->json($data['countrywithStatus']);
        } else {
            // Handle the case where $statusIds is not valid
            return response()->json(['error' => 'Invalid status IDs'], 400);
        }
    }

}
