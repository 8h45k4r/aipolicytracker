<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProfileUpdateRequest;
use App\Models\ResourceDownload;
use App\Support\Seo;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Redirect;
use Illuminate\View\View;

class ProfileController extends Controller
{
    /**
     * Display the user's profile form.
     */
    public function edit(Request $request): View
    {
        $user = $request->user();
        $seo = Seo::make('Your account', 'Profile, password, downloads and consent settings.', route('profile.edit'), false)->noindex()
            ->withBreadcrumbs([['Home', route('home')], ['Your account', route('profile.edit')]]);
        $downloads = ResourceDownload::with('tool')->where('user_id', $user->id)->orderByDesc('id')->limit(20)->get();

        $subscription = $user->activeSubscription();
        $billingEnabled = (bool) config('billing.enabled');

        return view('site.account.profile', compact('seo', 'user', 'downloads', 'subscription', 'billingEnabled'));
    }

    /**
     * Update the user's profile information.
     */
    public function update(ProfileUpdateRequest $request): RedirectResponse
    {
        $request->user()->fill($request->validated());
        $request->user()->organization_name = $request->input('organization_name') ?: null;
        $request->user()->marketing_consent_at = $request->boolean('marketing_consent') ? ($request->user()->marketing_consent_at ?? now()) : null;

        if ($request->user()->isDirty('email')) {
            $request->user()->email_verified_at = null;
        }

        $request->user()->save();

        return Redirect::route('profile.edit')->with('status', 'profile-updated');
    }

    /**
     * Delete the user's account.
     */
    public function destroy(Request $request): RedirectResponse
    {
        $request->validate([
            'password' => ['required', 'current_password'],
        ]);

        $user = $request->user();

        Auth::logout();

        $user->delete();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return Redirect::to('/');
    }
}
