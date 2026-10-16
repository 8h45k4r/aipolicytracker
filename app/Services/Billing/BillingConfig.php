<?php

namespace App\Services\Billing;

use App\Models\AppSetting;

/**
 * Resolves billing configuration: values saved in Admin → Settings take
 * precedence over the environment, so keys can be rotated without a deploy.
 */
class BillingConfig
{
    /** Checkout switch: the admin setting (on/off) overrides BILLING_ENABLED. */
    public function enabled(): bool
    {
        return match (AppSetting::get('billing_enabled')) {
            'on' => true,
            'off' => false,
            default => (bool) config('billing.enabled'),
        };
    }

    /** Where the current enabled value comes from, for the admin page. */
    public function enabledSource(): string
    {
        return in_array(AppSetting::get('billing_enabled'), ['on', 'off'], true) ? 'setting' : 'environment';
    }

    /** True when an API key and a webhook secret exist, whatever the source. */
    public function configured(): bool
    {
        return $this->apiKey() !== '' && $this->webhookSecret() !== '';
    }

    public function environment(): string
    {
        $env = AppSetting::get('dodo_environment') ?: (string) config('billing.environment', 'test_mode');

        return $env === 'live_mode' ? 'live_mode' : 'test_mode';
    }

    public function baseUrl(): string
    {
        return (string) config('billing.base_urls.'.$this->environment());
    }

    public function apiKey(): string
    {
        return (string) (AppSetting::get('dodo_api_key') ?: config('billing.api_key') ?: '');
    }

    public function webhookSecret(): string
    {
        return (string) (AppSetting::get('dodo_webhook_secret') ?: config('billing.webhook_secret') ?: '');
    }

    /** Provider product id for a plan key; the setting overrides the env value. */
    public function productId(string $planKey): ?string
    {
        $fromSetting = AppSetting::get('dodo_product_'.$planKey);
        $value = $fromSetting ?: config('billing.plans.'.$planKey.'.product_id');

        return $value ? (string) $value : null;
    }

    public function onHoldGraceDays(): int
    {
        return max(0, (int) config('billing.on_hold_grace_days', 7));
    }
}
