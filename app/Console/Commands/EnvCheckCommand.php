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

        foreach ($warnings as $w) {
            $this->warn("Missing: {$w}");
        }

        if ($errors) {
            $this->error('Missing required: '.implode(', ', $errors));

            return self::FAILURE;
        }

        $this->info('Environment OK');

        return self::SUCCESS;
    }
}
