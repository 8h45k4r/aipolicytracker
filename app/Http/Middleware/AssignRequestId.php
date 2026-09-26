<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * Gives every request one identifier that appears in three places: the log line of
 * anything that goes wrong, the X-Request-Id response header, and the reference the
 * error page asks the reader to quote. Before this, the error page printed a random
 * number that was written nowhere, so a quoted reference could not be found.
 *
 * The proxy may supply the id (nginx passes its own $request_id) so one number
 * follows the request through the access log as well; anything that does not look
 * like an identifier is replaced rather than trusted.
 */
class AssignRequestId
{
    public const ATTRIBUTE = 'request_id';

    public const HEADER = 'X-Request-Id';

    public function handle(Request $request, Closure $next): Response
    {
        $id = self::assign($request);

        $response = $next($request);
        $response->headers->set(self::HEADER, $id);

        return $response;
    }

    /** The request's id, assigning one if the request has none yet. */
    public static function assign(Request $request): string
    {
        if ($existing = $request->attributes->get(self::ATTRIBUTE)) {
            return $existing;
        }
        $supplied = (string) $request->headers->get(self::HEADER, '');
        $id = preg_match('/^[A-Za-z0-9._-]{8,64}$/', $supplied) ? $supplied : Str::lower(Str::random(16));
        $request->attributes->set(self::ATTRIBUTE, $id);
        Log::shareContext([self::ATTRIBUTE => $id]);

        return $id;
    }
}
