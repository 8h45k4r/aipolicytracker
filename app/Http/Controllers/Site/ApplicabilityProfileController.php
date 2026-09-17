<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Models\ApplicabilityProfile;
use App\Services\Applicability\ApplicabilityScreener;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Saves an applicability screen to the account so the daily alert can report
 * changes against it. Pro capability (saved.server); the screen itself is free.
 */
class ApplicabilityProfileController extends Controller
{
    public function store(Request $request, ApplicabilityScreener $screener): RedirectResponse
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:120']]);
        $answers = $screener->normalise($request->input('answers', []));
        if ($answers['jurisdictions'] === []) {
            return back()->with('error', 'Choose at least one jurisdiction before saving a profile.');
        }
        $user = $request->user();
        $existing = ApplicabilityProfile::where('user_id', $user->id)->get();
        if ($existing->contains(fn (ApplicabilityProfile $p) => $p->matchesAnswers($answers))) {
            return redirect()->route('following.index')->with('status', 'profile-exists');
        }
        if ($existing->count() >= ApplicabilityProfile::MAX_PER_USER) {
            return back()->with('error', 'You have reached '.ApplicabilityProfile::MAX_PER_USER.' profiles. Delete one first.');
        }
        ApplicabilityProfile::create(['user_id' => $user->id, 'name' => $data['name'], 'answers' => $answers]);

        return redirect()->route('following.index')->with('status', 'profile-saved');
    }

    public function destroy(Request $request, ApplicabilityProfile $profile): RedirectResponse
    {
        abort_unless($profile->user_id === $request->user()->id, 404);
        $profile->delete();

        return redirect()->route('following.index')->with('status', 'profile-deleted');
    }
}
