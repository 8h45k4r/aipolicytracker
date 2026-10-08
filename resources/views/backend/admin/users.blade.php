@extends('backend.layouts.app', ['title' => 'Users and roles'])
@section('content')
@php($status = fn ($u) => match (true) { $u->isSuspended() => 'suspended', $u->invitationPending() => 'invited', ! $u->email_verified_at => 'unverified', default => 'active' })
<x-backend.page-header title="Users and roles" description="Every account, and what it is allowed to do. Roles take effect immediately; a newly granted role is asked to enrol an authenticator at next sign-in.">
    <x-slot:actions>
        {{-- Opens the side panel; without JavaScript it is a link to the same form on a page of its own. --}}
        <a href="{{ route('backend.admin.users.invite.create') }}" class="btn-primary" data-drawer-open="invite-drawer" data-command="Invite a user">Invite a user</a>
        <a href="{{ route('backend.admin.users.permissions') }}" class="btn-secondary" data-command="Edit role permissions">Role permissions</a>
    </x-slot:actions>
</x-backend.page-header>

{{-- Each figure is also the filter that lists those accounts. --}}
<div class="mt-5 grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-6" data-user-stats>
    @foreach([
        'total' => ['Accounts', []],
        'owners' => ['Owners', ['role' => 'owner']],
        'granted' => ['Granted a role', ['role' => 'any']],
        'no_factor' => ['Admins without a second factor', ['status' => 'no_factor']],
        'invited' => ['Invitations pending', ['status' => 'invited']],
        'suspended' => ['Suspended', ['status' => 'suspended']],
    ] as $key => [$label, $query])
        @php($warn = $key === 'no_factor' && $counts[$key])
        <x-backend.stat :label="$label" :value="$counts[$key]" :href="route('backend.admin.users.index', $query)" :tone="$warn ? 'text-state-warn' : null" class="bg-white {{ $warn ? 'border-state-warn' : '' }}" />
    @endforeach
</div>

<x-backend.filters :action="route('backend.admin.users.index')" :filters="(object) $filters" :dates="false" :keep-sort="false"
    :export="route('backend.admin.users.export')" :total="$users->total()" noun="account" placeholder="Name, email or organisation">
    <div>
        <label for="f-role" class="adm-label">Role</label>
        <select id="f-role" name="role" class="input !min-h-[38px] !py-1.5 !w-auto">
            <option value="">Any</option>
            <option value="any" @selected($filters['role'] === 'any')>Any admin access</option>
            <option value="owner" @selected($filters['role'] === 'owner')>Owner</option>
            @foreach($roles as $role)<option value="{{ $role->value }}" @selected($filters['role'] === $role->value)>{{ $role->label() }}</option>@endforeach
            <option value="none" @selected($filters['role'] === 'none')>No access</option>
        </select>
    </div>
    <div>
        <label for="f-status" class="adm-label">Status</label>
        <select id="f-status" name="status" class="input !min-h-[38px] !py-1.5 !w-auto">
            <option value="">Any</option>
            <option value="active" @selected($filters['status'] === 'active')>Active</option>
            <option value="invited" @selected($filters['status'] === 'invited')>Invitation pending</option>
            <option value="unverified" @selected($filters['status'] === 'unverified')>Email unverified</option>
            <option value="suspended" @selected($filters['status'] === 'suspended')>Suspended</option>
            <option value="no_factor" @selected($filters['status'] === 'no_factor')>Admin without a second factor</option>
            <option value="dormant" @selected($filters['status'] === 'dormant')>Admin not signed in for 90 days</option>
        </select>
    </div>
    <div>
        <label for="f-sort" class="adm-label">Sort</label>
        <select id="f-sort" name="sort" class="input !min-h-[38px] !py-1.5 !w-auto">
            @foreach($sorts as $value => $label)<option value="{{ $value }}" @selected($filters['sort'] === $value)>{{ $label }}</option>@endforeach
        </select>
    </div>
</x-backend.filters>

@if($users->isEmpty())
    <div class="mt-6"><x-site.empty title="No accounts match this view">Change the filters, or clear them to see every account.</x-site.empty></div>
