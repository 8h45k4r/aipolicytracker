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
}
