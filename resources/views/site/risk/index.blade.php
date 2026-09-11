@extends('site.layouts.app')
@section('content')
<div class="container-site py-8">
    <x-site.breadcrumbs :items="$seo->breadcrumbs" />
    <p class="eyebrow mt-3">AI risk</p>
    <h1 class="mt-2 font-display text-3xl sm:text-4xl font-semibold text-brand-navy max-w-[24ch]">Seven domains of AI risk, and the policies that answer them</h1>
    <p class="mt-4 max-w-[64ch] text-brand-body leading-7">Policy is easier to read when it is anchored to concrete harms. This section uses the Domain Taxonomy from the MIT AI Risk Repository as a shared vocabulary, shows how often each domain appears in real-world incidents recorded by the AI Incident Database, and links to the instruments in this tracker that address related use cases. The mapping from risk domain to policy use case is our own editorial judgement and is labelled as such.</p>

    <div class="mt-10 grid gap-10 lg:grid-cols-12">
        <section class="lg:col-span-7" aria-labelledby="domains-heading">
            <div class="rule-strong pt-3"><h2 id="domains-heading" class="section-title">Risk domains</h2></div>
            <ol class="mt-2 divide-y divide-brand-line">
                @foreach($mit['domains'] ?? [] as $d)
                @php($count = $aiid['by_mit_domain'][$d['aiid_domain_label']] ?? null)
                <li class="py-4 grid gap-2 sm:grid-cols-12 sm:gap-6">
                    <div class="sm:col-span-8">
                        <a href="{{ route('risk.domain', $d['id']) }}" class="font-display text-lg text-brand-navy no-underline hover:underline">{{ $d['id'] }}. {{ $d['name'] }}</a>
                        <p class="meta mt-1">{{ count($d['subdomains']) }} {{ \Illuminate\Support\Str::plural('subdomain', count($d['subdomains'])) }}: {{ collect($d['subdomains'])->pluck('name')->map(fn ($n) => \Illuminate\Support\Str::limit($n, 48))->join('; ') }}</p>
                    </div>
                    <div class="sm:col-span-4 sm:text-right text-sm">
                        <span class="font-mono tabular-nums text-brand-navy text-lg">{{ $count !== null ? number_format($count) : '—' }}</span>
                        <span class="meta block">recorded incidents</span>
                    </div>
                </li>
                @endforeach
            </ol>
        </section>
        <aside class="lg:col-span-5" aria-labelledby="incidents-heading">
            <div class="rule-strong pt-3"><h2 id="incidents-heading" class="section-title">Incidents by year</h2></div>
            @if(!empty($aiid['incidents_per_year']))
            <x-site.bar-chart :series="collect($aiid['incidents_per_year'])->filter(fn ($v, $y) => $y >= 2016)" title="AI incidents recorded per year" class="mt-3" :note="'Snapshot '.($aiid['snapshot_date'] ?? '')" />
            <p class="mt-3 text-sm"><a href="{{ route('risk.incidents') }}">Full incident summary: domains, sectors, countries and latest records</a></p>
            @else
            <x-site.empty title="Incident summary not available yet" class="mt-3">The weekly refresh has not run. See the methodology for how external data is updated.</x-site.empty>
            @endif
            <p class="mt-6 meta">Counts reflect what has been reported and classified; they measure attention and reporting, not the true frequency or severity of harm.</p>
        </aside>
    </div>

    <x-site.attribution class="mt-12" :name="$mit['source'] ?? 'MIT AI Risk Repository'" :url="$mit['source_url'] ?? 'https://airisk.mit.edu/'" :license="$mit['license'] ?? 'CC BY 4.0'" :licenseUrl="$mit['license_url'] ?? 'https://creativecommons.org/licenses/by/4.0/'" :citation="$mit['citation'] ?? null" :note="$mit['changes_note'] ?? null" />
    <x-site.attribution class="mt-3" :name="$aiid['source'] ?? 'AI Incident Database'" :url="$aiid['source_url'] ?? 'https://incidentdatabase.ai/'" :license="$aiid['license'] ?? 'CC BY-SA 4.0'" :licenseUrl="$aiid['license_url'] ?? 'https://creativecommons.org/licenses/by-sa/4.0/'" :citation="$aiid['citation'] ?? null" :date="$aiid['snapshot_date'] ?? null" note="Aggregates only; report texts are not reproduced." />
    <x-site.disclaimer class="mt-6" />
</div>
@endsection