@else
{{-- Every action on the ticked accounts has its own button and its own inputs, so nothing
     depends on a select changing the rest of the form. Only "Set role" needs the role, so
     the other buttons skip the browser's check (formnovalidate); the server still answers
     a missing role with a message, not an error page. Your own account and owners are
     skipped server-side whatever is ticked, so they have no checkbox. --}}
<form method="post" action="{{ route('backend.admin.users.bulk') }}" id="bulk-users" class="mt-6 rounded-md border border-brand-line bg-white p-3 text-sm" aria-labelledby="bulk-users-heading">@csrf
    <div class="flex flex-wrap items-center gap-2">
        <h2 id="bulk-users-heading" class="text-sm font-semibold text-brand-navy">Act on the selected accounts</h2>
        <span class="badge-neutral" data-bulk-count="bulk-users">0 selected</span>
        <label class="ml-auto inline-flex items-center gap-1.5 text-xs text-brand-muted sm:hidden"><input type="checkbox" data-bulk-all="bulk-users"> Select all on this page</label>
    </div>
    <div class="mt-3 grid gap-3 lg:grid-cols-[auto_auto_1fr]">
        <fieldset class="flex flex-wrap items-end gap-2">
            <legend class="sr-only">Set role</legend>
            <div>
                <label for="bulk-role" class="adm-label">Role</label>
                <select id="bulk-role" name="admin_role" class="input !min-h-[38px] !py-1.5 !w-auto" required>
                    <option value="">Choose a role…</option>
                    @foreach($roles as $role)<option value="{{ $role->value }}">{{ $role->label() }}</option>@endforeach
                    <option value="none">No admin access</option>
                </select>
            </div>
            <button type="submit" name="action" value="role" class="btn-primary !min-h-[38px] !py-1.5" data-bulk-needs="bulk-users" data-confirm="Set the chosen role on {n} accounts? Any role they hold now is replaced." data-confirm-danger data-confirm-label="Set role">Set role</button>
        </fieldset>
        <fieldset class="flex flex-wrap items-end gap-2">
            <legend class="sr-only">Suspend</legend>
            <div>
                <label for="bulk-reason" class="adm-label">Reason <span class="normal-case tracking-normal">(optional)</span></label>
                <input id="bulk-reason" name="reason" maxlength="255" class="input !min-h-[38px] !py-1.5 !w-48" placeholder="Kept with the account">
            </div>
            <button type="submit" name="action" value="suspend" formnovalidate class="btn-secondary !min-h-[38px] !py-1.5" data-bulk-needs="bulk-users" data-confirm="Suspend {n} accounts? They are signed out everywhere and cannot sign in.">Suspend</button>
        </fieldset>
        <div class="flex flex-wrap items-end gap-2 lg:justify-end">
            <button type="submit" name="action" value="restore" formnovalidate class="btn-secondary !min-h-[38px] !py-1.5" data-bulk-needs="bulk-users" data-confirm="Restore access for {n} accounts?">Restore</button>
            <button type="submit" name="action" value="verify" formnovalidate class="btn-secondary !min-h-[38px] !py-1.5" data-bulk-needs="bulk-users" data-confirm="Re-send the verification email to {n} accounts?">Re-send verification</button>
            <button type="submit" formaction="{{ route('backend.admin.users.bulk.delete') }}" formnovalidate class="btn-secondary !min-h-[38px] !py-1.5 text-state-bad" data-bulk-needs="bulk-users" data-confirm="Delete {n} accounts permanently? This cannot be undone." data-confirm-danger>Delete</button>
        </div>
    </div>
</form>

{{-- Roles are changed on each account's page, where the change is confirmed by name; the
     list only shows them. Below 640px each row is a card (admin.css, users section). --}}
