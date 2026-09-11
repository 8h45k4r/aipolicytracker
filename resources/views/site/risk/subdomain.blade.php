@extends('site.layouts.app')
@section('content')
<div class="container-site py-8">
    <x-site.breadcrumbs :items="$seo->breadcrumbs" />
    <p class="eyebrow mt-3">MIT AI Risk Repository · domain {{ $d['id'] }}: {{ $d['name'] }}</p>
    <h1 class="mt-2 text-2xl sm:text-3xl lg:text-4xl font-semibold tracking-tight text-brand-navy">{{ $sub }} {{ $meta['name'] }}</h1>
    @if(!empty($meta['description']))<p class="mt-3 max-w-[70ch] text-brand-body leading-7">{{ $meta['description'] }}</p>@endif
    <dl class="mt-6 grid gap-4 sm:grid-cols-4 text-sm max-w-3xl">
        <div class="card-flat p-3"><dt class="meta">Risk entries</dt><dd class="mt-1 font-mono text-2xl text-brand-navy">{{ $riskCount ? number_format($riskCount) : '—' }}</dd></div>
        <div class="card-flat p-3"><dt class="meta">Frameworks citing it</dt><dd class="mt-1 font-mono text-2xl text-brand-navy">{{ $papers->count() ?: '—' }}</dd></div>
        <div class="card-flat p-3"><dt class="meta">Recorded incidents</dt><dd class="mt-1 font-mono text-2xl text-brand-navy">{{ $incidentCount ? number_format($incidentCount) : '—' }}</dd></div>
        <div class="card-flat p-3"><dt class="meta">Incidents since 2020</dt><dd class="mt-1 font-mono text-2xl text-brand-navy">{{ ($n = $incidentYears->filter(fn ($v, $y) => $y >= 2020)->sum()) ? number_format($n) : '—' }}</dd></div>
    </dl>
    <div class="mt-10 grid gap-6 lg:grid-cols-3">
        @foreach(['entity' => 'Causal entity', 'intent' => 'Intent', 'timing' => 'Timing'] as $k => $label)
        <x-site.bar-chart :series="$breakdown[$k]" :title="$label.' (risk entries)'" :height="120" />
        @endforeach
    </div>
    <div class="mt-6 grid gap-6 lg:grid-cols-2">
        @if($incidentYears->isNotEmpty())<x-site.bar-chart :series="$incidentYears->filter(fn ($v, $y) => $y >= 2012)" title="Recorded incidents per year" note="Incident date; current year partial" />@endif
        <x-site.bar-chart :series="$byLevel" title="Entries by level" :height="120" note="Risk categories, subcategories and additional evidence coded to this subdomain" />
    </div>
    <div class="mt-10 grid gap-10 lg:grid-cols-12">
        <section class="lg:col-span-7 min-w-0" aria-labelledby="risks-heading">
            <div class="rule-strong pt-3 flex items-baseline justify-between"><h2 id="risks-heading" class="section-title">Risk entries</h2><a href="{{ route('risk.risks', ['subdomain' => $sub]) }}" class="text-sm">Browse and export all</a></div>
            @if($risks->isEmpty())<div class="mt-4"><x-site.empty title="No risk entries coded to this subdomain" /></div>@else
            <ul class="mt-3 divide-y divide-brand-line">@foreach($risks as $r)<li class="py-3 text-sm"><a href="{{ $r->url() }}" class="font-medium text-brand-navy">{{ $r->risk_subcategory ?: $r->risk_category ?: $r->ev_id }}</a><p class="mt-0.5 text-brand-body">{{ \Illuminate\Support\Str::limit($r->description, 200) }}</p><p class="meta mt-0.5">{{ $r->paper_title }} ({{ $r->quick_ref }}) · {{ $r->entity ?: '—' }} · {{ $r->intent ?: '—' }} · {{ $r->timing ?: '—' }}</p></li>@endforeach</ul>
            <nav class="mt-4" aria-label="Pagination">{{ $risks->links() }}</nav>@endif
        </section>
        <aside class="lg:col-span-5 min-w-0 space-y-8">
            <section aria-labelledby="papers-heading">
                <div class="rule-strong pt-3"><h2 id="papers-heading" class="section-title">Frameworks covering this subdomain</h2></div>
                @if($papers->isEmpty())<p class="mt-2 text-sm text-brand-muted">—</p>@else<ul class="mt-2 divide-y divide-brand-line text-sm">@foreach($papers as $p)<li class="py-2 flex justify-between gap-3"><a href="{{ route('risk.risks', ['paper' => $p->quick_ref, 'subdomain' => $sub]) }}" class="text-brand-navy">{{ \Illuminate\Support\Str::limit($p->title, 70) }} <span class="meta">({{ $p->quick_ref }})</span></a><span class="font-mono text-brand-muted">{{ $p->n }}</span></li>@endforeach</ul>@endif
            </section>
            <section aria-labelledby="inc-heading">
                <div class="rule-strong pt-3 flex items-baseline justify-between"><h2 id="inc-heading" class="section-title">Recent incidents</h2>@if($incidentCount)<a href="{{ route('risk.incidents.browse', ['subdomain' => $meta['name']]) }}" class="text-sm">All {{ number_format($incidentCount) }}</a>@endif</div>
                @if($incidents->isEmpty())<p class="mt-2 text-sm text-brand-muted">No incidents classified to this subdomain yet.</p>@else<ol class="mt-2 divide-y divide-brand-line text-sm">@foreach($incidents as $i)<li class="py-2"><time class="datestamp" datetime="{{ $i->occurred_on->toDateString() }}">{{ $i->occurred_on->format('j M Y') }}</time> <a href="{{ $i->url() }}" class="text-brand-navy">{{ $i->title }}</a></li>@endforeach</ol>@endif
            </section>
            @if($policies->isNotEmpty())
            <section aria-labelledby="pol-heading">
                <div class="rule-strong pt-3"><h2 id="pol-heading" class="section-title">Policies addressing related use cases</h2></div>
                <ul class="mt-2 divide-y divide-brand-line text-sm">@foreach($policies as $p)<li class="py-2"><a href="{{ $p->url() }}" class="text-brand-navy">{{ $p->short_title ?: $p->title }}</a> <span class="meta">{{ $p->jurisdiction->name }}</span></li>@endforeach</ul>
            </section>
            @endif
        </aside>
    </div>
    <x-site.attribution class="mt-12" :name="$mit['source'] ?? 'MIT AI Risk Repository'" :url="$mit['source_url'] ?? 'https://airisk.mit.edu/'" :license="$mit['license'] ?? 'CC BY 4.0'" :licenseUrl="$mit['license_url'] ?? 'https://creativecommons.org/licenses/by/4.0/'" :citation="$mit['citation'] ?? null" note="Subdomain definitions are the repository's; incident classification is applied by the AI Incident Database." />
</div>
@endsection
