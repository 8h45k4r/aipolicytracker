<?php

namespace App\Http\Middleware;

use App\Models\AdminAuditLog;
use App\Support\Privacy;
use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Writes one audit row for every state-changing admin request, and for every CSV
 * export (personal data leaving the system).
 *
 * Other reads are not logged: they are numerous and reveal nothing that the audit is
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

        $this->record($request, $this->outcome($request, $response->getStatusCode()));

        return $response;
    }

    private function record(Request $request, int $status): void
    {
        if (! $request->user() || ! ($this->changes($request) || $this->exports($request))) {
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

    private function changes(Request $request): bool
    {
        return in_array($request->method(), self::LOGGED_METHODS, true);
    }

    /** A CSV export of personal data is a read, but one the audit must show: who took which list. */
    private function exports(Request $request): bool
    {
        return $request->isMethod('GET') && str_ends_with((string) $request->route()?->getName(), '.export');
    }

    /**
     * A refused form comes back as a 302 like a successful one. It is recorded as 422 when
     * the request flashed validation errors and 400 when it flashed an error message, so
     * the log's "failed" filter finds it. Only keys flashed by this request count, not
     * ones left over from the page before.
     */
    private function outcome(Request $request, int $status): int
    {
        if ($status < 300 || $status >= 400 || ! $request->hasSession()) {
            return $status;
        }
        $new = (array) $request->session()->get('_flash.new', []);

        return in_array('errors', $new, true) ? 422 : (in_array('error', $new, true) ? 400 : $status);
    }

    /** Fields of a bulk form that say what was done, never what was typed. */
    private const ACTION_FIELDS = ['action', 'decision', 'role', 'status', 'scope', 'state'];

    /**
     * Route parameters reduced to identifiers (a bound model becomes its key, never its
     * attributes), plus the ids and the action of a bulk request, which has no route
     * parameter to say which records it changed, and the filters of an export.
     */
    private function scalarParams(Request $request): ?array
    {
        $out = [];
        foreach ($request->route()?->parameters() ?? [] as $name => $value) {
            $out[$name] = $value instanceof Model ? $value->getKey() : (is_scalar($value) ? $value : null);
        }
        $ids = $request->input('ids');
        if (is_array($ids) && $ids !== []) {
            $ids = array_values(array_filter($ids, fn ($id) => is_scalar($id) && preg_match('/^[\w.-]{1,64}$/', (string) $id)));
            $out['ids'] = implode(',', array_slice($ids, 0, 200)).(count($ids) > 200 ? ' (+'.(count($ids) - 200).')' : '');
        }
        if ($this->changes($request)) {
            foreach (self::ACTION_FIELDS as $field) {
                $value = $request->input($field);
                if (is_string($value) && preg_match('/^[\w.-]{1,40}$/', $value)) {
                    $out[$field] = $value;
                }
            }
        }
        if ($this->exports($request)) {
            $filters = collect($request->query())->except(['page'])->filter(fn ($v) => is_scalar($v) && $v !== '')->map(fn ($v) => mb_substr((string) $v, 0, 60));
            if ($filters->isNotEmpty()) {
                $out['filters'] = $filters->map(fn ($v, $k) => $k.'='.$v)->implode('&');
            }
        }

        return $out === [] ? null : $out;
    }
}
