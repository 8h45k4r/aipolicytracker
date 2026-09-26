@extends('site.layouts.app')
@section('content')
@php($t = $report['totals'])
@php($levels = ['in_force' => ['Binding AI law in force', 'bg-brand-navy text-white'], 'binding' => ['Binding AI law recorded', 'bg-brand-blue text-white'], 'guidance' => ['Strategy, guidance or existing law', 'bg-brand-paper text-brand-body']])
<div class="container-site py-8">
    <x-site.breadcrumbs :items="$seo->breadcrumbs" />
    <p class="eyebrow mt-2">Quarterly report{{ $report['frozen'] ? ' · frozen '.\Carbon\Carbon::parse($report['frozen_at'])->format('j M Y') : ' · live' }}</p>
    <h1 class="mt-1 text-2xl sm:text-3xl font-semibold tracking-tight text-brand-navy">The state of AI regulation, {{ $label }}</h1>

    <section class="mt-5 card-flat p-5" aria-labelledby="summary-heading">
        <h2 id="summary-heading" class="sr-only">Summary</h2>
        <p class="text-brand-navy text-base sm:text-lg leading-relaxed">{{ $answer }}</p>
        <dl class="mt-4 grid grid-cols-2 gap-4 sm:grid-cols-4">
            <x-site.stat label="Jurisdictions with records" :value="number_format($t['jurisdictions_with_records'])" :href="route('jurisdictions.index')" />
            <x-site.stat label="With binding AI law" :value="number_format($t['jurisdictions_with_binding_law'])" :note="$t['jurisdictions_with_binding_in_force'].' in force'" />
            <x-site.stat label="Instruments" :value="number_format($t['instruments'])" :note="number_format($t['binding_instruments']).' binding'" :href="route('policies.index')" />
            <x-site.stat label="Verified" :value="($t['instruments'] ? (int) round(100 * $t['verified_instruments'] / $t['instruments']) : 0).'%'" :note="number_format($t['sourced_instruments']).' sourced'" :href="route('verification')" />
        </dl>
        <p class="mt-3 text-xs text-brand-muted">Period {{ $report['period']['start'] }} to {{ $report['period']['end'] }} · computed <time datetime="{{ $report['computed_at'] }}">{{ \Carbon\Carbon::parse($report['computed_at'])->format('j M Y, H:i') }} UTC</time> · <a href="{{ route('state-of.csv', $quarter) }}" data-track="state_of_csv">CSV</a> · method v{{ $report['version'] }}</p>
    </section>

    <div class="mt-8 grid gap-10 lg:grid-cols-3">
        <div class="lg:col-span-2 min-w-0">
            <section aria-labelledby="map-heading">
                <h2 id="map-heading" class="section-title">Where AI is regulated</h2>
                <p class="mt-1 text-xs text-brand-muted">A tile per jurisdiction with at least one record, grouped by region and shaded by the strongest instrument on record. The list below the tiles carries the same facts.</p>
                <div class="mt-3 flex flex-wrap gap-3 text-xs" aria-hidden="true">@foreach($levels as $key => [$name, $cls])<span class="inline-flex items-center gap-1.5"><span class="inline-block h-3 w-3 rounded-sm {{ $cls }}"></span>{{ $name }}</span>@endforeach</div>
                @foreach(collect($report['tiles'])->groupBy('region')->sortKeys() as $region => $tiles)
                <h3 class="mt-4 text-sm font-semibold text-brand-navy">{{ $region }} <span class="font-normal text-brand-muted">· {{ $tiles->count() }}</span>@if(\App\Services\Report\StateOfAiRegulation::regionHubUrl($region)) · <a href="{{ \App\Services\Report\StateOfAiRegulation::regionHubUrl($region) }}" class="font-normal text-brand-blue">regional hub</a>@endif</h3>
                <ul class="mt-1.5 flex flex-wrap gap-1" aria-label="{{ $region }}: jurisdictions by level of AI law">
                    @foreach($tiles as $tile)<li><a href="{{ $tile['url'] }}" class="inline-block rounded-sm px-2 py-1 text-xs no-underline {{ $levels[$tile['level']][1] }}" title="{{ $tile['name'] }}: {{ $levels[$tile['level']][0] }}, {{ $tile['instruments'] }} {{ \Illuminate\Support\Str::plural('instrument', $tile['instruments']) }}">{{ $tile['short'] }}<span class="sr-only">: {{ $levels[$tile['level']][0] }}, {{ $tile['instruments'] }} {{ \Illuminate\Support\Str::plural('instrument', $tile['instruments']) }}</span></a></li>@endforeach
                </ul>
                @endforeach
                <details class="mt-4 text-sm"><summary class="cursor-pointer text-brand-blue">The same facts as a table</summary>
                    <div class="table-wrap mt-2"><table><caption class="sr-only">Regions by level of AI law</caption><thead><tr><th scope="col">Region</th><th scope="col">Jurisdictions covered</th><th scope="col">With records</th><th scope="col">Binding AI law</th><th scope="col">Binding in force</th></tr></thead><tbody>@foreach($report['by_region'] as $region => $row)<tr><th scope="row" class="font-medium">{{ $region }}</th><td>{{ $row['jurisdictions'] }}</td><td>{{ $row['with_records'] }}</td><td>{{ $row['binding'] }}</td><td>{{ $row['in_force'] }}</td></tr>@endforeach</tbody></table></div>
                </details>
            </section>

            <section aria-labelledby="types-heading" class="mt-8">
                <h2 id="types-heading" class="section-title">Instruments by type and status</h2>
                <div class="mt-3 grid gap-4 md:grid-cols-2">
                    <x-site.bar-chart :series="collect($report['by_type'])->mapWithKeys(fn ($n, $k) => [ucfirst(str_replace('_', ' ', $k)) => $n])->all()" title="By instrument type" :export="false" />
                    <x-site.bar-chart :series="collect($report['by_status'])->mapWithKeys(fn ($n, $k) => [ucfirst(str_replace('_', ' ', $k)) => $n])->all()" title="By status" :export="false" />
                </div>
            </section>

            <section aria-labelledby="changes-heading" class="mt-8">
                <h2 id="changes-heading" class="section-title">What changed in {{ $label }}</h2>
                <p class="mt-1 text-sm text-brand-body">{{ $t['changes_in_quarter'] }} changes recorded, {{ $t['urgent_changes_in_quarter'] }} urgent.@if($report['changes_by_jurisdiction']) Most active: {{ collect($report['changes_by_jurisdiction'])->take(5)->map(fn ($n, $j) => $j.' ('.$n.')')->join(', ') }}.@endif</p>
                <ul class="mt-3 divide-y divide-brand-line border-y border-brand-line text-sm">@forelse($report['top_changes'] as $c)<li class="py-2"><time datetime="{{ $c['occurred_on'] }}" class="font-mono text-xs text-brand-navy">{{ $c['occurred_on'] }}</time> · <a href="{{ route('changes.show', $c['slug']) }}" class="text-brand-navy hover:underline">{{ $c['title'] }}</a> <span class="meta">{{ $c['jurisdiction'] }} · {{ $c['impact_level'] }}</span></li>@empty<li class="py-2 text-brand-muted">No change recorded in this quarter.</li>@endforelse</ul>
                <p class="mt-2 text-sm"><a href="{{ route('updates.month', substr($report['period']['start'], 0, 7)) }}" class="text-brand-blue hover:underline">Updates hub for this period →</a></p>
            </section>

            <section aria-labelledby="method-heading" class="mt-8">
                <h2 id="method-heading" class="section-title">Method</h2>
                <ul class="mt-2 list-disc space-y-1 pl-5 text-sm text-brand-body">
                    <li>Every figure is computed from the published records at the moment shown; nothing is estimated. "Binding" is the record's own flag; "in force" is status in force or partially applicable.</li>
                    <li>A quarter's figures are frozen as a snapshot when it closes (version {{ $report['version'] }}); a frozen report never changes, even when records are corrected afterwards. The current quarter is live and says so at the top.</li>
                    <li>Coverage is what this site has recorded, not what exists: a jurisdiction with no record is absent from the tiles, and the <a href="{{ route('gaps') }}">open gaps</a> page lists what is missing.</li>
                    <li>Verified means a named reviewer confirmed the record against its official source on a stated date; see the <a href="{{ route('verification') }}">verification</a> and <a href="{{ route('methodology') }}">methodology</a> pages.</li>
                </ul>
            </section>
            <x-site.faq :items="$seo->faqItems()" />
            <x-site.disclaimer class="mt-8" />
        </div>
        <aside class="space-y-6">
            <section aria-labelledby="next-heading"><h2 id="next-heading" class="section-title">Due next quarter</h2>
                <ul class="mt-2 space-y-2 text-sm">@forelse($report['upcoming'] as $d)<li><time datetime="{{ $d['due_on'] }}" class="font-medium text-brand-navy">{{ \Carbon\Carbon::parse($d['due_on'])->format('j M Y') }}</time> · <a href="{{ $d['policy_url'] }}" class="text-brand-body hover:underline">{{ $d['title'] }}</a> <span class="meta">{{ $d['jurisdiction'] }}</span></li>@empty<li class="text-brand-muted">No dated deadline falls in the next quarter.</li>@endforelse</ul>
                <p class="mt-2 text-sm"><a href="{{ route('calendar') }}" class="text-brand-blue hover:underline">Deadline calendar →</a> · <a href="{{ route('deadlines.engine') }}" class="text-brand-blue hover:underline">Which applies to you?</a></p>
            </section>
            <section aria-labelledby="q-heading"><h2 id="q-heading" class="section-title">Past quarters</h2>
                @if($frozen->isEmpty())<p class="mt-2 text-sm text-brand-muted">No quarter has been frozen yet; the first snapshot is taken when this quarter closes.</p>@else<ul class="mt-2 space-y-1 text-sm">@foreach($frozen as $q)<li><a href="{{ route('state-of.quarter', $q) }}" class="text-brand-navy hover:underline {{ $q === $quarter ? 'font-semibold' : '' }}">{{ str_replace('-Q', ' Q', $q) }}</a></li>@endforeach</ul>@endif
                @unless($isCurrent)<p class="mt-2 text-sm"><a href="{{ route('state-of.show') }}" class="text-brand-blue">Current quarter →</a></p>@endunless
            </section>
            <section aria-labelledby="embed-heading"><h2 id="embed-heading" class="section-title">Embed</h2><p class="mt-1 text-sm text-brand-body">The map, a jurisdiction card or the upcoming deadlines can be embedded on your site with attribution: <a href="{{ route('embed.index') }}">embed configurator</a>.</p></section>
            <x-site.subscribe-form source="state-of" />
        </aside>
    </div>
</div>
@endsection
