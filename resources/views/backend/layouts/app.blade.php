<!DOCTYPE html>
<html lang="en" data-scheme="light" data-admin>
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="robots" content="noindex,nofollow">
    <title>{{ $title ?? 'Admin' }} | AIPolicyTracker admin</title>
    <link rel="icon" type="image/svg+xml" href="{{ asset('brand/mark.svg') }}">
    {{-- Poppins and Space Mono are self-hosted (admin.css, public.css): no third-party font host. --}}
    @vite(['resources/css/public.css', 'resources/css/admin.css', 'resources/js/public.js', 'resources/js/admin.js'])
</head>
<body class="min-h-screen bg-brand-paper">
<div class="lg:grid lg:grid-cols-[248px_1fr] min-h-screen">
    {{-- The sidebar stays in view while the page scrolls (sticky, full height, its own
         scroll if the menu is taller than the window). On small screens it collapses
         behind a Menu button. Entries are grouped by job, and each names the capability
         its page requires: one the account does not hold is not offered, because a link
         that answers 403 reads as a broken admin. Route middleware still enforces access. --}}
    <aside class="bg-brand-ink text-white/85 lg:sticky lg:top-0 lg:h-screen lg:flex lg:flex-col" aria-label="Admin navigation">
        @php($groups = [
            'Overview' => [
                ['backend.admin.dashboard', 'Dashboard', 'dashboard.view'],
            ],
            'Content' => [
                ['backend.review.index', 'Review queue', 'submissions.decide'],
                ['backend.checks.index', 'Independent checks', 'records.verify'],
                ['backend.admin.submissions', 'Submissions and feedback', 'submissions.decide'],
                ['backend.admin.external', 'External data', 'external.sync'],
                ['backend.admin.tools.index', 'Tool library', 'tools.manage'],
            ],
            'Audience' => [
                ['backend.admin.subscribers', 'Subscribers', 'audience.view'],
                ['backend.admin.downloads', 'Guides and downloads', 'audience.view'],
                ['backend.admin.alerts.index', 'Alerts and watches', 'audience.view'],
            ],
            'Operations' => [
                ['backend.admin.jobs', 'Jobs and schedule', 'jobs.run'],
                ['backend.admin.audit', 'Audit log', 'audit.view'],
            ],
            'Administration' => [
                ['backend.admin.users.index', 'Users and roles', 'users.manage'],
                ['backend.admin.billing.index', 'Billing', 'billing.manage'],
                ['backend.admin.funding.index', 'Funding and funders', 'settings.manage'],
                ['backend.admin.settings', 'Settings and API keys', 'settings.manage'],
            ],
        ])
        @php($pattern = fn (string $r) => preg_match('/\.index$/', $r) ? preg_replace('/\.index$/', '.*', $r) : $r.'*')
        @php($current = collect($groups)->flatten(1)->first(fn ($e) => request()->routeIs($pattern($e[0]))))
        @php($me = auth()->user())
        @php($initials = \Illuminate\Support\Str::of($me->name ?: $me->email)->explode(' ')->filter()->map(fn ($w) => mb_substr($w, 0, 1))->pipe(fn ($c) => $c->count() > 1 ? $c->first().$c->last() : $c->first()))
        @php($searchKinds = \App\Http\Controllers\Backend\Admin\ShellController::searchableKinds($me))
        <div class="flex items-center justify-between gap-3 px-4 py-4 lg:py-5">
            <a href="{{ route('backend.admin.dashboard') }}" class="block no-underline"><img src="{{ asset('brand/logo-on-dark.svg') }}" alt="AIPolicyTracker admin" class="h-8 w-auto"></a>
            <span class="hidden lg:inline eyebrow !text-brand-cyan">Admin</span>
            <button type="button" class="lg:hidden rounded-sm border border-white/20 px-3 py-1.5 text-sm text-white" aria-controls="admin-nav" aria-expanded="false" data-admin-menu>{{ $current[1] ?? 'Menu' }} ▾</button>
        </div>
        <div id="admin-nav" class="hidden lg:flex lg:flex-1 lg:min-h-0 flex-col border-t border-white/10 lg:border-0" data-admin-nav>
            <div class="px-4 pb-2">
                <label for="admin-nav-filter" class="sr-only">Go to a page</label>
                <input id="admin-nav-filter" type="search" placeholder="Go to…  ( / )" autocomplete="off" class="w-full rounded-sm border-0 bg-white/10 px-3 py-1.5 text-sm text-white placeholder:text-white/50 focus:ring-2 focus:ring-brand-cyan" data-admin-nav-filter>
                <button type="button" class="mt-2 hidden w-full items-center justify-between gap-2 rounded-sm bg-white/5 px-3 py-1.5 text-left text-xs text-white/75 hover:bg-white/10 lg:flex" data-palette-open hidden><span class="truncate">{{ $searchKinds !== [] ? 'Search pages and records' : 'Search pages and actions' }}</span> <kbd class="adm-kbd shrink-0 whitespace-nowrap">Ctrl K</kbd></button>
            </div>
            {{-- min-h-0 lets the menu shrink below its content inside the column, so it
                 scrolls on a short window instead of pushing the account footer out of view. --}}
            <nav class="adm-nav min-h-0 flex-1 overflow-y-auto px-3 pb-3 text-sm" aria-label="Admin" data-admin-nav-scroll>
                @foreach($groups as $heading => $entries)
                @php($visible = collect($entries)->filter(fn ($e) => $e[2] === null || auth()->user()->can($e[2])))
                @continue($visible->isEmpty())
                <div class="mt-2.5 first:mt-1" data-admin-nav-group>
                    {{-- White at 65% on the ink is 8:1; at 45% it measured 4.47:1 and failed. --}}
                    <p class="px-3 pb-0.5 text-[11px] font-semibold uppercase tracking-[0.12em] text-white/65">{{ $heading }}</p>
                    @foreach($visible as [$r, $label, $capability])
                    @php($active = request()->routeIs($pattern($r)))
                    <a href="{{ route($r) }}" class="flex items-center rounded-sm border-l-2 px-3 py-[5px] no-underline {{ $active ? 'border-brand-cyan bg-white/10 text-white font-medium' : 'border-transparent text-white/75 hover:bg-white/5 hover:text-white' }}" @if($active) aria-current="page" @endif data-admin-nav-link>{{ $label }}</a>
                    @endforeach
                </div>
                @endforeach
            </nav>
            {{-- The account: who is signed in, with what role, and the few things about the
                 account itself. A <details> menu, so it opens without JavaScript; admin.js
                 closes it on Escape and on a click elsewhere. --}}
            <details class="adm-account relative shrink-0 border-t border-white/10" data-admin-account>
                <summary class="flex items-center gap-2.5 px-4 py-2.5 text-xs text-white/85 hover:bg-white/5" aria-label="Account menu for {{ $me->name }}, {{ $me->adminRoleLabel() }}">
                    <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-brand-cyan/25 text-[11px] font-semibold uppercase text-white ring-1 ring-white/20" aria-hidden="true">{{ $initials }}</span>
                    <span class="min-w-0 flex-1"><span class="block truncate text-[13px] text-white">{{ $me->name }}</span><span class="block text-white/65">{{ $me->adminRoleLabel() }}</span></span>
                    <span class="text-white/65" aria-hidden="true">▴</span>
                </summary>
                <div class="adm-account-menu absolute bottom-full left-2 right-2 mb-1 rounded-md border border-white/10 bg-brand-ink py-1 text-[13px] shadow-lg">
                    {{-- Not gated by a capability: a newly granted role has to reach its own factor first. --}}
                    <a href="{{ route('admin.two-factor.recovery') }}" class="block px-3 py-1.5 text-white/85 no-underline hover:bg-white/10 hover:text-white" data-command="Authenticator and recovery codes">Authenticator and recovery codes</a>
                    <a href="{{ route('profile.edit') }}" class="block px-3 py-1.5 text-white/85 no-underline hover:bg-white/10 hover:text-white" data-command="Your profile and password">Profile and password</a>
                    <a href="{{ route('home') }}" class="block px-3 py-1.5 text-white/85 no-underline hover:bg-white/10 hover:text-white">Public site ↗</a>
                    <form method="post" action="{{ route('logout') }}" class="border-t border-white/10 mt-1 pt-1">@csrf<button type="submit" class="block w-full cursor-pointer border-0 bg-transparent px-3 py-1.5 text-left text-white/85 hover:bg-white/10 hover:text-white" data-no-busy>Sign out</button></form>
                </div>
            </details>
        </div>
    </aside>
    <main id="main" class="bg-brand-paper px-4 sm:px-8 py-6 lg:py-8 min-w-0 min-h-screen">
        {{-- Messages from the last action, as toasts: rendered here so they show without
             JavaScript; admin.js adds the close button and lets successes fade out. --}}
        <div class="adm-toasts" data-toasts>
            @if(session('success'))<div class="adm-toast" data-toast="success" role="status"><span>{{ session('success') }}</span><button type="button" data-toast-close aria-label="Dismiss" hidden>×</button></div>@endif
            @if(session('error'))<div class="adm-toast" data-toast="error" role="alert"><span>{{ session('error') }}</span><button type="button" data-toast-close aria-label="Dismiss" hidden>×</button></div>@endif
            @if(isset($errors) && $errors->any())<div class="adm-toast" data-toast="error" role="alert"><span>{{ $errors->first() }}@if($errors->count() > 1) <span class="text-brand-muted">(and {{ $errors->count() - 1 }} more on the form)</span>@endif</span><button type="button" data-toast-close aria-label="Dismiss" hidden>×</button></div>@endif
        </div>
        @yield('content')
    </main>
