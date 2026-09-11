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

        $map = [
            'mail_mailer' => 'mail.default',
            'resend_key' => 'services.resend.key',
            'mail_from_address' => 'mail.from.address',
            'mail_from_name' => 'mail.from.name',
        ];
        foreach ($map as $key => $configKey) {
            $value = AppSetting::get($key);
            if ($value !== null && $value !== '') {
                config([$configKey => $value]);
            }
        }
    }
}
