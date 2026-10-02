<?php

namespace App\Services\Security;

use App\Models\AppSetting;
use Illuminate\Http\Request;
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

    /** True when the request's check failed: the form handlers' one-line guard. */
    public function rejects(Request $request): bool
    {
        return ! $this->passes($request->input('cf-turnstile-response'), $request->ip());
    }

    /** Where the keys come from, for the settings page: "settings", "environment" or "none". */
    public function source(): string
    {
        if (! $this->configured()) {
            return 'none';
        }

        return filled(AppSetting::get('turnstile_secret_key')) ? 'settings' : 'environment';
    }

    /**
     * Ask Cloudflare whether the secret key is valid, without a real visitor: a dummy
     * token sent with a valid secret fails with "invalid-input-response", while a wrong
     * secret fails with "invalid-input-secret". The site key cannot be checked from the
     * server; a wrong one shows as an error inside the widget on the page.
     *
     * @return array{ok: bool, message: string}
     */
    public function checkSecret(): array
    {
        if (blank(config('services.turnstile.secret_key'))) {
            return ['ok' => false, 'message' => 'No secret key is set.'];
        }
        try {
            $codes = (array) Http::asForm()->timeout(8)->post(self::ENDPOINT, [
                'secret' => config('services.turnstile.secret_key'),
                'response' => 'aipolicytracker-key-check',
            ])->json('error-codes', []);
        } catch (\Throwable $e) {
            return ['ok' => false, 'message' => 'Cloudflare could not be reached: '.$e->getMessage()];
        }
        if (in_array('invalid-input-secret', $codes, true) || in_array('missing-input-secret', $codes, true)) {
            return ['ok' => false, 'message' => 'Cloudflare rejected the secret key. Copy it again from the Turnstile widget settings.'];
        }

        return ['ok' => true, 'message' => 'Cloudflare accepted the secret key.'.(blank(config('services.turnstile.site_key')) ? ' Add the site key too.' : ' Open a form on the site to confirm the site key: a wrong one shows an error in the widget.')];
    }
}
