<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProfileUpdateRequest;
use App\Mail\EmailChangedMail;
use App\Models\ResourceDownload;
use App\Models\Subscriber;
use App\Models\TemplateDownloadRequest;
use App\Models\TemplateUpdateNotice;
use App\Services\Billing\BillingConfig;
use App\Services\Subscribers\AccountDigest;
use App\Support\Seo;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
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
        $billingEnabled = app(BillingConfig::class)->enabled();

        return view('site.account.profile', compact('seo', 'user', 'downloads', 'subscription', 'billingEnabled') + ['digestActive' => app(AccountDigest::class)->active($user)]);
    }

    /**
     * Update the user's profile information.
     */
    public function update(ProfileUpdateRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $request->user()->fill(Arr::only($data, ['name', 'email']));
        $request->user()->organization_name = ($data['organization_name'] ?? null) ?: null;

        $oldEmail = $request->user()->getOriginal('email');
        $emailChanged = $request->user()->isDirty('email');
        if ($emailChanged) {
            $request->user()->email_verified_at = null;
        }

        $request->user()->save();
        if ($emailChanged) {
            // Tell the old address. A failed send is reported, not shown: the change is made.
            try {
                Mail::to($oldEmail)->send(new EmailChangedMail($request->user()->name, $request->user()->email));
            } catch (\Throwable $e) {
                report($e);
            }
        }
        // The box shows whether the address receives the digest, so saving it unchanged changes nothing.
        app(AccountDigest::class)->set($request->user(), $request->boolean('marketing_consent'));

        return Redirect::route('profile.edit')->with('status', 'profile-updated');
    }

    /**
     * Delete the user's account.
     */
    public function destroy(Request $request): RedirectResponse
    {
        // The form shows errors from the userDeletion bag; a wrong password went to the
        // default bag and the page reloaded without saying why.
        $request->validateWithBag('userDeletion', [
            'password' => ['required', 'current_password'],
        ]);

        $user = $request->user();

        // A paid subscription is billed by the payment provider, not by this site: deleting
        // the account would leave it charging with nobody able to manage it here. The reader
        // cancels it first (Manage billing), then deletes.
        if (app(BillingConfig::class)->enabled() && $user->activeSubscription()) {
            return Redirect::route('profile.edit')->withErrors(['password' => 'Cancel your subscription under Manage billing first. Your account can be deleted once it has ended.'], 'userDeletion');
        }

        Auth::logout();

        DB::transaction(function () use ($user) {
            // What is kept by email address rather than by account goes too: the digest
            // subscription, template requests and the update emails queued for them. The
            // unsubscribe from template updates is kept so the address stays unsubscribed.
            // Contributions and licence enquiries stay (see the privacy notice), as do
            // billing records the law requires a merchant to keep.
            $email = strtolower($user->email);
            Subscriber::whereRaw('LOWER(email) = ?', [$email])->delete();
            TemplateDownloadRequest::whereRaw('LOWER(email) = ?', [$email])->delete();
            TemplateUpdateNotice::where('email', $email)->delete();
            // Personal access tokens are polymorphic, with no foreign key to cascade.
            $user->tokens()->delete();
            $user->delete();
        });

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return Redirect::to('/');
    }
}
