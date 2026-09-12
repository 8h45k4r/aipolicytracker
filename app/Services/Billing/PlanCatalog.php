<?php

namespace App\Services\Billing;

/** Plans as defined in config/billing.php, with product ids resolved through BillingConfig. */
class PlanCatalog
{
    public function __construct(private BillingConfig $config) {}

    /** @return array<string, array> plan_key => definition (+ product_id, key) */
    public function plans(): array
    {
        $out = [];
        foreach ((array) config('billing.plans', []) as $key => $plan) {
            $plan['key'] = $key;
            $plan['product_id'] = $this->config->productId($key);
            $out[$key] = $plan;
        }

        return $out;
    }

    public function plan(string $key): ?array
    {
        return $this->plans()[$key] ?? null;
    }

    /** A plan can be bought only when billing is enabled and its product id is known. */
    public function purchasable(string $key): bool
    {
        $plan = $this->plan($key);

        return $this->config->enabled() && $plan !== null && ! empty($plan['product_id']);
    }

    /** Plan key for a provider product id, or null when the product is not one of ours. */
    public function keyForProduct(?string $productId): ?string
    {
        if (! $productId) {
            return null;
        }
        foreach ($this->plans() as $key => $plan) {
            if (($plan['product_id'] ?? null) === $productId) {
                return $key;
            }
        }

        return null;
    }

    public function free(): array
    {
        return (array) config('billing.free', []);
    }

    /** Price formatted for display, e.g. "$29" or "$290". */
    public static function formatPrice(int $minor, string $currency): string
    {
        $symbol = match (strtoupper($currency)) {
            'USD' => '$', 'EUR' => '€', 'GBP' => '£', 'INR' => '₹', default => strtoupper($currency).' ',
        };
        $major = $minor / 100;

        return $symbol.(floor($major) == $major ? number_format($major) : number_format($major, 2));
    }
}
