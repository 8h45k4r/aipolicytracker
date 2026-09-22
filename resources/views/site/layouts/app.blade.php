{{--
    Questions for this page come from config/faq.php, keyed by route name, and are attached
    before <head> renders because the FAQPage block goes there while the visible section goes
    in <main>. A controller that has already called withFaq() wins, so a page with bespoke
    questions is left alone.
--}}
@php($seo->faqItems() ?: $seo->withFaq(\App\Support\Faq::for(request()->route()?->getName() ?? '')))
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $seo->fullTitle() }}</title>
    <meta name="description" content="{{ $seo->description }}">
    <link rel="canonical" href="{{ $seo->canonical }}">
    @foreach($seo->alternates as $alt)<link rel="alternate" type="{{ $alt['type'] }}" href="{{ $alt['url'] }}">
    @endforeach<meta name="robots" content="{{ $seo->robots }}">
    <link rel="license" href="{{ config('aipolicytracker.data_license_url') }}">
    <meta property="og:site_name" content="{{ config('aipolicytracker.site_name') }}">
    <meta property="og:locale" content="en_US">
    <meta property="og:type" content="{{ $seo->ogType }}">
    <meta property="og:title" content="{{ $seo->title }}">
    <meta property="og:description" content="{{ $seo->description }}">
    <meta property="og:url" content="{{ $seo->canonical }}">
    {{-- Cards are drawn at a fixed size, so the dimensions are always known and
         always declared: a platform that knows them reserves the right box before
         the image loads instead of reflowing the preview. --}}
    <meta property="og:image" content="{{ $seo->socialImage() }}">
    <meta property="og:image:alt" content="{{ $seo->title }} — {{ config('aipolicytracker.site_name') }}">
    <meta property="og:image:type" content="image/png">
    <meta property="og:image:width" content="{{ config('social.width', config('aipolicytracker.default_og_image_width')) }}">
    <meta property="og:image:height" content="{{ config('social.height', config('aipolicytracker.default_og_image_height')) }}">
    <meta name="twitter:card" content="summary_large_image">
    @if(config('aipolicytracker.x_handle'))<meta name="twitter:site" content="{{ config('aipolicytracker.x_handle') }}">@endif
    <meta name="twitter:title" content="{{ $seo->title }}">
    <meta name="twitter:description" content="{{ $seo->description }}">
    <meta name="twitter:image" content="{{ $seo->socialImage() }}">
    <meta name="twitter:image:alt" content="{{ $seo->title }} — {{ config('aipolicytracker.site_name') }}">
    @if($seo->modified)<meta property="article:modified_time" content="{{ $seo->modified->format(DATE_ATOM) }}">@endif
    @if($seo->feedUrl)<link rel="alternate" type="application/rss+xml" title="AI policy changes" href="{{ $seo->feedUrl }}">@endif
    @if(config('aipolicytracker.google_site_verification'))<meta name="google-site-verification" content="{{ config('aipolicytracker.google_site_verification') }}">@endif
    @if(config('aipolicytracker.bing_site_verification'))<meta name="msvalidate.01" content="{{ config('aipolicytracker.bing_site_verification') }}">@endif
    <link rel="icon" href="{{ asset('favicon.ico') }}" sizes="32x32">
    <link rel="icon" type="image/svg+xml" href="{{ asset('brand/mark.svg') }}">
    <link rel="icon" type="image/png" sizes="32x32" href="{{ asset('favicon-32x32.png') }}">
    <link rel="icon" type="image/png" sizes="192x192" href="{{ asset('icon-192.png') }}">
    <link rel="apple-touch-icon" sizes="180x180" href="{{ asset('apple-touch-icon.png') }}">
    <link rel="manifest" href="{{ asset('site.webmanifest') }}">
    <meta name="theme-color" content="#002147">
    {{-- Font files are fetched in anonymous CORS mode, so the early connection
         has to be opened the same way or the browser opens a second one. --}}
    <link rel="preconnect" href="https://fonts.bunny.net" crossorigin>
    <link href="https://fonts.bunny.net/css?family=space-grotesk:400,500,600,700|space-mono:400,700&display=swap" rel="stylesheet">
    {{-- The shared Organization and WebSite nodes are emitted on every page, not
         only the homepage, so the `@id` references on a record page resolve when
         that page is fetched on its own — which is how an answer engine reads it. --}}
    @foreach($seo->jsonLdBlocks() as $schema)
    <script type="application/ld+json">{!! json_encode($schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) !!}</script>
    @endforeach
    @vite(['resources/css/public.css', 'resources/js/public.js'])
    @if(config('aipolicytracker.google_analytics_id') || config('aipolicytracker.cloudflare_analytics_token'))
    <script nonce="{{ Illuminate\Support\Facades\Vite::cspNonce() }}">window.APT_ANALYTICS = @json(['gaId' => config('aipolicytracker.google_analytics_id'), 'cfToken' => config('aipolicytracker.cloudflare_analytics_token'), 'requireConsent' => (bool) config('aipolicytracker.analytics_require_consent')]);</script>
    @endif
