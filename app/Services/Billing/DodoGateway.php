<?php

namespace App\Services\Billing;

use App\Models\User;
use App\Services\Billing\Contracts\BillingGateway;
use Dodopayments\CheckoutSessions\ProductItemReq;
use Dodopayments\Client;
use Dodopayments\Payments\AttachExistingCustomer;
use Dodopayments\Payments\NewCustomer;
use Dodopayments\Products\Price\RecurringPrice;

/** Dodo Payments implementation of the gateway, using the official PHP SDK. */
class DodoGateway implements BillingGateway
{
    private ?Client $client = null;

    public function __construct(private BillingConfig $config) {}

    public function createCheckout(User $user, string $productId, array $metadata, string $returnUrl): array
    {
        $customer = $user->billingCustomer;
        $response = $this->client()->checkoutSessions->create(
            productCart: [ProductItemReq::with(productID: $productId, quantity: 1)],
            customer: $customer
                ? AttachExistingCustomer::with(customerID: $customer->provider_customer_id)
                : NewCustomer::with(email: $user->email, name: $user->name),
            metadata: $metadata,
            returnURL: $returnUrl,
        );

        return ['session_id' => $response->sessionID, 'url' => (string) $response->checkoutURL];
    }

    public function createPortalLink(string $providerCustomerId, string $returnUrl): string
    {
        return $this->client()->customers->customerPortal->create($providerCustomerId, returnURL: $returnUrl)->link;
    }

    public function retrieveProduct(string $productId): array
    {
        $product = $this->client()->products->retrieve($productId);
        $price = $product->price;

        return [
            'product_id' => $product->productID,
            'name' => $product->name,
            'price' => $price->price ?? null,
            'currency' => $price->currency ?? null,
            'interval' => $price instanceof RecurringPrice ? $price->paymentFrequencyInterval : null,
        ];
    }

    public function provisionWebhook(string $url, array $events): array
    {
        $client = $this->client();
        $existing = null;
        foreach ($client->webhooks->list(limit: 100)->getItems() as $webhook) {
            if ($webhook->url === $url) {
                $existing = $webhook;
                break;
            }
        }
        $webhook = $existing ?? $client->webhooks->create(url: $url, description: config('aipolicytracker.site_name').' billing', filterTypes: $events);

        return ['id' => $webhook->id, 'secret' => $client->webhooks->retrieveSecret($webhook->id)->secret, 'created' => $existing === null];
    }

    public function provisionProduct(string $name, int $price, string $currency, string $interval, string $description): array
    {
        $client = $this->client();
        foreach ($client->products->list(pageSize: 100, recurring: true)->getItems() as $product) {
            if ((string) $product->name === $name) {
                return ['product_id' => $product->productID, 'created' => false];
            }
        }
        $product = $client->products->create(
            name: $name,
            price: RecurringPrice::with(currency: strtoupper($currency), paymentFrequencyCount: 1, paymentFrequencyInterval: $interval, price: $price, subscriptionPeriodCount: 1, subscriptionPeriodInterval: $interval, taxInclusive: false),
            taxCategory: 'saas',
            description: $description,
        );

        return ['product_id' => $product->productID, 'created' => true];
    }

    private function client(): Client
    {
        return $this->client ??= new Client(
            bearerToken: $this->config->apiKey(),
            webhookKey: $this->config->webhookSecret(),
            baseUrl: $this->config->baseUrl(),
        );
    }
}
