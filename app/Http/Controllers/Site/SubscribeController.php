<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Mail\SubscriptionConfirmMail;
use App\Models\Jurisdiction;
use App\Models\Subscriber;
use App\Support\Seo;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\View\View;

/**
 * Double opt-in email subscriptions to the weekly change-log digest.
 * Personal data kept: the address, chosen topics and timestamps only.
 */
class SubscribeController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        if ($request->filled('website')) { // honeypot
            return back()->with('success', 'Check your inbox to confirm your subscription.');
        }
        $data = $request->validate([
            'email' => ['required', 'email:rfc', 'max:190'],
            'topics' => ['nullable', 'array', 'max:20'],
            'topics.*' => ['string', 'max:64', 'regex:/^[a-z0-9-]+$/'],
            'source' => ['nullable', 'string', 'max:64'],
        ]);
        $topics = array_values(array_unique($data['topics'] ?? [])) ?: ['all'];

        $subscriber = Subscriber::firstOrNew(['email' => strtolower($data['email'])]);
        $subscriber->fill(['topics' => $topics, 'source' => $data['source'] ?? null, 'unsubscribed_at' => null]);
        if (! $subscriber->token) {
            $subscriber->token = Subscriber::newToken();
        }
        $subscriber->save();

        if (! $subscriber->confirmed_at) {
            Mail::to($subscriber->email)->send(new SubscriptionConfirmMail($subscriber));
        }

        return back()->with('success', $subscriber->confirmed_at ? 'Your subscription is already active; topics updated.' : 'Check your inbox to confirm your subscription.');
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
