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
            // Enrolling and rotating your own factor is not gated by a capability: a newly
            // granted role has to be able to reach it before it can do anything else.
            'Your account' => [
                ['admin.two-factor.recovery', 'Authenticator', null],
            ],
        ])
        @php($pattern = fn (string $r) => preg_match('/\.index$/', $r) ? preg_replace('/\.index$/', '.*', $r) : $r.'*')
        @php($current = collect($groups)->flatten(1)->first(fn ($e) => request()->routeIs($pattern($e[0]))))
        <div class="flex items-center justify-between gap-3 px-4 py-4 lg:py-5">
            <a href="{{ route('backend.admin.dashboard') }}" class="block no-underline"><img src="{{ asset('brand/logo-on-dark.svg') }}" alt="AIPolicyTracker admin" class="h-8 w-auto"></a>
            <span class="hidden lg:inline eyebrow !text-brand-cyan">Admin</span>
            <button type="button" class="lg:hidden rounded-sm border border-white/20 px-3 py-1.5 text-sm text-white" aria-controls="admin-nav" aria-expanded="false" data-admin-menu>{{ $current[1] ?? 'Menu' }} ▾</button>
        </div>
        <div id="admin-nav" class="hidden lg:flex lg:flex-1 lg:min-h-0 flex-col border-t border-white/10 lg:border-0" data-admin-nav>
            <div class="px-4 pb-2">
                <label for="admin-nav-filter" class="sr-only">Go to a page</label>
                <input id="admin-nav-filter" type="search" placeholder="Go to…  ( / )" autocomplete="off" class="w-full rounded-sm border-0 bg-white/10 px-3 py-1.5 text-sm text-white placeholder:text-white/50 focus:ring-2 focus:ring-brand-cyan" data-admin-nav-filter>
                <button type="button" class="mt-2 hidden w-full items-center justify-between rounded-sm bg-white/5 px-3 py-1.5 text-left text-xs text-white/70 hover:bg-white/10 lg:flex" data-palette-open hidden>Search pages and actions <kbd class="adm-kbd">Ctrl K</kbd></button>
            </div>
            <nav class="flex-1 overflow-y-auto px-3 pb-4 text-sm" aria-label="Admin">
                @foreach($groups as $heading => $entries)
                @php($visible = collect($entries)->filter(fn ($e) => $e[2] === null || auth()->user()->can($e[2])))
                @continue($visible->isEmpty())
                <div class="mt-3 first:mt-1" data-admin-nav-group>
                    <p class="px-3 pb-1 text-[11px] font-semibold uppercase tracking-[0.12em] text-white/45">{{ $heading }}</p>
                    @foreach($visible as [$r, $label, $capability])
                    @php($active = request()->routeIs($pattern($r)))
                    <a href="{{ route($r) }}" class="flex items-center rounded-sm border-l-2 px-3 py-1.5 no-underline {{ $active ? 'border-brand-cyan bg-white/10 text-white font-medium' : 'border-transparent text-white/75 hover:bg-white/5 hover:text-white' }}" @if($active) aria-current="page" @endif data-admin-nav-link>{{ $label }}</a>
                    @endforeach
                </div>
                @endforeach
            </nav>
            <div class="border-t border-white/10 px-4 py-3 text-xs text-white/60">
                <p class="flex items-center justify-between gap-2"><span class="truncate text-white/85">{{ auth()->user()->name }}</span><span class="shrink-0 rounded-sm bg-white/10 px-1.5 py-0.5 text-white/80">{{ auth()->user()->adminRoleLabel() }}</span></p>
                <p class="mt-1.5 flex items-center gap-3"><a href="{{ route('home') }}" class="text-white/80 no-underline hover:text-white">Public site ↗</a><form method="post" action="{{ route('logout') }}" class="inline">@csrf<button type="submit" class="text-white/80 no-underline hover:text-white bg-transparent border-0 p-0 cursor-pointer">Sign out</button></form></p>
            </div>
        </div>
    </aside>
    <main id="main" class="bg-brand-paper px-4 sm:px-8 py-6 lg:py-8 min-w-0 min-h-screen">
        {{-- Messages from the last action, as toasts: rendered here so they show without
             JavaScript; admin.js adds the close button and lets successes fade out. --}}
        <div class="adm-toasts" data-toasts>
            @if(session('success'))<div class="adm-toast" data-toast="success" role="status"><span>{{ session('success') }}</span><button type="button" data-toast-close aria-label="Dismiss" hidden>×</button></div>@endif
            @if(session('error'))<div class="adm-toast" data-toast="error" role="alert"><span>{{ session('error') }}</span><button type="button" data-toast-close aria-label="Dismiss" hidden>×</button></div>@endif
            @if($errors->any())<div class="adm-toast" data-toast="error" role="alert"><span>{{ $errors->first() }}@if($errors->count() > 1) <span class="text-brand-muted">(and {{ $errors->count() - 1 }} more on the form)</span>@endif</span><button type="button" data-toast-close aria-label="Dismiss" hidden>×</button></div>@endif
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
<dialog id="adm-palette" class="adm-modal adm-palette" aria-label="Search pages and actions">
    <div class="border-b border-brand-line p-3">
        <input type="search" class="input" placeholder="Search pages and actions…" autocomplete="off" role="combobox" aria-expanded="true" aria-controls="adm-palette-list" aria-autocomplete="list">
    </div>
    <ul id="adm-palette-list" role="listbox" class="max-h-[50vh] overflow-y-auto py-1" aria-label="Results"></ul>
    <p class="px-4 py-3 text-sm text-brand-muted" data-palette-empty hidden>Nothing matches.</p>
    <p class="border-t border-brand-line bg-brand-paper px-4 py-2 text-xs text-brand-muted">↑ ↓ to move · Enter to open · Esc to close</p>
</dialog>
</body>
</html>
