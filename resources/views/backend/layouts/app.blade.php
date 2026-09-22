<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="robots" content="noindex,nofollow">
    <title>{{ $title ?? 'Admin' }} | AIPolicyTracker admin</title>
    <link rel="icon" type="image/svg+xml" href="{{ asset('brand/mark.svg') }}">
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=space-grotesk:400,500,600,700|space-mono:400,700&display=swap" rel="stylesheet">
    @vite(['resources/css/public.css', 'resources/js/public.js'])
</head>
<body class="min-h-screen bg-brand-ink">
<div class="lg:grid lg:grid-cols-[240px_1fr] min-h-screen">
    <aside class="bg-brand-ink text-white/85 px-4 py-5 lg:sticky lg:top-0 lg:h-screen lg:overflow-y-auto">
        <a href="{{ route('backend.admin.dashboard') }}" class="block no-underline"><img src="{{ asset('brand/logo-on-dark.svg') }}" alt="AIPolicyTracker" class="h-9 w-auto"></a>
        <p class="mt-2 eyebrow !text-brand-cyan">Admin</p>
        {{-- Each entry names the capability its page requires, and is hidden when the signed-in
             account does not hold it. Without this a reviewer saw links to Settings and Billing
             that answered 403 on click, which reads as a broken admin rather than a scoped one.
             The route middleware is still what enforces access; this only stops offering it. --}}
        @php($nav = [
            ['backend.admin.dashboard', 'Dashboard', 'dashboard.view'],
            ['backend.review.index', 'Review queue', 'submissions.decide'],
            ['backend.admin.submissions', 'Submissions and feedback', 'submissions.decide'],
            ['backend.admin.subscribers', 'Subscribers', 'audience.view'],
            ['backend.admin.external', 'External data', 'external.sync'],
            ['backend.admin.jobs', 'Jobs and schedule', 'jobs.run'],
            ['backend.admin.downloads', 'Guides and downloads', 'audience.view'],
            ['backend.admin.tools.index', 'Tool library', 'tools.manage'],
            ['backend.admin.users.index', 'Users and roles', 'users.manage'],
            ['backend.admin.billing.index', 'Billing', 'billing.manage'],
            ['backend.admin.settings', 'Settings and API keys', 'settings.manage'],
            ['backend.admin.audit', 'Audit log', 'audit.view'],
            // Enrolling and rotating your own factor is not gated by a capability: a newly
            // granted role has to be able to reach it before it can do anything else.
            ['admin.two-factor.recovery', 'Authenticator', null],
        ])
        <nav class="mt-4 space-y-1 text-sm" aria-label="Admin">
            @foreach($nav as [$r, $label, $capability])
            @continue($capability !== null && ! auth()->user()->can($capability))
            <a href="{{ route($r) }}" class="block rounded-sm px-3 py-2 no-underline {{ request()->routeIs($r) ? 'bg-white/10 text-white' : 'text-white/80 hover:bg-white/5 hover:text-white' }}" @if(request()->routeIs($r)) aria-current="page" @endif>{{ $label }}</a>
            @endforeach
        </nav>
        <div class="mt-8 text-xs text-white/60">
            <p>{{ auth()->user()->name }}</p>
            <p class="mt-0.5"><span class="rounded-sm bg-white/10 px-1.5 py-0.5 text-white/80">{{ auth()->user()->adminRoleLabel() }}</span></p>
            <p class="mt-1"><a href="{{ route('home') }}" class="text-white/80 no-underline hover:text-white">Public site</a> · <form method="post" action="{{ route('logout') }}" class="inline">@csrf<button type="submit" class="text-white/80 no-underline hover:text-white bg-transparent border-0 p-0 cursor-pointer">Sign out</button></form></p>
        </div>
    </aside>
    <main class="bg-brand-paper px-4 sm:px-8 py-8 min-w-0 min-h-screen">
        @if(session('success'))<div class="mb-4 rounded-sm border border-state-good/30 bg-state-goodbg px-3 py-2 text-sm text-state-good" role="status">{{ session('success') }}</div>@endif
        @if(session('error'))<div class="mb-4 rounded-sm border border-state-bad/30 bg-state-badbg px-3 py-2 text-sm text-state-bad" role="alert">{{ session('error') }}</div>@endif
        @if($errors->any())<div class="mb-4 rounded-sm border border-state-bad/30 bg-state-badbg px-3 py-2 text-sm text-state-bad" role="alert">{{ $errors->first() }}</div>@endif
        @yield('content')
    </main>
</div>
</body>
</html>
