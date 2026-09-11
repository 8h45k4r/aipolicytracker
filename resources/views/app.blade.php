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

    <!-- Font Awesome CDN -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.6.0/css/all.min.css"
        integrity="sha512-Kc323vGBEqzTmouAECnVceyQqyqdsSiqLQISBL29aUW4U/M7pSPA/gEUZQqv1cwx4OnYxTxve5UMg5GT6L4JJg=="
        crossorigin="anonymous" referrerpolicy="no-referrer" />

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />
    <!-- Scripts -->
    @routes
    @viteReactRefresh
    @vite(['resources/js/app.jsx', "resources/js/Pages/{$page['component']}.jsx"])
    @inertiaHead

    @if (config('aipolicytracker.google_analytics_id'))
        <!-- Google tag (gtag.js) -->
        <script async src="https://www.googletagmanager.com/gtag/js?id={{ config('aipolicytracker.google_analytics_id') }}"></script>
        <script>
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

    <script src="https://cdn.jsdelivr.net/npm/flowbite@2.4.1/dist/flowbite.min.js"></script>
</body>

</html>
