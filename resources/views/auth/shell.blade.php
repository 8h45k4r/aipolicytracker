<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="robots" content="noindex,nofollow">
    <title>{{ $title }} | AIPolicyTracker</title>
    <link rel="icon" type="image/svg+xml" href="{{ asset('brand/mark.svg') }}">
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=space-grotesk:400,500,600,700|space-mono:400,700&display=swap" rel="stylesheet">
    @vite(['resources/css/public.css', 'resources/js/public.js'])
</head>
<body class="min-h-screen bg-brand-paper">
<div class="min-h-screen lg:grid lg:grid-cols-2">
    <section class="hidden lg:flex flex-col justify-between bg-brand-ink text-white/85 p-10">
        <a href="{{ route('home') }}" class="no-underline"><img src="{{ asset('brand/logo-on-dark.svg') }}" alt="AIPolicyTracker" class="h-10 w-auto"></a>
        <div>
            <p class="eyebrow !text-brand-cyan">{{ $eyebrow ?? 'Account' }}</p>
            <h1 class="mt-3 font-display text-3xl font-semibold text-white">{{ $panelTitle ?? 'Source-backed AI policy intelligence' }}</h1>
            <p class="mt-4 text-sm text-white/80 max-w-md">{{ $panelText ?? 'Every record links an official source and shows its review status. Your account gives you free templates, saved records and policy-change updates.' }}</p>
        </div>
        <p class="text-xs text-white/50">{{ config('aipolicytracker.disclaimer') }}</p>
    </section>
    <main class="flex items-center justify-center px-4 py-12">
        <div class="w-full max-w-sm">
            <a href="{{ route('home') }}" class="lg:hidden inline-block no-underline mb-6"><img src="{{ asset('brand/logo-on-light.svg') }}" alt="AIPolicyTracker" class="h-9 w-auto"></a>
            <p class="eyebrow">{{ $eyebrow ?? 'Account' }}</p>
            <h2 class="mt-2 font-display text-2xl font-semibold text-brand-navy">{{ $title }}</h2>
            @if(session('status'))<p class="mt-3 rounded-sm border border-state-good/30 bg-state-goodbg px-3 py-2 text-sm text-state-good" role="status">{{ session('status') === 'verification-link-sent' ? 'A new verification link has been sent to your email address.' : (session('status') === 'passwords.sent' ? 'We emailed you a password reset link.' : session('status')) }}</p>@endif
            @if($errors->any())<div class="mt-3 rounded-sm border border-state-bad/30 bg-state-badbg p-3 text-sm text-state-bad" role="alert"><ul class="list-disc pl-5">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>@endif
            {{ $slot }}
            <p class="mt-6 text-xs text-brand-muted"><a href="{{ route('home') }}">Back to the site</a>@auth · <a href="{{ route('profile.edit') }}">Your account</a>@else · <a href="{{ route('login') }}">Sign in</a>@endauth</p>
        </div>
    </main>
</div>
</body>
</html>
