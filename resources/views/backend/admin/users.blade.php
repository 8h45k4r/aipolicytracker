@extends('backend.layouts.app', ['title' => 'Users and roles'])
@section('content')
<div class="flex flex-wrap items-end justify-between gap-3">
    <div>
        <h1 class="font-display text-2xl font-semibold text-brand-navy">Users and roles</h1>
        <p class="mt-1 meta">Every account, and what it is allowed to do. Roles take effect immediately; a newly granted role is asked to enrol an authenticator at next sign-in.</p>
    </div>
</div>

<dl class="mt-4 grid grid-cols-2 gap-3 sm:grid-cols-4">
    @foreach(['total' => 'Accounts', 'owners' => 'Owners', 'granted' => 'Granted a role', 'suspended' => 'Suspended'] as $key => $label)
    <div class="card-flat bg-white py-3 text-center">
        <dt class="text-xs text-brand-muted">{{ $label }}</dt>
        <dd class="text-2xl font-semibold text-brand-navy">{{ $counts[$key] }}</dd>
    </div>
    @endforeach
</dl>

<div class="mt-4 rounded-sm border border-brand-line bg-white p-4 text-sm">
    <p class="font-semibold text-brand-navy">Ownership is not managed here</p>
    <p class="mt-1 text-brand-body">
        Owners come from the <code class="font-mono text-xs">ADMIN_EMAILS</code> environment list
        ({{ $ownerAddresses }} {{ \Illuminate\Support\Str::plural('address', $ownerAddresses) }} configured), so this page cannot grant, remove or suspend one.
        That is deliberate: it means a mistake made here, or a stolen session, can always be undone from the host.
        To change who owns the platform, edit that list and restart the container.
    </p>
</div>

<form method="get" action="{{ route('backend.admin.users.index') }}" class="mt-5 flex flex-wrap items-end gap-2">
    <label class="text-sm">
        <span class="block text-brand-muted">Search</span>
        <input type="search" name="q" value="{{ $filters['q'] }}" placeholder="name, email or organisation" class="mt-1 w-64 rounded-sm border-brand-line text-sm">
    </label>
    <label class="text-sm">
        <span class="block text-brand-muted">Role</span>
        <select name="role" class="mt-1 rounded-sm border-brand-line text-sm">
            <option value="">Any</option>
            <option value="any" @selected($filters['role'] === 'any')>Any admin access</option>
            <option value="owner" @selected($filters['role'] === 'owner')>Owner</option>
            @foreach($roles as $role)<option value="{{ $role->value }}" @selected($filters['role'] === $role->value)>{{ $role->label() }}</option>@endforeach
            <option value="none" @selected($filters['role'] === 'none')>No access</option>
        </select>
    </label>
    <label class="text-sm">
        <span class="block text-brand-muted">Status</span>
        <select name="status" class="mt-1 rounded-sm border-brand-line text-sm">
            <option value="">Any</option>
            <option value="suspended" @selected($filters['status'] === 'suspended')>Suspended</option>
            <option value="unverified" @selected($filters['status'] === 'unverified')>Email unverified</option>
        </select>
    </label>
    <button class="btn-primary">Filter</button>
    @if($filters['q'] !== '' || $filters['role'] || $filters['status'])<a href="{{ route('backend.admin.users.index') }}" class="btn-secondary">Clear</a>@endif
</form>

@if($users->isEmpty())
    <div class="mt-6"><x-site.empty title="No accounts match this view">Change the filters, or clear them to see every account.</x-site.empty></div>
