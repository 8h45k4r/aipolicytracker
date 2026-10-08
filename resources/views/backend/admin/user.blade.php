@extends('backend.layouts.app', ['title' => ($user->name ?: $user->email).' · Users'])
@section('content')
<p class="text-sm"><a href="{{ route('backend.admin.users.index') }}">← Users and roles</a></p>

<div class="mt-2">
    <x-backend.page-header :title="$user->name ?: $user->email" :description="$user->email.($user->organization_name ? ' · '.$user->organization_name : '')">
        <x-slot:actions>
            @if($user->isOwner())<span class="badge bg-brand-navy text-white ring-brand-navy">Owner</span>@else<span class="badge bg-brand-paper text-brand-body ring-brand-line">{{ $user->adminRole() ? $user->adminRoleLabel() : 'No admin access' }}</span>@endif
            @if($user->isSuspended())<x-backend.badge status="suspended" />@elseif($user->invitationPending())<x-backend.badge status="unconfirmed">invitation pending</x-backend.badge>@elseif(! $user->email_verified_at)<x-backend.badge status="unconfirmed">email unverified</x-backend.badge>@else<x-backend.badge status="active" />@endif
        </x-slot:actions>
    </x-backend.page-header>
</div>

{{-- The invitation link, when the email carrying it was not delivered (log or array mailer,
     or a refused delivery). It is flashed for one request to the person who made it, so it
     shows here once; afterwards "Re-send invitation" makes a new link and retires this one. --}}
@php($invitation = session('invitation_link'))
@if(is_array($invitation) && (int) ($invitation['user'] ?? 0) === $user->getKey())
<section class="mt-5 rounded-md border border-state-warn bg-white p-4" aria-labelledby="invite-link-heading" data-invitation-link>
    <h2 id="invite-link-heading" class="font-semibold text-brand-navy">Invitation link (not emailed)</h2>
    <p class="mt-1 max-w-3xl text-sm text-brand-body">Send this link to {{ $user->email }} yourself, privately. Whoever holds it can choose this account's password until it is used or {{ \App\Mail\AdminInvitationMail::DAYS }} days pass. It is shown only this once; if it is lost, use "Re-send invitation" once mail is set up, which makes a new link and retires this one.</p>
    <div class="mt-3 flex flex-wrap items-center gap-2">
        <label for="invite-link" class="sr-only">Invitation link</label>
        <input id="invite-link" type="text" readonly value="{{ $invitation['url'] }}" class="input !min-h-[38px] min-w-0 flex-1 font-mono text-xs">
        {{-- Shown by admin.js (users section), which does the copying; without it, select the text. --}}
        <button type="button" class="btn-primary btn-sm" data-copy-from="invite-link" hidden>Copy invitation link</button>
    </div>
</section>
@endif

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
            <a href="#role-heading" class="btn-secondary mt-3 w-full">Change role</a>
            <div class="mt-2 flex flex-col gap-2">
                @if($user->invitationPending())
                    <form method="post" action="{{ route('backend.admin.users.invitation', $user) }}">@csrf<button class="btn-secondary w-full">Re-send invitation</button></form>
                    @if(in_array(config('mail.default'), ['log', 'array'], true))
                        <p class="text-xs text-brand-muted">This server's mailer is "{{ config('mail.default') }}", so nothing is emailed: re-sending makes a new link, retires the old one and shows the new one to you here.</p>
                    @endif
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

