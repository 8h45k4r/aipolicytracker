@extends('backend.layouts.app', ['title' => ($user->name ?: $user->email).' · Users'])
@section('content')
<p class="text-sm"><a href="{{ route('backend.admin.users.index') }}">← Users and roles</a></p>

<div class="mt-2 flex flex-wrap items-start justify-between gap-3">
    <div>
        <h1 class="font-display text-2xl font-semibold text-brand-navy">{{ $user->name ?: $user->email }}</h1>
        <p class="mt-1 font-mono text-sm text-brand-muted">{{ $user->email }}</p>
        @if($user->organization_name)<p class="text-sm text-brand-body">{{ $user->organization_name }}</p>@endif
    </div>
    <div class="flex flex-wrap gap-1">
        @if($user->isOwner())<span class="badge bg-brand-navy text-white ring-brand-navy">Owner</span>@elseif($user->adminRole())<span class="badge bg-brand-paper text-brand-body ring-brand-line">{{ $user->adminRoleLabel() }}</span>@endif
        @if($user->isSuspended())<x-backend.badge status="suspended" />@elseif($user->invitationPending())<x-backend.badge status="unconfirmed">invitation pending</x-backend.badge>@elseif(! $user->email_verified_at)<x-backend.badge status="unconfirmed">email unverified</x-backend.badge>@else<x-backend.badge status="active" />@endif
    </div>
</div>

<div class="mt-6 grid gap-4 lg:grid-cols-3">
    <section class="card-flat bg-white p-4 lg:col-span-2" aria-labelledby="facts-heading">
        <h2 id="facts-heading" class="font-semibold text-brand-navy">Account</h2>
        <dl class="mt-3 grid gap-x-6 gap-y-2 text-sm sm:grid-cols-2">
            <div><dt class="meta">Joined</dt><dd>{{ $user->created_at?->format('j M Y H:i') ?? '—' }}</dd></div>
            <div><dt class="meta">Last signed in</dt><dd>{{ $user->last_login_at ? $user->last_login_at->format('j M Y H:i').' ('.$user->last_login_at->diffForHumans().')' : 'Never' }}</dd></div>
            <div><dt class="meta">Email verified</dt><dd>{{ $user->email_verified_at?->format('j M Y') ?? 'No' }}</dd></div>
            <div><dt class="meta">Second factor</dt><dd>@if($user->hasTwoFactorEnabled())<span class="text-state-good">Enrolled {{ $user->two_factor_confirmed_at?->format('j M Y') }}</span>@elseif($user->isAdmin())<span class="text-state-warn">Not enrolled: will be asked at next sign-in</span>@else<span class="text-brand-muted">Not required</span>@endif</dd></div>
            @if($user->invited_at)<div><dt class="meta">Invited</dt><dd>{{ $user->invited_at->format('j M Y') }} by {{ $user->invitedBy?->email ?? 'a removed account' }}</dd></div>@endif
            @if($user->admin_role_granted_at)<div><dt class="meta">Role granted</dt><dd>{{ $user->admin_role_granted_at->format('j M Y') }} by {{ $user->adminRoleGrantedBy?->email ?? 'a removed account' }}</dd></div>@endif
            @if($user->isSuspended())<div class="sm:col-span-2"><dt class="meta">Suspended</dt><dd class="text-state-bad">{{ $user->suspended_at->format('j M Y H:i') }}@if($user->suspended_reason): {{ $user->suspended_reason }}@endif</dd></div>@endif
            <div><dt class="meta">Following</dt><dd>{{ $counts['follows'] }} {{ \Illuminate\Support\Str::plural('record', $counts['follows']) }}</dd></div>
            <div><dt class="meta">Downloads</dt><dd>{{ $counts['downloads'] }}</dd></div>
        </dl>
    </section>

    <section class="card-flat bg-white p-4" aria-labelledby="actions-heading">
        <h2 id="actions-heading" class="font-semibold text-brand-navy">Actions</h2>
        @if($locked)
            <p class="mt-2 text-sm text-brand-muted">{{ $user->isOwner() ? 'Owners are defined by the ADMIN_EMAILS environment list, so they are managed on the host, not here.' : 'You cannot act on your own account here; ask another owner, or use your profile.' }}</p>
        @else
            <form method="post" action="{{ route('backend.admin.users.role', $user) }}" class="mt-3">@csrf
                <label for="role" class="label !text-xs">Role</label>
                <div class="flex gap-1">
                    <select id="role" name="admin_role" class="input !min-h-0 !py-1.5">
                        <option value="" @selected($user->adminRole() === null)>No admin access</option>
                        @foreach($roles as $role)<option value="{{ $role->value }}" @selected($user->adminRole() === $role)>{{ $role->label() }}</option>@endforeach
                    </select>
                    <button class="btn-primary !min-h-0 !py-1.5">Save</button>
                </div>
            </form>
            <div class="mt-4 flex flex-col gap-2">
                @if($user->invitationPending())
                    <form method="post" action="{{ route('backend.admin.users.invitation', $user) }}">@csrf<button class="btn-secondary w-full">Re-send invitation</button></form>
                @elseif(! $user->email_verified_at)
                    <form method="post" action="{{ route('backend.admin.users.verification', $user) }}">@csrf<button class="btn-secondary w-full">Re-send verification email</button></form>
                @endif
                @unless($user->invitationPending())
                    <form method="post" action="{{ route('backend.admin.users.password-reset', $user) }}" data-confirm="Email {{ $user->email }} a password reset link?">@csrf<button class="btn-secondary w-full">Send password reset link</button></form>
                @endunless
                @if($user->hasTwoFactorEnabled())
                    <form method="post" action="{{ route('backend.admin.users.two-factor.reset', $user) }}" data-confirm="Clear the authenticator for {{ $user->email }}? They will enrol again at next sign-in.">@csrf<button class="btn-secondary w-full">Reset second factor</button></form>
                @endif
                @if($user->isSuspended())
                    <form method="post" action="{{ route('backend.admin.users.restore', $user) }}">@csrf<button class="btn-secondary w-full">Restore access</button></form>
                @else
                    <form method="post" action="{{ route('backend.admin.users.suspend', $user) }}" class="flex flex-col gap-1" data-confirm="Suspend {{ $user->email }}? They are signed out everywhere and cannot sign in.">@csrf
                        <label for="reason" class="sr-only">Reason for suspending</label>
                        <input id="reason" name="reason" maxlength="255" placeholder="Reason (optional, kept with the account)" class="input !min-h-0 !py-1.5 text-sm">
                        <button class="btn-secondary w-full">Suspend</button>
                    </form>
                @endif
                <form method="post" action="{{ route('backend.admin.users.destroy', $user) }}" data-confirm="Delete {{ $user->email }} permanently? This cannot be undone.">@csrf @method('DELETE')<button class="btn-secondary w-full text-state-bad">Delete account</button></form>
            </div>
        @endif
    </section>
