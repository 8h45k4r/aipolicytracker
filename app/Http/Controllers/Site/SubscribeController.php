<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Mail\SubscriptionConfirmMail;
use App\Models\ChangeEvent;
use App\Models\Jurisdiction;
use App\Models\Subscriber;
use App\Rules\NotDisposableEmail;
use App\Support\Seo;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\View\View;

/**
 * Double opt-in email subscriptions to the weekly change-log digest.
 * Personal data kept: the address, chosen topics and timestamps only.
 */
class SubscribeController extends Controller
{
    /** Full subscription page: choose jurisdictions (or everything) and subscribe. */
    public function show(Request $request): View
    {
        $seo = Seo::make(
            'Subscribe: weekly AI policy digest by jurisdiction',
            'A weekly email of dated, source-linked AI policy changes and upcoming application dates. Pick the jurisdictions you follow, or receive everything. Double opt-in, one-click unsubscribe.',
            route('subscribe.show')
        )->withBreadcrumbs([['Home', route('home')], ['Subscribe', route('subscribe.show')]]);

        $jurisdictions = Jurisdiction::published()->orderBy('region')->orderBy('name')->get(['slug', 'name', 'region', 'jurisdiction_type'])->groupBy('region');
        $selected = array_values(array_filter((array) $request->query('topics', []), fn ($t) => is_string($t) && preg_match('/^[a-z0-9-]+$/', $t)));
        $recent = ChangeEvent::published()->with('jurisdiction')->orderByDesc('occurred_on')->limit(5)->get();

        return view('site.subscribe.show', ['seo' => $seo, 'jurisdictions' => $jurisdictions, 'selected' => $selected, 'recent' => $recent, 'subscriberCount' => Subscriber::active()->count()]);
    }

    public function store(Request $request): RedirectResponse
    {
        if ($request->filled('website')) { // honeypot
            return back()->with('success', 'Check your inbox to confirm your subscription.');
        }
        $data = $request->validate([
            'email' => ['required', 'email:rfc', 'max:190', new NotDisposableEmail],
            'topics' => ['nullable', 'array', 'max:20'],
            'topics.*' => ['string', 'max:64', 'regex:/^[a-z0-9-]+$/'],
            'source' => ['nullable', 'string', 'max:64'],
        ]);
        $topics = array_values(array_unique($data['topics'] ?? [])) ?: ['all'];

        $subscriber = Subscriber::firstOrNew(['email' => strtolower($data['email'])]);

        // An address that is already receiving the digest is left exactly as it
        // is. The form is public, so anyone who knows an address could otherwise
        // rewrite its topics; the owner changes them from the link in any email.
        // The reply is the same in every case so the form cannot be used to find
        // out whether an address is subscribed.
        if ($subscriber->exists && $subscriber->isActive()) {
            return back()->with('success', 'Check your inbox to confirm your subscription.');
        }

        // At most three confirmation emails per address per day, whoever asks. The
        // per-IP throttle alone let anyone mail an unconfirmed address thousands of
        // times a day and keep replacing its token so the owner could never confirm.
        $key = 'subscribe-confirm:'.sha1(strtolower($data['email']));
        if (RateLimiter::tooManyAttempts($key, 3)) {
            return back()->with('success', 'Check your inbox to confirm your subscription.');
        }
        RateLimiter::hit($key, 86400);

        // An address that opted out, or one that never confirmed, starts over:
        // new topics, a new token, and a fresh confirmation the owner has to click.
        // Anything less would let a third party quietly re-enrol someone who left.
        $subscriber->fill(['topics' => $topics, 'source' => $data['source'] ?? null, 'confirmed_at' => null, 'unsubscribed_at' => null]);
        $subscriber->token = Subscriber::newToken();
        $subscriber->save();

        Mail::to($subscriber->email)->send(new SubscriptionConfirmMail($subscriber));

        return back()->with('success', 'Check your inbox to confirm your subscription.');
    }

    public function confirm(string $token): View
    {
        $subscriber = Subscriber::where('token', $token)->firstOrFail();
        if (! $subscriber->confirmed_at) {
            $subscriber->update(['confirmed_at' => now(), 'unsubscribed_at' => null]);
        }
        $seo = Seo::make('Subscription confirmed', 'Your weekly AI policy digest subscription is active.', route('subscribe.confirm', $token), false)->noindex();

        return view('site.subscribe.confirmed', ['seo' => $seo, 'subscriber' => $subscriber]);
    }

    public function unsubscribe(string $token): View
    {
        $subscriber = Subscriber::where('token', $token)->firstOrFail();
        $subscriber->update(['unsubscribed_at' => now()]);
        $seo = Seo::make('Unsubscribed', 'You will no longer receive the AI policy digest.', route('subscribe.unsubscribe', $token), false)->noindex();

        return view('site.subscribe.unsubscribed', ['seo' => $seo, 'subscriber' => $subscriber]);
    }

    /** RFC 8058 one-click unsubscribe (List-Unsubscribe-Post). */
    public function unsubscribePost(string $token): RedirectResponse
    {
        Subscriber::where('token', $token)->update(['unsubscribed_at' => now()]);

        return redirect()->route('subscribe.unsubscribe', $token);
    }

    public static function topicOptions()
    {
        return Jurisdiction::published()->orderBy('name')->get(['slug', 'name']);
    }
}
