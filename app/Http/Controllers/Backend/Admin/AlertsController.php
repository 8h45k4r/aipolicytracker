<?php

namespace App\Http\Controllers\Backend\Admin;

use App\Http\Controllers\Controller;
use App\Models\AlertChannel;
use App\Models\AlertDelivery;
use App\Models\ApplicabilityProfile;
use App\Models\ChannelDelivery;
use App\Models\ConsentEvent;
use App\Models\Follow;
use App\Models\JobRun;
use App\Services\Alerts\WebhookDispatcher;
use App\Support\Admin\CsvStream;
use App\Support\Admin\ListFilters;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Admin view of the alerts module: who watches what, where alerts go, how Slack and
 * webhook deliveries are doing (with a retry that runs the dispatcher's own attempt),
 * and the consent log. Channel endpoints are shown shortened and never exported in full:
 * a Slack incoming-webhook URL is itself a credential.
 */
class AlertsController extends Controller
{
    public const STATUSES = ['pending', 'sent', 'failed'];

    private const SORTS = ['created' => 'id', 'attempts' => 'attempts', 'next' => 'next_attempt_at', 'status' => 'status'];

    /** Each retry is one outbound request with WebhookDispatcher::TIMEOUT; this keeps a bulk retry inside a request's time. */
    public const RETRY_MAX = 25;

    public function index(Request $request): View
    {
        $filters = ListFilters::from($request, self::SORTS);
        $status = $this->status($request);
        $consentKind = (string) $request->query('consent', '');
        $consentKinds = ConsentEvent::query()->distinct()->orderBy('kind')->pluck('kind');
        if (! $consentKinds->contains($consentKind)) {
            $consentKind = '';
        }

        $channels = AlertChannel::selectRaw('kind, enabled, COUNT(*) as n')->groupBy('kind', 'enabled')->get()
            ->groupBy('kind')->map(fn ($rows) => ['enabled' => (int) $rows->where('enabled', true)->sum('n'), 'total' => (int) $rows->sum('n')]);

        $cards = [
            'accounts' => Follow::query()->distinct()->count('user_id'),
            'watches' => Follow::selectRaw('subject_type, COUNT(*) as n')->groupBy('subject_type')->orderByDesc('n')->pluck('n', 'subject_type'),
            'profiles' => ApplicabilityProfile::count(),
            'profile_accounts' => ApplicabilityProfile::query()->distinct()->count('user_id'),
            'channels' => $channels,
            'last_send' => JobRun::where('job', 'alerts')->where(fn ($q) => $q->whereNull('exit_code')->orWhere('exit_code', '!=', JobRun::SKIPPED))->orderByDesc('started_at')->first(),
            'emails_today' => AlertDelivery::whereDate('sent_on', today())->count(),
            'channel_sent_today' => ChannelDelivery::where('status', 'sent')->where('sent_at', '>=', today())->count(),
            'failed_week' => ChannelDelivery::where('status', 'failed')->where('updated_at', '>=', now()->subDays(7))->count(),
        ];

        return view('backend.admin.alerts', [
            'cards' => $cards,
            'filters' => $filters,
            'status' => $status,
            'counts' => ChannelDelivery::selectRaw('status, COUNT(*) as n')->groupBy('status')->pluck('n', 'status'),
            'deliveries' => $this->query($request, $filters)->with('channel.user')->paginate(50)->withQueryString(),
            'consentKind' => $consentKind,
            'consentKinds' => $consentKinds,
            'consents' => ConsentEvent::with('user')->when($consentKind !== '', fn ($q) => $q->where('kind', $consentKind))->orderByDesc('id')->limit(100)->get(),
            'retryMax' => self::RETRY_MAX,
        ]);
    }

    /** Every delivery matching the filters, as CSV. The endpoint is shortened, as on screen; the payload is not exported. */
    public function export(Request $request): StreamedResponse
    {
        return CsvStream::from($this->query($request, ListFilters::from($request, self::SORTS))->with('channel.user'), 'channel-deliveries',
            ['id', 'created_at', 'email', 'channel', 'endpoint', 'event', 'status', 'attempts', 'response_code', 'last_error', 'next_attempt_at', 'sent_at'],
            fn (ChannelDelivery $d) => [$d->id, $d->created_at, $d->channel?->user?->email, $d->channel?->kind, $d->channel?->endpointLabel(), $d->payload['event'] ?? null, $d->status, $d->attempts, $d->response_code, $d->last_error, $d->next_attempt_at, $d->sent_at]);
    }

    /** One delivery, now, through the dispatcher's own attempt: same signature, same backoff and attempt count. */
    public function retry(ChannelDelivery $delivery, WebhookDispatcher $dispatcher): RedirectResponse
    {
        if ($delivery->status === 'sent') {
            return back()->with('error', 'Delivery #'.$delivery->id.' was already sent.');
        }
        $delivery->loadMissing('channel');
        if ($dispatcher->attempt($delivery)) {
            return back()->with('success', 'Delivery #'.$delivery->id.' sent.');
        }
        $delivery->refresh();

        return back()->with('error', 'Delivery #'.$delivery->id.' failed again: '.($delivery->last_error ?? 'no detail').'. '
            .($delivery->status === 'failed' ? 'No further automatic attempts.' : 'Next automatic attempt '.$delivery->next_attempt_at?->format('j M Y H:i').' UTC.'));
    }

    public function retryMany(Request $request, WebhookDispatcher $dispatcher): RedirectResponse
    {
        $ids = array_values(array_unique($request->validate([
            'ids' => ['required', 'array', 'min:1', 'max:'.self::RETRY_MAX],
            'ids.*' => ['integer'],
        ], ['ids.max' => 'Retry at most '.self::RETRY_MAX.' deliveries at a time.'])['ids']));

        $sent = $failed = 0;
        $deliveries = ChannelDelivery::with('channel')->whereIn('id', $ids)->where('status', '!=', 'sent')->orderBy('id')->get();
        foreach ($deliveries as $delivery) {
            $dispatcher->attempt($delivery) ? $sent++ : $failed++;
        }
        $skipped = count($ids) - $deliveries->count();
        $message = $sent.' '.Str::plural('delivery', $sent).' sent, '.$failed.' failed again.'.($skipped ? ' '.$skipped.' already sent or missing, not retried.' : '');

        return back()->with($failed ? 'error' : 'success', $message);
    }

    private function status(Request $request): ?string
    {
        return in_array($request->query('status'), self::STATUSES, true) ? (string) $request->query('status') : null;
    }

    private function query(Request $request, ListFilters $filters): Builder
    {
        $status = $this->status($request);
        $kind = in_array($request->query('kind'), ['slack', 'webhook'], true) ? (string) $request->query('kind') : null;
        $query = ChannelDelivery::query()
            ->when($status, fn ($q) => $q->where('status', $status))
            ->when($kind, fn ($q) => $q->whereHas('channel', fn ($c) => $c->where('kind', $kind)));
        if ($filters->q !== '') {
            $query->where(fn ($w) => $filters->search($w, ['last_error'])->orWhereHas('channel.user', fn ($u) => $filters->search($u, ['email', 'name'])));
        }
        $filters->dateRange($query, 'created_at');

        return $filters->order($query);
    }
}
