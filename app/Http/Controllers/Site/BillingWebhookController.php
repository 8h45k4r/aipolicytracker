<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Services\Billing\WebhookProcessor;
use App\Services\Billing\WebhookVerifier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use StandardWebhooks\Exception\WebhookVerificationException;

/**
 * Receives Dodo Payments webhooks. Unsigned or mis-signed requests are refused
 * before anything is read; verified events are stored once and applied.
 * A processing error returns 500 so the provider retries the same webhook id.
 */
class BillingWebhookController extends Controller
{
    public function __invoke(Request $request, WebhookVerifier $verifier, WebhookProcessor $processor): JsonResponse
    {
        try {
            $payload = $verifier->verify($request);
        } catch (WebhookVerificationException $e) {
            Log::notice('billing.webhook.rejected', ['reason' => $e->getMessage(), 'ip' => $request->ip()]);

            return response()->json(['message' => 'Invalid signature'], 400);
        }

        try {
            $outcome = $processor->handle($payload, (string) $request->header('webhook-id'));
        } catch (\Throwable) {
            return response()->json(['message' => 'Processing failed'], 500);
        }

        return response()->json(['outcome' => $outcome]);
    }
}
