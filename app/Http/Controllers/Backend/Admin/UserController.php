<?php

namespace App\Http\Controllers\Backend\Admin;

use App\Enums\AdminCapability;
use App\Enums\AdminRole;
use App\Http\Controllers\Auth\InvitationController;
use App\Http\Controllers\Controller;
use App\Mail\AdminInvitationMail;
use App\Models\AdminAuditLog;
use App\Models\ResourceDownload;
use App\Models\User;
use App\Rules\NotDisposableEmail;
use App\Services\Admin\RolePermissions;
use App\Support\Csv;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Accounts and the roles granted to them.
 *
 * Every write here goes through guardFor(), which enforces the two rules that keep this page
 * from becoming a way to take the platform over or lock it: nobody acts on their own account,
 * and nobody acts on an owner. Ownership lives in the ADMIN_EMAILS environment list, so an
 * owner cannot be demoted, suspended or deleted from a web form at all — that is the property
 * that makes a mistake here, or a stolen session, recoverable.
 */
class UserController extends Controller
{
    public const SORTS = ['joined' => 'Newest first', 'last_login' => 'Last signed in', 'name' => 'Name', 'email' => 'Email'];

    public function index(Request $request): View
    {
        $owners = config('aipolicytracker.admin_emails', []);
        $filters = $this->filters($request);

        return view('backend.admin.users', [
            'users' => $this->filteredQuery($filters)->with(['adminRoleGrantedBy', 'invitedBy'])->paginate(50)->withQueryString(),
            'filters' => $filters,
            'roles' => AdminRole::cases(),
            'sorts' => self::SORTS,
            'counts' => [
                'total' => User::count(),
                'owners' => User::owners()->count(),
                'granted' => User::whereNotNull('admin_role')->count(),
                'no_factor' => User::whereNotNull('admin_role')->whereNull('two_factor_confirmed_at')->whereNull('suspended_at')->count(),
                'invited' => User::whereNotNull('invited_at')->whereNull('email_verified_at')->count(),
                'suspended' => User::whereNotNull('suspended_at')->count(),
            ],
            'ownerAddresses' => count($owners),
            'inviteDays' => AdminInvitationMail::DAYS,
        ]);
    }

    /** One account: what it can do, where that came from, and what it has done in the admin. */
    public function show(User $user, RolePermissions $permissions): View
    {
        $user->load(['adminRoleGrantedBy', 'invitedBy']);

        // Rows about this account (a role change, a suspension) carry its id as the {user}
        // route parameter; rows by it carry its user_id. The two are shown separately.
        $about = AdminAuditLog::with('user')->where('route_name', 'like', 'backend.admin.users.%')
            ->where(fn ($q) => $q->where('route_params->user', $user->getKey())->orWhere('route_params->user', (string) $user->getKey()))
            ->latest('created_at')->limit(25)->get();

        return view('backend.admin.user', [
            'user' => $user,
            'roles' => AdminRole::cases(),
            'capabilities' => AdminCapability::cases(),
            'held' => $user->effectiveCapabilities(),
            'roleEdited' => $user->adminRole() ? $permissions->isEdited($user->adminRole()) : false,
            'actions' => AdminAuditLog::where('user_id', $user->getKey())->latest('created_at')->limit(25)->get(),
            'about' => $about,
            'locked' => $user->is(request()->user()) || $user->isOwner(),
            'counts' => [
                'follows' => $user->follows()->count(),
                'downloads' => ResourceDownload::where('user_id', $user->getKey())->count(),
            ],
        ]);
    }

