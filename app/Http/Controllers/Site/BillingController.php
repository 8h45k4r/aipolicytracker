<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Models\BillingCheckout;
use App\Services\Billing\BillingConfig;
use App\Services\Billing\Contracts\BillingGateway;
use App\Services\Billing\Entitlements;
use App\Services\Billing\PlanCatalog;
use App\Support\Seo;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

/**
 * Pricing page, checkout hand-off and the return page. Payment happens on the
 * provider's hosted checkout; access is granted only by the verified webhook,
 * so the return page shows "confirming" until the subscription row exists.
 */
class BillingController extends Controller
{
    public function __construct(private BillingConfig $config, private PlanCatalog $catalog, private Entitlements $entitlements) {}

    public function pricing(Request $request): View
    {
        $enabled = $this->config->enabled();
        $seo = Seo::make('Pricing: Free and Pro plans', 'Every policy record stays free and open. Pro adds a daily alert for the policies, jurisdictions and obligations you follow, and reminders before application dates fall due.', route('pricing'), $enabled)
            ->withBreadcrumbs([['Home', route('home')], ['Pricing', route('pricing')]]);
        if (! $enabled) {
            $seo->noindex();
        }
        $plans = array_filter($this->catalog->plans(), fn ($p) => ! empty($p['product_id']) || ! $enabled);
        $user = $request->user();
        $current = $user ? $this->entitlements->activeSubscription($user) : null;

        return view('site.pricing', [
            'seo' => $seo,
            'enabled' => $enabled,
            'free' => $this->catalog->free(),
            'plans' => $plans,
            'features' => (array) config('billing.features'),
            'current' => $current,
            'user' => $user,
        ]);
    }

    /** Creates a checkout session and hands the browser to the provider via an interstitial (CSP form-action is 'self'). */
    public function checkout(Request $request, string $plan, BillingGateway $gateway): View|RedirectResponse
    {
        abort_unless($this->catalog->purchasable($plan), 404);
        $user = $request->user();
        if ($this->entitlements->activeSubscription($user)) {
            return redirect()->route('profile.edit')->with('status', 'already-subscribed');
        }
        $definition = $this->catalog->plan($plan);
        $checkout = BillingCheckout::create(['user_id' => $user->id, 'plan_key' => $plan, 'product_id' => $definition['product_id'], 'status' => 'created']);
        try {
            $session = $gateway->createCheckout($user, $definition['product_id'], [
                'app_user_id' => (string) $user->id,
                'plan_key' => $plan,
                'checkout_id' => (string) $checkout->id,
            ], route('billing.return', ['checkout' => $checkout->id]));
        } catch (\Throwable $e) {
            Log::error('billing.checkout.failed', ['user_id' => $user->id, 'plan' => $plan, 'error' => $e->getMessage()]);
            $checkout->update(['status' => 'abandoned', 'error' => mb_substr($e->getMessage(), 0, 2000)]);

            return redirect()->route('pricing')->with('error', 'We could not start the checkout. Please try again in a minute.');
        }
        $checkout->update(['provider_session_id' => $session['session_id']]);

        return view('site.billing.redirect', ['url' => $session['url'], 'plan' => $definition, 'seo' => Seo::make('Redirecting to secure checkout', 'Redirecting to the payment provider.', route('pricing'), false)]);
    }

    /** Provider return URL. Shows the plan state; confirmation itself comes from the webhook. */
    public function returned(Request $request, BillingCheckout $checkout): View
    {
        abort_unless($checkout->user_id === $request->user()->id, 404);
        if ($checkout->status === 'created') {
            $checkout->update(['status' => 'returned']);
        }
        $subscription = $this->entitlements->activeSubscription($request->user());
        $status = $request->query('status');

        return view('site.billing.return', [
            'seo' => Seo::make('Checkout complete', 'Your plan status.', route('pricing'), false),
            'checkout' => $checkout->fresh(),
            'subscription' => $subscription,
            'plan' => $this->catalog->plan($checkout->plan_key),
            'failed' => in_array($status, ['failed', 'cancelled'], true),
        ]);
    }

    /** Sends the customer to the provider portal to change the card, download invoices or cancel. */
    public function portal(Request $request, BillingGateway $gateway): View|RedirectResponse
    {
        abort_unless($this->config->enabled(), 404);
        $customer = $request->user()->billingCustomer;
        if (! $customer) {
            return redirect()->route('profile.edit')->with('error', 'No billing account yet. Choose a plan first.');
        }
        try {
            $url = $gateway->createPortalLink($customer->provider_customer_id, route('profile.edit'));
        } catch (\Throwable $e) {
            Log::error('billing.portal.failed', ['user_id' => $request->user()->id, 'error' => $e->getMessage()]);

            return redirect()->route('profile.edit')->with('error', 'The billing portal is unavailable right now. Please try again shortly.');
        }

        return view('site.billing.redirect', ['url' => $url, 'plan' => null, 'seo' => Seo::make('Opening billing portal', 'Redirecting to the billing portal.', route('profile.edit'), false)]);
    }
}