</head>
<body class="min-h-screen flex flex-col" @isset($pageTrack) data-page-track="{{ $pageTrack }}" @endisset>
<a href="#main" class="skip-link">Skip to content</a>

<header class="border-b border-brand-line bg-white">
    <div class="container-site">
        <div class="flex items-center justify-between gap-6">
            <a href="{{ route('home') }}" class="flex items-center py-3 no-underline shrink-0" aria-label="AIPolicyTracker home">
                <img src="{{ asset('brand/logo-on-light.svg') }}" alt="AIPolicyTracker" width="163" height="50" class="h-10 w-auto" decoding="async">
            </a>
            <x-site.nav />
            <div class="flex items-center gap-2">
                <a href="{{ route('saved') }}" class="hidden sm:inline-flex btn-secondary !min-h-[38px] !py-1.5 gap-1" data-track="header_saved_click" aria-label="Saved records">Saved <span class="rounded-sm bg-brand-navy px-1.5 text-[11px] font-semibold leading-5 text-white" data-saved-count hidden></span></a>
                <a href="{{ route('subscribe.show') }}" class="hidden sm:inline-flex btn-primary !min-h-[38px] !py-1.5" data-track="header_subscribe_click">Subscribe</a>
                <details class="relative lg:hidden">
                    <summary class="btn-secondary !min-h-[38px] !py-1.5 list-none" aria-label="Open menu">Menu</summary>
                    <x-site.nav-mobile />
                </details>
            </div>
        </div>
    </div>
</header>

@if(session('success'))
<div class="bg-brand-paper border-b border-brand-line" role="status"><div class="container-site py-3 text-sm text-brand-navy">{{ session('success') }}</div></div>
@endif

<main id="main" class="flex-1" tabindex="-1">
    @yield('content')
    @if($seo->faqItems())
        {{-- Rendered here rather than per view, so every page that declares questions shows
             them in the same place and the markup always matches what a visitor can read. --}}
        <div class="container-site pb-14"><x-site.faq :items="$seo->faqItems()" /></div>
    @endif
</main>

