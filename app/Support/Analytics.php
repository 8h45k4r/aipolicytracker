<?php

namespace App\Support;

/**
 * When a page may load analytics. Pages whose address carries a secret (a password
 * reset, an invitation, an email check, a subscription confirm or unsubscribe link)
 * never do: the page URL is what an analytics hit records, and the token in it would
 * let whoever reads the report act as the person it was sent to.
 */
final class Analytics
{
    /** Routes whose URL carries a token. */
    // Pages whose address carries a secret (a token or a URL signature that acts on the
    // reader's behalf): analytics would send that address to a third party.
    public const TOKEN_ROUTES = ['password.reset', 'invitation.show', 'verification.verify', 'subscribe.confirm', 'subscribe.unsubscribe', 'alerts.unsubscribe', 'templates.updates.unsubscribe', 'templates.download', 'tools.ready', 'tools.file'];

    public static function configured(): bool
    {
        return (bool) (config('aipolicytracker.google_analytics_id') || config('aipolicytracker.cloudflare_analytics_token'));
    }

    /** Whether this request may load analytics at all (consent is asked for separately). */
    public static function allowedHere(): bool
    {
        return self::configured() && ! request()->routeIs(...self::TOKEN_ROUTES);
    }
}
