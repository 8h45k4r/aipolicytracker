@extends('site.layouts.app')
@section('content')
<div class="container-site py-8">
    <x-site.breadcrumbs :items="$seo->breadcrumbs" />
    <p class="eyebrow mt-3">AI incidents</p>
    <h1 class="mt-2 font-display text-3xl sm:text-4xl font-semibold text-brand-navy max-w-[24ch]">What has actually gone wrong with AI, in numbers</h1>
    <p class="mt-4 max-w-[64ch] text-brand-body leading-7">A weekly-refreshed summary of the AI Incident Database, an open catalogue of harms and near-misses involving AI systems. We keep only aggregate counts and incident metadata; every row links back to the original incident record.</p>
    @if(empty($aiid['totals']))
    <x-site.empty title="Incident summary not available yet" class="mt-8">The weekly refresh has not produced a summary yet.</x-site.empty>
    @else
    <dl class="mt-8 grid gap-4 sm:grid-cols-3 text-sm max-w-2xl">
        <div class="rule pt-2"><dt class="meta">Incidents recorded</dt><dd class="font-mono tabular-nums text-2xl text-brand-navy">{{ number_format($aiid['totals']['incidents']) }}</dd></div>
        <div class="rule pt-2"><dt class="meta">Classified by MIT risk domain</dt><dd class="font-mono tabular-nums text-2xl text-brand-navy">{{ number_format($aiid['totals']['classified_mit']) }}</dd></div>
        <div class="rule pt-2"><dt class="meta">Snapshot</dt><dd class="font-mono tabular-nums text-2xl text-brand-navy">{{ $aiid['snapshot_date'] }}</dd></div>
    </dl>

    <p class="mt-4 text-sm"><a href="{{ route('risk.incidents.browse') }}" class="btn-primary">Browse and export all incidents</a></p>
    <div class="mt-10 grid gap-8 lg:grid-cols-2">
        <x-site.bar-chart :series="collect($aiid['incidents_per_year'])->filter(fn ($v, $y) => $y >= 2012)" title="Incidents per year (incident date)" note="Current year is partial" />
        <x-site.bar-chart :series="collect($aiid['by_mit_domain'])->mapWithKeys(fn ($v, $k) => [\Illuminate\Support\Str::limit(preg_replace('/^(AI system safety).*/', '$1…', $k), 26) => $v])" title="Incidents by MIT risk domain" />
    </div>

    @if(!empty($aiid['domain_by_year']))
    <x-site.stacked-chart class="mt-8" :series="collect($aiid['domain_by_year'])->filter(fn ($v, $y) => $y >= 2018)->all()" :keys="array_keys($aiid['by_mit_domain'])" title="Incidents per year by MIT risk domain" note="Classified incidents only" />
    @endif
    <div class="mt-10 grid gap-10 lg:grid-cols-12">
        <section class="lg:col-span-5" aria-labelledby="sector-heading">
            <div class="rule-strong pt-3"><h2 id="sector-heading" class="section-title">Sector of deployment</h2></div>
            <table class="mt-2 w-full text-sm"><caption class="sr-only">Incidents by sector of deployment (CSET taxonomy)</caption>
                <tbody class="divide-y divide-brand-line">@foreach($aiid['by_sector'] as $sector => $n)<tr><td class="py-2 pr-3 text-brand-body">{{ ucfirst($sector) }}</td><td class="py-2 font-mono tabular-nums text-right text-brand-navy">{{ $n }}</td></tr>@endforeach</tbody></table>
            <p class="meta mt-2">Sector coverage comes from the CSET classification and is partial.</p>
        </section>
        <section class="lg:col-span-3" aria-labelledby="country-heading">
            <div class="rule-strong pt-3"><h2 id="country-heading" class="section-title">Country</h2></div>
            <table class="mt-2 w-full text-sm"><caption class="sr-only">Incidents by country code</caption>
                <tbody class="divide-y divide-brand-line">@foreach(array_slice($aiid['by_country'], 0, 12, true) as $cc => $n)<tr><td class="py-2 pr-3 font-mono text-brand-body">{{ $cc }}</td><td class="py-2 font-mono tabular-nums text-right text-brand-navy">{{ $n }}</td></tr>@endforeach</tbody></table>
            <p class="meta mt-2">{{ number_format($aiid['totals']['with_country']) }} incidents carry a country code.</p>
        </section>
        <section class="lg:col-span-4" aria-labelledby="harm-heading">
            <div class="rule-strong pt-3"><h2 id="harm-heading" class="section-title">Harm level</h2></div>
            <table class="mt-2 w-full text-sm"><caption class="sr-only">Incidents by assessed AI harm level</caption>
                <tbody class="divide-y divide-brand-line">@foreach($aiid['by_harm_level'] as $level => $n)<tr><td class="py-2 pr-3 text-brand-body">{{ ucfirst($level) }}</td><td class="py-2 font-mono tabular-nums text-right text-brand-navy">{{ $n }}</td></tr>@endforeach</tbody></table>
        </section>
    </div>

    <section class="mt-12" aria-labelledby="latest-heading">
        <div class="rule-strong pt-3"><h2 id="latest-heading" class="section-title">Latest recorded incidents</h2></div>
        <ol class="mt-2 divide-y divide-brand-line">
            @foreach($aiid['latest'] as $i)
            <li class="py-3 grid gap-1 sm:grid-cols-12 sm:gap-4 text-sm">
                <time class="datestamp sm:col-span-2" datetime="{{ $i['date'] }}">{{ $i['date'] }}</time>
                <div class="sm:col-span-8"><a href="https://incidentdatabase.ai/cite/{{ $i['id'] }}" rel="noopener" class="text-brand-navy no-underline hover:underline">{{ $i['title'] }}</a></div>
                <div class="sm:col-span-2 meta">{{ $i['domain'] ?: '—' }}@if($i['countries']) · {{ implode(', ', $i['countries']) }}@endif</div>
            </li>
            @endforeach
        </ol>
    </section>
    @endif

    <x-site.attribution class="mt-12" :name="$aiid['source'] ?? 'AI Incident Database'" :url="$aiid['source_url'] ?? 'https://incidentdatabase.ai/'" :license="$aiid['license'] ?? 'CC BY-SA 4.0'" :licenseUrl="$aiid['license_url'] ?? 'https://creativecommons.org/licenses/by-sa/4.0/'" :citation="$aiid['citation'] ?? null" :date="$aiid['snapshot_date'] ?? null" note="Incident titles and identifiers are reproduced under CC BY-SA 4.0; report texts are not. Derived aggregates on this page are shared under the same licence." />
    <x-site.disclaimer class="mt-6" />
</div>
@endsection
