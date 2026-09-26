@extends('site.layouts.app')
@section('content')
<div class="container-site py-8">
    <x-site.breadcrumbs :items="$seo->breadcrumbs" />
    <div class="mt-2 flex flex-wrap items-end justify-between gap-3">
        <div><p class="eyebrow">AI Incident Database</p><h1 class="mt-1 font-display text-3xl font-semibold text-brand-navy">Browse AI incidents</h1><p class="mt-2 max-w-[64ch] text-brand-body">Every incident record (metadata only) from the weekly snapshot of {{ $aiid['snapshot_date'] ?? '—' }}. Filter, then export the selection with its licence attached; each row links to the full record and its reports.</p></div>
        <div class="flex gap-2"><a href="{{ route('risk.incidents.export', array_merge(['format' => 'csv'], array_filter($filters))) }}" class="btn-secondary" data-track="export_click">Export CSV</a><a href="{{ route('risk.incidents.export', array_merge(['format' => 'json'], array_filter($filters))) }}" class="btn-secondary" data-track="export_click">Export JSON</a><a href="{{ route('risk.incidents') }}" class="btn-primary">Charts and summary</a></div>
    </div>
    <form method="get" action="{{ route('risk.incidents.browse') }}" class="mt-5 grid gap-3 sm:grid-cols-6" aria-label="Filter incidents">
        <div class="sm:col-span-2"><label for="i-q" class="label">Keyword</label><input id="i-q" type="search" name="q" value="{{ $filters['q'] }}" class="input" placeholder="e.g. chatbot, facial recognition, deepfake"></div>
        <div><label for="i-y" class="label">Year</label><select id="i-y" name="year" class="input"><option value="">All</option>@foreach($facets['year'] as $y => $n)<option value="{{ $y }}" @selected((string) $filters['year'] === (string) $y)>{{ $y }} ({{ $n }})</option>@endforeach</select></div>
        <div class="sm:col-span-2"><label for="i-d" class="label">Risk domain</label><select id="i-d" name="domain" class="input"><option value="">All</option>@foreach($facets['domain'] as $d => $n)<option value="{{ $d }}" @selected($filters['domain'] === $d)>{{ $d }} ({{ $n }})</option>@endforeach</select></div>
        <div><label for="i-h" class="label">Harm level</label><select id="i-h" name="harm" class="input"><option value="">All</option>@foreach($facets['harm'] as $h => $n)<option value="{{ $h }}" @selected($filters['harm'] === $h)>{{ ucfirst($h) }} ({{ $n }})</option>@endforeach</select></div>
        <div><label for="i-c" class="label">Country code</label><input id="i-c" name="country" value="{{ $filters['country'] }}" class="input" maxlength="2" placeholder="US"></div>
        <div class="sm:col-span-2"><label for="i-s" class="label">Sector contains</label><input id="i-s" name="sector" value="{{ $filters['sector'] }}" class="input" placeholder="health"></div>
        @if($filters['subdomain'])<input type="hidden" name="subdomain" value="{{ $filters['subdomain'] }}">@endif
        <div class="sm:col-span-3 flex gap-2 items-end"><button type="submit" class="btn-primary">Apply</button><a href="{{ route('risk.incidents.browse') }}" class="btn-secondary">Reset</a></div>
    </form>
    <p class="mt-6 meta" role="status">{{ number_format($incidents->total()) }} {{ \Illuminate\Support\Str::plural('incident', $incidents->total()) }}@if($filters['subdomain']) · subdomain: {{ $filters['subdomain'] }}@endif @if($incidents->lastPage() > 1)· page {{ $incidents->currentPage() }} of {{ $incidents->lastPage() }}@endif</p>
    @if($incidents->isEmpty())<div class="mt-4"><x-site.empty title="No incidents match these filters" :reset="route('risk.incidents.browse')" /></div>@else
    <ol class="mt-2 divide-y divide-brand-line border-y border-brand-line">
        @foreach($incidents as $i)
        <li class="py-4 grid gap-2 lg:grid-cols-12 lg:gap-6 text-sm">
            <div class="lg:col-span-2"><time class="datestamp" datetime="{{ $i->occurred_on->toDateString() }}">{{ $i->occurred_on->format('j M Y') }}</time><p class="meta">#{{ $i->incident_id }} · {{ $i->report_count }} {{ \Illuminate\Support\Str::plural('report', $i->report_count) }}</p></div>
            <div class="lg:col-span-7"><a href="{{ $i->url() }}" class="font-display text-base text-brand-navy no-underline hover:underline">{{ $i->displayTitle() }}</a> <a href="{{ $i->citeUrl() }}" rel="noopener" class="meta no-underline hover:underline">AIID ↗</a><p class="mt-1 text-brand-body leading-6">{{ $i->description ?: '—' }}</p>
                <p class="mt-1 meta">@if($i->deployers)Deployer: {{ implode(', ', $i->deployers) }}@endif @if($i->developers)· Developer: {{ implode(', ', $i->developers) }}@endif @if($i->harmed)· Harmed: {{ implode(', ', $i->harmed) }}@endif</p></div>
            <div class="lg:col-span-3 flex flex-wrap gap-1.5 content-start">
                @if($i->mit_domain)<a class="chip !min-h-0 !py-0.5" href="{{ route('risk.incidents.browse', ['domain' => $i->mit_domain]) }}">{{ \Illuminate\Support\Str::limit($i->mit_domain, 30) }}</a>@endif
                @if($i->mit_subdomain)<a class="chip !min-h-0 !py-0.5" href="{{ route('risk.incidents.browse', ['subdomain' => $i->mit_subdomain]) }}">{{ \Illuminate\Support\Str::limit($i->mit_subdomain, 34) }}</a>@endif
                @if($i->harm_level)<span class="badge-neutral">{{ $i->harm_level }}</span>@endif
                @foreach($i->countries ?? [] as $c)<a class="chip !min-h-0 !py-0.5 font-mono" href="{{ route('risk.incidents.browse', ['country' => $c]) }}">{{ $c }}</a>@endforeach
            </div>
        </li>
        @endforeach
    </ol>
    <nav class="mt-6" aria-label="Pagination">{{ $incidents->links() }}</nav>
    @endif
    <x-site.attribution class="mt-10" :name="$aiid['source'] ?? 'AI Incident Database'" :url="$aiid['source_url'] ?? 'https://incidentdatabase.ai/'" :license="$aiid['license'] ?? 'CC BY-SA 4.0'" :licenseUrl="$aiid['license_url'] ?? 'https://creativecommons.org/licenses/by-sa/4.0/'" :citation="$aiid['citation'] ?? null" :date="$aiid['snapshot_date'] ?? null" note="Titles, descriptions and classifications are reproduced under CC BY-SA 4.0; report texts are not. Exports carry the same licence." />
    <x-site.disclaimer class="mt-6" />
</div>
@endsection
