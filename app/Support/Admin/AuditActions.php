<?php

namespace App\Support\Admin;

use App\Enums\AdminRole;
use App\Models\AdminAuditLog;
use App\Models\ContributorSubmission;
use App\Models\JobRun;
use App\Models\Tool;
use App\Models\User;
use App\Services\Review\ReviewableTypes;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * The audit log in words. A row stores the route name, the method and the route
 * parameters, which is what makes it exact; this turns them into "Changed a role to
 * Editor" and a link to the record, which is what makes it readable. The stored row is
 * never changed, so the export keeps the raw columns beside the label.
 */
final class AuditActions
{
    /** Route name => what the person did, in the past tense. */
    public const LABELS = [
        // Users and roles
        'backend.admin.users.invite' => 'Invited a user',
        'backend.admin.users.role' => 'Changed a role',
        'backend.admin.users.suspend' => 'Suspended a user',
        'backend.admin.users.restore' => 'Restored a user',
        'backend.admin.users.destroy' => 'Deleted a user',
        'backend.admin.users.invitation' => 'Resent an invitation',
        'backend.admin.users.password-reset' => 'Sent a password reset',
        'backend.admin.users.verification' => 'Resent a verification email',
        'backend.admin.users.two-factor.reset' => 'Reset a user’s second factor',
        'backend.admin.users.bulk' => 'Changed several users',
        'backend.admin.users.bulk.delete' => 'Deleted several users',
        'backend.admin.users.permissions.update' => 'Changed role permissions',
        'backend.admin.users.permissions.reset' => 'Reset role permissions to the defaults',
        'backend.admin.users.export' => 'Exported users',
        // Settings, billing, funding
        'backend.admin.settings.save' => 'Saved settings',
        'backend.admin.settings.test' => 'Sent a test email',
        'backend.admin.settings.turnstile' => 'Checked the bot-protection keys',
        'backend.admin.billing.check' => 'Checked the billing configuration',
        'backend.admin.billing.probe' => 'Tested the billing provider connection',
        'backend.admin.billing.provision' => 'Created the billing products',
        'backend.admin.billing.events.reapply' => 'Re-applied a billing webhook',
        'backend.admin.billing.export' => 'Exported subscriptions',
        'backend.admin.billing.payments.export' => 'Exported payments',
        'backend.admin.funding.store' => 'Added a funder',
        'backend.admin.funding.update' => 'Edited a funder',
        'backend.admin.funding.destroy' => 'Removed a funder',
        'backend.admin.funding.move' => 'Reordered funders',
        'backend.admin.funding.publish' => 'Published or hid a funder',
        // Operations
        'backend.admin.jobs.run' => 'Ran a job',
        'backend.admin.jobs.run.confirmed' => 'Ran a job',
        'backend.admin.jobs.export' => 'Exported job runs',
        'backend.admin.external.sync' => 'Synced the AI Incident Database',
        'backend.admin.audit.export' => 'Exported the audit log',
        // Audience
        'backend.admin.subscribers.resend' => 'Resent a subscription confirmation',
        'backend.admin.subscribers.resend.many' => 'Resent subscription confirmations',
        'backend.admin.subscribers.delete' => 'Removed a subscriber',
        'backend.admin.subscribers.delete.many' => 'Removed subscribers',
        'backend.admin.subscribers.export' => 'Exported subscribers',
        'backend.admin.downloads.export' => 'Exported downloads',
        'backend.admin.alerts.deliveries.retry' => 'Retried an alert delivery',
        'backend.admin.alerts.deliveries.retry.many' => 'Retried alert deliveries',
        'backend.admin.alerts.deliveries.export' => 'Exported alert deliveries',
        // Content
        'backend.admin.tools.store' => 'Created a tool',
        'backend.admin.tools.update' => 'Edited a tool',
        'backend.admin.tools.destroy' => 'Archived a tool',
        'backend.admin.tools.status.many' => 'Changed the status of tools',
        'backend.admin.tools.files.store' => 'Uploaded a tool file',
        'backend.admin.tools.files.toggle' => 'Switched a tool file on or off',
        'backend.admin.tools.files.destroy' => 'Removed a tool file',
        'backend.admin.submissions.export' => 'Exported submissions',
        'backend.review.decide' => 'Decided a submission',
        'backend.review.decide.many' => 'Decided several submissions',
        'backend.review.verify' => 'Recorded a verification',
        'backend.review.verify.many' => 'Recorded verifications',
        'backend.review.publish' => 'Published or unpublished a record',
        'backend.review.publish.many' => 'Published or unpublished records',
        'backend.review.export' => 'Exported the review queue',
        'backend.checks.export' => 'Exported independent checks',
        // Your own account
        'admin.two-factor.verify' => 'Entered a sign-in code',
        'admin.two-factor.confirm' => 'Set up an authenticator',
        'admin.two-factor.regenerate' => 'Regenerated recovery codes',
    ];

