<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Models\Country;
use Illuminate\Http\Request;
use Inertia\Inertia;

class CountryController extends Controller
{
    public function index()
    {

        $data['tableData'] = Country::orderBy('name', 'asc')->paginate(10);
        return Inertia::render("Backend/Country/Index", $data);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|unique:countries,name|max:255',
            'symbol' => 'required|string|unique:countries,symbol|max:10',
            'status' => 'required|boolean',
        ]);

        $validated['symbol'] = strtoupper($validated['symbol']);

        Country::create($validated);



        return to_route('backend.country.index')->with('success', 'SuccessFully Created');
    }

    public function view($id)
    {
        $country = Country::with(['aiPolicyTrackers'])
            ->withCount(['aiPolicyTrackers'])->find($id);



        // ai_policy_trackers_count

        return response()->json(['country' => $country]);
    }


    public function updatedStatus(Request $request)
    {
        $validated = $request->validate([
            'id' => 'required|uuid|exists:countries,id',
            'status' => 'required|boolean',
        ]);

        try {
            $country = Country::findOrFail($validated['id']);
            $country->update(['status' => (bool) $validated['status']]);

            return response()->json(['message' => "{$country->name} updated successfully"], 200);
        } catch (\Throwable $th) {
            report($th);

            return response()->json(['message' => 'Something went wrong on server'], 500);
        }
    }

    public function search(Request $request)
    {
        try {
            $searchTerm = $request->input('name');

            $query = Country::orderBy('name', 'asc')
                ->orderBy('created_at', 'desc');

            if (!empty($searchTerm)) {
                $query->where('name', 'LIKE', '%' . $searchTerm . '%');
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
