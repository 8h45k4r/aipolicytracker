<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Models\AlertChannel;
use App\Models\ConsentEvent;
use App\Models\User;
use App\Services\Alerts\AlertBuilder;
use App\Services\Alerts\WebhookDispatcher;
use App\Support\Seo;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

/**
 * Alert channels beyond the inbox: a private RSS feed, Slack, a signed
 * webhook. Every change of consent is written to consent_events. The
 * unsubscribe link in every alert works without signing in (a signed URL).
 */
class AlertChannelController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();
        $data = $request->validate([
            'kind' => ['required', 'in:rss,slack,webhook'],
            'endpoint' => ['required_if:kind,slack,webhook', 'nullable', 'url:https', 'max:2000'],
        ]);
        abort_if(AlertChannel::where('user_id', $user->id)->count() >= AlertChannel::MAX_PER_USER, 422, 'Channel limit reached');
        if ($data['kind'] === 'slack' && ! str_starts_with((string) $data['endpoint'], 'https://hooks.slack.com/')) {
            return back()->withErrors(['endpoint' => 'A Slack incoming webhook starts with https://hooks.slack.com/.'])->withInput();
        }
        if ($data['kind'] === 'rss' && AlertChannel::where('user_id', $user->id)->where('kind', 'rss')->exists()) {
            return redirect()->route('following.index')->with('status', 'channel-exists');
        }
        $secret = AlertChannel::newSecret();
        $channel = AlertChannel::create(['user_id' => $user->id, 'kind' => $data['kind'], 'endpoint' => $data['endpoint'] ?? null, 'secret' => $secret, 'enabled' => true, 'confirmed_at' => now()]);
        ConsentEvent::record($user, 'alerts.channel', true, 'account', $channel->kind.($channel->endpoint ? ' '.$channel->endpointLabel() : ''));

        return redirect()->route('following.index')->with('status', 'channel-added')->with('channel_secret', $channel->kind === 'webhook' ? $secret : null)->with('channel_id', $channel->id);
    }

    public function toggle(Request $request, AlertChannel $channel): RedirectResponse
    {
        abort_unless($channel->user_id === $request->user()->id, 404);
        $channel->update(['enabled' => ! $channel->enabled]);
        ConsentEvent::record($request->user(), $channel->kind === 'email' ? 'alerts.email' : 'alerts.channel', $channel->enabled, 'account', $channel->kind);

        return redirect()->route('following.index')->with('status', $channel->enabled ? 'channel-enabled' : 'channel-disabled');
    }

    public function destroy(Request $request, AlertChannel $channel): RedirectResponse
    {
        abort_unless($channel->user_id === $request->user()->id, 404);
        ConsentEvent::record($request->user(), 'alerts.channel', false, 'account', $channel->kind.' removed');
        $channel->delete();

        return redirect()->route('following.index')->with('status', 'channel-removed');
    }

    /** Sends a test payload now, so an endpoint can be checked before the first real alert. */
    public function test(Request $request, AlertChannel $channel, WebhookDispatcher $dispatcher): RedirectResponse
    {
        abort_unless($channel->user_id === $request->user()->id && in_array($channel->kind, ['slack', 'webhook'], true), 404);
        $payload = ['event' => 'alert.test', 'title' => 'Test alert from AIPolicyTracker', 'sent_at' => now()->toAtomString(), 'changes' => [], 'deadlines' => [], 'note' => 'If you can read this, the channel works. Real alerts carry the same shape with changes and deadlines filled in.', 'manage_url' => route('following.index')];
        $delivery = $dispatcher->queue($channel, $payload);
        $ok = $dispatcher->attempt($delivery);

        return redirect()->route('following.index')->with('status', $ok ? 'channel-test-ok' : 'channel-test-failed')->with('channel_error', $delivery->fresh()->last_error);
    }

    /** The private feed: the account's watched changes of the last 30 days, by token. */
    public function feed(string $token, AlertBuilder $builder): Response
    {
        $channel = AlertChannel::where('kind', 'rss')->where('secret', $token)->where('enabled', true)->first();
        abort_unless($channel, 404);
        $digest = $builder->build($channel->user, now()->subDays(30), now());
        $changes = $digest['changes'];
        $body = view('site.changes.feed', ['changes' => $changes, 'title' => 'AIPolicyTracker: your watched changes', 'link' => route('following.index'), 'description' => 'Changes to the records, jurisdictions, sectors, frameworks and searches this account watches, last 30 days. Private feed; do not share the address.', 'self' => route('alerts.feed', $token)])->render();

        return response($body, 200, ['Content-Type' => 'application/rss+xml; charset=UTF-8', 'Cache-Control' => 'private, no-store', 'X-Robots-Tag' => 'noindex']);
    }

    /** Unsubscribe without signing in: a signed link from every alert email. GET confirms, POST acts. */
    public function unsubscribe(Request $request, User $user): View|RedirectResponse
    {
        $seo = Seo::make('Stop daily alerts', 'Turn off alert emails for this account.', route('alerts.unsubscribe', $user->id), false)->noindex();
        if ($request->isMethod('post')) {
            $channel = AlertChannel::firstOrCreate(['user_id' => $user->id, 'kind' => 'email'], ['enabled' => true, 'confirmed_at' => $user->email_verified_at]);
            $channel->update(['enabled' => false]);
            ConsentEvent::record($user, 'alerts.email', false, 'unsubscribe-link');

            return redirect()->to(request()->fullUrl())->with('status', 'unsubscribed');
        }

        return view('site.account.unsubscribe', ['seo' => $seo, 'user' => $user, 'done' => session('status') === 'unsubscribed']);
    }

    /** Everything held about the account, as JSON, for the person it is about. Secrets are not included. */
    public function export(Request $request): Response
    {
        $user = $request->user();
        $data = [
            'exported_at' => now()->toAtomString(),
            'account' => ['name' => $user->name, 'email' => $user->email, 'organization_name' => $user->organization_name, 'created_at' => $user->created_at?->toAtomString(), 'email_verified_at' => $user->email_verified_at?->toAtomString(), 'marketing_consent_at' => $user->marketing_consent_at?->toAtomString(), 'terms_accepted_at' => $user->terms_accepted_at?->toAtomString()],
            'watches' => $user->follows()->get()->map(fn ($f) => ['type' => $f->subject_type, 'subject' => $f->subject_slug, 'label' => $f->label, 'params' => $f->params, 'since' => $f->created_at?->toAtomString()])->all(),
            'profiles' => $user->applicabilityProfiles()->get()->map(fn ($p) => ['name' => $p->name, 'answers' => $p->answers, 'created_at' => $p->created_at?->toAtomString()])->all(),
            'channels' => AlertChannel::where('user_id', $user->id)->get()->map(fn ($c) => ['kind' => $c->kind, 'endpoint' => $c->endpoint, 'enabled' => $c->enabled, 'created_at' => $c->created_at?->toAtomString(), 'last_delivered_at' => $c->last_delivered_at?->toAtomString()])->all(),
            'alert_deliveries' => $user->alertDeliveries()->get()->map(fn ($d) => ['sent_on' => $d->sent_on?->toDateString(), 'changes' => $d->changes_count, 'deadlines' => $d->deadlines_count])->all(),
            'consent_events' => ConsentEvent::where('user_id', $user->id)->orderBy('id')->get()->map(fn ($e) => ['kind' => $e->kind, 'granted' => $e->granted, 'source' => $e->source, 'detail' => $e->detail, 'at' => $e->created_at?->toAtomString()])->all(),
            'downloads' => $user->resourceDownloads()->get()->map(fn ($d) => ['tool' => $d->resource_slug, 'version' => $d->version, 'at' => $d->created_at?->toAtomString()])->all(),
        ];

        return response(json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), 200, ['Content-Type' => 'application/json', 'Content-Disposition' => 'attachment; filename="aipolicytracker-account.json"', 'Cache-Control' => 'private, no-store']);
    }

    /** Turns the inbox off from the alerts page (no email channel row exists until the first change). */
    public function emailOff(Request $request): RedirectResponse
    {
        $channel = AlertChannel::firstOrCreate(['user_id' => $request->user()->id, 'kind' => 'email'], ['enabled' => true, 'confirmed_at' => $request->user()->email_verified_at]);
        $channel->update(['enabled' => false]);
        ConsentEvent::record($request->user(), 'alerts.email', false, 'account');

        return redirect()->route('following.index')->with('status', 'channel-disabled');
    }

    /** A personal access token for /api/v1/watches, shown once. Creating a new one revokes the old. */
    public function apiToken(Request $request): RedirectResponse
    {
        $user = $request->user();
        $user->tokens()->where('name', 'watches')->delete();
        $token = $user->createToken('watches', ['watches'])->plainTextToken;
        ConsentEvent::record($user, 'api.token', true, 'account', 'watches');

        return redirect()->route('profile.edit')->with('api_token', $token);
    }
}
