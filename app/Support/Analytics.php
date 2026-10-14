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
    public const TOKEN_ROUTES = ['password.reset', 'invitation.show', 'verification.verify', 'subscribe.confirm', 'subscribe.unsubscribe'];

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
