<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="robots" content="noindex,nofollow">
    <title>Create a free account | AIPolicyTracker</title>
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
            <p class="eyebrow !text-brand-cyan">Free account</p>
            <h1 class="mt-3 font-display text-3xl font-semibold text-white">Download free templates, save records and follow policy changes</h1>
            <ul class="mt-6 space-y-3 text-sm text-white/80 max-w-md">
                <li>AI system inventory, risk register, EU AI Act readiness and incident response templates.</li>
                <li>Every template states its version and that it is informational, not legal advice.</li>
                <li>We store your name, email and download history. Updates are opt-in and unsubscribe is one click.</li>
            </ul>
        </div>
        <p class="text-xs text-white/50">{{ config('aipolicytracker.disclaimer') }}</p>
    </section>
    <main class="flex items-center justify-center px-4 py-12">
        <div class="w-full max-w-sm">
            <a href="{{ route('home') }}" class="lg:hidden inline-block no-underline mb-6"><img src="{{ asset('brand/logo-on-light.svg') }}" alt="AIPolicyTracker" class="h-9 w-auto"></a>
            <p class="eyebrow">Create a free account</p>
            <h2 class="mt-2 font-display text-2xl font-semibold text-brand-navy">Sign up with email</h2>
            @if($errors->any())<div class="mt-3 rounded-sm border border-state-bad/30 bg-state-badbg p-3 text-sm text-state-bad" role="alert"><ul class="list-disc pl-5">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>@endif
            <form method="post" action="{{ route('register') }}" class="mt-6 space-y-4">
                @csrf
                <div><label for="name" class="label">Name</label><input id="name" name="name" value="{{ old('name') }}" required autocomplete="name" class="input"></div>
                <div><label for="email" class="label">Email address</label><input id="email" type="email" name="email" value="{{ old('email') }}" required autocomplete="email" class="input"></div>
                <div>@php($orgRequired = str_contains((string) session('url.intended'), '/guides/tools/'))<label for="organization_name" class="label">Organisation @if(! $orgRequired)<span class="text-brand-muted font-normal">(optional)</span>@endif</label><input id="organization_name" name="organization_name" value="{{ old('organization_name') }}" autocomplete="organization" class="input" @required($orgRequired) placeholder="{{ $orgRequired ? 'Company, agency, university or self' : '' }}">@error('organization_name')<p class="mt-1 text-xs text-state-bad">{{ $message }}</p>@enderror</div>
                <div><label for="password" class="label">Password</label><div class="relative"><input id="password" type="password" name="password" required autocomplete="new-password" class="input pr-20" minlength="8"><button type="button" class="absolute inset-y-0 right-0 px-3 text-xs font-medium text-brand-muted hover:text-brand-navy" data-password-toggle aria-controls="password" aria-pressed="false">Show</button></div><p class="mt-1 text-xs text-brand-muted">At least 8 characters with upper and lower case letters and a symbol.</p></div>
                <div><label for="password_confirmation" class="label">Confirm password</label><input id="password_confirmation" type="password" name="password_confirmation" required autocomplete="new-password" class="input"></div>
                <label class="flex items-start gap-2 text-sm text-brand-body"><input type="checkbox" name="terms_condition" value="1" required class="mt-1 rounded-sm border-brand-line text-brand-navy focus:ring-brand-navy"><span>I accept the <a href="{{ config('aipolicytracker.links.terms_of_use') ?: route('about') }}">terms</a> and <a href="{{ config('aipolicytracker.links.privacy_policy') ?: route('about') }}">privacy policy</a>, and understand that AIPolicyTracker provides informational resources, not legal advice.</span></label>
                <label class="flex items-start gap-2 text-sm text-brand-body"><input type="checkbox" name="marketing_consent" value="1" class="mt-1 rounded-sm border-brand-line text-brand-navy focus:ring-brand-navy" @checked(old('marketing_consent'))><span>Email me guides and updates when related AI policy requirements change (optional).</span></label>
                <button type="submit" class="btn-primary w-full">Create free account</button>
            </form>
            <p class="mt-6 text-xs text-brand-muted">Already have an account? <a href="{{ route('login') }}">Sign in</a> · <a href="{{ route('home') }}">Back to the site</a></p>
        </div>
    </main>
</div>
</body>
</html>
