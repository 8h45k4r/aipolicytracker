<?php

namespace App\Services\Billing;

use App\Models\User;
use App\Services\Billing\Contracts\BillingGateway;
use Dodopayments\CheckoutSessions\ProductItemReq;
use Dodopayments\Client;
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
                ? ['customer_id' => $customer->provider_customer_id]
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

    private function client(): Client
    {
        return $this->client ??= new Client(
            bearerToken: $this->config->apiKey(),
            webhookKey: $this->config->webhookSecret(),
            baseUrl: $this->config->baseUrl(),
        );
    }
}
