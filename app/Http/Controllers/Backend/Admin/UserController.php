<?php

namespace App\Http\Controllers\Backend\Admin;

use App\Enums\AdminRole;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

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
    public function index(Request $request): View
    {
        $owners = config('aipolicytracker.admin_emails', []);
        $filters = [
            'q' => trim((string) $request->query('q')),
            'role' => $request->query('role'),
            'status' => $request->query('status'),
        ];

        $query = User::query()->with('adminRoleGrantedBy')->orderByDesc('created_at');

        if ($filters['q'] !== '') {
            $term = '%'.$filters['q'].'%';
            $query->where(fn ($q) => $q->where('email', 'like', $term)->orWhere('name', 'like', $term)->orWhere('organization_name', 'like', $term));
        }

        match ($filters['role']) {
            // Owners are matched by address because that is where ownership is defined.
            'owner' => $owners === [] ? $query->whereRaw('1 = 0') : $query->whereIn('email', $owners),
            'none' => $query->whereNull('admin_role')->when($owners !== [], fn ($q) => $q->whereNotIn('email', $owners)),
            // Grouped, because the owner clause is an OR: ungrouped it escapes the search
            // above and "bob" with this filter returns every owner regardless of name.
            'any' => $query->where(fn ($q) => $q->whereNotNull('admin_role')->when($owners !== [], fn ($inner) => $inner->orWhereIn('email', $owners))),
            default => in_array($filters['role'], AdminRole::values(), true) ? $query->where('admin_role', $filters['role']) : $query,
        };

        match ($filters['status']) {
            'suspended' => $query->whereNotNull('suspended_at'),
            'unverified' => $query->whereNull('email_verified_at'),
            default => $query,
        };

        return view('backend.admin.users', [
            'users' => $query->paginate(50)->withQueryString(),
            'filters' => $filters,
            'roles' => AdminRole::cases(),
            'counts' => [
                'total' => User::count(),
                'owners' => $owners === [] ? 0 : User::whereIn('email', $owners)->count(),
                'granted' => User::whereNotNull('admin_role')->count(),
                'suspended' => User::whereNotNull('suspended_at')->count(),
            ],
            'ownerAddresses' => count($owners),
        ]);
    }

    /** Grant one of the storable roles, or clear it. Ownership is not among the options. */
    public function updateRole(Request $request, User $user): RedirectResponse
    {
        $this->guardFor($user, 'change the role of');

        $data = $request->validate([
            'admin_role' => ['nullable', 'string', 'in:'.implode(',', AdminRole::values())],
        ]);

        $role = $data['admin_role'] ?? null;
        $user->forceFill([
            'admin_role' => $role,
            'admin_role_granted_by' => $role === null ? null : $request->user()->getKey(),
            'admin_role_granted_at' => $role === null ? null : now(),
        ])->save();

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
        ])->save();

        return back()->with('success', "{$user->email} will enrol a new authenticator at next sign-in.");
    }

    public function destroy(User $user): RedirectResponse
    {
        $this->guardFor($user, 'delete');

        $email = $user->email;
        $user->delete();

        return back()->with('success', "Deleted {$email}.");
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