@else
<div class="table-wrap mt-6 bg-white">
    <table>
        <caption class="sr-only">Accounts, their roles and the actions available on each</caption>
        <thead><tr><th scope="col">Account</th><th scope="col">Role</th><th scope="col">Second factor</th><th scope="col">Status</th><th scope="col">Joined</th><th scope="col">Actions</th></tr></thead>
        <tbody>
        @foreach($users as $u)
            @php($self = $u->is(auth()->user()))
            @php($locked = $self || $u->isOwner())
            <tr>
                <td>
                    <span class="block font-medium text-brand-navy">{{ $u->name ?: '—' }}</span>
                    <span class="block font-mono text-xs text-brand-muted">{{ $u->email }}</span>
                    @if($u->organization_name)<span class="block text-xs text-brand-muted">{{ $u->organization_name }}</span>@endif
                </td>
                <td>
                    @if($u->isOwner())
                        <span class="badge bg-brand-navy text-white ring-brand-navy">Owner</span>
                        <span class="block mt-1 text-xs text-brand-muted">From the environment</span>
                    @elseif($locked)
                        <span class="badge bg-brand-paper text-brand-body ring-brand-line">{{ $u->adminRoleLabel() }}</span>
                        <span class="block mt-1 text-xs text-brand-muted">This is you</span>
                    @else
                        <form method="post" action="{{ route('backend.admin.users.role', $u) }}" class="flex items-center gap-1">
                            @csrf
                            <select name="admin_role" class="rounded-sm border-brand-line text-xs" aria-label="Role for {{ $u->email }}">
                                <option value="" @selected($u->adminRole() === null)>No access</option>
                                @foreach($roles as $role)<option value="{{ $role->value }}" @selected($u->adminRole() === $role)>{{ $role->label() }}</option>@endforeach
                            </select>
                            <button class="btn-secondary !min-h-0 !py-1 !px-2 text-xs">Save</button>
                        </form>
                        @if($u->admin_role_granted_at)
                            <span class="block mt-1 text-xs text-brand-muted">by {{ $u->adminRoleGrantedBy?->email ?? 'a removed account' }}, {{ $u->admin_role_granted_at->format('j M Y') }}</span>
                        @endif
                    @endif
                </td>
                <td class="text-xs">
                    @if(! $u->isAdmin())
                        <span class="text-brand-muted">Not required</span>
                    @elseif($u->hasTwoFactorEnabled())
                        <span class="text-state-good">Enrolled</span>
                    @else
                        <span class="text-state-warn">Not enrolled</span>
                    @endif
                </td>
                <td class="text-xs">
                    @if($u->isSuspended())
                        <span class="text-state-bad">Suspended {{ $u->suspended_at->format('j M Y') }}</span>
                        @if($u->suspended_reason)<span class="block text-brand-muted">{{ $u->suspended_reason }}</span>@endif
                    @elseif(! $u->email_verified_at)
                        <span class="text-state-warn">Email unverified</span>
                    @else
                        <span class="text-state-good">Active</span>
                    @endif
                </td>
                <td class="whitespace-nowrap text-xs">{{ $u->created_at?->format('j M Y') ?? '—' }}</td>
                <td class="whitespace-nowrap">
                    @if($locked)
                        <span class="text-xs text-brand-muted">{{ $u->isOwner() ? 'Managed in the environment' : 'No actions on your own account' }}</span>
                    @else
                        @if($u->isSuspended())
                            <form method="post" action="{{ route('backend.admin.users.restore', $u) }}" class="inline">@csrf<button class="btn-secondary !min-h-0 !py-1 !px-2 text-xs">Restore</button></form>
                        @else
                            {{-- The reason is optional but collected here rather than nowhere, so
                                 suspended_reason has a source and "why is this account stopped"
                                 has an answer six months later. --}}
                            <form method="post" action="{{ route('backend.admin.users.suspend', $u) }}" class="inline-flex items-center gap-1" onsubmit="return confirm('Suspend {{ $u->email }}? They will not be able to sign in.')">
                                @csrf
                                <input type="text" name="reason" maxlength="255" placeholder="reason (optional)" class="w-36 rounded-sm border-brand-line text-xs" aria-label="Reason for suspending {{ $u->email }}">
                                <button class="btn-secondary !min-h-0 !py-1 !px-2 text-xs">Suspend</button>
                            </form>
                        @endif
                        @if($u->hasTwoFactorEnabled())
                            <form method="post" action="{{ route('backend.admin.users.two-factor.reset', $u) }}" class="inline" onsubmit="return confirm('Clear the authenticator for {{ $u->email }}? They will enrol again at next sign-in.')">@csrf<button class="btn-secondary !min-h-0 !py-1 !px-2 text-xs">Reset factor</button></form>
                        @endif
                        <form method="post" action="{{ route('backend.admin.users.destroy', $u) }}" class="inline" onsubmit="return confirm('Delete {{ $u->email }} permanently? This cannot be undone.')">@csrf @method('DELETE')<button class="btn-secondary !min-h-0 !py-1 !px-2 text-xs text-state-bad">Delete</button></form>
                    @endif
                </td>
            </tr>
        @endforeach
        </tbody>
    </table>
</div>
<nav class="mt-4" aria-label="Pagination">{{ $users->links() }}</nav>
@endif

<section class="mt-10" aria-labelledby="roles-heading">
    <h2 id="roles-heading" class="font-display text-lg font-semibold text-brand-navy">What each role can do</h2>
    <p class="mt-1 meta">Capabilities are fixed in code, not editable here, so a role always means the same thing.</p>
    <div class="mt-3 grid gap-3 md:grid-cols-3">
        @foreach($roles as $role)
        <div class="card-flat bg-white p-4">
            <p class="font-semibold text-brand-navy">{{ $role->label() }}</p>
            <p class="mt-1 text-sm text-brand-body">{{ $role->description() }}</p>
            <ul class="mt-2 space-y-1 text-xs text-brand-muted list-disc pl-4">
                @foreach($role->capabilities() as $capability)<li>{{ $capability->label() }}</li>@endforeach
            </ul>
        </div>
        @endforeach
    </div>
</section>
@endsection