<div class="table-wrap mt-3 bg-white">
    <table class="adm-users-table">
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
                <td class="adm-users-select">@unless($locked)<input type="checkbox" name="ids[]" value="{{ $u->id }}" form="bulk-users" data-bulk-item aria-label="Select {{ $u->email }}">@endunless</td>
                <td class="adm-users-account">
                    <a href="{{ route('backend.admin.users.show', $u) }}" class="block font-medium text-brand-navy">{{ $u->name ?: '—' }}</a>
                    <span class="block break-all font-mono text-xs text-brand-muted">{{ $u->email }}</span>
                    @if($u->organization_name)<span class="block text-xs text-brand-muted">{{ $u->organization_name }}</span>@endif
                </td>
                <td data-label="Role">
                    @if($u->isOwner())
                        <span class="badge bg-brand-navy text-white ring-brand-navy">Owner</span>
                        <span class="block mt-1 text-xs text-brand-muted">From the environment</span>
                    @else
                        <span class="badge bg-brand-paper text-brand-body ring-brand-line">{{ $u->adminRole() ? $u->adminRoleLabel() : 'No access' }}</span>
                        @if($self)<span class="block mt-1 text-xs text-brand-muted">This is you</span>
                        @elseif($u->admin_role_granted_at)<span class="block mt-1 text-xs text-brand-muted">by {{ $u->adminRoleGrantedBy?->email ?? 'a removed account' }}, {{ $u->admin_role_granted_at->format('j M Y') }}</span>@endif
                    @endif
                </td>
                <td class="text-xs" data-label="Second factor">
                    @if(! $u->isAdmin())
                        <span class="text-brand-muted">Not required</span>
                    @elseif($u->hasTwoFactorEnabled())
                        <span class="text-state-good">Enrolled</span>
                    @else
                        <span class="text-state-warn">Not enrolled</span>
                    @endif
                </td>
                <td class="text-xs" data-label="Status">
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
                <td class="whitespace-nowrap text-xs" data-label="Last signed in">
                    @if($u->last_login_at)<time datetime="{{ $u->last_login_at->toIso8601String() }}" title="{{ $u->last_login_at->format('j M Y H:i') }}">{{ $u->last_login_at->diffForHumans() }}</time>
                    @else<span class="text-brand-muted">Never</span>@endif
                </td>
                <td class="whitespace-nowrap text-xs" data-label="Joined">{{ $u->created_at?->format('j M Y') ?? '—' }}</td>
                <td class="whitespace-nowrap adm-users-manage"><a href="{{ route('backend.admin.users.show', $u) }}" class="btn-secondary !min-h-0 !py-1 !px-2 text-xs" aria-label="Manage {{ $u->email }}">Manage</a></td>
            </tr>
        @endforeach
        </tbody>
    </table>
</div>
<nav class="mt-4" aria-label="Pagination">{{ $users->links() }}</nav>
@endif

<section class="mt-10" aria-labelledby="roles-heading">
    <div class="flex flex-wrap items-baseline justify-between gap-2">
        <h2 id="roles-heading" class="text-lg font-semibold text-brand-navy">What each role can do</h2>
        <a href="{{ route('backend.admin.users.permissions') }}" class="text-sm">Edit role permissions →</a>
    </div>
    <div class="mt-3 grid gap-3 md:grid-cols-3">
        @foreach(array_slice($roleOptions, 1) as $option)
        <div class="card-flat bg-white p-4">
            <p class="font-semibold text-brand-navy">{{ $option['label'] }}@if($option['edited']) <span class="badge-neutral ml-1">Edited</span>@endif</p>
            <p class="mt-1 text-sm text-brand-body">@if($option['edited'])The default was: {{ lcfirst($option['description']) }} The list below is what it can do now.@else{{ $option['description'] }}@endif</p>
            <ul class="mt-2 space-y-1 text-xs text-brand-muted list-disc pl-4">
                @forelse($option['capabilities'] as $capability)<li>{{ $capability }}</li>@empty<li>Nothing at the moment.</li>@endforelse
            </ul>
        </div>
        @endforeach
    </div>
</section>

<x-backend.drawer id="invite-drawer" title="Invite a user" description="A new account, with or without a role, and a link to choose its password."
    :open="$errors->any() && old('_form') === 'invite'">
    @include('backend.admin.users.invite-form')
</x-backend.drawer>
@endsection
