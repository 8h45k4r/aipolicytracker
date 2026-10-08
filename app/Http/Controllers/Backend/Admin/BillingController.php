<?php

namespace App\Http\Controllers\Backend\Admin;

use App\Http\Controllers\Controller;
use App\Models\BillingCheckout;
use App\Models\BillingEvent;
use App\Models\BillingPayment;
use App\Models\Follow;
use App\Models\Subscription;
use App\Models\User;
use App\Services\Billing\BillingConfig;
use App\Services\Billing\Contracts\BillingGateway;
use App\Services\Billing\Entitlements;
use App\Services\Billing\PlanCatalog;
use App\Services\Billing\Provisioner;
use App\Services\Billing\WebhookProcessor;
use App\Support\Admin\CsvStream;
use App\Support\Admin\ListFilters;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Admin view of billing: what is sold and collected (summary), subscriptions, payments,
 * received webhooks (with a re-apply for one that failed), the free-tier quota and the
 * provider configuration. Nothing here edits a subscription by hand: the provider and its
 * verified webhooks stay the only source of entitlement changes.
 */
class BillingController extends Controller
{
    public const TABS = ['subscriptions' => 'Subscriptions', 'payments' => 'Payments', 'webhooks' => 'Webhooks', 'quota' => 'Free tier'];

    /** Payment events that take money back; counted on the summary and raised on the dashboard. */
    public const REVERSAL_EVENTS = ['refund.succeeded', 'dispute.lost', 'dispute.accepted'];

    /** The subscriptions filter takes every provider status, plus "revoked" (refunded or charged back). */
    private const REVOKED = 'revoked';

    private const SORTS = ['created' => 'id', 'period_end' => 'current_period_end', 'plan' => 'plan_key', 'status' => 'status'];

    private const PAYMENT_SORTS = ['paid' => 'paid_at', 'amount' => 'total_amount', 'status' => 'status'];

    private const EVENT_SORTS = ['received' => 'id', 'type' => 'event_type', 'outcome' => 'outcome'];

    /** Accounts over the free allowance listed on the quota tab; the count above it is exact. */
    private const QUOTA_LIST = 200;

    public function index(Request $request, BillingConfig $config, PlanCatalog $catalog, Entitlements $entitlements): View
    {
        $tab = array_key_exists((string) $request->query('tab'), self::TABS) ? (string) $request->query('tab') : 'subscriptions';
        $data = ['tab' => $tab, 'summary' => $this->summary($catalog, $entitlements)];

        $data += match ($tab) {
            'payments' => $this->paymentsTab($request),
            'webhooks' => $this->webhooksTab($request),
            'quota' => ['quota' => $this->quota($config, $entitlements)],
            default => $this->subscriptionsTab($request, $config, $catalog),
        };

        return view('backend.admin.billing', $data);
    }

    private function subscriptionsTab(Request $request, BillingConfig $config, PlanCatalog $catalog): array
    {
        $filters = ListFilters::from($request, self::SORTS);

        return [
            'filters' => $filters,
            'status' => $this->subscriptionStatus($request),
            'subscriptions' => $this->subscriptionQuery($request, $filters)->with('user')->paginate(25, ['*'], 'subs')->withQueryString(),
            'byStatus' => Subscription::selectRaw('status, COUNT(*) as n')->groupBy('status')->pluck('n', 'status')->put(self::REVOKED, Subscription::whereNotNull('revoked_at')->count()),
            'checkouts' => BillingCheckout::selectRaw('status, COUNT(*) as n')->groupBy('status')->pluck('n', 'status'),
            'recentCheckouts' => BillingCheckout::with('user')->orderByDesc('id')->limit(10)->get(),
            'setup' => [
                'enabled' => $config->enabled(),
                'enabled_source' => $config->enabledSource(),
                'environment' => $config->environment(),
                'api_key' => $config->apiKey() !== '',
                'webhook_secret' => $config->webhookSecret() !== '',
                'webhook_url' => route('billing.webhook'),
                'plans' => $catalog->plans(),
            ],
            'check' => session('billing_check'),
            'probe' => session('probe'),
        ];
    }

    private function paymentsTab(Request $request): array
    {
        $filters = ListFilters::from($request, self::PAYMENT_SORTS);
        $payments = $this->paymentQuery($request, $filters)->paginate(25)->withQueryString();

        return [
            'filters' => $filters,
            'paymentStatus' => $this->paymentStatus($request),
            'payments' => $payments,
            // The subscription (and account) each payment paid for, in one query rather than one per row.
            'paymentSubs' => Subscription::with('user')->whereIn('provider_subscription_id', $payments->pluck('provider_subscription_id')->filter()->unique()->values())->get()->keyBy('provider_subscription_id'),
            'paymentCounts' => BillingPayment::selectRaw('status, COUNT(*) as n')->groupBy('status')->pluck('n', 'status'),
        ];
    }

