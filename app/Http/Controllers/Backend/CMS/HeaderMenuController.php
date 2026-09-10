<?php

namespace App\Http\Controllers\Backend\CMS;

use App\Http\Controllers\Controller;
use App\Models\ContributingOrg;
use App\Models\NavBar;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;

class HeaderMenuController extends Controller
{
    public function index()
    {
        $data['logo'] = NavBar::first();
        $data['contributing_orgs'] = ContributingOrg::query()
            ->get();

        return Inertia::render("Backend/Cms/HeaderMenu", $data);
    }

    public function store(Request $request)
    {

        $validated = $request->validate([
            'logo' => 'sometimes|nullable|mimes:png,jpg|max:2048',
            // 'navBars.*' => 'sometimes|nullable',
            // 'aiPolicyTrackeData.*' => 'sometimes|nullable',
            // 'contributorData.*' => 'sometimes|nullable',
            // 'organizationData.*' => 'sometimes|nullable',

            'organizationData' => 'array',
            'organizationData.*.orgLogo' => [
                'nullable',
                function ($attribute, $value, $fail) use ($request) {
                    // Extract the index from organizationData.*.orgLogo
                    $index = explode('.', $attribute)[1];

                    // Get the corresponding URL field
                    $url = $request->input("organizationData.$index.url");

                    if (!empty($value) && empty($url)) {
                        $fail("The URL field is required when an organization logo is provided.");
                    }
                },
            ],
            'organizationData.*.url' => [
                'nullable',
                function ($attribute, $value, $fail) use ($request) {
                    // Extract the index from organizationData.*.url
                    $index = explode('.', $attribute)[1];

                    // Get the corresponding orgLogo field
                    $orgLogo = $request->file("organizationData.$index.orgLogo");

                    if (!empty($value) && empty($orgLogo)) {
                        $fail("The Organization Logo field is required when a URL is provided.");
                    }
                },
            ],
        ]);
        DB::beginTransaction();
        // try {
        if ($request->hasFile('logo')) {

            $file = $request->file('logo');
            $fileName = Str::uuid() . '.' . $file->getClientOriginalExtension();
            $filePath = Storage::disk('public')
                ->put(
                    path: 'logos/' . $fileName,
                    contents: $file,
                );
            // $filePath = Storage::disk('public')->putFileAs("logos", $file, $fileName);

            if (!$filePath) {
                throw new \Exception('File upload failed.');
            }
            $navBar = NavBar::first();
            if (!$navBar) {
                NavBar::create(
                    [
                        'user_id' => Auth::id(),
                        'name' => $fileName,
                        'file_path' => $filePath,
                    ]
                );
            } else {
                $navBar->update(
                    [
                        'user_id' => Auth::id(),
                        'name' => $fileName,
                        'file_path' => $filePath,
                    ]
                );
            }
        }

        foreach ($validated["organizationData"] as $orgData) {

            if (isset($orgData["orgLogo"]) && isset($orgData["url"])) {
                $fileName = Str::uuid() . '.' . $orgData["orgLogo"]->getClientOriginalExtension();
                // $logoPath = Storage::disk('public')->putFileAs(
                //     "contrubuting_organization_logos",
                //     $orgData["orgLogo"],
                //     $fileName
                // );
                $logoPath = Storage::disk('public')
                    ->put(
                        path: 'contrubuting_organization_logos/' . $fileName,
                        contents: $orgData["orgLogo"],
                    );
                if (!$logoPath) {
                    throw new \Exception('File upload failed.');
                }

                ContributingOrg::create([
                    'user_id' => Auth::id(),
                    'name' => $fileName,
                    'file_path' => $logoPath,
                    'url' => $orgData["url"],
                ]);
            }
        }

        DB::commit();
        return redirect()->route('backend.header_menu.index')->with('success', 'SuccessFully Created');
        // } catch (\Throwable $th) {
        //     report($th);
        //     DB::rollBack();
        //     return to_route('backend.header_menu.index')->with('error', 'Oops! Something went wrong');
        // }


    }

    public function showContributingOrgIndex()
    {
        $data['logo'] = NavBar::first();
        $data['tableData'] = ContributingOrg::latest()
            ->paginate(10);

        return Inertia::render("Backend/Cms/ContributingOrg/Index", $data);
    }


    public function contributingOrgDelete($id)
    {
        try {
            $org = ContributingOrg::find($id);
            if (!$org) {
                return redirect()->route('backend.header_menu.showContributingOrgIndex')->with('error', 'Not founded');
            }
            $org->delete();
            return to_route('backend.header_menu.showContributingOrgIndex')->with('success', 'SuccessFully Deleted');
        } catch (\Throwable $th) {
            report($th);
            return redirect()->route('backend.header_menu.showContributingOrgIndex')->with('error', 'Oops! Somethings went wrong');
        }
    }
}
