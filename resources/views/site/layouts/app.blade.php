<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $seo->fullTitle() }}</title>
    <meta name="description" content="{{ $seo->description }}">
    <link rel="canonical" href="{{ $seo->canonical }}">
    <meta name="robots" content="{{ $seo->robots }}">
    <meta property="og:site_name" content="{{ config('aipolicytracker.site_name') }}">
    <meta property="og:type" content="{{ $seo->ogType }}">
    <meta property="og:title" content="{{ $seo->title }}">
    <meta property="og:description" content="{{ $seo->description }}">
    <meta property="og:url" content="{{ $seo->canonical }}">
    <meta property="og:image" content="{{ url($seo->ogImage ?? config('aipolicytracker.default_og_image')) }}">
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="{{ $seo->title }}">
    <meta name="twitter:description" content="{{ $seo->description }}">
    <meta name="twitter:image" content="{{ url($seo->ogImage ?? config('aipolicytracker.default_og_image')) }}">
    @if($seo->modified)<meta property="article:modified_time" content="{{ $seo->modified->format(DATE_ATOM) }}">@endif
    @if($seo->feedUrl)<link rel="alternate" type="application/rss+xml" title="AI policy changes" href="{{ $seo->feedUrl }}">@endif
    @if(config('aipolicytracker.google_site_verification'))<meta name="google-site-verification" content="{{ config('aipolicytracker.google_site_verification') }}">@endif
    @if(config('aipolicytracker.bing_site_verification'))<meta name="msvalidate.01" content="{{ config('aipolicytracker.bing_site_verification') }}">@endif
    <link rel="icon" type="image/png" sizes="32x32" href="{{ asset('favicon-32x32.png') }}">
    <link rel="icon" type="image/png" sizes="16x16" href="{{ asset('favicon-16x16.png') }}">
    <meta name="theme-color" content="#0f172a">
    @foreach($seo->jsonLd as $schema)
    <script type="application/ld+json">{!! json_encode($schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) !!}</script>
    @endforeach
    @vite(['resources/css/public.css', 'resources/js/public.js'])
    @if(config('aipolicytracker.google_analytics_id') || config('aipolicytracker.cloudflare_analytics_token'))
    <script>window.APT_ANALYTICS = @json(['gaId' => config('aipolicytracker.google_analytics_id'), 'cfToken' => config('aipolicytracker.cloudflare_analytics_token'), 'requireConsent' => (bool) config('aipolicytracker.analytics_require_consent')]);</script>
    @endif
</head>
<body class="min-h-screen flex flex-col" @isset($pageTrack) data-page-track="{{ $pageTrack }}" @endisset>
<a href="#main" class="skip-link">Skip to content</a>

<header class="border-b border-slate-200 bg-white">
    <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
        <div class="flex h-16 items-center justify-between gap-4">
            <a href="{{ route('home') }}" class="flex items-center gap-2 no-underline" aria-label="AIPolicyTracker home">
                <span class="inline-flex h-8 w-8 items-center justify-center rounded-md bg-slate-900 text-white font-bold text-sm" aria-hidden="true">AP</span>
                <span class="text-base font-semibold text-slate-900 tracking-tight">AIPolicyTracker</span>
            </a>
            <nav aria-label="Primary" class="hidden lg:flex items-center gap-1 text-sm">
                @foreach([['policies.index','Policies'],['jurisdictions.index','Jurisdictions'],['obligations.index','Obligations'],['compare.index','Compare'],['changes.index','Changes'],['tools.applicability','Applicability check'],['open-data','Open data']] as [$r,$label])
                <a href="{{ route($r) }}" class="rounded-md px-3 py-2 text-slate-700 hover:bg-slate-100 hover:text-slate-900 no-underline {{ request()->routeIs($r) ? 'bg-slate-100 font-medium text-slate-900' : '' }}" @if(request()->routeIs($r)) aria-current="page" @endif>{{ $label }}</a>
                @endforeach
            </nav>
            <div class="flex items-center gap-2">
                <a href="{{ route('policies.index') }}" class="hidden sm:inline-flex btn-secondary !min-h-[40px] !py-2" data-track="header_search_click">Search</a>
                <details class="relative lg:hidden">
                    <summary class="btn-secondary !min-h-[40px] !py-2 list-none" aria-label="Open menu">Menu</summary>
                    <nav aria-label="Mobile" class="absolute right-0 mt-2 w-64 rounded-lg border border-slate-200 bg-white p-2 shadow-lg z-40">
                        @foreach([['policies.index','Policies'],['jurisdictions.index','Jurisdictions'],['obligations.index','Obligations'],['compare.index','Compare'],['changes.index','Changes'],['tools.applicability','Applicability check'],['open-data','Open data'],['guides.index','Guides'],['methodology','Methodology'],['about','About'],['contribute','Contribute']] as [$r,$label])
                        <a href="{{ route($r) }}" class="block rounded-md px-3 py-2.5 text-sm text-slate-800 hover:bg-slate-100 no-underline">{{ $label }}</a>
                        @endforeach
                    </nav>
                </details>
            </div>
        </div>
    </div>
