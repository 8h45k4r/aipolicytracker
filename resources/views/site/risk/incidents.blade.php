@extends('site.layouts.app')
@section('content')
<div class="container-site py-8">
    <x-site.breadcrumbs :items="$seo->breadcrumbs" />
    <p class="eyebrow mt-3">AI incidents</p>
    <h1 class="mt-2 font-display text-3xl sm:text-4xl font-semibold text-brand-navy max-w-[24ch]">What has actually gone wrong with AI, in numbers</h1>
    <p class="mt-4 max-w-[64ch] text-brand-body leading-7">The AI Incident Database is an open catalogue of harms and near-misses involving AI systems. New and updated records are synced from its API several times a day; the charts come from its weekly export. We keep incident metadata only, and every record links back to the original.</p>
    @if(empty($aiid['totals']))
    <x-site.empty title="Incident summary not available yet" class="mt-8">The weekly refresh has not produced a summary yet.</x-site.empty>
    @else
    <dl class="mt-8 grid gap-4 sm:grid-cols-3 text-sm max-w-2xl">
        <div class="rule pt-2"><dt class="meta">Incidents recorded</dt><dd class="font-mono tabular-nums text-2xl text-brand-navy">{{ number_format($aiid['totals']['incidents']) }}</dd></div>
        <div class="rule pt-2"><dt class="meta">Classified by MIT risk domain</dt><dd class="font-mono tabular-nums text-2xl text-brand-navy">{{ number_format($aiid['totals']['classified_mit']) }}</dd></div>
        <div class="rule pt-2"><dt class="meta">Last synced</dt><dd class="font-mono tabular-nums text-2xl text-brand-navy">{{ $live['synced_at'] ? \Illuminate\Support\Carbon::parse($live['synced_at'])->format('j M Y') : $aiid['snapshot_date'] }}</dd></div>
    </dl>

    <p class="mt-4 text-sm flex flex-wrap gap-2"><a href="#latest" class="btn-primary">Latest recorded incidents</a><a href="{{ route('risk.incidents.browse') }}" class="btn-secondary">Browse and export all incidents</a></p>
    <div class="mt-10 grid gap-8 lg:grid-cols-2">
        <x-site.bar-chart :series="collect($aiid['incidents_per_year'])->filter(fn ($v, $y) => $y >= 2012)" title="Incidents per year (incident date)" note="Current year is partial" />
        <x-site.bar-chart :series="collect($aiid['by_mit_domain'])->mapWithKeys(fn ($v, $k) => [\Illuminate\Support\Str::limit(preg_replace('/^(AI system safety).*/', '$1…', $k), 26) => $v])" title="Incidents by MIT risk domain" />
    </div>

    @if(!empty($aiid['domain_by_year']))
    <x-site.stacked-chart class="mt-8" :series="collect($aiid['domain_by_year'])->filter(fn ($v, $y) => $y >= 2018)->all()" :keys="array_keys($aiid['by_mit_domain'])" title="Incidents per year by MIT risk domain" note="Classified incidents only" />
    @endif
    @if($causal['classified'] > 0)
    @php($pct = fn ($n) => round(100 * $n / max(1, $causal['classified']), 1))
    @php($post = $causal['timing']['Post-deployment'] ?? 0)
    @php($aiUnintended = $causal['matrix']['AI']['Unintentional'] ?? 0)
    <section class="mt-10" aria-labelledby="causal-heading">
        <div class="rule-strong pt-3">
            <h2 id="causal-heading" class="section-title">How these harms arise</h2>
            <p class="mt-2 max-w-[70ch] text-brand-body">Every record above is coded for who caused the harm, whether it was intended, and whether it happened before or after the system was released. That coding is what turns a list of incidents into an argument about where regulation has to act.</p>
        </div>

        <div class="mt-5 grid gap-4 sm:grid-cols-3">
            <div class="card-flat p-4">
                <p class="font-mono tabular-nums text-3xl text-brand-navy">{{ $pct($post) }}%</p>
                <p class="mt-1 text-sm font-medium text-brand-navy">happened after deployment</p>
                <p class="mt-1 meta">{{ number_format($post) }} of {{ number_format($causal['classified']) }} classified records. Pre-release testing is not where these were caught.</p>
            </div>
            <div class="card-flat p-4">
                <p class="font-mono tabular-nums text-3xl text-brand-navy">{{ $pct($aiUnintended) }}%</p>
                <p class="mt-1 text-sm font-medium text-brand-navy">the system itself, unintended</p>
                <p class="mt-1 meta">{{ number_format($aiUnintended) }} records where the AI was the cause and the harm was not intended by anyone.</p>
            </div>
            <div class="card-flat p-4">
                <p class="font-mono tabular-nums text-3xl text-brand-navy">{{ $pct($causal['matrix']['Human']['Intentional'] ?? 0) }}%</p>
                <p class="mt-1 text-sm font-medium text-brand-navy">deliberate human misuse</p>
                <p class="mt-1 meta">{{ number_format($causal['matrix']['Human']['Intentional'] ?? 0) }} records where a person used the system to cause the harm on purpose.</p>
            </div>
        </div>

        <div class="mt-6 grid gap-8 lg:grid-cols-2">
            <x-site.matrix-chart
                :rows="array_keys($causal['entity'])"
                :cols="array_keys($causal['intent'])"
                :cells="$causal['matrix']"
                title="Who caused it, and was it intended"
                rowLabel="Cause"
                colLabel="Intent" />
            <x-site.bar-chart
                :series="$causal['timing']"
                title="When it happened in the system's life"
                note="Classified records only" />
        </div>

        <p class="mt-3 meta">Coded on {{ number_format($causal['classified']) }} of {{ number_format($causal['total']) }} records ({{ round(100 * $causal['classified'] / max(1, $causal['total'])) }}%). Percentages are of the classified records; the remaining {{ number_format($causal['total'] - $causal['classified']) }} are uncoded, which means unknown rather than none of the above. Classification is the source dataset's, not ours.</p>
    </section>
    @endif

    <div class="mt-10 grid gap-10 lg:grid-cols-12">
        <section class="lg:col-span-5 min-w-0" aria-labelledby="sector-heading">
            <div class="rule-strong pt-3"><h2 id="sector-heading" class="section-title">Sector of deployment</h2></div>
            <table class="mt-2 w-full text-sm"><caption class="sr-only">Incidents by sector of deployment (CSET taxonomy)</caption>
                <tbody class="divide-y divide-brand-line">@foreach($aiid['by_sector'] as $sector => $n)<tr><td class="py-2 pr-3 text-brand-body">{{ ucfirst($sector) }}</td><td class="py-2 font-mono tabular-nums text-right text-brand-navy">{{ $n }}</td></tr>@endforeach</tbody></table>
            <p class="meta mt-2">Sector coverage comes from the CSET classification and is partial.</p>
        </section>
        <section class="lg:col-span-3 min-w-0" aria-labelledby="country-heading">
            <div class="rule-strong pt-3"><h2 id="country-heading" class="section-title">Country</h2></div>
            <table class="mt-2 w-full text-sm"><caption class="sr-only">Incidents by country code</caption>
                <tbody class="divide-y divide-brand-line">@foreach(array_slice($aiid['by_country'], 0, 12, true) as $cc => $n)<tr><td class="py-2 pr-3 font-mono text-brand-body">{{ $cc }}</td><td class="py-2 font-mono tabular-nums text-right text-brand-navy">{{ $n }}</td></tr>@endforeach</tbody></table>
            <p class="meta mt-2">{{ number_format($aiid['totals']['with_country']) }} incidents carry a country code.</p>
        </section>
        <section class="lg:col-span-4 min-w-0" aria-labelledby="harm-heading">
            <div class="rule-strong pt-3"><h2 id="harm-heading" class="section-title">Harm level</h2></div>
            <table class="mt-2 w-full text-sm"><caption class="sr-only">Incidents by assessed AI harm level</caption>
                <tbody class="divide-y divide-brand-line">@foreach($aiid['by_harm_level'] as $level => $n)<tr><td class="py-2 pr-3 text-brand-body">{{ ucfirst($level) }}</td><td class="py-2 font-mono tabular-nums text-right text-brand-navy">{{ $n }}</td></tr>@endforeach</tbody></table>
        </section>
    </div>

    @endif

    <section class="mt-12" aria-labelledby="latest-heading" id="latest">
        <div class="rule-strong pt-3 flex flex-wrap items-baseline justify-between gap-2"><h2 id="latest-heading" class="section-title">Latest recorded incidents</h2><p class="meta">@if($live['synced_at'])Synced from the AI Incident Database API <time datetime="{{ \Illuminate\Support\Carbon::parse($live['synced_at'])->toIso8601String() }}">{{ \Illuminate\Support\Carbon::parse($live['synced_at'])->diffForHumans() }}</time> · latest id #{{ $live['latest_id'] }}@else Weekly snapshot {{ $aiid['snapshot_date'] ?? '—' }}@endif</p></div>
        <p class="mt-2 max-w-[64ch] text-sm text-brand-body">{{ number_format($live['recent']) }} {{ \Illuminate\Support\Str::plural('incident', $live['recent']) }} dated in the last 30 days out of {{ number_format($live['count']) }} on record. Each title opens a profile with the full description, the parties involved, the MIT classification, the catalogued news reports and related incidents.</p>
        @if($latest->isEmpty())
        <ol class="mt-2 divide-y divide-brand-line">
            @foreach($aiid['latest'] ?? [] as $i)
            <li class="py-3 grid gap-1 sm:grid-cols-12 sm:gap-4 text-sm min-w-0">
                <time class="datestamp sm:col-span-2" datetime="{{ $i['date'] }}">{{ $i['date'] }}</time>
                <div class="sm:col-span-8"><a href="https://incidentdatabase.ai/cite/{{ $i['id'] }}" rel="noopener" class="text-brand-navy no-underline hover:underline">{{ $i['title'] }}</a></div>
                <div class="sm:col-span-2 meta">{{ $i['domain'] ?: '—' }}@if($i['countries']) · {{ implode(', ', $i['countries']) }}@endif</div>
            </li>
            @endforeach
        </ol>
        @else
        <ol class="mt-3 divide-y divide-brand-line border-y border-brand-line">
            @foreach($latest as $i)
            <li class="py-4 grid gap-2 lg:grid-cols-12 lg:gap-6 text-sm min-w-0">
                <div class="lg:col-span-2"><time class="datestamp" datetime="{{ $i->occurred_on->toDateString() }}">{{ $i->occurred_on->format('j M Y') }}</time><p class="meta">#{{ $i->incident_id }} · {{ $i->report_count }} {{ \Illuminate\Support\Str::plural('report', $i->report_count) }}</p></div>
                <div class="lg:col-span-7 min-w-0"><a href="{{ $i->url() }}" class="font-display text-base text-brand-navy no-underline hover:underline">{{ $i->title }}</a> <a href="{{ $i->citeUrl() }}" rel="noopener" class="meta no-underline hover:underline" data-track="source_click">AIID ↗</a>
                    <p class="mt-1 text-brand-body leading-6">{{ \Illuminate\Support\Str::limit($i->description, 260) ?: '—' }}</p>
                    <p class="mt-1 meta">@if($i->deployers)Deployer: {{ implode(', ', array_slice($i->deployers, 0, 3)) }}@endif @if($i->harmed)· Harmed: {{ implode(', ', array_slice($i->harmed, 0, 3)) }}@endif</p>
                    @if($i->reports->isNotEmpty())<p class="mt-1 meta">Latest report: <a href="{{ $i->reports->last()->url }}" rel="noopener nofollow">{{ \Illuminate\Support\Str::limit($i->reports->last()->title, 90) }}</a> ({{ $i->reports->last()->source_domain ?: '—' }})</p>@endif</div>
                <div class="lg:col-span-3 flex flex-wrap gap-1.5 content-start">
                    @if($i->mit_domain)<a class="chip !min-h-0 !py-0.5" href="{{ route('risk.incidents.browse', ['domain' => $i->mit_domain]) }}">{{ \Illuminate\Support\Str::limit($i->mit_domain, 30) }}</a>@else<span class="badge-neutral">Awaiting classification</span>@endif
                    @if($i->harm_level)<span class="badge-neutral">{{ $i->harm_level }}</span>@endif
                    @foreach($i->countries ?? [] as $c)<a class="chip !min-h-0 !py-0.5 font-mono" href="{{ route('risk.incidents.browse', ['country' => $c]) }}">{{ $c }}</a>@endforeach
                </div>
            </li>
            @endforeach
        </ol>
        <p class="mt-3 text-sm"><a href="{{ route('risk.incidents.browse') }}" class="btn-secondary">Browse all {{ number_format($live['count']) }} incidents</a></p>
        @endif
    </section>

    <x-site.attribution class="mt-12" :name="$aiid['source'] ?? 'AI Incident Database'" :url="$aiid['source_url'] ?? 'https://incidentdatabase.ai/'" :license="$aiid['license'] ?? 'CC BY-SA 4.0'" :licenseUrl="$aiid['license_url'] ?? 'https://creativecommons.org/licenses/by-sa/4.0/'" :citation="$aiid['citation'] ?? null" :date="$aiid['snapshot_date'] ?? null" note="Incident titles and identifiers are reproduced under CC BY-SA 4.0; report texts are not. Derived aggregates on this page are shared under the same licence." />
    <x-site.disclaimer class="mt-6" />
</div>
@endsection
