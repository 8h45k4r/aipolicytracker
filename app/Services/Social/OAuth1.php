<?php

namespace App\Services\Social;

/**
 * OAuth 1.0a request signing (HMAC-SHA1), as X's API still accepts for posts
 * made on behalf of one account. The four keys never expire, which suits a
 * server that posts unattended; OAuth 2.0 tokens would need a refresh flow.
 *
 * A JSON body is not part of the signature, so for POST /2/tweets only the
 * oauth_* values are signed. $params is for query or form parameters.
 */
final class OAuth1
{
    public function __construct(
        private string $consumerKey,
        private string $consumerSecret,
        private string $token,
        private string $tokenSecret,
    ) {}

    /** @param array<string, string> $params */
    public function header(string $method, string $url, array $params = [], ?string $nonce = null, ?int $timestamp = null): string
    {
        $oauth = [
            'oauth_consumer_key' => $this->consumerKey,
            'oauth_nonce' => $nonce ?? bin2hex(random_bytes(16)),
            'oauth_signature_method' => 'HMAC-SHA1',
            'oauth_timestamp' => (string) ($timestamp ?? time()),
            'oauth_token' => $this->token,
            'oauth_version' => '1.0',
        ];
        $oauth['oauth_signature'] = $this->signature($method, $url, $oauth + $params);

        ksort($oauth);

        return 'OAuth '.implode(', ', array_map(fn ($k, $v) => rawurlencode($k).'="'.rawurlencode($v).'"', array_keys($oauth), $oauth));
    }

    /** @param array<string, string> $params */
    public function signature(string $method, string $url, array $params): string
    {
        $encoded = [];
        foreach ($params as $k => $v) {
            $encoded[rawurlencode((string) $k)] = rawurlencode((string) $v);
        }
        ksort($encoded, SORT_STRING);
        $pairs = implode('&', array_map(fn ($k, $v) => $k.'='.$v, array_keys($encoded), $encoded));
        $base = strtoupper($method).'&'.rawurlencode($url).'&'.rawurlencode($pairs);
        $key = rawurlencode($this->consumerSecret).'&'.rawurlencode($this->tokenSecret);

        return base64_encode(hash_hmac('sha1', $base, $key, true));
    }
}
