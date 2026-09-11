<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="robots" content="noindex,nofollow">
    <title>Sign in | AIPolicyTracker admin</title>
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
            <p class="eyebrow !text-brand-cyan">Editorial admin</p>
            <h1 class="mt-3 font-display text-3xl font-semibold text-white">Review queue, submissions, subscribers and settings</h1>
            <ul class="mt-6 space-y-3 text-sm text-white/80 max-w-md">
                <li>Every public record links an official source and carries a review status; publishing is a deliberate, logged action.</li>
                <li>Submissions from readers arrive here with the record and field they refer to.</li>
                <li>Digest subscribers are double opt-in; API keys are stored encrypted.</li>
            </ul>
        </div>
        <p class="text-xs text-white/50">{{ config('aipolicytracker.disclaimer') }}</p>
    </section>
    <main class="flex items-center justify-center px-4 py-12">
        <div class="w-full max-w-sm">
            <a href="{{ route('home') }}" class="lg:hidden inline-block no-underline mb-6"><img src="{{ asset('brand/logo-on-light.svg') }}" alt="AIPolicyTracker" class="h-9 w-auto"></a>
            <p class="eyebrow">Admin sign in</p>
            <h2 class="mt-2 font-display text-2xl font-semibold text-brand-navy">Sign in to the admin</h2>
            @if(session('status'))<p class="mt-3 rounded-sm border border-state-good/30 bg-state-goodbg px-3 py-2 text-sm text-state-good" role="status">{{ session('status') }}</p>@endif
            <form method="post" action="{{ route('login') }}" class="mt-6 space-y-4" autocomplete="on">
                @csrf
                <div>
                    <label for="email" class="label">Email address</label>
                    <input id="email" type="email" name="email" value="{{ old('email') }}" required autofocus autocomplete="username" class="input">
                    @error('email')<p class="mt-1 text-xs text-state-bad" role="alert">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label for="password" class="label">Password</label>
                    <div class="relative">
                        <input id="password" type="password" name="password" required autocomplete="current-password" class="input pr-20" data-password-input>
                        <button type="button" class="absolute inset-y-0 right-0 px-3 text-xs font-medium text-brand-muted hover:text-brand-navy" data-password-toggle aria-controls="password" aria-pressed="false">Show</button>
                    </div>
                    @error('password')<p class="mt-1 text-xs text-state-bad" role="alert">{{ $message }}</p>@enderror
                </div>
                <div class="flex items-center justify-between text-sm">
                    <label class="flex items-center gap-2 text-brand-body"><input type="checkbox" name="remember" class="rounded-sm border-brand-line text-brand-navy focus:ring-brand-navy">Remember me</label>
                    @if(Route::has('password.request'))<a href="{{ route('password.request') }}" class="text-brand-muted hover:text-brand-navy">Forgot password?</a>@endif
                </div>
                <button type="submit" class="btn-primary w-full">Sign in</button>
            </form>
            <p class="mt-6 text-xs text-brand-muted">Access is limited to addresses listed in the admin configuration. <a href="{{ route('home') }}">Back to the public site</a></p>
        </div>
    </main>
</div>
</body>
</html>
