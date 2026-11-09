<!DOCTYPE html>
<html lang="en" data-scheme="light" data-admin>
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="robots" content="noindex,nofollow">
    <title>{{ $title ?? 'Admin' }} | AIPolicyTracker admin</title>
    <link rel="icon" type="image/svg+xml" href="{{ asset('brand/mark.svg') }}">
    {{-- The system font, as on the public site: nothing to download, no third-party font host. --}}
    @vite(['resources/css/public.css', 'resources/css/admin.css', 'resources/js/public.js', 'resources/js/admin.js'])
</head>
<body class="min-h-screen bg-brand-paper">
{{-- Past the long sidebar menu in one keystroke, as on the public site. --}}
<a href="#main" class="skip-link">Skip to content</a>
<div class="lg:grid lg:grid-cols-[248px_1fr] min-h-screen">
    {{-- The sidebar stays in view while the page scrolls (sticky, full height, its own
         scroll if the menu is taller than the window). On small screens it collapses
         behind a Menu button. Entries are grouped by job, and each names the capability
         its page requires: one the account does not hold is not offered, because a link
         that answers 403 reads as a broken admin. Route middleware still enforces access. --}}
    <aside class="adm-sidebar lg:sticky lg:top-0 lg:h-screen lg:flex lg:flex-col" aria-label="Admin navigation">
        @php($groups = config('admin-navigation.groups'))
        @php($pattern = fn (string $r) => preg_match('/\.index$/', $r) ? preg_replace('/\.index$/', '.*', $r) : $r.'*')
        @php($current = collect($groups)->flatten(1)->first(fn ($e) => request()->routeIs($pattern($e[0]))))
        @php($me = auth()->user())
        @php($initials = \Illuminate\Support\Str::of($me->name ?: $me->email)->explode(' ')->filter()->map(fn ($w) => mb_substr($w, 0, 1))->pipe(fn ($c) => $c->count() > 1 ? $c->first().$c->last() : $c->first()))
        @php($searchKinds = \App\Http\Controllers\Backend\Admin\ShellController::searchableKinds($me))
        @php($counts = app(\App\Services\Admin\NavCounts::class)->all())
        <div class="flex items-center justify-between gap-3 px-4 py-3.5 lg:py-4">
            <a href="{{ route('backend.admin.dashboard') }}" class="flex items-center gap-2.5 no-underline"><img src="{{ asset('brand/logo-on-light.svg') }}" alt="AIPolicyTracker admin" class="h-8 w-auto"></a>
            <span class="hidden lg:inline rounded-sm bg-brand-paper px-1.5 py-0.5 text-xs font-semibold text-brand-navy ring-1 ring-inset ring-brand-line">Admin</span>
            <div class="flex items-center gap-2 lg:hidden">
                <button type="button" class="btn-secondary btn-sm" data-palette-open hidden aria-label="Search pages and records"><svg class="h-4 w-4" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true"><circle cx="8.5" cy="8.5" r="5.5"/><path d="m13 13 4 4" stroke-linecap="round"/></svg></button>
                <button type="button" class="btn-secondary btn-sm" aria-controls="admin-nav" aria-expanded="false" data-admin-menu>{{ $current[1] ?? 'Menu' }} ▾</button>
            </div>
        </div>
        <div id="admin-nav" class="hidden lg:flex lg:flex-1 lg:min-h-0 flex-col border-t border-brand-line lg:border-0" data-admin-nav>
            <div class="px-3 pb-2 pt-2 lg:pt-0">
                <label for="admin-nav-filter" class="sr-only">Filter the menu</label>
                <input id="admin-nav-filter" type="search" placeholder="Filter the menu  ( / )" autocomplete="off" class="adm-nav-filter" data-admin-nav-filter>
            </div>
            {{-- Grouped by the work, and every group folds away. The group that holds the current
                 page is always open; admin.js remembers the others the person closed. A figure
                 beside an entry is how much is waiting there (NavCounts). min-h-0 lets the menu
                 scroll on a short window instead of pushing the account footer out of view. --}}
            <nav class="adm-nav min-h-0 flex-1 overflow-y-auto px-3 pb-3" aria-label="Admin" data-admin-nav-scroll>
                @foreach($groups as $heading => $entries)
                @php($visible = collect($entries)->filter(fn ($e) => $e[2] === null || $me->can($e[2])))
                @continue($visible->isEmpty())
                @php($holdsCurrent = $visible->contains(fn ($e) => request()->routeIs($pattern($e[0]))))
                @php($waiting = $visible->sum(fn ($e) => $counts[$e[0]]['count'] ?? 0))
                <details class="adm-nav-group" data-admin-nav-group data-group="{{ \Illuminate\Support\Str::slug($heading) }}" open{{ $holdsCurrent ? ' data-current' : '' }}>
                    <summary class="adm-nav-heading"><span data-admin-nav-heading>{{ $heading }}</span>@if($waiting > 0)<span class="adm-nav-dot" aria-hidden="true"></span>@endif<svg class="adm-nav-chevron" viewBox="0 0 12 12" aria-hidden="true"><path d="M3 4.5 6 7.5 9 4.5" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg></summary>
                    <div class="adm-nav-items">
                    @foreach($visible as [$r, $label, $capability])
                    @php($active = request()->routeIs($pattern($r)))
                    @php($count = $counts[$r] ?? null)
                    <a href="{{ route($r) }}" class="adm-nav-link" @if($active) aria-current="page" @endif data-admin-nav-link><span data-nav-label>{{ $label }}</span>@if($count)<span class="adm-nav-count adm-nav-count-{{ $count['tone'] }}" title="{{ number_format($count['count']).' '.$count['label'] }}"><span class="sr-only">, </span>{{ $count['count'] > 999 ? '999+' : number_format($count['count']) }}<span class="sr-only"> {{ $count['label'] }}</span></span>@endif</a>
                    @endforeach
                    </div>
                </details>
                @endforeach
            </nav>
            {{-- The account: who is signed in, with what role, and the few things about the
                 account itself. A <details> menu, so it opens without JavaScript; admin.js
                 closes it on Escape and on a click elsewhere. --}}
            <details class="adm-account relative shrink-0 border-t border-brand-line" data-admin-account>
                <summary class="flex items-center gap-2.5 px-4 py-2.5 text-xs text-brand-body hover:bg-brand-paper" aria-label="{{ $me->name }} {{ $me->adminRoleLabel() }}: account menu">
                    <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-brand-navy text-xs font-semibold uppercase text-white" aria-hidden="true">{{ $initials }}</span>
                    <span class="min-w-0 flex-1"><span class="block truncate text-sm font-medium text-brand-navy">{{ $me->name }}</span><span class="block text-brand-muted">{{ $me->adminRoleLabel() }}</span></span>
                    <span class="text-brand-muted" aria-hidden="true">▴</span>
                </summary>
                <div class="adm-account-menu absolute bottom-full left-2 right-2 mb-1 rounded-md border border-brand-line bg-white py-1 text-sm shadow-lg">
                    {{-- Not gated by a capability: a newly granted role has to reach its own factor first. --}}
                    <a href="{{ route('admin.two-factor.recovery') }}" class="block px-3 py-1.5 text-brand-body no-underline hover:bg-brand-paper hover:text-brand-navy" data-command="Authenticator and recovery codes">Authenticator and recovery codes</a>
                    <a href="{{ route('profile.edit') }}" class="block px-3 py-1.5 text-brand-body no-underline hover:bg-brand-paper hover:text-brand-navy" data-command="Your profile and password">Profile and password</a>
                    <a href="{{ route('home') }}" class="block px-3 py-1.5 text-brand-body no-underline hover:bg-brand-paper hover:text-brand-navy">Public site ↗</a>
                    <form method="post" action="{{ route('logout') }}" class="border-t border-brand-line mt-1 pt-1">@csrf<button type="submit" class="block w-full cursor-pointer border-0 bg-transparent px-3 py-1.5 text-left text-brand-body hover:bg-brand-paper hover:text-brand-navy" data-no-busy>Sign out</button></form>
                </div>
            </details>
        </div>
    </aside>
    <div class="min-w-0">
    {{-- One search for the whole admin (Ctrl/⌘ K): pages, this page's actions and, for a role
         that may open them, records. Shown once JavaScript has wired the palette. --}}
    <div class="adm-topbar hidden lg:flex">
        <p class="truncate text-sm text-brand-muted">@if($current)<span>{{ collect($groups)->search(fn ($entries) => in_array($current, $entries, true)) }}</span> <span aria-hidden="true">/</span> <span class="font-medium text-brand-navy">{{ $current[1] }}</span>@endif</p>
        <div class="flex items-center gap-2">
            <button type="button" class="adm-search-button" data-palette-open hidden><svg class="h-4 w-4" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true"><circle cx="8.5" cy="8.5" r="5.5"/><path d="m13 13 4 4" stroke-linecap="round"/></svg><span>{{ $searchKinds !== [] ? 'Search pages and records' : 'Search pages and actions' }}</span><kbd class="adm-kbd">Ctrl K</kbd></button>
            <a href="{{ route('home') }}" class="btn-secondary btn-sm">View site ↗</a>
        </div>
    </div>
    <main id="main" tabindex="-1" class="bg-brand-paper px-4 sm:px-8 py-6 lg:py-7 min-w-0 min-h-screen">
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
