<?php

namespace App\Http\Controllers\Backend\Admin;

use App\Http\Controllers\Controller;
use App\Models\BillingCheckout;
use App\Models\BillingEvent;
use App\Models\Subscription;
use App\Services\Billing\BillingConfig;
use App\Services\Billing\Contracts\BillingGateway;
use App\Services\Billing\PlanCatalog;
use App\Services\Billing\Provisioner;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Admin view of subscriptions, received webhooks and the provider configuration. */
class BillingController extends Controller
{
    public function index(Request $request, BillingConfig $config, PlanCatalog $catalog): View
    {
        $status = $request->query('status');
        $q = Subscription::with('user')->orderByDesc('id');
        if ($status && in_array($status, Subscription::STATUSES, true)) {
            $q->where('status', $status);
        }
        $subscriptions = $q->paginate(25, ['*'], 'subs')->withQueryString();
        $byStatus = Subscription::selectRaw('status, COUNT(*) as n')->groupBy('status')->pluck('n', 'status');
        $events = BillingEvent::orderByDesc('id')->paginate(25, ['*'], 'events')->withQueryString();
        $checkouts = BillingCheckout::selectRaw('status, COUNT(*) as n')->groupBy('status')->pluck('n', 'status');
        $recentCheckouts = BillingCheckout::with('user')->orderByDesc('id')->limit(10)->get();
        $setup = [
            'enabled' => $config->enabled(),
            'enabled_source' => $config->enabledSource(),
            'environment' => $config->environment(),
            'api_key' => $config->apiKey() !== '',
            'webhook_secret' => $config->webhookSecret() !== '',
            'webhook_url' => route('billing.webhook'),
            'plans' => $catalog->plans(),
        ];
        $check = session('billing_check');
        $probe = session('probe');

        return view('backend.admin.billing', compact('subscriptions', 'byStatus', 'events', 'checkouts', 'recentCheckouts', 'setup', 'status', 'check', 'probe'));
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
