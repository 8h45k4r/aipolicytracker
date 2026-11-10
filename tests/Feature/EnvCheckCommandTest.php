<?php

namespace Tests\Feature;

use Tests\TestCase;

class EnvCheckCommandTest extends TestCase
{
    public function test_env_check_passes_with_required_vars(): void
    {
        $this->artisan('aip:env-check')
            ->assertSuccessful();
    }

    public function test_env_check_fails_when_app_key_is_missing(): void
    {
        $saved = [
            'env' => $_ENV['APP_KEY'] ?? null,
            'server' => $_SERVER['APP_KEY'] ?? null,
            'getenv' => getenv('APP_KEY'),
        ];

        unset($_ENV['APP_KEY'], $_SERVER['APP_KEY']);
        putenv('APP_KEY');

        try {
            $this->artisan('aip:env-check')
                ->assertFailed();
        } finally {
            if ($saved['env'] !== null) {
                $_ENV['APP_KEY'] = $saved['env'];
            }
            if ($saved['server'] !== null) {
                $_SERVER['APP_KEY'] = $saved['server'];
            }
            if ($saved['getenv'] !== false) {
                putenv("APP_KEY={$saved['getenv']}");
            }
        }
    }

    /** Runs $check with the given environment variables set (null unsets), then restores them. */
    private function withEnv(array $vars, callable $check): void
    {
        $saved = [];
        foreach ($vars as $key => $value) {
            $saved[$key] = [$_ENV[$key] ?? null, $_SERVER[$key] ?? null, getenv($key)];
            if ($value === null) {
                unset($_ENV[$key], $_SERVER[$key]);
                putenv($key);
            } else {
                $_ENV[$key] = $_SERVER[$key] = $value;
                putenv("{$key}={$value}");
            }
        }
        try {
            $check();
        } finally {
            foreach ($saved as $key => [$env, $server, $get]) {
                unset($_ENV[$key], $_SERVER[$key]);
                putenv($key);
                if ($env !== null) {
                    $_ENV[$key] = $env;
                }
                if ($server !== null) {
                    $_SERVER[$key] = $server;
                }
                if ($get !== false) {
                    putenv("{$key}={$get}");
                }
            }
        }
    }

    public function test_production_refuses_a_local_app_url_and_debug_mode(): void
    {
        $this->app['env'] = 'production';
        $good = ['APP_URL' => 'https://aipolicytracker.org', 'APP_DEBUG' => 'false'];

        $this->withEnv($good, fn () => $this->artisan('aip:env-check')->assertSuccessful());
        $this->withEnv(['APP_URL' => 'http://localhost'] + $good, fn () => $this->artisan('aip:env-check')->expectsOutputToContain('APP_URL')->assertFailed());
        $this->withEnv(['APP_URL' => null] + $good, fn () => $this->artisan('aip:env-check')->assertFailed());
        $this->withEnv(['APP_URL' => 'http://aipolicytracker.org'] + $good, fn () => $this->artisan('aip:env-check')->assertFailed());
        $this->withEnv(['APP_DEBUG' => 'true'] + $good, fn () => $this->artisan('aip:env-check')->expectsOutputToContain('APP_DEBUG')->assertFailed());
    }

    public function test_production_warns_but_still_boots_when_mail_goes_to_the_log(): void
    {
        $this->app['env'] = 'production';
        config(['mail.default' => 'log']);
        $this->withEnv(['APP_URL' => 'https://aipolicytracker.org', 'APP_DEBUG' => 'false'], fn () => $this->artisan('aip:env-check')
            ->expectsOutputToContain('MAIL_MAILER is "log"')->assertSuccessful());
    }

    public function test_outside_production_the_local_defaults_are_fine(): void
    {
        $this->withEnv(['APP_URL' => 'http://localhost', 'APP_DEBUG' => 'true'], fn () => $this->artisan('aip:env-check')->assertSuccessful());
    }
}
