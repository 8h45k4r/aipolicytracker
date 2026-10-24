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
}
