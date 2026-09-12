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

        return view('backend.admin.billing', compact('subscriptions', 'byStatus', 'events', 'checkouts', 'recentCheckouts', 'setup', 'status', 'check'));
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
