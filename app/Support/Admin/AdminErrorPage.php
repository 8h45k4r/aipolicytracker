<?php

namespace App\Support\Admin;

use App\Enums\AdminCapability;
use App\Http\Middleware\AssignRequestId;
use App\Http\Middleware\EnsureAdminSecondFactor;
use App\Models\User;
use App\Providers\AppServiceProvider;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

/**
 * Errors inside /backend, explained in the admin rather than on the public site.
 *
 * The public error pages tell a reader that every policy record is public and offer the
 * home page. Inside the admin that is the wrong answer: an editor refused the audit log
 * needs to know it is their role, and an owner who clicked too fast needs to know how
 * long to wait. For a signed-in administrator whose session has passed the second factor,
 * the error renders inside the admin layout, sidebar intact, with a plain explanation and
 * a way back. A session that has not (signed out, or not yet past the factor) never sees
 * the admin navigation: it gets a standalone admin card for an expired form or a rate
 * limit, and the public page for anything else.
 *
 * Anything that goes wrong while drawing the admin page (a 500 is often the database)
 * returns null, and the framework falls back to the self-contained public error pages.
 */
final class AdminErrorPage
{
    public const STATUSES = [403, 404, 419, 429, 500];

    public static function render(Throwable $e, Request $request): ?Response
    {
        if (! $request->is('backend', 'backend/*') || $request->expectsJson()) {
            return null;
        }
        $status = self::status($e);
        if ($status === null) {
            return null;
        }

        try {
            $user = $request->user();
            $admin = $user instanceof User && EnsureAdminSecondFactor::passed($request);
            if (! $admin && ! in_array($status, [419, 429], true)) {
                return null;
            }
            $headers = $e instanceof HttpExceptionInterface ? $e->getHeaders() : [];
            $html = view('backend.errors.show', self::content($status, $request, $admin ? $user : null, $headers))->render();

            return response($html, $status, $headers);
        } catch (Throwable) {
            return null;
        }
    }

    /** The status this exception will answer with, when it is one this page explains. */
    private static function status(Throwable $e): ?int
    {
        if ($e instanceof HttpExceptionInterface) {
            $status = $e->getStatusCode();

            return in_array($status, self::STATUSES, true) && ($status !== 500 || ! config('app.debug')) ? $status : null;
        }
        // These become redirects or validation responses, not error pages.
        if ($e instanceof AuthenticationException || $e instanceof ValidationException || $e instanceof HttpResponseException) {
            return null;
        }

        // An unhandled fault. With debugging on, the developer's page is more use.
        return config('app.debug') ? null : 500;
    }

    /**
     * What the page says.
     *
     * @return array{status:int, admin:bool, eyebrow:string, heading:string, body:string, detail:?string, actions:list<array{0:string,1:string,2:bool}>, reference:?string}
     */
    public static function content(int $status, Request $request, ?User $user, array $headers = []): array
    {
        $back = self::back($request);
        $dashboard = $user?->can('dashboard.view') ? [route('backend.admin.dashboard'), 'Back to the dashboard', true] : null;
        $detail = null;
        $reference = null;

        switch ($status) {
            case 403:
                $role = $user?->adminRoleLabel() ?? 'None';
                $needed = self::missingCapability($request, $user);
                $heading = 'You can’t open this page';
                if ($user?->isOwner()) {
                    $body = 'Even an owner can’t do this here. A few actions are refused for every account, such as changing your own access or another owner’s.';
                } elseif ($needed) {
                    $body = "Your role ({$role}) can’t open this page: it needs the permission “{$needed->label()}”, which your role does not have. Ask an owner if you need it.";
                } else {
                    $body = "Your role ({$role}) isn’t allowed to do this. If you need it, ask an owner to change your role.";
                }
                $detail = 'Nothing was changed. The sidebar only lists the pages your role can open.';
                $actions = array_values(array_filter([$dashboard, $back ? [$back, 'Go back', false] : null]));
                break;
            case 404:
                $heading = 'Nothing at this address';
                $body = 'The record may have been deleted or renamed, or the link may be mistyped.';
                $detail = 'Press Ctrl K (⌘ K on a Mac) to search pages, people and records.';
                $actions = array_values(array_filter([$dashboard, $back ? [$back, 'Go back', false] : null]));
                break;
            case 419:
                if ($user) {
                    $heading = 'This form expired before it was sent';
                    $body = 'The page was open long enough that its security token went stale, so nothing was saved. Go back, reload the page and send it again.';
                    $actions = array_values(array_filter([$back ? [$back, 'Go back and reload', true] : null, $dashboard]));
                } else {
                    $heading = 'Your session has ended';
                    $body = 'You were signed out while the page was open, so nothing was saved. Sign in again, then repeat the last step.';
                    $actions = [[route('login'), 'Sign in again', true]];
                }
                break;
            case 429:
                $wait = max(1, (int) ($headers['Retry-After'] ?? 60));
                $heading = 'Slow down a moment';
                $body = 'That was more requests in a minute than the admin accepts, so the last one was refused before it ran: nothing from it was saved. Try again in '.$wait.' '.($wait === 1 ? 'second' : 'seconds').'.';
                $detail = $user ? 'The admin allows '.number_format(AppServiceProvider::ADMIN_REQUESTS_PER_MINUTE).' requests a minute per account; a few actions, such as invitations and sign-in codes, have tighter limits of their own.' : null;
                $actions = array_values(array_filter([$back ? [$back, 'Go back', true] : null, $dashboard]));
                break;
            default:
                $heading = 'Something broke on our side';
                $body = 'This is a fault in the site, not in anything you did, and it has been logged. If you were saving something, reload the page to check whether it went through before trying again.';
                $reference = AssignRequestId::assign($request);
                $actions = array_values(array_filter([$back ? [$back, 'Go back', true] : null, $dashboard]));
        }

        return ['status' => $status, 'admin' => $user !== null, 'eyebrow' => 'Error '.$status, 'heading' => $heading, 'body' => $body, 'detail' => $detail, 'actions' => $actions, 'reference' => $reference];
    }

    /**
     * The permission named by the route's `can:` middleware that this account lacks, when
     * that is why it was refused. A refusal from inside a controller, on a route whose
     * permissions the account does hold, names none rather than the wrong one.
     */
    private static function missingCapability(Request $request, ?User $user): ?AdminCapability
    {
        foreach (array_reverse($request->route()?->gatherMiddleware() ?? []) as $middleware) {
            if (is_string($middleware) && str_starts_with($middleware, 'can:')) {
                $capability = AdminCapability::tryFrom(explode(',', substr($middleware, 4))[0]);
                if ($capability && ! $user?->can($capability->value)) {
                    return $capability;
                }
            }
        }

        return null;
    }

    /** The page the visitor came from, when it is on this site and is not this page. */
    private static function back(Request $request): ?string
    {
        $previous = (string) $request->headers->get('referer');
        if ($previous === '' || $previous === $request->fullUrl() || parse_url($previous, PHP_URL_HOST) !== $request->getHost()) {
            return null;
        }

        return $previous;
    }
}
