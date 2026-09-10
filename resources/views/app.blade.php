<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="white">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <!-- Meta Tags -->
    <meta name="twitter:card" content="summary">
    <meta name="twitter:title" content="Global Artificial Intelligence (AI) Policy Tracker">
    <meta name="twitter:description"
        content="Stay informed on AI policy and governance.
Explore in-depth resources on AI ethics, legislation, compliance, and more. Join our community for discussions, workshops, and conferences. Get access to policy guidelines, standards, best practices, and strategic roadmaps to navigate the evolving landscape of AI policies. Whether you're a researcher, developer, or business leader, we provide the insights you need to understand and shape AI's future.">
    <meta name="twitter:image" content="{{ asset('aipolicytracker-logo.jpg') }}">
    <meta name="og:title" content="Global Artificial Intelligence (AI) Policy Tracker">
    <meta name="og:site_name" content="Global Artificial Intelligence (AI) Policy Tracker">
    <meta name="og:type" content="website">
    <meta name="og:description"
        content="Stay informed on AI policy and governance.
Explore in-depth resources on AI ethics, legislation, compliance, and more. Join our community for discussions, workshops, and conferences. Get access to policy guidelines, standards, best practices, and strategic roadmaps to navigate the evolving landscape of AI policies. Whether you're a researcher, developer, or business leader, we provide the insights you need to understand and shape AI's future.">
    <meta name="og:image" content="{{ asset('aipolicytracker-logo.jpg') }}">
    <meta name="description"
        content="Stay informed on AI policy and governance.
Explore in-depth resources on AI ethics, legislation, compliance, and more. Join our community for discussions, workshops, and conferences. Get access to policy guidelines, standards, best practices, and strategic roadmaps to navigate the evolving landscape of AI policies. Whether you're a researcher, developer, or business leader, we provide the insights you need to understand and shape AI's future.">
    <meta name="keywords"
        content="Stay informed on AI policy trends with our comprehensive AI Policy Tracker.
Monitor global AI regulations, analyze AI governance landscapes, and track AI ethics developments. Our user-friendly platform offers AI policy monitoring, alerts, comparison tools, and in-depth search capabilities. Whether you're an AI researcher, developer, or policymaker, our tracker provides valuable insights and resources to navigate the complex world of AI policy.">

    <!-- Meta icon -->
    <link rel="icon" type="image/png" href="{{ asset('favicon-16x16.png') }}">

    <title>Global Artificial Intelligence (AI) Policy Status</title>

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
