<?php

namespace App\Services\Alerts;

use App\Models\AlertChannel;
use App\Models\ChannelDelivery;
use Illuminate\Support\Facades\Http;

/**
 * Sends an alert to Slack and webhook channels and keeps the delivery log.
 * A generic webhook receives the JSON payload with an X-AIP-Signature header
 * (sha256 HMAC of the body with the channel secret) and X-AIP-Delivery id;
 * Slack receives a text message. A failure is retried with backoff on the
 * next run, up to five attempts, and the last error is kept.
 */
final class WebhookDispatcher
{
    public const TIMEOUT = 8;

    public function queue(AlertChannel $channel, array $payload, ?int $alertDeliveryId = null): ChannelDelivery
    {
        return ChannelDelivery::create(['alert_channel_id' => $channel->id, 'alert_delivery_id' => $alertDeliveryId, 'payload' => $payload, 'status' => 'pending', 'attempts' => 0]);
    }

    /** Try every due delivery once. @return array{sent:int, failed:int} */
    public function deliverDue(): array
    {
        $sent = $failed = 0;
        ChannelDelivery::due()->with('channel')->orderBy('id')->chunkById(100, function ($deliveries) use (&$sent, &$failed) {
            foreach ($deliveries as $d) {
                $this->attempt($d) ? $sent++ : $failed++;
            }
        });

        return compact('sent', 'failed');
    }

    public function attempt(ChannelDelivery $delivery): bool
    {
        $channel = $delivery->channel;
        if (! $channel || ! $channel->enabled || ! $channel->endpoint) {
            $delivery->update(['status' => 'failed', 'last_error' => 'Channel disabled or missing', 'attempts' => $delivery->attempts + 1]);

            return false;
        }
        $delivery->attempts++;
        try {
            $response = $channel->kind === 'slack'
                ? Http::timeout(self::TIMEOUT)->asJson()->post($channel->endpoint, ['text' => $this->slackText($delivery->payload)])
                : $this->signedPost($channel, $delivery);
            if ($response->successful()) {
                $delivery->forceFill(['status' => 'sent', 'response_code' => $response->status(), 'sent_at' => now(), 'last_error' => null, 'next_attempt_at' => null])->save();
                $channel->update(['last_delivered_at' => now()]);

                return true;
            }
            $this->fail($delivery, $response->status(), 'HTTP '.$response->status());
        } catch (\Throwable $e) {
            $this->fail($delivery, null, mb_substr($e->getMessage(), 0, 480));
        }

        return false;
    }

    private function signedPost(AlertChannel $channel, ChannelDelivery $delivery)
    {
        $body = json_encode($delivery->payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        return Http::timeout(self::TIMEOUT)->withHeaders([
            'Content-Type' => 'application/json',
            'User-Agent' => 'aipolicytracker-alerts/1.0',
            'X-AIP-Signature' => $channel->sign($body),
            'X-AIP-Delivery' => (string) $delivery->id,
            'X-AIP-Event' => $delivery->payload['event'] ?? 'alert',
        ])->withBody($body, 'application/json')->post($channel->endpoint);
    }

    private function fail(ChannelDelivery $delivery, ?int $code, string $error): void
    {
        $exhausted = $delivery->attempts >= ChannelDelivery::MAX_ATTEMPTS;
        $delivery->forceFill([
            'status' => $exhausted ? 'failed' : 'pending',
            'response_code' => $code,
            'last_error' => $error,
            'next_attempt_at' => $exhausted ? null : now()->addMinutes(ChannelDelivery::backoffMinutes($delivery->attempts)),
        ])->save();
    }

    /** Slack gets the same facts as the email, as text with links. */
    public function slackText(array $payload): string
    {
        $lines = ['*'.($payload['title'] ?? 'AI policy alert').'*'];
        foreach (array_slice($payload['changes'] ?? [], 0, 10) as $c) {
            $lines[] = sprintf('• %s <%s|%s> (%s, %s)', $c['occurred_on'] ?? '', $c['url'] ?? '', $c['title'] ?? '', $c['jurisdiction'] ?? '', $c['impact_level'] ?? '');
        }
        foreach (array_slice($payload['deadlines'] ?? [], 0, 5) as $d) {
            $lines[] = sprintf('• Due %s: <%s|%s> (%s)', $d['due_on'] ?? '', $d['url'] ?? '', $d['title'] ?? '', $d['jurisdiction'] ?? '');
        }
        $lines[] = '_'.($payload['note'] ?? 'Informational only; not legal advice.').'_ '.($payload['manage_url'] ?? '');

        return implode("\n", $lines);
    }

    /** The payload every channel receives: the same rows as the email, as data with official source links. */
    public static function payload(array $digest, string $title, string $manageUrl): array
    {
        return [
            'event' => 'alert.daily',
            'title' => $title,
            'sent_at' => now()->toAtomString(),
            'changes' => $digest['changes']->map(fn ($c) => ['slug' => $c->slug, 'title' => $c->title, 'occurred_on' => $c->occurred_on?->toDateString(), 'jurisdiction' => $c->jurisdiction?->name, 'policy' => $c->policyInstrument?->slug, 'impact_level' => $c->impact_level, 'what_changed' => $c->what_changed, 'official_source_url' => $c->official_source_url, 'url' => $c->url()])->values()->all(),
            'deadlines' => $digest['deadlines']->map(fn ($d) => ['title' => $d->title, 'due_on' => $d->due_on?->toDateString(), 'jurisdiction' => $d->policyInstrument?->jurisdiction?->name, 'policy' => $d->policyInstrument?->slug, 'official_source_url' => $d->official_source_url, 'url' => $d->policyInstrument?->url()])->values()->all(),
            'note' => 'Informational only; not legal advice. Every row links to its record and official source.',
            'manage_url' => $manageUrl,
        ];
    }
}
