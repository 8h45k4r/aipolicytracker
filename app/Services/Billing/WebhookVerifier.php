<?php

namespace App\Services\Billing;

use Illuminate\Http\Request;
use StandardWebhooks\Exception\WebhookVerificationException;
use StandardWebhooks\Webhook;

/**
 * Verifies the Standard Webhooks signature Dodo puts on every delivery
 * (HMAC-SHA256 over "id.timestamp.body", 5-minute timestamp tolerance).
 */
class WebhookVerifier
{
    public function __construct(private BillingConfig $config) {}

    /**
     * @return array the decoded payload
     *
     * @throws WebhookVerificationException on a missing secret, bad signature or stale timestamp
     */
    public function verify(Request $request): array
    {
        $secret = $this->config->webhookSecret();
        if ($secret === '') {
            throw new WebhookVerificationException('Webhook secret is not configured');
        }
        $headers = [
            'webhook-id' => (string) $request->header('webhook-id', ''),
            'webhook-timestamp' => (string) $request->header('webhook-timestamp', ''),
            'webhook-signature' => (string) $request->header('webhook-signature', ''),
        ];
        if (in_array('', $headers, true)) {
            throw new WebhookVerificationException('Missing required headers');
        }
        $payload = (new Webhook($secret))->verify($request->getContent(), $headers);
        if (! is_array($payload)) {
            throw new WebhookVerificationException('Payload is not a JSON object');
        }

        return $payload;
    }
}
