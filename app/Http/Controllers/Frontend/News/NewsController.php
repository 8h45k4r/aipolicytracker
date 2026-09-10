<?php

namespace App\Http\Controllers\Frontend\News;

use App\Http\Controllers\Controller;
use App\Models\AiPolicyTracker;
use App\Models\Country;
use App\Models\News;
use Illuminate\Http\Request;
use Inertia\Inertia;

class NewsController extends Controller
{
    public function index()
    {
        $data['news'] = News::with(['thumbnail', 'status'])
            ->orderBy('created_at', 'Desc')
            ->paginate(10);
        $data['aiPolicies'] = AiPolicyTracker::get()
            ->map(function ($aiPolicy) {
                return [
                    'value' => $aiPolicy->id,
                    'label' => $aiPolicy->ai_policy_name,
                ];
            });

        $data['countries'] = Country::get()
            ->map(function ($country) {
                return [
                    'value' => $country->id,
                    'label' => $country->name,
                ];
            });

        return Inertia::render('Frontend/News/News', $data);
    }

    public function singleNews($id = null)
    {

        try {
            $data['news'] = News::with(['thumbnail', 'status'])->find($id);

            if (!$data['news']) {
                return to_route('news.index')->with('error', 'News Not Founded');
            }

            return Inertia::render('Frontend/News/SingleNews', $data);
        } catch (\Throwable $th) {
            report($th);
            return to_route('news.index')->with('error', 'Oops! Something went wrong');
        }
    }

    public function getFilteredData(Request $request)
    {
        $query = News::query();

        // Apply filters based on the request parameters
        if ($request->has('AI_Policy_Name') && !empty($request->AI_Policy_Name)) {
            $aiPolicyIds = explode(',', $request->AI_Policy_Name);
            $query->whereIn('policy_tracker_id', $aiPolicyIds);
        }

        if ($request->has('country_id') && !empty($request->country_id)) {
            $countryId = $request->country_id;
            $query->whereHas('policyTracker', function ($subQuery) use ($countryId) {
                $subQuery->where('country_id', $countryId);
            });
        }

        // Apply pagination
        $perPage = min(max((int) $request->get('per_page', 10), 1), config('aipolicytracker.max_per_page'));
        $filteredData = $query->orderBy('created_at', 'Desc')->paginate($perPage);

        // Format the data as needed
        $news = $filteredData->getCollection()->map(function ($policy) {
            return [
                'id' => $policy->id,
                'description' => $policy->description,
                'thumbnail' => $policy->thumbnail,
                'title' => $policy->title,
                'upload_date' => \Carbon\Carbon::parse($policy->upload_date)->format('M d, Y'),
                'status' => $policy->status,
            ];
        });

        // Return the filtered and paginated data as JSON
        return response()->json([
            'current_page' => $filteredData->currentPage(),
            'data' => $news,
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
    public function showAdvancedInfoPaginate(Request $request)
    {
        $perPage = min(max((int) $request->input('perPage', 10), 1), config('aipolicytracker.max_per_page'));
        $currentPage = $request->input('page', 1);

        // Fetch paginated news items
        $news = News::paginate($perPage);

        // Check if the requested page is within the available pages
        if ($currentPage > $news->lastPage()) {
            return response()->json([
                'message' => 'Requested page exceeds available pages.',
                'data' => [],
                'current_page' => $currentPage,
                'total_pages' => $news->lastPage(),
            ], 404);
        }

        // Return the paginated data as JSON
        return response()->json([
            'data' => $news->items(),
            'current_page' => $news->currentPage(),
            'last_page' => $news->lastPage(),
            'per_page' => $news->perPage(),
            'total' => $news->total(),
        ]);
    }



}
