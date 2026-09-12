<?php

namespace App\Services\Billing\Contracts;

use App\Models\User;

/**
 * The few provider calls the application makes. A fake implementation is bound
 * in tests; DodoGateway is bound in production.
 */
interface BillingGateway
{
    /**
     * Start a hosted checkout for one subscription product.
     *
     * @return array{session_id: string, url: string}
     */
    public function createCheckout(User $user, string $productId, array $metadata, string $returnUrl): array;

    /** URL of the provider-hosted customer portal (payment method, invoices, cancellation). */
    public function createPortalLink(string $providerCustomerId, string $returnUrl): string;

    /**
     * Product as configured at the provider, used by the admin page to detect a price mismatch.
     *
     * @return array{product_id: string, name: ?string, price: ?int, currency: ?string, interval: ?string}
     */
    public function retrieveProduct(string $productId): array;

    /**
     * Ensure a webhook endpoint exists for the URL (reuse by URL, else create) and return its signing secret.
     *
     * @param  list<string>  $events  event types the endpoint should receive
     * @return array{id: string, secret: string, created: bool}
     */
    public function provisionWebhook(string $url, array $events): array;

    /**
     * Ensure a recurring product exists (reuse by exact name, else create).
     *
     * @param  string  $interval  Month or Year
     * @return array{product_id: string, created: bool}
     */
    public function provisionProduct(string $name, int $price, string $currency, string $interval, string $description): array;
}
