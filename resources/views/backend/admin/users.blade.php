@extends('backend.layouts.app', ['title' => 'Users and roles'])
@section('content')
@php($status = fn ($u) => match (true) { $u->isSuspended() => 'suspended', $u->invitationPending() => 'invited', ! $u->email_verified_at => 'unverified', default => 'active' })
<div class="flex flex-wrap items-end justify-between gap-3">
    <div>
        <h1 class="font-display text-2xl font-semibold text-brand-navy">Users and roles</h1>
        <p class="mt-1 meta">Every account, and what it is allowed to do. Roles take effect immediately; a newly granted role is asked to enrol an authenticator at next sign-in.</p>
    </div>
    <div class="flex flex-wrap gap-2">
        <a href="#invite" class="btn-primary" data-open-details="invite">Invite a user</a>
        <a href="{{ route('backend.admin.users.permissions') }}" class="btn-secondary">Role permissions</a>
        <a href="{{ route('backend.admin.users.export', request()->query()) }}" class="btn-secondary">Export CSV</a>
    </div>
</div>

{{-- Each figure is also the filter that lists those accounts. --}}
<dl class="mt-4 grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-6">
    @foreach([
        'total' => ['Accounts', []],
        'owners' => ['Owners', ['role' => 'owner']],
        'granted' => ['Granted a role', ['role' => 'any']],
        'no_factor' => ['Admins without a second factor', ['status' => 'no_factor']],
        'invited' => ['Invitations pending', ['status' => 'invited']],
        'suspended' => ['Suspended', ['status' => 'suspended']],
    ] as $key => [$label, $query])
    <a href="{{ route('backend.admin.users.index', $query) }}" class="card-flat block bg-white py-3 text-center no-underline hover:border-brand-blue {{ $key === 'no_factor' && $counts[$key] ? 'border-state-warn' : '' }}">
        <dt class="text-xs text-brand-muted">{{ $label }}</dt>
        <dd class="text-2xl font-semibold {{ $key === 'no_factor' && $counts[$key] ? 'text-state-warn' : 'text-brand-navy' }}">{{ $counts[$key] }}</dd>
    </a>
    @endforeach
</dl>

<details id="invite" class="mt-4 rounded-sm border border-brand-line bg-white p-4" @if($errors->hasAny(['name', 'email', 'admin_role', 'note'])) open @endif>
    <summary class="cursor-pointer font-semibold text-brand-navy">Invite a user</summary>
    <p class="mt-2 text-sm text-brand-body">Creates the account and emails a link to choose a password. The link works for {{ $inviteDays }} days; choosing the password also confirms the address. Owners are not invited: they register with an address in <code class="font-mono text-xs">ADMIN_EMAILS</code>.</p>
    <form method="post" action="{{ route('backend.admin.users.invite') }}" class="mt-3 grid gap-3 md:grid-cols-4">@csrf
        <div><label for="invite-name" class="label">Name</label><input id="invite-name" name="name" value="{{ old('name') }}" required maxlength="120" class="input" autocomplete="off"></div>
        <div><label for="invite-email" class="label">Email</label><input id="invite-email" type="email" name="email" value="{{ old('email') }}" required class="input" autocomplete="off"></div>
        <div>
            <label for="invite-role" class="label">Role</label>
            <select id="invite-role" name="admin_role" class="input">
                <option value="">No admin access</option>
                @foreach($roles as $role)<option value="{{ $role->value }}" @selected(old('admin_role') === $role->value)>{{ $role->label() }}</option>@endforeach
            </select>
        </div>
        <div><label for="invite-note" class="label">Personal note (optional)</label><input id="invite-note" name="note" value="{{ old('note') }}" maxlength="500" class="input" placeholder="Shown in the email"></div>
        <div class="md:col-span-4"><button class="btn-primary">Send invitation</button></div>
    </form>
    @if($errors->hasAny(['name', 'email', 'admin_role', 'note']))<ul class="mt-2 list-disc pl-5 text-sm text-state-bad" role="alert">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>@endif
</details>

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
            <option value="active" @selected($filters['status'] === 'active')>Active</option>
            <option value="invited" @selected($filters['status'] === 'invited')>Invitation pending</option>
            <option value="unverified" @selected($filters['status'] === 'unverified')>Email unverified</option>
            <option value="suspended" @selected($filters['status'] === 'suspended')>Suspended</option>
            <option value="no_factor" @selected($filters['status'] === 'no_factor')>Admin without a second factor</option>
            <option value="dormant" @selected($filters['status'] === 'dormant')>Admin not signed in for 90 days</option>
        </select>
    </label>
    <label class="text-sm">
        <span class="block text-brand-muted">Sort</span>
        <select name="sort" class="mt-1 rounded-sm border-brand-line text-sm">
            @foreach($sorts as $value => $label)<option value="{{ $value }}" @selected($filters['sort'] === $value)>{{ $label }}</option>@endforeach
        </select>
    </label>
    <button class="btn-primary">Filter</button>
    @if($filters['q'] !== '' || $filters['role'] || $filters['status'] || $filters['sort'] !== 'joined')<a href="{{ route('backend.admin.users.index') }}" class="btn-secondary">Clear</a>@endif
</form>

@if($users->isEmpty())
    <div class="mt-6"><x-site.empty title="No accounts match this view">Change the filters, or clear them to see every account.</x-site.empty></div>
