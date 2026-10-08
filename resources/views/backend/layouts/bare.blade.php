<!DOCTYPE html>
<html lang="en" data-scheme="light" data-admin>
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="robots" content="noindex,nofollow">
    <title>{{ $title ?? 'Admin' }} | AIPolicyTracker admin</title>
    <link rel="icon" type="image/svg+xml" href="{{ asset('brand/mark.svg') }}">
    @vite(['resources/css/public.css', 'resources/css/admin.css'])
</head>
{{-- The admin without its navigation: for a page shown to a session that has not passed
     the admin's gates (an expired session, a rate-limited sign-in check). --}}
<body class="min-h-screen bg-brand-paper">
<header class="bg-brand-ink px-4 py-4 sm:px-8"><a href="{{ url('/') }}" class="inline-block no-underline"><img src="{{ asset('brand/logo-on-dark.svg') }}" alt="AIPolicyTracker" class="h-8 w-auto"></a></header>
<main id="main" class="px-4 py-10 sm:px-8 sm:py-16">
    @yield('content')
</main>
</body>
</html>