    private function webhooksTab(Request $request): array
    {
        $filters = ListFilters::from($request, self::EVENT_SORTS);

        return [
            'filters' => $filters,
            'outcome' => $this->eventOutcome($request),
            'events' => $this->eventQuery($request, $filters)->paginate(25)->withQueryString(),
            'outcomeCounts' => BillingEvent::selectRaw("COALESCE(outcome, 'pending') as o, COUNT(*) as n")->groupBy('o')->pluck('n', 'o'),
        ];
    }

    /**
     * The figures at the top of the page. Recurring revenue is computed from the config
     * prices of the subscriptions that cover today (Entitlements::covers), so it matches
     * who has access; collected money comes from billing_payments as the provider reported it.
     *
     * @return array<string, mixed>
     */
    private function summary(PlanCatalog $catalog, Entitlements $entitlements): array
    {
        $since = now()->subDays(30);
        $covering = Subscription::whereNotNull('plan_key')->whereIn('status', ['active', 'on_hold', 'past_due', 'cancelled'])->get()
            ->filter(fn (Subscription $s) => $entitlements->covers($s));

        $intervals = ['month' => 0, 'year' => 0];
        $mrr = [];
        foreach ($covering as $s) {
            $plan = $catalog->plan($s->plan_key);
            $interval = ($plan['interval'] ?? 'month') === 'year' ? 'year' : 'month';
            $intervals[$interval]++;
            $currency = strtoupper((string) ($plan['currency'] ?? 'USD'));
            $mrr[$currency] = ($mrr[$currency] ?? 0) + ((int) ($plan['price'] ?? 0)) / ($interval === 'year' ? 12 : 1);
        }

        $payments = BillingPayment::where('paid_at', '>=', $since)->get(['total_amount', 'currency']);
        $reversals = BillingEvent::whereIn('event_type', self::REVERSAL_EVENTS)->where('outcome', WebhookProcessor::OUTCOME_APPLIED)->where('received_at', '>=', $since)
            ->selectRaw('event_type, COUNT(*) as n')->groupBy('event_type')->pluck('n', 'event_type');

        return [
            'monthly' => $intervals['month'],
            'annual' => $intervals['year'],
            'mrr' => array_map(fn ($minor) => (int) round($minor), $mrr),
            'payments_count' => $payments->count(),
            'payments_total' => $payments->groupBy(fn ($p) => strtoupper((string) ($p->currency ?: 'USD')))->map(fn ($g) => (int) $g->sum('total_amount'))->all(),
            'refunds' => (int) ($reversals['refund.succeeded'] ?? 0),
            'disputes_lost' => (int) ($reversals['dispute.lost'] ?? 0) + (int) ($reversals['dispute.accepted'] ?? 0),
            'revoked' => Subscription::whereNotNull('revoked_at')->count(),
            'grace' => $covering->filter(fn (Subscription $s) => in_array($s->status, ['on_hold', 'past_due'], true))->count(),
            'errors' => BillingEvent::where('outcome', WebhookProcessor::OUTCOME_ERROR)->count(),
        ];
    }

    /**
     * How many watches accounts hold against the free allowance. While selling is off every
     * account has the largest allowance (Entitlements::value), so "over the free limit" is
     * what would apply the day selling starts; with selling on it is who is over now. An
     * account over the limit keeps its watches; only the oldest that many are alerted on.
     *
     * @return array<string, mixed>
     */
    private function quota(BillingConfig $config, Entitlements $entitlements): array
    {
        $limit = (int) (config('billing.free.entitlements')['watches.max'] ?? 0);
        $perUser = fn () => DB::query()->fromSub(Follow::query()->selectRaw('user_id, COUNT(*) as n')->groupBy('user_id'), 'w');
        $within = $perUser()->where('n', '<=', $limit)->count();
        $overIds = $perUser()->where('n', '>', $limit)->orderByDesc('n')->pluck('n', 'user_id');

        // Over the free allowance and not covered by a paid subscription. Checked per account
        // with the same rule that grants access, so a refunded or lapsed Pro counts as free.
        $paid = Subscription::whereIn('user_id', $overIds->keys())->whereNotNull('plan_key')->get()
            ->filter(fn (Subscription $s) => $entitlements->covers($s))->pluck('user_id')->unique()->flip();
        $overFree = $overIds->reject(fn ($n, $id) => isset($paid[$id]));
        $users = User::whereIn('id', $overFree->keys()->take(self::QUOTA_LIST))->get()->keyBy('id');

        return [
            'limit' => $limit,
            'selling' => $config->enabled(),
            'buckets' => ['none' => max(0, User::count() - $within - $overIds->count()), 'within' => $within, 'over' => $overIds->count()],
            'over_free_count' => $overFree->count(),
            'over_free' => $overFree->take(self::QUOTA_LIST)->map(fn ($n, $id) => ['user' => $users[$id] ?? null, 'watches' => (int) $n])->filter(fn ($r) => $r['user'])->values(),
            'list_cap' => self::QUOTA_LIST,
        ];
    }