    /** Every account matching the current filters, as CSV. No secrets: roles, status and dates only. */
    public function export(Request $request): StreamedResponse
    {
        $query = $this->filteredQuery($this->filters($request));

        return response()->streamDownload(function () use ($query) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['name', 'email', 'organisation', 'role', 'second_factor', 'status', 'email_verified_at', 'invited_at', 'last_login_at', 'created_at']);
            $query->chunk(500, function ($users) use ($out) {
                foreach ($users as $u) {
                    fputcsv($out, Csv::row([
                        $u->name, $u->email, $u->organization_name, $u->adminRoleLabel(),
                        $u->hasTwoFactorEnabled() ? 'enrolled' : 'none', $this->statusLabel($u),
                        $u->email_verified_at?->toIso8601String(), $u->invited_at?->toIso8601String(),
                        $u->last_login_at?->toIso8601String(), $u->created_at?->toIso8601String(),
                    ]));
                }
            });
            fclose($out);
        }, 'users-'.now()->format('Y-m-d').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /**
     * Create an account and email a week-long link to choose its password. A role can be
     * granted at the same time; ownership cannot, and an owner address is refused, because
     * an owner's account should be created by its owner.
     */
    public function invite(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'string', 'lowercase', 'email:rfc', 'max:255', Rule::unique('users', 'email'), new NotDisposableEmail],
            'admin_role' => ['nullable', 'string', Rule::in(AdminRole::values())],
            'note' => ['nullable', 'string', 'max:500'],
        ]);
        $email = Str::lower($data['email']);
        if (in_array($email, array_map('strtolower', config('aipolicytracker.admin_emails', [])), true)) {
            return back()->withInput()->withErrors(['email' => 'This address is an owner (ADMIN_EMAILS). Owners create their own account by registering.']);
        }

        $role = isset($data['admin_role']) && $data['admin_role'] !== '' ? AdminRole::from($data['admin_role']) : null;
        $user = new User(['name' => $data['name'], 'email' => $email, 'password' => Str::password(40)]);
        $user->forceFill([
            'invited_at' => now(),
            'invited_by' => $request->user()->getKey(),
            'admin_role' => $role,
            'admin_role_granted_by' => $role ? $request->user()->getKey() : null,
            'admin_role_granted_at' => $role ? now() : null,
        ])->save();

        $this->sendInvitation($user, $request->user(), $role, $data['note'] ?? null);

        return redirect()->route('backend.admin.users.show', $user)->with('success', "Invitation sent to {$email}. The link works for ".AdminInvitationMail::DAYS.' days.');
    }

    public function resendInvitation(Request $request, User $user): RedirectResponse
    {
        $this->guardFor($user, 'invite');
        abort_unless($user->invitationPending(), 422, 'This account has already accepted its invitation.');

        $this->sendInvitation($user, $request->user(), $user->adminRole(), null);
        $user->forceFill(['invited_at' => now()])->save();

        return back()->with('success', "A new invitation was sent to {$user->email}; the previous link no longer works.");
    }

    /** Email a password reset link, the same one "Forgot password" sends. */
    public function sendPasswordReset(User $user): RedirectResponse
    {
        $this->guardFor($user, 'send a password reset to');
        $status = Password::sendResetLink(['email' => $user->email]);

        return back()->with($status === Password::RESET_LINK_SENT ? 'success' : 'error', $status === Password::RESET_LINK_SENT
            ? "Password reset link sent to {$user->email}."
            : 'A reset link was sent to this address very recently; wait a minute and try again.');
    }

    public function resendVerification(User $user): RedirectResponse
    {
        $this->guardFor($user, 'verify');
        abort_if($user->hasVerifiedEmail(), 422, 'This address is already verified.');
        $user->sendEmailVerificationNotification();

        return back()->with('success', "Verification email sent to {$user->email}.");
    }

    /**
     * One action on every ticked account. Your own account and owners are skipped and
     * counted, never acted on, for the same reasons guardFor() gives for single actions.
     */
    public function bulk(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'ids' => ['required', 'array', 'min:1', 'max:500'],
            'ids.*' => ['integer'],
            'action' => ['required', Rule::in(['role', 'suspend', 'restore', 'verify'])],
            'admin_role' => ['nullable', 'string', Rule::in([...AdminRole::values(), 'none'])],
            'reason' => ['nullable', 'string', 'max:255'],
        ]);
        if ($data['action'] === 'role' && empty($data['admin_role'])) {
            return back()->with('error', 'Choose the role to set, then apply it again.');
        }

        [$targets, $skipped] = $this->bulkTargets($request, $data['ids']);
        $actor = $request->user();
        $done = 0;

        foreach ($targets as $u) {
            $changed = match ($data['action']) {
                'role' => $this->applyRole($u, $data['admin_role'] === 'none' ? null : AdminRole::from($data['admin_role']), $actor),
                'suspend' => $u->isSuspended() ? false : (function () use ($u, $data) {
                    $u->forceFill(['suspended_at' => now(), 'suspended_reason' => $data['reason'] ?? null])->save();
                    $u->endAllSessions();

                    return true;
                })(),
                'restore' => $u->isSuspended() ? (bool) $u->forceFill(['suspended_at' => null, 'suspended_reason' => null])->save() : false,
                'verify' => $u->hasVerifiedEmail() ? false : (function () use ($u) {
                    $u->sendEmailVerificationNotification();

                    return true;
                })(),
            };
            $done += $changed ? 1 : 0;
        }

        $verb = ['role' => 'Role set on', 'suspend' => 'Suspended', 'restore' => 'Restored', 'verify' => 'Verification email sent to'][$data['action']];
        $unchanged = $targets->count() - $done;

        return back()->with('success', trim($verb.' '.$done.' '.Str::plural('account', $done).'.'
            .($unchanged ? " {$unchanged} already in that state." : '')
            .($skipped ? " {$skipped} skipped (your own account or an owner)." : '')));
    }

    /** Deleting in bulk is its own route so it sits behind password confirmation, as a single delete does. */
    public function bulkDelete(Request $request): RedirectResponse
    {
        $data = $request->validate(['ids' => ['required', 'array', 'min:1', 'max:500'], 'ids.*' => ['integer']]);
        [$targets, $skipped] = $this->bulkTargets($request, $data['ids']);
        $targets->each->delete();
        $n = $targets->count();

        return back()->with('success', "Deleted {$n} ".Str::plural('account', $n).'.'.($skipped ? " {$skipped} skipped (your own account or an owner)." : ''));
    }

    /**
     * The ticked accounts minus your own and every owner.
     *
     * @param  list<int>  $ids
     * @return array{0: Collection<int, User>, 1: int}
     */
    private function bulkTargets(Request $request, array $ids): array
    {
        $users = User::whereIn('id', array_unique($ids))->get();
        $targets = $users->reject(fn (User $u) => $u->is($request->user()) || $u->isOwner())->values();

        return [$targets, $users->count() - $targets->count()];
    }

    /** The role × capability matrix, editable by owners. */
    public function permissions(RolePermissions $permissions): View
    {
        $roles = AdminRole::cases();

        return view('backend.admin.permissions', [
            'roles' => $roles,
            'capabilities' => AdminCapability::cases(),
            'current' => collect($roles)->mapWithKeys(fn (AdminRole $r) => [$r->value => $permissions->for($r)])->all(),
            'edited' => collect($roles)->mapWithKeys(fn (AdminRole $r) => [$r->value => $permissions->record($r)])->all(),
            'holders' => User::whereNotNull('admin_role')->whereNull('suspended_at')->selectRaw('admin_role, count(*) as n')->groupBy('admin_role')->pluck('n', 'admin_role')->all(),
        ]);
    }

    /**
     * Save the matrix: every role column in the form at once. A column with nothing
     * ticked is a role with no capabilities, which is allowed (it still marks the holder
     * as staff for the second-factor rule) but is reported so it is not done by accident.
     */
    public function updatePermissions(Request $request, RolePermissions $permissions): RedirectResponse
    {
        $grantable = array_map(fn (AdminCapability $c) => $c->value, array_filter(AdminCapability::cases(), fn (AdminCapability $c) => ! $c->ownerOnly()));
        $data = $request->validate([
            'roles' => ['required', 'array', 'min:1'],
            'roles.*' => ['string', Rule::in(AdminRole::values())],
            'capabilities' => ['nullable', 'array'],
            'capabilities.*' => ['array'],
            'capabilities.*.*' => ['string', Rule::in($grantable)],
        ]);

        $label = fn (array $values) => implode(', ', array_map(fn ($v) => AdminCapability::from($v)->label(), $values));
        $lines = [];
        foreach (array_unique($data['roles']) as $value) {
            $role = AdminRole::from($value);
            $before = collect($permissions->for($role))->map->value->all();
            $after = collect($permissions->save($role, $data['capabilities'][$value] ?? [], $request->user()))->map->value->all();
            $added = array_diff($after, $before);
            $removed = array_diff($before, $after);
            if ($added || $removed) {
                $lines[] = $role->label().': '.implode('; ', array_filter([
                    $added ? 'added '.$label($added) : null,
                    $removed ? 'removed '.$label($removed) : null,
                ])).($after === [] ? ' (it now holds nothing)' : '').'.';
            }
        }

        return back()->with('success', $lines === [] ? 'No change.' : implode(' ', $lines).' Holders get the new permissions on their next page load.');
    }

    public function resetPermissions(Request $request, RolePermissions $permissions): RedirectResponse
    {
        $role = AdminRole::from($request->validate(['role' => ['required', Rule::in(AdminRole::values())]])['role']);
        $permissions->reset($role);

        return back()->with('success', $role->label().' is back to its default permissions.');
    }

    /** Grant one of the storable roles, or clear it. Ownership is not among the options. */
    public function updateRole(Request $request, User $user): RedirectResponse
    {
        $this->guardFor($user, 'change the role of');

        $data = $request->validate([
            'admin_role' => ['nullable', 'string', 'in:'.implode(',', AdminRole::values())],
        ]);

        $role = $data['admin_role'] ?? null;
        $this->applyRole($user, $role === null ? null : AdminRole::from($role), $request->user());

        return back()->with('success', $role === null
            ? "Removed admin access from {$user->email}."
            : "{$user->email} is now ".AdminRole::from($role)->label().'. They will be asked to enrol an authenticator at next sign-in.');
    }

    public function suspend(Request $request, User $user): RedirectResponse
    {
        $this->guardFor($user, 'suspend');

        $data = $request->validate(['reason' => ['nullable', 'string', 'max:255']]);

        $user->forceFill([
            'suspended_at' => now(),
            'suspended_reason' => $data['reason'] ?? null,
        ])->save();
        // Suspension is otherwise only checked at the next password sign-in, so an open
        // session or a remember-me cookie would keep working on every non-admin route.
        $user->endAllSessions();

        return back()->with('success', "{$user->email} is suspended and can no longer sign in.");
    }

    public function restore(User $user): RedirectResponse
    {
        $this->guardFor($user, 'restore');

        $user->forceFill(['suspended_at' => null, 'suspended_reason' => null])->save();

        return back()->with('success', "{$user->email} can sign in again.");
    }

    /**
     * Clear the authenticator so the account enrols again at next sign-in. This is the
     * locked-out path; the same thing the admin:two-factor-reset command does from a shell.
     */
    public function resetTwoFactor(User $user): RedirectResponse
    {
        $this->guardFor($user, 'reset the second factor of');

        $user->forceFill([
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
            'two_factor_last_step' => null,
        ])->save();
        // Any live session could otherwise enrol an authenticator of its own choosing.
        $user->endAllSessions();

        return back()->with('success', "{$user->email} will enrol a new authenticator at next sign-in.");
    }

    public function destroy(User $user): RedirectResponse
    {
        $this->guardFor($user, 'delete');

        $email = $user->email;
        $user->delete();

        // Not back(): the only Delete button is on this account's own page, which no longer exists.
        return redirect()->route('backend.admin.users.index')->with('success', "Deleted {$email}.");
    }

    /** Set or clear a role, recording who granted it. Returns whether anything changed. */
    private function applyRole(User $user, ?AdminRole $role, User $by): bool
    {
        if ($user->adminRole() === $role) {
            return false;
        }
        $user->forceFill([
            'admin_role' => $role,
            'admin_role_granted_by' => $role === null ? null : $by->getKey(),
            'admin_role_granted_at' => $role === null ? null : now(),
        ])->save();

        return true;
    }

    private function sendInvitation(User $user, User $inviter, ?AdminRole $role, ?string $note): void
    {
        $broker = Password::broker(InvitationController::BROKER);
        $token = $broker->createToken($user);
        Mail::to($user->email)->send(new AdminInvitationMail($user, $inviter, route('invitation.show', ['token' => $token, 'email' => $user->email]), $role, $note));
    }

    /** @return array{q:string, role:?string, status:?string, sort:string} */
    private function filters(Request $request): array
    {
        $sort = (string) $request->query('sort', 'joined');

        return [
            'q' => trim((string) $request->query('q')),
            'role' => $request->query('role'),
            'status' => $request->query('status'),
            'sort' => array_key_exists($sort, self::SORTS) ? $sort : 'joined',
        ];
    }

    private function filteredQuery(array $filters): Builder
    {
        $query = User::query();

        if ($filters['q'] !== '') {
            // LOWER on both sides: PostgreSQL's LIKE is case-sensitive, and "bhaskar"
            // has to find Bhaskar on the host as it does in development.
            $term = '%'.mb_strtolower($filters['q']).'%';
            $query->where(fn ($q) => $q->whereRaw('LOWER(email) LIKE ?', [$term])->orWhereRaw('LOWER(name) LIKE ?', [$term])->orWhereRaw('LOWER(organization_name) LIKE ?', [$term]));
        }

        match ($filters['role']) {
            // Owners are matched by address because that is where ownership is defined.
            'owner' => $query->owners(),
            'none' => $query->whereNull('admin_role')->whereNotIn('id', User::owners()->select('id')),
            // Grouped, because the owner clause is an OR: ungrouped it escapes the search
            // above and "bob" with this filter returns every owner regardless of name.
            'any' => $query->where(fn ($q) => $q->whereNotNull('admin_role')->orWhereIn('id', User::owners()->select('id'))),
            default => in_array($filters['role'], AdminRole::values(), true) ? $query->where('admin_role', $filters['role']) : $query,
        };

        match ($filters['status']) {
            'active' => $query->whereNull('suspended_at')->whereNotNull('email_verified_at'),
            'suspended' => $query->whereNotNull('suspended_at'),
            'unverified' => $query->whereNull('email_verified_at')->whereNull('invited_at'),
            'invited' => $query->whereNotNull('invited_at')->whereNull('email_verified_at'),
            // Admins who could not pass the second-factor gate today: the access-review list.
            'no_factor' => $query->whereNotNull('admin_role')->whereNull('two_factor_confirmed_at')->whereNull('suspended_at'),
            'dormant' => $query->whereNotNull('admin_role')->where(fn ($q) => $q->whereNull('last_login_at')->orWhere('last_login_at', '<', now()->subDays(90))),
            default => $query,
        };

        return match ($filters['sort']) {
            // Never-signed-in accounts last, on both databases.
            'last_login' => $query->orderByRaw('CASE WHEN last_login_at IS NULL THEN 1 ELSE 0 END')->orderByDesc('last_login_at'),
            'name' => $query->orderBy('name'),
            'email' => $query->orderBy('email'),
            default => $query->orderByDesc('created_at')->orderByDesc('id'),
        };
    }

    private function statusLabel(User $u): string
    {
        return match (true) {
            $u->isSuspended() => 'suspended',
            $u->invitationPending() => 'invited',
            ! $u->email_verified_at => 'unverified',
            default => 'active',
        };
    }

    /**
     * The two rules every write on this page obeys.
     *
     * Acting on yourself is refused because the useful version of it is a mistake: suspending
     * or demoting your own account mid-session is how someone locks themselves out, and the
     * legitimate cases (leaving, rotating your own factor) are better done by another owner or
     * from a shell.
     *
     * Acting on an owner is refused because ownership is not stored here. Allowing it would let
     * a web form contradict the environment, which is exactly the recovery path this design
     * keeps intact.
     */
    private function guardFor(User $target, string $action): void
    {
        abort_if($target->is(request()->user()), 403, "You cannot {$action} your own account.");
        abort_if($target->isOwner(), 403, "Owners are defined by the ADMIN_EMAILS environment list, so you cannot {$action} one here. Change that list and restart the container instead.");
    }
}
