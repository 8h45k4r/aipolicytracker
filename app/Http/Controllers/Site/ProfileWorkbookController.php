<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Models\ApplicabilityProfile;
use App\Models\ProfileWorkbookVersion;
use App\Services\Applicability\ApplicabilityScreener;
use App\Services\Workbooks\ComplianceWorkbook;
use App\Support\Seo;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * A saved profile as a declared AI system: what it is, who owns it, and its
 * compliance workbook with version history. The account's own page; never
 * indexed, never shown to anyone else.
 */
class ProfileWorkbookController extends Controller
{
    public function show(Request $request, ApplicabilityProfile $profile, ComplianceWorkbook $workbook, ApplicabilityScreener $screener): View
    {
        $this->authorizeOwner($request, $profile);
        $canWorkbook = $request->user()->entitled('profiles.workbook');
        $current = $canWorkbook ? $workbook->current($profile) : null;
        $versions = $canWorkbook ? $profile->workbookVersions()->limit(20)->get() : collect();
        $summary = $screener->summarise($screener->screen($profile->answers)['obligations']);
        $seo = Seo::make($profile->name, 'A declared AI system and its compliance workbook.', route('profiles.show', $profile), false)->noindex()
            ->withBreadcrumbs([['Home', route('home')], ['Your account', route('profile.edit')], ['Following', route('following.index')], [$profile->name, route('profiles.show', $profile)]]);

        return view('site.account.profile-show', compact('seo', 'profile', 'current', 'versions', 'summary', 'canWorkbook'));
    }

    public function update(Request $request, ApplicabilityProfile $profile): RedirectResponse
    {
        $this->authorizeOwner($request, $profile);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'system_description' => ['nullable', 'string', 'max:1000'],
            'system_owner' => ['nullable', 'string', 'max:120'],
            'lifecycle' => ['nullable', Rule::in(array_keys(ApplicabilityProfile::LIFECYCLE))],
        ]);
        $profile->update($data);

        return redirect()->route('profiles.show', $profile)->with('status', 'system-updated');
    }

    /** The latest version, or an earlier one by number, as XLSX. */
    public function download(Request $request, ApplicabilityProfile $profile, ComplianceWorkbook $workbook, ?int $version = null): Response
    {
        $this->authorizeOwner($request, $profile);
        $chosen = $version === null
            ? $workbook->current($profile)
            : ProfileWorkbookVersion::where('applicability_profile_id', $profile->id)->where('version', $version)->firstOrFail();
        $name = Str::slug($profile->name) ?: 'system';

        return response($workbook->xlsx($profile, $chosen), 200, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => 'attachment; filename="compliance-workbook-'.$name.'-v'.$chosen->version.'.xlsx"',
            'X-Robots-Tag' => 'noindex',
            'Cache-Control' => 'private, no-store',
        ]);
    }

    /** Another account's profile is a 404, not a 403: its existence is not disclosed. */
    private function authorizeOwner(Request $request, ApplicabilityProfile $profile): void
    {
        abort_unless($profile->user_id === $request->user()->id, 404);
    }
}
