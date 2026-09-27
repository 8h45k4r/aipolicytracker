<?php

namespace App\Services\Security;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Cloudflare Turnstile, checked on the server. The widget in the page only produces
 * a token; a token is worth nothing until Cloudflare confirms it here.
 *
 * With no keys configured (development, tests) the check is skipped and says so in
 * the log, so a missing secret is visible rather than silently letting bots through
 * in production: `configured()` is shown on the admin settings page.
 */
class Turnstile
{
    private const ENDPOINT = 'https://challenges.cloudflare.com/turnstile/v0/siteverify';

    public function configured(): bool
    {
        return filled(config('services.turnstile.site_key')) && filled(config('services.turnstile.secret_key'));
    }

    public function siteKey(): ?string
    {
        return config('services.turnstile.site_key') ?: null;
    }

    public function passes(?string $token, ?string $ip): bool
    {
        if (! $this->configured()) {
            Log::notice('turnstile.skipped', ['reason' => 'TURNSTILE_SITE_KEY / TURNSTILE_SECRET_KEY not set']);

            return true;
        }
        if (blank($token)) {
            return false;
        }

        try {
            $response = Http::asForm()->timeout(5)->post(self::ENDPOINT, [
                'secret' => config('services.turnstile.secret_key'),
                'response' => $token,
                'remoteip' => $ip,
            ]);

            return (bool) $response->json('success', false);
        } catch (\Throwable $e) {
            // Cloudflare unreachable: refuse rather than wave everything through,
            // and log it, since a spike of these is an outage, not a bot wave.
            Log::warning('turnstile.unreachable', ['error' => $e->getMessage()]);

            return false;
        }
    }
}