    /** Every subscription matching the filters, as CSV: the account, plan, dates and any revocation; no payment details. */
    public function export(Request $request): StreamedResponse
    {
        return CsvStream::from($this->subscriptionQuery($request, ListFilters::from($request, self::SORTS))->with('user'), 'subscriptions',
            ['email', 'plan', 'status', 'cancel_at_period_end', 'current_period_end', 'cancelled_at', 'revoked_at', 'revoked_reason', 'last_event', 'last_event_at', 'provider_subscription_id', 'created_at'],
            fn (Subscription $s) => [$s->user?->email, $s->planName(), $s->status, $s->cancel_at_period_end, $s->current_period_end, $s->cancelled_at, $s->revoked_at, $s->revoked_reason, $s->last_event_type, $s->last_event_at, $s->provider_subscription_id, $s->created_at]);
    }

    /** Every payment matching the filters, as CSV, with the account it paid for. Amounts are in the smallest currency unit. */
    public function paymentsExport(Request $request): StreamedResponse
    {
        $emails = [];
        $email = function (?string $subId) use (&$emails) {
            if (! $subId) {
                return null;
            }

            return $emails[$subId] ??= Subscription::with('user')->where('provider_subscription_id', $subId)->first()?->user?->email;
        };

        return CsvStream::from($this->paymentQuery($request, ListFilters::from($request, self::PAYMENT_SORTS)), 'payments',
            ['paid_at', 'provider_payment_id', 'provider_subscription_id', 'email', 'total_amount', 'currency', 'refunded_amount', 'status', 'dispute_status'],
            fn (BillingPayment $p) => [$p->paid_at, $p->provider_payment_id, $p->provider_subscription_id, $email($p->provider_subscription_id), $p->total_amount, $p->currency, $p->refunded_amount, $p->status, $p->dispute_status]);
    }

    /**
     * Applies a webhook whose processing failed, from the payload stored when it arrived and
     * under the same event id, exactly as a provider retry would: WebhookProcessor applies an
     * errored event again and acknowledges anything else as a duplicate. Only an event with
     * outcome `error` is accepted here, so this cannot replay one that was already applied.
     */
    public function reapply(BillingEvent $event, WebhookProcessor $processor): RedirectResponse
    {
        if ($event->outcome !== WebhookProcessor::OUTCOME_ERROR) {
            return back()->with('error', 'Only an event whose processing failed can be re-applied. This one is '.($event->outcome ?? 'pending').'.');
        }
        try {
            $outcome = $processor->handle((array) $event->payload, $event->event_id);
        } catch (\Throwable $e) {
            return back()->with('error', 'Re-applying '.$event->event_type.' failed again: '.mb_substr($e->getMessage(), 0, 300));
        }

        return back()->with('success', 'Re-applied '.$event->event_type.' ('.$event->event_id.'): '.$outcome.'.');
    }

    private function subscriptionStatus(Request $request): ?string
    {
        $status = (string) $request->query('status');

        return in_array($status, [...Subscription::STATUSES, self::REVOKED], true) ? $status : null;
    }

    private function subscriptionQuery(Request $request, ListFilters $filters): Builder
    {
        $status = $this->subscriptionStatus($request);
        $query = Subscription::query()
            ->when($status === self::REVOKED, fn ($q) => $q->whereNotNull('revoked_at'))
            ->when($status !== null && $status !== self::REVOKED, fn ($q) => $q->where('status', $status));
        if ($filters->q !== '') {
            $query->where(fn ($w) => $filters->search($w, ['provider_subscription_id', 'plan_key'])->orWhereHas('user', fn ($u) => $filters->search($u, ['email', 'name'])));
        }
        $filters->dateRange($query, 'created_at');

        return $filters->order($query);
    }

    private function paymentStatus(Request $request): ?string
    {
        return in_array($request->query('status'), BillingPayment::STATUSES, true) ? (string) $request->query('status') : null;
    }