@else
{{-- One action for every ticked account. Your own account and owners are skipped
     server-side whatever is ticked, so they have no checkbox. --}}
<form method="post" action="{{ route('backend.admin.users.bulk') }}" id="bulk-users" class="mt-6 flex flex-wrap items-end gap-2 rounded-sm border border-brand-line bg-white p-3 text-sm">@csrf
    <p class="w-full flex flex-wrap items-center gap-2"><span class="font-medium text-brand-navy">Act on the selected accounts</span><span class="badge-neutral" data-bulk-count="bulk-users">0 selected</span></p>
    <div>
        <label for="bulk-action" class="label !mb-0.5 !text-xs">Action</label>
        <select id="bulk-action" name="action" class="input !min-h-0 !py-1.5 !w-auto">
            <option value="role">Set role</option>
            <option value="suspend">Suspend</option>
            <option value="restore">Restore</option>
            <option value="verify">Re-send verification email</option>
        </select>
    </div>
    <div>
        <label for="bulk-role" class="label !mb-0.5 !text-xs">Role (for "Set role")</label>
        <select id="bulk-role" name="admin_role" class="input !min-h-0 !py-1.5 !w-auto">
            <option value="">Choose…</option>
            @foreach($roles as $role)<option value="{{ $role->value }}">{{ $role->label() }}</option>@endforeach
            <option value="none">No admin access</option>
        </select>
    </div>
    <div class="min-w-[12rem] flex-1"><label for="bulk-reason" class="label !mb-0.5 !text-xs">Reason (for "Suspend", optional)</label><input id="bulk-reason" name="reason" maxlength="255" class="input !min-h-0 !py-1.5"></div>
    <button type="submit" class="btn-primary !min-h-0 !py-1.5" data-bulk-needs="bulk-users" data-confirm="Apply this action to {n} accounts?">Apply to selected</button>
    <button type="submit" formaction="{{ route('backend.admin.users.bulk.delete') }}" class="btn-secondary !min-h-0 !py-1.5 text-state-bad" data-bulk-needs="bulk-users" data-confirm="Delete {n} accounts permanently? This cannot be undone.">Delete selected</button>
</form>

<div class="table-wrap mt-3 bg-white">
    <table>
        <caption class="sr-only">Accounts, their roles and status</caption>
        <thead><tr>
            <th scope="col" class="w-8"><input type="checkbox" data-bulk-all="bulk-users" aria-label="Select every account on this page"></th>
            <th scope="col">Account</th><th scope="col">Role</th><th scope="col">Second factor</th><th scope="col">Status</th><th scope="col">Last signed in</th><th scope="col">Joined</th><th scope="col"><span class="sr-only">Manage</span></th>
        </tr></thead>
        <tbody>
        @foreach($users as $u)
            @php($self = $u->is(auth()->user()))
            @php($locked = $self || $u->isOwner())
            <tr>
                <td>@unless($locked)<input type="checkbox" name="ids[]" value="{{ $u->id }}" form="bulk-users" data-bulk-item aria-label="Select {{ $u->email }}">@endunless</td>
                <td>
                    <a href="{{ route('backend.admin.users.show', $u) }}" class="block font-medium text-brand-navy">{{ $u->name ?: '—' }}</a>
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
                    @switch($status($u))
                        @case('suspended')
                            <x-backend.badge status="suspended" /> <span class="text-brand-muted">{{ $u->suspended_at->format('j M Y') }}</span>
                            @if($u->suspended_reason)<span class="block text-brand-muted">{{ $u->suspended_reason }}</span>@endif
                            @break
                        @case('invited')
                            <x-backend.badge status="unconfirmed">invited</x-backend.badge> <span class="block text-brand-muted">{{ $u->invited_at->format('j M Y') }}@if($u->invitedBy) by {{ $u->invitedBy->email }}@endif</span>
                            @break
                        @case('unverified')
                            <x-backend.badge status="unconfirmed">email unverified</x-backend.badge>
                            @break
                        @default
                            <x-backend.badge status="active" />
                    @endswitch
                </td>
                <td class="whitespace-nowrap text-xs">
                    @if($u->last_login_at)<time datetime="{{ $u->last_login_at->toIso8601String() }}" title="{{ $u->last_login_at->format('j M Y H:i') }}">{{ $u->last_login_at->diffForHumans() }}</time>
                    @else<span class="text-brand-muted">Never</span>@endif
                </td>
                <td class="whitespace-nowrap text-xs">{{ $u->created_at?->format('j M Y') ?? '—' }}</td>
                <td class="whitespace-nowrap"><a href="{{ route('backend.admin.users.show', $u) }}" class="btn-secondary !min-h-0 !py-1 !px-2 text-xs" aria-label="Manage {{ $u->email }}">Manage</a></td>
            </tr>
        @endforeach
        </tbody>
    </table>
</div>
<nav class="mt-4" aria-label="Pagination">{{ $users->links() }}</nav>
@endif

<section class="mt-10" aria-labelledby="roles-heading">
    <div class="flex flex-wrap items-baseline justify-between gap-2">
        <h2 id="roles-heading" class="font-display text-lg font-semibold text-brand-navy">What each role can do</h2>
        <a href="{{ route('backend.admin.users.permissions') }}" class="text-sm">Edit role permissions →</a>
    </div>
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