    private const DECISIONS = ['approved' => 'Approved', 'rejected' => 'Rejected', 'needs_information' => 'Asked for more information on'];

    /** What the row says the person did, with the detail its parameters add. */
    public static function label(?string $route, string $method = 'POST', ?string $path = null, array $params = []): string
    {
        if ($route === null || $route === '') {
            return trim($method.' '.($path ?? ''));
        }
        $label = self::LABELS[$route] ?? self::humanise($route);

        return match (true) {
            $route === 'backend.admin.users.role' && ($role = AdminRole::tryFrom((string) ($params['role'] ?? ''))) !== null => 'Changed a role to '.$role->label(),
            $route === 'backend.admin.users.bulk' => match ($params['action'] ?? null) {
                'role' => 'Set a role on several users',
                'suspend' => 'Suspended several users',
                'restore' => 'Restored several users',
                'verify' => 'Resent verification to several users',
                default => $label,
            },
            in_array($route, ['backend.admin.jobs.run', 'backend.admin.jobs.run.confirmed'], true) && isset(JobRun::JOBS[$params['job'] ?? '']) => 'Ran “'.JobRun::JOBS[$params['job']]['label'].'”',
            $route === 'backend.review.decide' && isset(self::DECISIONS[$params['decision'] ?? '']) => self::DECISIONS[$params['decision']].' a submission',
            $route === 'backend.review.decide.many' && isset(self::DECISIONS[$params['decision'] ?? '']) => self::DECISIONS[$params['decision']].' several submissions',
            $route === 'backend.admin.funding.publish' && in_array($params['state'] ?? null, ['on', 'off'], true) => $params['state'] === 'on' ? 'Published a funder' : 'Hid a funder',
            $route === 'backend.admin.tools.status.many' && isset(Tool::STATUSES[$params['status'] ?? '']) => 'Set tools to '.Str::lower(Tool::STATUSES[$params['status']]),
            default => $label,
        };
    }

    /** A route name nobody has labelled yet, as words: "backend.admin.foo.bar-baz" → "Foo bar baz". */
    public static function humanise(string $route): string
    {
        $name = preg_replace('/^(backend\.admin\.|backend\.|admin\.)/', '', $route);

        return Str::ucfirst(trim(preg_replace('/\s+/', ' ', str_replace(['.', '-', '_'], ' ', (string) $name))));
    }

    /** Success, Refused or Failed, from the stored status. */
    public static function outcome(int $status): string
    {
        return match (true) {
            $status >= 500 => 'Failed',
            $status >= 400 => 'Refused',
            default => 'Success',
        };
    }

    /** The badge tone for an outcome, as x-backend.badge reads it. */
    public static function tone(int $status): string
    {
        return $status >= 500 ? 'failed' : ($status >= 400 ? 'unconfirmed' : 'ok');
    }

    /** Names for the records a page of rows points at, fetched once per page rather than per row. */
    public static function lookups(Collection $entries): array
    {
        $ids = fn (string $key) => $entries->pluck('route_params.'.$key)->filter(fn ($v) => is_numeric($v))->map(fn ($v) => (int) $v)->unique()->values();

        return [
            'user' => User::whereKey($ids('user'))->pluck('name', 'id')->all(),
            'tool' => Tool::whereKey($ids('tool'))->pluck('title', 'id')->all(),
            'submission' => ContributorSubmission::whereKey($ids('submission'))->pluck('summary', 'id')->all(),
        ];
    }

