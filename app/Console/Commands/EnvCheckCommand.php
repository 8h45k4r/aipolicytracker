<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class EnvCheckCommand extends Command
{
    protected $signature = 'aip:env-check';

    protected $description = 'Fail loudly when required environment variables are missing';

    private const REQUIRED = [
        'APP_KEY',
    ];

    private const REQUIRED_PGSQL = [
        'DB_HOST',
        'DB_DATABASE',
        'DB_USERNAME',
        'DB_PASSWORD',
    ];

    private const EXPECTED = [
        'ADMIN_EMAILS' => 'no admin notifications',
        'MAIL_FROM_ADDRESS' => 'mail will come from "hello@example.com"',
        'CRON_TOKEN' => 'the /cron endpoint is unprotected',
    ];

    public function handle(): int
    {
        $errors = [];
        $warnings = [];

        foreach (self::REQUIRED as $key) {
            if (blank(env($key))) {
                $errors[] = $key;
            }
        }

        if (config('database.default') === 'pgsql') {
            foreach (self::REQUIRED_PGSQL as $key) {
                if (blank(env($key))) {
                    $errors[] = $key;
                }
            }
        }

        foreach (self::EXPECTED as $key => $consequence) {
            if (blank(env($key))) {
                $warnings[] = "{$key} ({$consequence})";
            }
        }

        // Production values that boot fine but break the site quietly.
        if (app()->isProduction()) {
            $url = (string) env('APP_URL');
            $host = (string) parse_url($url, PHP_URL_HOST);
            if ($url === '' || in_array($host, ['', 'localhost', '127.0.0.1'], true) || ! str_starts_with($url, 'https://')) {
                // Canonical links, sitemaps, emailed links and the billing webhook are all built from it.
                $errors[] = 'APP_URL (must be the public https address in production)';
            }
            if (filter_var(env('APP_DEBUG'), FILTER_VALIDATE_BOOLEAN)) {
                $errors[] = 'APP_DEBUG (must be false in production: debug pages expose code and settings)';
            }
            if (in_array((string) config('mail.default'), ['log', 'array'], true)) {
                // A warning, not a failure: refusing to boot would take the site down over mail.
                $warnings[] = 'MAIL_MAILER is "'.config('mail.default').'" (no email reaches anyone: confirmations, resets and alerts are written to the log)';
            }
        }

        foreach ($warnings as $w) {
            $this->warn("Missing: {$w}");
        }

        if ($errors) {
            $this->error('Missing or invalid: '.implode(', ', $errors));

            return self::FAILURE;
        }

        $this->info('Environment OK');

        return self::SUCCESS;
    }
}
