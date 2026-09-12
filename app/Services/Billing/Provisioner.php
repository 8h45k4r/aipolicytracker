<?php

namespace App\Services\Billing;

use App\Models\AppSetting;
use App\Services\Billing\Contracts\BillingGateway;

/**
 * Creates (or reuses) the provider-side objects the billing module needs: the
 * webhook endpoint for this site and one recurring product per plan in
 * config/billing.php. The resulting webhook secret and product ids are stored
 * as encrypted settings, so an operator only has to supply the API key.
 * Idempotent: the endpoint is matched by URL, products by exact name.
 */
class Provisioner
{
    /** Events the endpoint subscribes to; WebhookProcessor applies subscription.* and records the rest. */
    public const EVENTS = [
        'payment.succeeded', 'payment.failed', 'payment.processing', 'payment.cancelled',
        'subscription.active', 'subscription.renewed', 'subscription.on_hold', 'subscription.past_due', 'subscription.paused', 'subscription.unpaused',
        'subscription.cancelled', 'subscription.failed', 'subscription.expired', 'subscription.plan_changed', 'subscription.updated', 'subscription.update_payment_method',
    ];

    public function __construct(private BillingConfig $config, private BillingGateway $gateway) {}

    /**
     * @return array{environment: string, webhook: array{id: string, created: bool}, products: array<string, array{name: string, product_id: string, created: bool}>}
     *
     * @throws \RuntimeException when no API key is configured
     */
    public function run(?int $userId = null): array
    {
        if ($this->config->apiKey() === '') {
            throw new \RuntimeException('Store the Dodo API key under Settings first.');
        }
        $webhook = $this->gateway->provisionWebhook(route('billing.webhook'), self::EVENTS);
        AppSetting::put('dodo_webhook_secret', $webhook['secret'], $userId);

        $products = [];
        $description = 'Follow any policy, jurisdiction or obligation and get one daily email when it changes, plus reminders before application dates fall due. The policy data itself stays free and open ('.config('aipolicytracker.data_license', 'CC BY 4.0').').';
        foreach ((array) config('billing.plans', []) as $key => $plan) {
            $name = config('aipolicytracker.site_name').' '.$plan['name'];
            $product = $this->gateway->provisionProduct($name, (int) $plan['price'], (string) $plan['currency'], $plan['interval'] === 'year' ? 'Year' : 'Month', $description);
            AppSetting::put('dodo_product_'.$key, $product['product_id'], $userId);
            $products[$key] = ['name' => $name, 'product_id' => $product['product_id'], 'created' => $product['created']];
        }

        return ['environment' => $this->config->environment(), 'webhook' => ['id' => $webhook['id'], 'created' => $webhook['created']], 'products' => $products];
    }
}