@unless($locked)
{{-- Changing the role: one button per choice, each confirmed by name ("from Reviewer to
     Editor"). A choice that adds any permission the account does not hold now is marked
     as raising its access, and its confirmation is styled as a dangerous action. --}}
@php($currentValue = $user->adminRole()?->value ?? '')
@php($currentOption = collect($roleOptions)->firstWhere('value', $currentValue))
<section class="mt-6" aria-labelledby="role-heading" id="role">
    <h2 id="role-heading" class="text-lg font-semibold text-brand-navy">Role</h2>
    <p class="mt-1 meta">Now: <span class="font-medium text-brand-navy">{{ $currentOption['label'] }}</span>. A change takes effect on their next page load; a role with admin access asks for an authenticator at next sign-in.</p>
    <form method="post" action="{{ route('backend.admin.users.role', $user) }}" class="mt-3 grid gap-3 md:grid-cols-2 xl:grid-cols-4">@csrf
        @foreach($roleOptions as $option)
            @php($isCurrent = $option['value'] === $currentValue)
            @php($raises = array_diff($option['capabilities'], $currentOption['capabilities']) !== [])
            <div class="flex flex-col rounded-md border bg-white p-4 {{ $isCurrent ? 'border-brand-navy' : 'border-brand-line' }}" data-role-option="{{ $option['value'] ?: 'none' }}">
                <p class="font-semibold text-brand-navy">{{ $option['label'] }}@if($option['edited']) <span class="badge-neutral ml-1">Edited</span>@endif</p>
                <p class="mt-1 text-xs text-brand-body">{{ $option['description'] }}</p>
                @if($option['capabilities'] !== [])
                    <ul class="mt-2 flex flex-wrap gap-1" aria-label="{{ $option['label'] }} can">
                        @foreach($option['capabilities'] as $capability)<li class="rounded-sm bg-brand-paper px-1.5 py-0.5 text-[11px] text-brand-navy ring-1 ring-inset ring-brand-line">{{ $capability }}</li>@endforeach
                    </ul>
                @endif
                <div class="mt-auto pt-3">
                    @if($isCurrent)
                        <p class="text-sm font-medium text-brand-navy">Current role</p>
                    @else
                        <button type="submit" name="admin_role" value="{{ $option['value'] }}" class="{{ $raises ? 'btn-primary' : 'btn-secondary' }} w-full"
                            data-confirm="Change {{ $user->email }} from {{ $currentOption['label'] }} to {{ $option['label'] }}?{{ $raises ? ' This gives them access they do not have now.' : '' }}"
                            data-confirm-label="Change to {{ $option['label'] }}" @if($raises) data-confirm-danger @endif>
                            {{ $option['value'] === '' ? 'Remove admin access' : 'Change to '.$option['label'] }}
                        </button>
                        @if($raises)<p class="mt-1 text-xs text-state-warn">Raises their access</p>@endif
                    @endif
                </div>
            </div>
        @endforeach
    </form>
</section>
@endunless

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
        <p class="mt-1 meta">Role, suspension and second-factor changes made on this account's own page or row. Bulk actions are logged once on the audit page, with the ids of the accounts they changed.</p>
        @if($about->isEmpty())<p class="mt-3 text-sm text-brand-muted">None recorded.</p>@else
        <div class="table-wrap mt-3 bg-white"><table><caption class="sr-only">Admin actions on this account</caption>
            <thead><tr><th scope="col">When</th><th scope="col">By</th><th scope="col">Action</th><th scope="col">Result</th></tr></thead>
            <tbody>@foreach($about as $row)<tr><td class="whitespace-nowrap font-mono text-xs">{{ $row->created_at->format('Y-m-d H:i') }}</td><td class="text-xs">{{ $row->user_email }}</td><td class="text-xs">{{ \Illuminate\Support\Str::of($row->route_name)->after('backend.admin.users.')->replace(['.', '-'], ' ') }}</td><td class="font-mono text-xs">{{ $row->status }}</td></tr>@endforeach</tbody>
        </table></div>@endif
    </section>
    <section aria-labelledby="by-heading">
        <div class="flex flex-wrap items-baseline justify-between gap-2"><h2 id="by-heading" class="font-display text-lg font-semibold text-brand-navy">Actions by this account</h2>@can('audit.view')<a href="{{ route('backend.admin.audit', ['user' => $user->email]) }}" class="text-sm">Everything in the audit log</a>@endcan</div>
        <p class="mt-1 meta">The last 25 changes this account made in the admin.@can('audit.view') <a href="{{ route('backend.admin.audit') }}">Full audit log</a>.@endcan</p>
        @if($actions->isEmpty())<p class="mt-3 text-sm text-brand-muted">None recorded.</p>@else
        <div class="table-wrap mt-3 bg-white"><table><caption class="sr-only">Admin actions by this account</caption>
            <thead><tr><th scope="col">When</th><th scope="col">Route</th><th scope="col">Result</th></tr></thead>
            <tbody>@foreach($actions as $row)<tr><td class="whitespace-nowrap font-mono text-xs">{{ $row->created_at->format('Y-m-d H:i') }}</td><td class="font-mono text-xs">{{ $row->route_name ?? $row->path }}</td><td class="font-mono text-xs">{{ $row->status }}</td></tr>@endforeach</tbody>
        </table></div>@endif
    </section>
</div>
@endsection
