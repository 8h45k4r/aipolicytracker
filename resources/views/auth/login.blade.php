<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="robots" content="noindex,nofollow">
    <title>Sign in | AIPolicyTracker</title>
    <link rel="icon" type="image/svg+xml" href="{{ asset('brand/mark.svg') }}">
    @vite(['resources/css/public.css', 'resources/js/public.js'])
</head>
<body class="min-h-screen bg-brand-paper">
<div class="min-h-screen lg:grid lg:grid-cols-2">
    {{-- A fixed dark panel, not the theme token: in dark mode the token turns light and the white logo vanished. --}}
    <section class="hidden lg:flex flex-col justify-between bg-brand-navy text-white/85 p-10">
        <a href="{{ route('home') }}" class="no-underline"><img src="{{ asset('brand/logo-on-dark.svg') }}" alt="AIPolicyTracker" class="h-10 w-auto"></a>
        <div>
            <p class="eyebrow !text-brand-cyan">Your account</p>
            <p class="mt-3 font-display text-3xl font-semibold text-white">Follow the AI rules that matter to you</p>
            <ul class="mt-6 space-y-3 text-sm text-white/85 max-w-md">
                <li>Follow laws, countries and duties, and get an email when they change or a date comes due.</li>
                <li>Keep the records you saved, and export the obligations that apply to you.</li>
                <li>Editors and reviewers use this same sign-in for the review queue.</li>
            </ul>
        </div>
        <p class="text-xs text-white/70">{{ config('aipolicytracker.disclaimer') }}</p>
    </section>
    <main class="flex items-center justify-center px-4 py-12">
        <div class="w-full max-w-sm">
            <a href="{{ route('home') }}" class="lg:hidden inline-block no-underline mb-6"><img src="{{ asset('brand/logo-on-light.svg') }}" alt="AIPolicyTracker" class="h-9 w-auto"></a>
            <p class="eyebrow">Sign in</p>
            <h1 class="mt-2 font-display text-2xl font-semibold text-brand-navy">Sign in to AIPolicyTracker</h1>
            @if(session('status'))<p class="mt-3 rounded-md border border-state-good/30 bg-state-goodbg px-3 py-2 text-sm text-state-good" role="status">{{ session('status') }}</p>@endif
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
            @if(Route::has('register'))<p class="mt-6 text-sm text-brand-body">New here? <a href="{{ route('register') }}" class="font-medium">Create a free account</a></p>@endif
            <p class="mt-3 text-xs text-brand-muted"><a href="{{ route('home') }}">Back to the public site</a></p>
        </div>
    </main>
</div>
</body>
</html>
