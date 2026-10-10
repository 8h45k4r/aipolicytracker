<?php

namespace App\Services\Social;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

/** Creates posts on X for the one account whose keys are configured. */
class XClient
{
    public function configured(): bool
    {
        foreach (['api_key', 'api_secret', 'access_token', 'access_secret'] as $key) {
            if (blank(config('social.x.'.$key))) {
                return false;
            }
        }

        return true;
    }

    /**
     * Post the text and return the new post's id.
     *
     * @throws XPostFailed
     */
    public function post(string $text): string
    {
        if (! $this->configured()) {
            throw new XPostFailed('The X keys are not set. Add them under Settings → Posting to X.');
        }
        $url = (string) config('social.x.endpoint');
        $signer = new OAuth1((string) config('social.x.api_key'), (string) config('social.x.api_secret'), (string) config('social.x.access_token'), (string) config('social.x.access_secret'));

        try {
            $response = Http::timeout(15)->acceptJson()->asJson()
                ->withHeaders(['Authorization' => $signer->header('POST', $url)])
                ->post($url, ['text' => $text]);
        } catch (ConnectionException $e) {
            throw new XPostFailed('Could not reach X: '.mb_substr($e->getMessage(), 0, 160), true);
        }

        $id = $response->json('data.id');
        if ($response->successful() && is_string($id) && $id !== '') {
            return $id;
        }

        // X explains itself in `detail` (v2 problem) or `errors[0].message`.
        $why = $response->json('detail') ?? $response->json('errors.0.message') ?? $response->json('title') ?? 'no explanation given';
        $status = $response->status();
        $hint = match (true) {
            $status === 401 => ' Check the four keys, and that the access token was generated after the app was given "Read and write" permission.',
            $status === 402 => ' The X developer account has no credit left.',
            $status === 403 && str_contains(mb_strtolower((string) $why), 'duplicate') => ' X refuses a post identical to a recent one.',
            default => '',
        };

        throw new XPostFailed("X answered {$status}: ".mb_substr((string) $why, 0, 200).'.'.$hint, $status === 429 || $status >= 500);
    }
}