    private function paymentQuery(Request $request, ListFilters $filters): Builder
    {
        $status = $this->paymentStatus($request);
        $query = BillingPayment::query()->when($status, fn ($q) => $q->where('status', $status));
        if ($filters->q !== '') {
            $byAccount = Subscription::whereHas('user', fn ($u) => $filters->search($u, ['email', 'name']))->select('provider_subscription_id');
            $query->where(fn ($w) => $filters->search($w, ['provider_payment_id', 'provider_subscription_id'])->orWhereIn('provider_subscription_id', $byAccount));
        }
        $filters->dateRange($query, 'paid_at');

        return $filters->order($query);
    }

    /** Stored outcomes, plus "pending" for a row still being applied (or whose process died mid-way). */
    private function eventOutcome(Request $request): ?string
    {
        return in_array($request->query('outcome'), [...BillingEvent::OUTCOMES, 'pending'], true) ? (string) $request->query('outcome') : null;
    }

    private function eventQuery(Request $request, ListFilters $filters): Builder
    {
        $outcome = $this->eventOutcome($request);
        $query = BillingEvent::query()
            ->when($outcome === 'pending', fn ($q) => $q->whereNull('outcome'))
            ->when($outcome !== null && $outcome !== 'pending', fn ($q) => $q->where('outcome', $outcome));
        $filters->search($query, ['event_type', 'event_id', 'provider_subscription_id']);
        $filters->dateRange($query, 'received_at');

        return $filters->order($query);
    }

    /**
     * Asks the provider to open a checkout session for the first purchasable plan and
     * reports its answer. No charge is made and no attempt row is written: this only
     * proves whether the account, key and product can sell right now.
     */
    public function probe(Request $request, BillingConfig $config, PlanCatalog $catalog, BillingGateway $gateway): RedirectResponse
    {
        if (! $config->configured()) {
            return back()->with('error', 'Add the API key and webhook secret under Settings first.');
        }
        $plan = collect($catalog->plans())->first(fn ($p) => ! empty($p['product_id']));
        if (! $plan) {
            return back()->with('error', 'No plan has a product id yet. Run "Provision webhook and products" first.');
        }
        try {
            $session = $gateway->createCheckout($request->user(), $plan['product_id'], ['probe' => '1'], route('home'));
        } catch (\Throwable $e) {
            return back()->with('probe', ['ok' => false, 'plan' => $plan['name'], 'environment' => $config->environment(), 'detail' => mb_substr($e->getMessage(), 0, 2000)]);
        }

        return back()->with('probe', ['ok' => true, 'plan' => $plan['name'], 'environment' => $config->environment(), 'detail' => $session['url']]);
    }

    /** Creates or reuses the webhook endpoint and plan products at the provider and stores the resulting keys. */
    public function provision(Request $request, Provisioner $provisioner): RedirectResponse
    {
        try {
            $report = $provisioner->run($request->user()->id);
        } catch (\Throwable $e) {
            return back()->with('error', 'Provisioning failed: '.mb_substr($e->getMessage(), 0, 300));
        }
        $lines = ['Webhook '.$report['webhook']['id'].($report['webhook']['created'] ? ' created' : ' reused').', secret stored'];
        foreach ($report['products'] as $p) {
            $lines[] = $p['name'].': '.$p['product_id'].($p['created'] ? ' created' : ' reused');
        }

        return back()->with('success', 'Provisioned in '.$report['environment'].'. '.implode('; ', $lines).'. Run the product check below, then switch Checkout on under Settings.');
    }

    /** Fetches each configured product from the provider and compares its price with config/billing.php. */
    public function check(BillingConfig $config, PlanCatalog $catalog, BillingGateway $gateway): RedirectResponse
    {
        if (! $config->configured()) {
            return back()->with('error', 'Add the API key and webhook secret under Settings first.');
        }
        $rows = [];
        foreach ($catalog->plans() as $key => $plan) {
            if (empty($plan['product_id'])) {
                $rows[$key] = ['ok' => false, 'note' => 'No product id configured'];

                continue;
            }
            try {
                $remote = $gateway->retrieveProduct($plan['product_id']);
                $match = (int) $remote['price'] === (int) $plan['price'] && strtoupper((string) $remote['currency']) === strtoupper($plan['currency']);
                $rows[$key] = ['ok' => $match, 'remote' => $remote, 'note' => $match ? 'Price matches' : 'Price differs from config/billing.php'];
            } catch (\Throwable $e) {
                $rows[$key] = ['ok' => false, 'note' => 'Lookup failed: '.mb_substr($e->getMessage(), 0, 200)];
            }
        }

        return back()->with('billing_check', $rows)->with('success', 'Provider products checked.');
    }
}
