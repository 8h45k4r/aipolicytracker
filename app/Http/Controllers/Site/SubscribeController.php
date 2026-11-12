<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Mail\SubscriptionConfirmMail;
use App\Models\ChangeEvent;
use App\Models\Jurisdiction;
use App\Models\Subscriber;
use App\Models\User;
use App\Rules\NotDisposableEmail;
use App\Services\Security\Turnstile;
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

        $jurisdictions = Jurisdiction::published()->withCount(['policyInstruments' => fn ($q) => $q->published()])->orderBy('name')->get();
        $selected = array_values(array_filter((array) $request->query('topics', []), fn ($t) => is_string($t) && preg_match('/^[a-z0-9-]+$/', $t)));
        $recent = ChangeEvent::published()->with('jurisdiction')->orderByDesc('occurred_on')->limit(5)->get();

        return view('site.subscribe.show', ['seo' => $seo, 'jurisdictions' => $jurisdictions, 'selected' => $selected, 'recent' => $recent, 'subscriberCount' => Subscriber::active()->count()]);
    }

    public function store(Request $request, Turnstile $turnstile): RedirectResponse
    {
        if ($request->filled('website')) { // honeypot
            return back()->with('success', 'Check your inbox to confirm your subscription.');
        }
        // Reported under "email" because that is the error every subscribe form shows.
        if ($turnstile->rejects($request)) {
            return back()->withInput()->withErrors(['email' => 'The automated check did not complete. Please wait a moment and subscribe again.']);
        }
        $data = $request->validate([
            // Any real mailbox, personal ones such as Gmail and Outlook included; throwaway and
            // temporary addresses, and domains with no mail route, are refused (NotDisposableEmail).
            'email' => ['required', 'email:rfc', 'max:190', new NotDisposableEmail],
            'topics' => ['nullable', 'array', 'max:20'],
            'topics.*' => ['string', 'max:64', 'regex:/^[a-z0-9-]+$/'],
            'source' => ['nullable', 'string', 'max:64'],
            'terms' => ['accepted'],
        ], ['terms.accepted' => 'Please accept the terms of use and the privacy notice to subscribe.']);
        $topics = array_values(array_unique($data['topics'] ?? []));
        // "Templates" is an extra, not a narrowing: with no jurisdiction or record chosen the
        // digest still covers everything, so ticking only the extra keeps "all" as well.
        if (array_diff($topics, ['templates']) === []) {
            array_unshift($topics, 'all');
        }

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
        $key = 'subscribe-confirm:'.hash('sha256', strtolower($data['email']));
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

        // A mail service that refuses or times out must not turn the form into a server error.
        // The row stays unconfirmed, the failure is logged, and the reader is told to try again.
        try {
            Mail::to($subscriber->email)->send(new SubscriptionConfirmMail($subscriber));
        } catch (\Throwable $e) {
            report($e);

            return back()->withInput()->withErrors(['email' => 'We could not send the confirmation email just now. Please try again in a few minutes.']);
        }

        return back()->with('success', 'Check your inbox to confirm your subscription.');
    }

    /*
     * Opening a link never changes anything. Mail security scanners and link
     * previewers follow every URL in a message, so a GET that confirmed or
     * unsubscribed would complete the double opt-in without a human, or quietly
     * unsubscribe a reader. The link shows a button; the button posts.
     */
    public function confirm(string $token): View
    {
        $subscriber = Subscriber::where('token', $token)->firstOrFail();

        return $this->confirmView($subscriber, pending: ! $subscriber->confirmed_at || $subscriber->unsubscribed_at);
    }

    public function confirmPost(string $token): View
    {
        $subscriber = Subscriber::where('token', $token)->firstOrFail();
        if (! $subscriber->confirmed_at || $subscriber->unsubscribed_at) {
            $subscriber->update(['confirmed_at' => now(), 'unsubscribed_at' => null]);
        }

        return $this->confirmView($subscriber, pending: false);
    }

    public function unsubscribe(string $token): View
    {
        $subscriber = Subscriber::where('token', $token)->firstOrFail();
        $seo = Seo::make('Unsubscribe', 'Stop receiving the AI policy digest.', route('subscribe.unsubscribe', $token), false)->noindex();

        return view('site.subscribe.unsubscribed', ['seo' => $seo, 'subscriber' => $subscriber, 'pending' => $subscriber->unsubscribed_at === null]);
    }

    /** The button on the unsubscribe page, and RFC 8058 one-click unsubscribe (List-Unsubscribe-Post). */
    public function unsubscribePost(string $token): RedirectResponse
    {
        $subscriber = Subscriber::where('token', $token)->first();
        if ($subscriber) {
            $subscriber->update(['unsubscribed_at' => now()]);
            // An account on the same address must not show the digest as on.
            User::whereRaw('LOWER(email) = ?', [strtolower($subscriber->email)])->update(['marketing_consent_at' => null]);
        }

        return redirect()->route('subscribe.unsubscribe', $token);
    }

    private function confirmView(Subscriber $subscriber, bool $pending): View
    {
        $seo = Seo::make($pending ? 'Confirm your subscription' : 'Subscription confirmed', 'Your weekly AI policy digest subscription.', route('subscribe.confirm', $subscriber->token), false)->noindex();

        return view('site.subscribe.confirmed', ['seo' => $seo, 'subscriber' => $subscriber, 'pending' => $pending]);
    }

    public static function topicOptions()
    {
        return Jurisdiction::published()->orderBy('name')->get(['slug', 'name']);
    }
}