</div>
{{-- Asked before any [data-confirm] action (admin.js); without JavaScript, nothing is asked twice. --}}
<dialog id="adm-confirm" class="adm-modal" aria-labelledby="adm-confirm-title">
    <div class="p-5">
        <h2 id="adm-confirm-title" class="text-base font-semibold text-brand-navy">Please confirm</h2>
        <p class="mt-2 text-sm text-brand-body" data-confirm-text></p>
    </div>
    <div class="flex justify-end gap-2 border-t border-brand-line bg-brand-paper px-5 py-3">
        <button type="button" class="btn-secondary" data-confirm-cancel>Cancel</button>
        <button type="button" class="btn-primary" data-confirm-ok>Confirm</button>
    </div>
</dialog>
{{-- Ctrl/⌘ K. Pages and this page's actions first, then, from two letters on, records from
     the search endpoint: only the kinds this account may open, so a role with none is not
     given the address at all. --}}
@php($searchHint = collect(['user' => 'people', 'policy' => 'policy records', 'submission' => 'submissions'])->only($searchKinds)->values())
<dialog id="adm-palette" class="adm-modal adm-palette" aria-label="Search pages{{ $searchHint->isNotEmpty() ? ', records' : '' }} and actions" @if($searchKinds !== []) data-search-url="{{ route('backend.admin.search') }}" @endif>
    <div class="border-b border-brand-line p-3">
        <label for="adm-palette-input" class="sr-only">Search</label>
        <input id="adm-palette-input" type="search" class="input" placeholder="{{ $searchHint->isNotEmpty() ? 'Pages, actions, '.$searchHint->implode(', ').'…' : 'Search pages and actions…' }}" autocomplete="off" role="combobox" aria-expanded="false" aria-controls="adm-palette-list" aria-autocomplete="list">
    </div>
    <ul id="adm-palette-list" role="listbox" class="max-h-[50vh] overflow-y-auto py-1" aria-label="Results"></ul>
    <p class="px-4 py-3 text-sm text-brand-muted" data-palette-empty hidden>Nothing matches.</p>
    <p class="sr-only" role="status" aria-live="polite" data-palette-status></p>
    <p class="border-t border-brand-line bg-brand-paper px-4 py-2 text-xs text-brand-muted">↑ ↓ to move · Enter to open · Esc to close @if($searchHint->isNotEmpty())· two letters or more also search {{ $searchHint->implode(', ') }}@endif</p>
</dialog>
</body>
</html>
