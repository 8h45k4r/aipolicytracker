<?php

namespace App\Http\Controllers\Backend;

use App\Helpers\GovAiIndexHelper;
use App\Jobs\SendAiPolicyTrackerNotificationJob;
use Inertia\Inertia;
use App\Models\Status;
use App\Models\Country;
use Illuminate\Http\Request;
use App\Models\AiPolicyTracker;
use App\Http\Controllers\Controller;
use App\Helpers\Logs\AiPolicyActivityLogHelper;
use App\Http\Requests\AiPolicyTrackerPostRequest;

class AiPolicyTrackerController extends Controller
{
    public function index()
    {
        $countries = Country::select('id as value', 'name as label')
            ->where('status', 1)
            ->orderBy('name', 'asc')
            ->get();

        $status = Status::select("id", "name")
            ->get()
            ->map(function ($value) {
                return [
                    "value" => $value->id,
                    "label" => $value->name,
                ];
            });

        $tableData = AiPolicyTracker::with(['country', 'status'])
            ->withCount('bookmarks')
            ->orderBy('created_at', 'desc')->paginate(10);

        $govAiIndex = GovAiIndexHelper::$govAiIndex;

        return Inertia::render("Backend/AiPolicyTracker/Index", [
            'countries' => $countries,
            'status' => $status,
            'tableData' => $tableData,
            'govAiIndex' => $govAiIndex,
        ]);
    }

    public function store(AiPolicyTrackerPostRequest $request)
    {
        $validated = $request->validated();
        $aiPolicyTracker = AiPolicyTracker::create($validated);

        //-- Log the AI policy activity (Create)
        $activity_name = 'added data';
        $description = 'A new AI policy, <b>' . $aiPolicyTracker->ai_policy_name . '</b>, has been added.';
        AiPolicyActivityLogHelper::createAiPolicyActivityLog($aiPolicyTracker->id, $activity_name, $description);
        return to_route('backend.ai_policy_tracker.index')->with('success', 'Successfully Created');
    }

    public function edit($id)
    {
        try {
            $aiPolicyTracker = AiPolicyTracker::find($id);

            if (!$aiPolicyTracker) {
                return to_route('backend.ai_policy_tracker.index')->with('error', 'Not founded (AI) policy tracker');
            }

            // Return the updated tracker data
            return response()->json(['aiPolicyTracker' => $aiPolicyTracker]);
        } catch (\Throwable $th) {
            report($th);

            return to_route('backend.ai_policy_tracker.index')->with('error', 'Oops! Somethings went wrong');
        }
    }

    public function update(AiPolicyTrackerPostRequest $request, $id)
    {
        $validated = $request->validated();

        try {
            $aiPolicyTracker = AiPolicyTracker::find($id);

            if (!$aiPolicyTracker) {
                return to_route('backend.ai_policy_tracker.index')->with('error', 'Not founded (AI) policy tracker');
            }

            //-- Log the AI policy activity (only status Update)
            if ($validated['status_id'] !== $aiPolicyTracker->status_id) {
                $activity_name = 'status update';
                $description = 'AI policy, <b>' . $aiPolicyTracker->ai_policy_name . '</b>, status has been updated.';
                AiPolicyActivityLogHelper::createAiPolicyActivityLog($aiPolicyTracker->id, $activity_name, $description);
            }

            $aiPolicyTracker->update($validated);


            //-- Log the AI policy activity (Update)
            $activity_name = 'updated data';
            $description = 'AI policy, <b>' . $aiPolicyTracker->ai_policy_name . '</b>, has been Updated.';
            AiPolicyActivityLogHelper::createAiPolicyActivityLog($aiPolicyTracker->id, $activity_name, $description);

            $method = "update";


            // Dispatch the job with a delay of 1 minute
            SendAiPolicyTrackerNotificationJob::dispatch($aiPolicyTracker, $method)
                ->delay(now()->addMinute());

            return to_route('backend.ai_policy_tracker.index')->with('success', 'SuccessFully Updated');
        } catch (\Throwable $th) {
            report($th);

            return to_route('backend.ai_policy_tracker.index')->with('error', 'Oops! Somethings went wrong');
        }
    }

    public function delete($id)
    {
        try {
            $aiPolicyTracker = AiPolicyTracker::find($id);

            if (!$aiPolicyTracker) {
                return redirect()->route('backend.ai_policy_tracker.index')
                    ->with('error', 'AI Policy Tracker not found.');
            }

            $aiPolicyName = $aiPolicyTracker->ai_policy_name;
            $aiPolicyId = $aiPolicyTracker->id;

            $aiPolicyTracker->delete();

            // Log the AI policy activity (Delete)
            $activity_name = 'delete data';
            $description = 'AI policy, <b>' . e($aiPolicyName) . '</b>, has been deleted.';
            AiPolicyActivityLogHelper::createAiPolicyActivityLog($aiPolicyId, $activity_name, $description);

            return redirect()->route('backend.ai_policy_tracker.index')
                ->with('success', 'Successfully deleted.');
        } catch (\Throwable $th) {
            report($th);

            return redirect()->route('backend.ai_policy_tracker.index')
                ->with('error', 'Oops! Something went wrong.');
        }
    }

    public function search(Request $request)
    {
        try {
            $searchTerm = $request->input('name');

            $query = AiPolicyTracker::with(['country', 'status'])
                ->withCount('bookmarks')
                ->orderBy('created_at', 'desc');

            if (!empty($searchTerm)) {
                $query->where('ai_policy_name', 'LIKE', '%' . $searchTerm . '%');
            }
            $tableData = $query->paginate(10);

            return response()->json($tableData);

        } catch (\Throwable $th) {
            report($th);
            return response()->json([
                'message' => 'An error occurred while fetching the data.',
            ], 500);
        }
    }
}
