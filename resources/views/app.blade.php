<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="white">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <!-- The Inertia shell serves legacy, authenticated and admin screens only; public content pages are server-rendered. -->
    <meta name="robots" content="noindex,follow">
    <meta name="description" content="AIPolicyTracker legacy and account area.">
    <meta property="og:site_name" content="AIPolicyTracker">
    <meta property="og:type" content="website">
    <meta property="og:title" content="AIPolicyTracker">
    <meta property="og:image" content="{{ asset('aipolicytracker-logo.jpg') }}">

    <!-- Meta icon -->
    <link rel="icon" type="image/png" href="{{ asset('favicon-16x16.png') }}">

    <title>AIPolicyTracker</title>

    <!-- Scripts -->
    @routes(nonce: Illuminate\Support\Facades\Vite::cspNonce())
    @viteReactRefresh
    @vite(['resources/js/app.jsx', "resources/js/Pages/{$page['component']}.jsx"])
    @inertiaHead

    @if (config('aipolicytracker.google_analytics_id'))
        <!-- Google tag (gtag.js) -->
        <script async src="https://www.googletagmanager.com/gtag/js?id={{ config('aipolicytracker.google_analytics_id') }}"></script>
        <script nonce="{{ Illuminate\Support\Facades\Vite::cspNonce() }}">
            window.dataLayer = window.dataLayer || [];

            function gtag() {
                dataLayer.push(arguments);
            }
            gtag('js', new Date());
            gtag('config', @json(config('aipolicytracker.google_analytics_id')));
        </script>
    @endif
</head>

<body class="Poppins" style="max-width: 1920px; margin-right: auto; margin-left: auto">
    @inertia
</body>

</html>
