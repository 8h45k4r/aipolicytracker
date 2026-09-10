<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Models\AiPolicyTracker;
use App\Models\Country;
use App\Models\News;
use App\Models\Status;
use App\Models\Thumbnail;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;

class NewsController extends Controller
{
    public function index()
    {
        //-- get countries list
        $countries = Country::select('id', 'name')
            ->orderBy('name', 'asc')
            ->get()
            ->map(function ($country) {
                return [
                    'value' => $country->id,
                    'label' => $country->name,
                ];
            });

        //-- get news Categories
        $newsCategories = Status::select("id", "name")
            ->get()
            ->map(function ($value) {
                return [
                    "value" => $value->id,
                    "label" => $value->name,
                ];
            });



        $aiPolicyTrackers = AiPolicyTracker::select("id", "ai_policy_name")
            ->get()
            ->map(function ($value) {
                return [
                    "value" => $value->id,
                    "label" => $value->ai_policy_name,
                ];
            });

        $tableData = News::with(['thumbnail', 'status', 'policyTracker'])
            ->orderBy('created_at', 'DESC')
            ->paginate(10);

        return Inertia::render("Backend/News/Index", [
            'countries' => $countries,
            'categories' => $newsCategories,
            'aiPolicyTrackers' => $aiPolicyTrackers,
            'tableData' => $tableData,
        ]);
    }

    public function store(Request $request)
    {

        $validate = $request->validate([
            'title' => 'required|string|max:255',
            'status_id' => 'nullable|string',
            'policy_tracker_id' => 'nullable|string',
            'upload_date' => 'required|date',
            'description' => 'sometimes|nullable|string',
            // 'thumbnails.*' => 'required|image|mimes:jpeg,png,jpg|max:2048', // Validation for multiple images

            'thumbnail' => 'required|mimes:jpeg,png,jpg|max:2048', // Validation for multiple images

            // 'future_images.*' => 'image|mimes:jpeg,png,jpg|max:2048'
        ]);
        DB::beginTransaction();
        // try {
        $news = News::create($validate);
        if ($request->hasFile('thumbnail')) {
            $thumbnail = $request->file('thumbnail');
            $fileName = Str::uuid() . '.' . $thumbnail->getClientOriginalExtension();

            $storedPath = Storage::disk('public')
                ->put(
                    path: 'thumbnails/' . $fileName,
                    contents: $request->file('thumbnail'),

                );
            // $storedPath = Storage::disk('public')
            //     ->putFileAs(
            //         path: 'thumbnails',
            //         file: $thumbnail,
            //         name: $fileName
            //     );

            if (!$storedPath) {
                throw new \Exception('File upload failed.');
            }

            $news->thumbnail()->create([
                'type' => $thumbnail->getMimeType(),
                'name' => $fileName,
                'path' => $storedPath,
            ]);
            // $this->fileUpload(imageFile: $thumbnail, folderPath: "thumbnails", news: $news);
        }
        // if ($request->hasFile('future_images')) {
        //     $futureImages = $request->future_images;
        //     foreach ($futureImages as $futureImage) {
        //         $fileName = time() . '-' . $futureImage->getClientOriginalName();
        //         $filePath = $futureImage->storeAs('future_images', $fileName, 'public');
        //         $relativePath = str_replace('public/', '', $filePath);

        //         $news->newsFutureImage()->create([
        //             'type' => $futureImage->getMimeType(),
        //             'name' => $futureImage->getClientOriginalName(),
        //             'path' => $relativePath,
        //         ]);
        //     }
        // }
        DB::commit();
        return to_route('backend.news.index')->with('success', 'SuccessFully Created');
        // } catch (\Throwable $th) {
        //     report($th);
        //     DB::rollBack();
        //     return to_route('backend.news.index')->with('error', 'Oops! Something went wrong');
        // }
    }

    public function updateData($id)
    {
        try {
            $news = News::with('thumbnail')->find($id);
            if (!$news) {

                return to_route('backend.news.index')->with('error', 'Not founded');
            }
            return response()->json(['news' => $news]);
        } catch (\Throwable $th) {
            report($th);
            return to_route('backend.news.index')->with('error', 'Oops! Somethings went wrong');
        }
    }

    public function update(Request $request, $id)
    {
        $validate = $request->validate([
            'title' => 'required|string|max:255',
            'status_id' => 'nullable|string',
            'upload_date' => 'required|date',
            'description' => 'sometimes|nullable|string',
            'policy_tracker_id' => 'nullable|string',
            'thumbnail' => 'image|mimes:jpeg,png,jpg|max:2048',
            // 'future_images.*' => 'image|mimes:jpeg,png,jpg,gif|max:2048'
        ]);

        try {
            $news = News::find($id);
            if (!$news) {
                return to_route('backend.news.index')->with('error', 'Not founded');
            }
            if ($request->hasFile('thumbnail')) {
                $thumbnail = $request->thumbnail;
                $fileName = Str::uuid() . '.' . $thumbnail->getClientOriginalExtension();

                // $filePath = $thumbnails->storeAs("thumbnails", $fileName, 'public');
                // $relativePath = str_replace('public/', '', $filePath);

                $filePath = Storage::disk('public')->putFileAs("thumbnails", $thumbnail, $fileName);

                if (!$filePath) {
                    throw new \Exception('File upload failed.');
                }

                Thumbnail::updateOrCreate(['news_id' => $news->id], [
                    'type' => $thumbnail->getMimeType(),
                    'name' => $thumbnail->getClientOriginalName(),
                    'path' => $filePath,
                ]);
            }

            $news->update($validate);
            return to_route('backend.news.index')->with('success', 'SuccessFully Updated');
        } catch (\Throwable $th) {
            report($th);
            return to_route('backend.news.index')->with('error', 'Oops! Somethings went wrong');
        }
    }

    public function delete($id)
    {
        try {
            $news = News::find($id);
            if (!$news) {
                return to_route('backend.news.index')->with('error', 'Not founded');
            }
            $news->delete();
            return to_route('backend.news.index')->with('success', 'SuccessFully Deleted');
        } catch (\Throwable $th) {
            report($th);
            return to_route('backend.news.index')->with('error', 'Oops! Somethings went wrong');
        }
    }
    // file upload
    private function fileUpload($imageFile, string $folderPath, News $news)
    {
        $fileName = Str::uuid() . '.' . $imageFile->getClientOriginalExtension();

        $storedPath = Storage::disk('public')
            ->putFileAs(path: $folderPath, file: $imageFile, name: $fileName);

        if (!$storedPath) {
            throw new \Exception('File upload failed.');
        }

        $news->thumbnail()->create([
            'type' => $imageFile->getMimeType(),
            'name' => $fileName,
            'path' => $storedPath,
        ]);

        return $storedPath;
    }


    public function search(Request $request)
    {
        try {
            $searchTerm = $request->input('name');

            $query = News::with(['thumbnail', 'status', 'policyTracker'])
                ->orderBy('created_at', 'DESC');
            if (!empty($searchTerm)) {
                $query->where('title', 'LIKE', '%' . $searchTerm . '%');
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