</div>

<section class="mt-6" aria-labelledby="caps-heading">
    <h2 id="caps-heading" class="font-display text-lg font-semibold text-brand-navy">What this account can do</h2>
    <p class="mt-1 meta">
        @if($user->isSuspended()) Nothing while suspended.
        @elseif($user->isOwner()) Everything: owners hold every capability.
        @elseif($user->adminRole()) From the {{ $user->adminRoleLabel() }} role{{ $roleEdited ? ', as edited on the permissions page' : ' (its defaults)' }}. <a href="{{ route('backend.admin.users.permissions') }}">Change what the role can do</a>.
        @else No admin access. The public site works as for any signed-in reader.
        @endif
    </p>
    <ul class="mt-3 grid gap-1 text-sm sm:grid-cols-2 lg:grid-cols-3">
        @foreach($capabilities as $c)
            @php($has = in_array($c, $held, true))
            <li class="flex items-start gap-2 {{ $has ? 'text-brand-navy' : 'text-brand-muted' }}"><span aria-hidden="true" class="{{ $has ? 'text-state-good' : '' }}">{{ $has ? '✓' : '–' }}</span><span>{{ $c->label() }}<span class="sr-only">{{ $has ? ': allowed' : ': not allowed' }}</span></span></li>
        @endforeach
    </ul>
</section>

<div class="mt-8 grid gap-6 lg:grid-cols-2">
    <section aria-labelledby="about-heading">
        <h2 id="about-heading" class="font-display text-lg font-semibold text-brand-navy">Changes to this account</h2>
        <p class="mt-1 meta">Role, suspension and second-factor changes made on this account's own page or row. Bulk actions are logged once, on the audit page.</p>
        @if($about->isEmpty())<p class="mt-3 text-sm text-brand-muted">None recorded.</p>@else
        <div class="table-wrap mt-3 bg-white"><table><caption class="sr-only">Admin actions on this account</caption>
            <thead><tr><th scope="col">When</th><th scope="col">By</th><th scope="col">Action</th><th scope="col">Result</th></tr></thead>
            <tbody>@foreach($about as $row)<tr><td class="whitespace-nowrap font-mono text-xs">{{ $row->created_at->format('Y-m-d H:i') }}</td><td class="text-xs">{{ $row->user_email }}</td><td class="text-xs">{{ \Illuminate\Support\Str::of($row->route_name)->after('backend.admin.users.')->replace(['.', '-'], ' ') }}</td><td class="font-mono text-xs">{{ $row->status }}</td></tr>@endforeach</tbody>
        </table></div>@endif
    </section>
    <section aria-labelledby="by-heading">
        <h2 id="by-heading" class="font-display text-lg font-semibold text-brand-navy">Actions by this account</h2>
        <p class="mt-1 meta">The last 25 changes this account made in the admin.@can('audit.view') <a href="{{ route('backend.admin.audit') }}">Full audit log</a>.@endcan</p>
        @if($actions->isEmpty())<p class="mt-3 text-sm text-brand-muted">None recorded.</p>@else
        <div class="table-wrap mt-3 bg-white"><table><caption class="sr-only">Admin actions by this account</caption>
            <thead><tr><th scope="col">When</th><th scope="col">Route</th><th scope="col">Result</th></tr></thead>
            <tbody>@foreach($actions as $row)<tr><td class="whitespace-nowrap font-mono text-xs">{{ $row->created_at->format('Y-m-d H:i') }}</td><td class="font-mono text-xs">{{ $row->route_name ?? $row->path }}</td><td class="font-mono text-xs">{{ $row->status }}</td></tr>@endforeach</tbody>
        </table></div>@endif
    </section>
</div>
@endsection
