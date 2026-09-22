<?php

namespace App\Http\Middleware;

use App\Models\AdminAuditLog;
use App\Support\Privacy;
use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Writes one audit row for every state-changing admin request.
 *
 * Reads are not logged: they are numerous and reveal nothing that the audit is
 * for. Request bodies are never stored, because they carry passwords, codes and
 * API keys; route parameters are enough to say which record was acted on. The
 * row is written after the response so a failed request is recorded with its
 * status rather than as if it had succeeded, and a logging failure never breaks
 * the request it describes.
 *
 * A request refused by an earlier gate is audited too: an attempt to save
 * settings that was bounced to password confirmation is a fact worth keeping.
 *
 * Statuses come from the response, which is the status the visitor actually
 * receives: Laravel's routing pipeline turns an exception raised in a controller
 * into a response before it reaches this middleware, so an `abort(404)` is
 * recorded as 404 and a failed validation as the 302 the browser follows. The
 * `catch` below is a safety net for anything that still escapes, not the usual
 * path.
 *
 * The one gap is deliberate. Route-model binding runs in the `web` group, which
 * is ahead of all route middleware, so a POST naming a record that does not
 * exist 404s before this ever runs and leaves no row. Nothing was changed in
 * that case, so the audit's purpose — tracing a change to a person — holds.
 */
class RecordAdminAction
{
    private const LOGGED_METHODS = ['POST', 'PUT', 'PATCH', 'DELETE'];

    public function handle(Request $request, Closure $next): Response
    {
        try {
            $response = $next($request);
        } catch (\Throwable $e) {
            // Rare: almost everything is already a response by now. Recorded as a server
            // error and rethrown unchanged, because an action that blew up is exactly the
            // kind an audit must not lose.
            $this->record($request, 500);
            throw $e;
        }

        $this->record($request, $response->getStatusCode());

        return $response;
    }

    private function record(Request $request, int $status): void
    {
        if (! in_array($request->method(), self::LOGGED_METHODS, true) || ! $request->user()) {
            return;
        }
        try {
            AdminAuditLog::create([
                'user_id' => $request->user()->getKey(),
                'user_email' => $request->user()->email,
                'method' => $request->method(),
                'route_name' => $request->route()?->getName(),
                'path' => mb_substr('/'.ltrim($request->path(), '/'), 0, 512),
                'route_params' => $this->scalarParams($request),
                'status' => $status,
                'ip_hash' => $request->ip() ? Privacy::ipHash((string) $request->ip()) : null,
                'user_agent' => mb_substr((string) $request->userAgent(), 0, 255),
                'created_at' => now(),
            ]);
        } catch (\Throwable $e) {
            report($e); // a logging failure never breaks the request it describes
        }
    }

    /** Route parameters reduced to identifiers: a bound model becomes its key, never its attributes. */
    private function scalarParams(Request $request): ?array
    {
        $out = [];
        foreach ($request->route()?->parameters() ?? [] as $name => $value) {
            $out[$name] = $value instanceof Model ? $value->getKey() : (is_scalar($value) ? $value : null);
        }

        return $out === [] ? null : $out;
    }
}
