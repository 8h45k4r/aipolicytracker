<?php

namespace App\Support;

use App\Http\Middleware\AssignRequestId;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Tells the administrators by email when production hits an unhandled error, so a broken
 * page is not discovered by a reader first. There is no error-tracking service; this is
 * the smallest thing that alerts a person.
 *
 * Throttled twice: one email per distinct error (class, file, line) per hour, and at most
 * DAILY_CAP emails a day in all, so an outage cannot flood the inbox. The email carries the
 * error class, message, place and request id, never request data, cookies or input.
 * Reporting must never fail: anything that goes wrong here is swallowed.
 */
class ErrorAlert
{
    public const DAILY_CAP = 20;

    public static function report(Throwable $e): void
    {
        try {
            $to = array_values(array_filter((array) config('aipolicytracker.admin_emails')));
            if (! app()->isProduction() || ! config('aipolicytracker.error_alerts', true) || $to === [] || config('mail.default') === 'log') {
                return;
            }
            $fingerprint = sha1(get_class($e).'|'.$e->getFile().'|'.$e->getLine());
            if (! Cache::add('error-alert:'.$fingerprint, 1, now()->addHour())) {
                return;
            }
            $day = 'error-alert-count:'.now()->toDateString();
            Cache::add($day, 0, now()->addDay());
            if (Cache::increment($day) > self::DAILY_CAP) {
                return;
            }

            $request = app()->runningInConsole() ? null : request();
            $lines = [
                'An unhandled error occurred on '.config('app.url').'.',
                '',
                'Error: '.get_class($e),
                'Message: '.mb_substr($e->getMessage(), 0, 500),
                'Where: '.str_replace(base_path().'/', '', $e->getFile()).':'.$e->getLine(),
                'When: '.now()->toIso8601String(),
                $request ? 'Request: '.$request->method().' /'.ltrim($request->path(), '/').' (id '.AssignRequestId::assign($request).')' : 'Context: console or queue',
                '',
                'The full trace is in the container log (docker compose logs app). Further alerts for this error are held for an hour.',
            ];
            Mail::raw(implode("\n", $lines), function ($message) use ($to, $e) {
                $message->to($to)->subject('[AIPolicyTracker] Error: '.class_basename($e));
            });
        } catch (Throwable) {
            // Never let alerting break error reporting.
        }
    }
}
