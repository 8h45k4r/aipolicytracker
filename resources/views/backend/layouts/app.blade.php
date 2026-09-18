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
        @php($nav = [
            ['backend.admin.dashboard', 'Dashboard'],
            ['backend.review.index', 'Review queue'],
            ['backend.admin.submissions', 'Submissions and feedback'],
            ['backend.admin.subscribers', 'Subscribers'],
            ['backend.admin.external', 'External data'],
            ['backend.admin.downloads', 'Guides and downloads'],
            ['backend.admin.tools.index', 'Tool library'],
            ['backend.admin.billing.index', 'Billing'],
            ['backend.admin.settings', 'Settings and API keys'],
            ['backend.admin.audit', 'Audit log'],
            ['admin.two-factor.recovery', 'Authenticator'],
        ])
        <nav class="mt-4 space-y-1 text-sm" aria-label="Admin">
            @foreach($nav as [$r, $label])
            <a href="{{ route($r) }}" class="block rounded-sm px-3 py-2 no-underline {{ request()->routeIs($r) ? 'bg-white/10 text-white' : 'text-white/80 hover:bg-white/5 hover:text-white' }}" @if(request()->routeIs($r)) aria-current="page" @endif>{{ $label }}</a>
            @endforeach
        </nav>
        <div class="mt-8 text-xs text-white/60">
            <p>{{ auth()->user()->name }}</p>
            <p class="mt-1"><a href="{{ route('home') }}" class="text-white/80 no-underline hover:text-white">Public site</a> · <a href="{{ route('logout') }}" class="text-white/80 no-underline hover:text-white">Sign out</a></p>
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
