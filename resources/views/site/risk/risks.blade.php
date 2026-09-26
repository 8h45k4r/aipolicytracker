@extends('site.layouts.app')
@section('content')
<div class="container-site py-8">
    <x-site.breadcrumbs :items="$seo->breadcrumbs" />
    <div class="mt-2 flex flex-wrap items-end justify-between gap-3">
        <div><p class="eyebrow">MIT AI Risk Repository</p><h1 class="mt-1 font-display text-3xl font-semibold text-brand-navy">Browse AI risks</h1><p class="mt-2 max-w-[64ch] text-brand-body">{{ number_format($risks->total()) }} risk entries extracted from {{ count($facets['level']) ? '74' : '—' }} frameworks, coded by domain, subdomain, causal entity, intent and timing. Filter, then export the current selection with its licence and citation attached.</p></div>
        <div class="flex gap-2"><a href="{{ route('risk.risks.export', array_merge(['format' => 'csv'], array_filter($filters))) }}" class="btn-secondary" data-track="export_click">Export CSV</a><a href="{{ route('risk.risks.export', array_merge(['format' => 'json'], array_filter($filters))) }}" class="btn-secondary" data-track="export_click">Export JSON</a></div>
    </div>
    <form method="get" action="{{ route('risk.risks') }}" class="mt-5 grid gap-3 sm:grid-cols-4 lg:grid-cols-8 lg:items-end" aria-label="Filter risks">
        <div class="sm:col-span-2 lg:col-span-2"><label for="r-q" class="label">Keyword</label><input id="r-q" type="search" name="q" value="{{ $filters['q'] }}" class="input" placeholder="e.g. bias, jailbreak, deepfake"></div>
        <div><label for="r-d" class="label">Domain</label><select id="r-d" name="domain" class="input"><option value="">All</option>@foreach($mit['domains'] ?? [] as $d)<option value="{{ $d['id'] }}" @selected((string) $filters['domain'] === (string) $d['id'])>{{ $d['id'] }}. {{ \Illuminate\Support\Str::limit($d['name'], 28) }} ({{ $facets['domain'][$d['id']] ?? 0 }})</option>@endforeach</select></div>
        <div><label for="r-e" class="label">Entity</label><select id="r-e" name="entity" class="input"><option value="">All</option>@foreach(\App\Models\ExternalRisk::CAUSAL['entity'] as $v)<option value="{{ $v }}" @selected($filters['entity'] === $v)>{{ $v }} ({{ $facets['entity'][$v] ?? 0 }})</option>@endforeach</select></div>
        <div><label for="r-i" class="label">Intent</label><select id="r-i" name="intent" class="input"><option value="">All</option>@foreach(\App\Models\ExternalRisk::CAUSAL['intent'] as $v)<option value="{{ $v }}" @selected($filters['intent'] === $v)>{{ $v }} ({{ $facets['intent'][$v] ?? 0 }})</option>@endforeach</select></div>
        <div><label for="r-t" class="label">Timing</label><select id="r-t" name="timing" class="input"><option value="">All</option>@foreach(\App\Models\ExternalRisk::CAUSAL['timing'] as $v)<option value="{{ $v }}" @selected($filters['timing'] === $v)>{{ $v }} ({{ $facets['timing'][$v] ?? 0 }})</option>@endforeach</select></div>
        <div><label for="r-l" class="label">Level</label><select id="r-l" name="level" class="input"><option value="">All</option>@foreach(\App\Models\ExternalRisk::LEVELS as $k => $label)<option value="{{ $k }}" @selected($filters['level'] === $k)>{{ $label }} ({{ $facets['level'][$k] ?? 0 }})</option>@endforeach</select></div>
        @if($filters['subdomain'])<input type="hidden" name="subdomain" value="{{ $filters['subdomain'] }}">@endif
        @if($filters['paper'])<input type="hidden" name="paper" value="{{ $filters['paper'] }}">@endif
        <div class="sm:col-span-2 lg:col-span-8 flex flex-wrap items-center gap-2">
            <button type="submit" class="btn-primary">Apply</button><a href="{{ route('risk.risks') }}" class="btn-secondary">Reset</a>
            @if($filters['subdomain'] || $filters['paper'])<span class="meta ml-2">Also filtered by</span>
                @if($filters['subdomain'])<a class="chip chip-active !min-h-0" href="{{ route('risk.risks', array_filter(array_diff_key($filters, ['subdomain' => 1]))) }}" title="Remove subdomain filter">subdomain {{ $filters['subdomain'] }} ×</a>@endif
                @if($filters['paper'])<a class="chip chip-active !min-h-0" href="{{ route('risk.risks', array_filter(array_diff_key($filters, ['paper' => 1]))) }}" title="Remove framework filter">framework {{ $filters['paper'] }} ×</a>@endif
            @endif
        </div>
    </form>

    <p class="mt-6 meta" role="status">{{ number_format($risks->total()) }} {{ \Illuminate\Support\Str::plural('entry', $risks->total()) }}@if($risks->lastPage() > 1) · page {{ $risks->currentPage() }} of {{ $risks->lastPage() }}@endif</p>
    @if($risks->isEmpty())<div class="mt-4"><x-site.empty title="No risks match these filters" :reset="route('risk.risks')" /></div>@else
    <ol class="mt-2 divide-y divide-brand-line border-y border-brand-line">
        @foreach($risks as $r)
        <li class="py-4 grid gap-2 lg:grid-cols-12 lg:gap-6 text-sm">
            <div class="lg:col-span-3">
                <p class="font-mono text-xs text-brand-muted"><a href="{{ $r->url() }}" class="no-underline hover:underline">{{ $r->ev_id }}</a> · {{ $r->level }}</p>
                <p class="mt-1 font-display text-base text-brand-navy"><a href="{{ $r->url() }}" class="no-underline hover:underline">{{ $r->risk_category ?: '—' }}</a></p>
                @if($r->risk_subcategory)<p class="text-brand-body">{{ $r->risk_subcategory }}</p>@endif
            </div>
            <div class="lg:col-span-6">
                <p class="text-brand-body leading-6">{{ $r->description ?: '—' }}</p>
                <p class="mt-1 meta">From <a href="{{ route('risk.risks', ['paper' => $r->quick_ref]) }}">{{ $r->paper_title }}</a> ({{ $r->quick_ref }})</p>
            </div>
            <div class="lg:col-span-3 flex flex-wrap gap-1.5 content-start">
                @if($r->domain)<a class="chip !min-h-0 !py-0.5" href="{{ \App\Support\RiskTaxonomy::domainUrl($r->domain) }}">Domain {{ $r->domain }}</a>@endif
                @if($r->subdomain)<a class="chip !min-h-0 !py-0.5" href="{{ route('risk.risks', ['subdomain' => $r->subdomain]) }}">{{ $r->subdomain }}</a>@endif
                @foreach(['entity', 'intent', 'timing'] as $k)@if($r->{$k})<a class="chip !min-h-0 !py-0.5" href="{{ route('risk.risks', [$k => $r->{$k}]) }}">{{ $r->{$k} }}</a>@endif @endforeach
            </div>
        </li>
        @endforeach
    </ol>
    <nav class="mt-6" aria-label="Pagination">{{ $risks->links() }}</nav>
    @endif
    <x-site.attribution class="mt-10" :name="$mit['source'] ?? 'MIT AI Risk Repository'" :url="$mit['source_url'] ?? 'https://airisk.mit.edu/'" :license="$mit['license'] ?? 'CC BY 4.0'" :licenseUrl="$mit['license_url'] ?? 'https://creativecommons.org/licenses/by/4.0/'" :citation="$mit['citation'] ?? null" note="Descriptions are the repository's extracted evidence, reproduced under CC BY 4.0; identifiers were reformatted." />
    <x-site.disclaimer class="mt-6" />
</div>
@endsection