    /**
     * The record a row acted on, linked to its admin page when this viewer may open it.
     * Null when the row names no single record (a settings save, a bulk action).
     *
     * @return array{text:string, url:?string}|null
     */
    public static function target(AdminAuditLog $entry, ?User $viewer, array $lookups = []): ?array
    {
        $p = $entry->route_params ?? [];
        $can = fn (string $capability) => (bool) $viewer?->can($capability);

        if (isset($p['user']) && is_numeric($p['user'])) {
            $name = $lookups['user'][(int) $p['user']] ?? null;

            return ['text' => $name ?? 'User #'.$p['user'].($lookups !== [] ? ' (deleted)' : ''), 'url' => $name !== null && $can('users.manage') ? route('backend.admin.users.show', (int) $p['user']) : null];
        }
        if (isset($p['tool']) && is_numeric($p['tool'])) {
            $title = $lookups['tool'][(int) $p['tool']] ?? null;
            $file = isset($p['file']) ? ' · file #'.$p['file'] : '';

            return ['text' => ($title ?? 'Tool #'.$p['tool']).$file, 'url' => $title !== null && $can('tools.manage') ? route('backend.admin.tools.edit', (int) $p['tool']) : null];
        }
        if (isset($p['submission']) && is_numeric($p['submission'])) {
            $summary = $lookups['submission'][(int) $p['submission']] ?? null;

            return ['text' => $summary !== null ? Str::limit($summary, 60) : 'Submission #'.$p['submission'],
                'url' => $summary !== null && $can('submissions.decide') ? route('backend.admin.submissions', ['q' => Str::limit($summary, 60, '')]).'#submission-'.$p['submission'] : null];
        }
        if (isset($p['type'], $p['slug']) && ReviewableTypes::has((string) $p['type'])) {
            return ['text' => ReviewableTypes::label((string) $p['type']).': '.$p['slug'], 'url' => $can('submissions.decide') ? route('backend.review.index', ['type' => $p['type'], 'q' => $p['slug']]) : null];
        }
        if (isset($p['job']) && isset(JobRun::JOBS[$p['job']])) {
            return ['text' => JobRun::JOBS[$p['job']]['label'], 'url' => $can('jobs.run') ? route('backend.admin.jobs', ['job' => $p['job']]).'#history' : null];
        }
        if (isset($p['funder'])) {
            return ['text' => 'Funder #'.$p['funder'], 'url' => $can('settings.manage') ? route('backend.admin.funding.index') : null];
        }
        if (isset($p['event'])) {
            return ['text' => 'Billing webhook #'.$p['event'], 'url' => $can('billing.manage') ? route('backend.admin.billing.index', ['tab' => 'webhooks']) : null];
        }
        if (isset($p['delivery'])) {
            return ['text' => 'Alert delivery #'.$p['delivery'], 'url' => $can('audience.view') ? route('backend.admin.alerts.index') : null];
        }
        if (isset($p['subscriber'])) {
            return ['text' => 'Subscriber #'.$p['subscriber'], 'url' => null];
        }
        if (isset($p['ids']) && is_string($p['ids']) && $p['ids'] !== '') {
            $n = count(explode(',', preg_replace('/\s*\(\+(\d+)\)$/', '', $p['ids'])));
            $more = preg_match('/\(\+(\d+)\)$/', $p['ids'], $m) ? (int) $m[1] : 0;
            $total = $n + $more;

            return ['text' => $total.' '.($total === 1 ? 'record' : 'records'), 'url' => null];
        }

        return null;
    }

    /** The parameters not already said by the label or the target, as "key=value" for the detail line. */
    public static function rest(AdminAuditLog $entry): string
    {
        $said = ['user', 'tool', 'file', 'submission', 'type', 'slug', 'job', 'funder', 'event', 'delivery', 'subscriber', 'role', 'decision', 'state', 'ids'];

        return collect($entry->route_params ?? [])->except($said)->map(fn ($v, $k) => $k.'='.$v)->implode(' ');
    }
}