</header>

@if(session('success'))
<div class="bg-emerald-50 border-b border-emerald-200" role="status"><div class="mx-auto max-w-7xl px-4 py-3 text-sm text-emerald-900">{{ session('success') }}</div></div>
@endif

<main id="main" class="flex-1" tabindex="-1">
    @yield('content')
</main>

<footer class="mt-16 border-t border-slate-200 bg-slate-50">
    <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 py-10 grid gap-8 md:grid-cols-4 text-sm">
        <div class="md:col-span-1">
            <p class="font-semibold text-slate-900">AIPolicyTracker</p>
            <p class="mt-2 text-slate-600">{{ config('aipolicytracker.positioning') }}</p>
            <p class="mt-3 text-xs text-slate-500">{{ config('aipolicytracker.disclaimer') }}</p>
        </div>
        <div>
            <p class="font-semibold text-slate-900">Explore</p>
            <ul class="mt-2 space-y-1.5">
                <li><a class="text-slate-700 hover:text-slate-900" href="{{ route('policies.index') }}">Policies</a></li>
                <li><a class="text-slate-700 hover:text-slate-900" href="{{ route('jurisdictions.index') }}">Jurisdictions</a></li>
                <li><a class="text-slate-700 hover:text-slate-900" href="{{ route('obligations.index') }}">Obligations</a></li>
                <li><a class="text-slate-700 hover:text-slate-900" href="{{ route('compare.index') }}">Compare</a></li>
                <li><a class="text-slate-700 hover:text-slate-900" href="{{ route('changes.index') }}">Change log</a> · <a class="text-slate-700 hover:text-slate-900" href="{{ route('changes.feed') }}">RSS</a></li>
                <li><a class="text-slate-700 hover:text-slate-900" href="{{ route('guides.index') }}">Guides</a></li>
            </ul>
        </div>
        <div>
            <p class="font-semibold text-slate-900">Project</p>
            <ul class="mt-2 space-y-1.5">
                <li><a class="text-slate-700 hover:text-slate-900" href="{{ route('open-data') }}">Open data and API</a></li>
                <li><a class="text-slate-700 hover:text-slate-900" href="{{ route('methodology') }}">Methodology</a></li>
                <li><a class="text-slate-700 hover:text-slate-900" href="{{ route('about') }}">About</a></li>
                <li><a class="text-slate-700 hover:text-slate-900" href="{{ route('contribute') }}">Contribute</a></li>
                <li><a class="text-slate-700 hover:text-slate-900" href="{{ config('aipolicytracker.github_url') }}" rel="noopener" data-track="github_click">GitHub repository</a></li>
                <li><a class="text-slate-700 hover:text-slate-900" href="{{ route('llms') }}">llms.txt</a></li>
            </ul>
        </div>
        <div>
            <p class="font-semibold text-slate-900">Turn obligations into workflows</p>
            <p class="mt-2 text-slate-600">AIPolicyTracker is the open intelligence layer. <a href="{{ config('aipolicytracker.certifyi_url') }}" rel="noopener" class="text-slate-800" data-track="certifyi_click">Certifyi</a> is a separate compliance execution platform for teams that want to turn obligations into workflows.</p>
            <p class="mt-4 text-xs text-slate-500">© {{ date('Y') }} AIPolicyTracker. Code Apache-2.0. Data {{ config('aipolicytracker.data_license') }}.
                @if(config('aipolicytracker.links.privacy_policy'))· <a href="{{ config('aipolicytracker.links.privacy_policy') }}">Privacy</a>@endif
                @if(config('aipolicytracker.links.terms_of_use'))· <a href="{{ config('aipolicytracker.links.terms_of_use') }}">Terms</a>@endif
            </p>
        </div>
    </div>
</footer>

@if(config('aipolicytracker.google_analytics_id') || config('aipolicytracker.cloudflare_analytics_token'))
<div id="consent-banner" hidden class="fixed inset-x-0 bottom-0 z-50 border-t border-slate-200 bg-white p-4 shadow-lg" role="dialog" aria-label="Analytics consent">
    <div class="mx-auto max-w-7xl flex flex-col sm:flex-row items-start sm:items-center justify-between gap-3 text-sm">
        <p class="text-slate-700">We use privacy-conscious analytics to understand which policies and tools are useful. No advertising. You can decline.</p>
        <div class="flex gap-2"><button type="button" class="btn-secondary" data-consent="no">Decline</button><button type="button" class="btn-primary" data-consent="yes">Allow analytics</button></div>
    </div>
</div>
@endif
@stack('after-body')
</body>
</html>
