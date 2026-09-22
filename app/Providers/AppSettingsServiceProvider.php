<?php

namespace App\Providers;

use App\Models\AppSetting;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;

/**
 * Applies operator-managed settings from the database on top of the
 * environment configuration, so the mail transport can be configured from the
 * admin dashboard without redeploying. Environment values remain the fallback.
 */
class AppSettingsServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        if ($this->app->runningInConsole() && ! $this->app->runningUnitTests()) {
            // Console commands (migrate, seed, digest) still get settings when the table exists.
        }
        try {
            if (! Schema::hasTable('app_settings')) {
                return;
            }
        } catch (\Throwable) {
            return; // no database yet (first boot, key:generate, etc.)
        }

        // Plain string overrides.
        $map = [
            'mail_mailer' => 'mail.default',
            'resend_key' => 'services.resend.key',
            'mail_from_address' => 'mail.from.address',
            'mail_from_name' => 'mail.from.name',
            'contact_email' => 'aipolicytracker.contact_email',
            'x_handle' => 'aipolicytracker.x_handle',
            'newsletter_url' => 'aipolicytracker.newsletter_url',
            'google_analytics_id' => 'aipolicytracker.google_analytics_id',
            'cloudflare_analytics_token' => 'aipolicytracker.cloudflare_analytics_token',
            'google_site_verification' => 'aipolicytracker.google_site_verification',
            'bing_site_verification' => 'aipolicytracker.bing_site_verification',
        ];
        foreach ($map as $key => $configKey) {
            $value = AppSetting::get($key);
            if ($value !== null && $value !== '') {
                config([$configKey => $value]);
            }
        }
        // The contact list follows the single address unless the environment listed several.
        if (($contact = AppSetting::get('contact_email')) && ! env('CONTACT_EMAILS')) {
            config(['aipolicytracker.contact_emails' => [$contact]]);
        }
        // Switches: "on" / "off" stored as text, applied as booleans.
        foreach (['analytics_require_consent' => 'aipolicytracker.analytics_require_consent', 'social_cards_enabled' => 'social.cards', 'email_domain_enforcement' => 'email.enforce'] as $key => $configKey) {
            $value = AppSetting::get($key);
            if (in_array($value, ['on', 'off'], true)) {
                config([$configKey => $value === 'on']);
            }
        }
        if (($days = AppSetting::get('stale_after_days')) && ctype_digit($days)) {
            config(['aipolicytracker.stale_after_days' => (int) $days]);
        }
    }
}