<footer class="mt-20 bg-brand-ink text-white/80">
    <div class="container-site py-12 grid gap-10 md:grid-cols-12 text-sm">
        <div class="md:col-span-4">
            <img src="{{ asset('brand/logo-on-dark.svg') }}" alt="AIPolicyTracker" width="163" height="50" class="h-10 w-auto" loading="lazy" decoding="async">
            <p class="mt-4 max-w-sm text-white/80">{{ config('aipolicytracker.positioning') }}</p>
            <p class="mt-4 max-w-sm text-xs leading-5 text-white/60">{{ config('aipolicytracker.disclaimer') }}</p>
            <ul class="mt-5 flex items-center gap-3" aria-label="Follow AIPolicyTracker">
                @foreach(config('aipolicytracker.social', []) as $social)
                <li><a href="{{ $social['url'] }}" rel="me noopener" class="block rounded-sm ring-1 ring-white/20 hover:ring-white/60" data-track="social_click" data-track-label="{{ $social['key'] }}" title="{{ $social['label'] }}"><img src="{{ asset('brand/social/'.$social['key'].'.svg') }}" alt="{{ $social['label'] }}" width="32" height="32" class="h-8 w-8 rounded-sm" loading="lazy"></a></li>
                @endforeach
            </ul>
        </div>
        <div class="md:col-span-2">
            <p class="eyebrow !text-brand-cyan">Explore</p>
            <ul class="mt-3 space-y-2">
                <li><a class="text-white/85 hover:text-white no-underline" href="{{ route('policies.index') }}">Policies</a></li>
                <li><a class="text-white/85 hover:text-white no-underline" href="{{ route('jurisdictions.index') }}">Jurisdictions</a></li>
                <li><a class="text-white/85 hover:text-white no-underline" href="{{ route('obligations.index') }}">Obligations</a></li>
                <li><a class="text-white/85 hover:text-white no-underline" href="{{ route('compare.index') }}">Compare</a></li>
                <li><a class="text-white/85 hover:text-white no-underline" href="{{ route('frameworks.index') }}">Frameworks</a></li>
                <li><a class="text-white/85 hover:text-white no-underline" href="{{ route('changes.index') }}">Change log</a> <span class="text-white/40">·</span> <a class="text-white/85 hover:text-white no-underline" href="{{ route('changes.feed') }}">RSS</a> <span class="text-white/40">·</span> <a class="text-white/85 hover:text-white no-underline" href="{{ route('calendar') }}">Calendar</a></li>
                <li><a class="text-white/85 hover:text-white no-underline" href="{{ route('risk.index') }}">AI risk</a> <span class="text-white/40">·</span> <a class="text-white/85 hover:text-white no-underline" href="{{ route('risk.incidents') }}">Incidents</a></li>
                <li><a class="text-white/85 hover:text-white no-underline" href="{{ route('guides.index') }}">Guides</a></li>
                <li><a class="text-white/85 hover:text-white no-underline" href="{{ route('subscribe.show') }}">Weekly digest</a> <span class="text-white/40">·</span> <a class="text-white/85 hover:text-white no-underline" href="{{ route('saved') }}">Saved</a></li>
            </ul>
        </div>
        <div class="md:col-span-2">
            <p class="eyebrow !text-brand-cyan">Project</p>
            <ul class="mt-3 space-y-2">
                <li><a class="text-white/85 hover:text-white no-underline" href="{{ route('open-data') }}">Open data and API</a></li>
                <li><a class="text-white/85 hover:text-white no-underline" href="{{ route('methodology') }}">Methodology</a> <span class="text-white/40">·</span> <a class="text-white/85 hover:text-white no-underline" href="{{ route('verification') }}">Verification</a></li>
                <li><a class="text-white/85 hover:text-white no-underline" href="{{ route('coverage') }}">Coverage</a> <span class="text-white/40">·</span> <a class="text-white/85 hover:text-white no-underline" href="{{ route('gaps') }}">Open queue</a> <span class="text-white/40">·</span> <a class="text-white/85 hover:text-white no-underline" href="{{ route('reviewers') }}">Reviewers</a></li>
                <li><a class="text-white/85 hover:text-white no-underline" href="{{ route('about') }}">About</a></li>
                <li><a class="text-white/85 hover:text-white no-underline" href="{{ route('contribute') }}">Contribute</a> <span class="text-white/40">·</span> <a class="text-white/85 hover:text-white no-underline" href="{{ route('corrections') }}">Corrections log</a></li>
                <li><a class="text-white/85 hover:text-white no-underline" href="{{ config('aipolicytracker.github_url') }}" rel="noopener" data-track="github_click">GitHub repository</a></li>
                @if(config('aipolicytracker.contact_email'))<li><a class="text-white/85 hover:text-white no-underline" href="mailto:{{ config('aipolicytracker.contact_email') }}" data-track="contact_click">{{ config('aipolicytracker.contact_email') }}</a></li>@endif
            </ul>
        </div>
        <div class="md:col-span-4">
            <p class="eyebrow !text-brand-cyan">From policy to practice</p>
            <p class="mt-3 text-white/80">AIPolicyTracker is the regulatory intelligence layer for AI governance. <a href="{{ config('aipolicytracker.certifyi_url') }}" rel="noopener" class="text-white underline decoration-white/40 hover:decoration-white" data-track="certifyi_click">Certifyi</a> is a separate platform for teams that need to turn obligations into owned tasks, evidence and audit trails.</p>
        </div>
    </div>
    <div class="border-t border-white/10">
        <div class="container-site py-5 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2 text-xs text-white/55">
            <p>© {{ date('Y') }} AIPolicyTracker · Code Apache-2.0 · Data {{ config('aipolicytracker.data_license') }}
                · <a class="text-white/70 hover:text-white no-underline" href="{{ config('aipolicytracker.links.privacy_policy') ?: route('privacy') }}">Privacy</a>
                · <a class="text-white/70 hover:text-white no-underline" href="{{ config('aipolicytracker.links.terms_of_use') ?: route('terms') }}">Terms</a>
            </p>
            <p>Built by <a class="text-white/70 hover:text-white no-underline" href="https://certifyi.ai" rel="noopener">Dignep Group Pvt. Ltd.</a> · Powering AI governance for regulated industries
                @foreach(config('aipolicytracker.maintainers', []) as $m)<br>Maintained by <a class="text-white/70 hover:text-white no-underline" href="{{ $m['url'] }}" rel="me noopener">{{ $m['name'] }}</a>, {{ strtolower($m['role']) }}@foreach($m['same_as'] ?? [] as $link) · <a class="text-white/70 hover:text-white no-underline" href="{{ $link }}" rel="me noopener">{{ str_contains($link, 'linkedin') ? 'LinkedIn' : (str_contains($link, 'x.com') ? 'X' : 'GitHub') }}</a>@endforeach @endforeach</p>
        </div>
    </div>
</footer>

@if(config('aipolicytracker.google_analytics_id') || config('aipolicytracker.cloudflare_analytics_token'))
<div id="consent-banner" hidden class="fixed inset-x-0 bottom-0 z-50 border-t border-brand-line bg-white p-4 shadow-lg" role="dialog" aria-label="Analytics consent">
    <div class="mx-auto max-w-7xl flex flex-col sm:flex-row items-start sm:items-center justify-between gap-3 text-sm">
        <p class="text-brand-body">We use privacy-conscious analytics to understand which policies and tools are useful. No advertising. You can decline.</p>
        <div class="flex gap-2"><button type="button" class="btn-secondary" data-consent="no">Decline</button><button type="button" class="btn-primary" data-consent="yes">Allow analytics</button></div>
    </div>
</div>
@endif
@stack('after-body')
</body>
</html>
