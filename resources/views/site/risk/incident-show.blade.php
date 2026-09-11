@extends('site.layouts.app')
@section('content')
<div class="container-site py-8">
    <x-site.breadcrumbs :items="$seo->breadcrumbs" />
    <header class="mt-3">
        <p class="eyebrow">AI incident #{{ $i->incident_id }} · <time datetime="{{ $i->occurred_on->toDateString() }}">{{ $i->occurred_on->format('j F Y') }}</time></p>
        <h1 class="mt-2 text-2xl sm:text-3xl lg:text-4xl font-semibold tracking-tight text-brand-navy">{{ $i->title }}</h1>
        <div class="mt-3 flex flex-wrap items-center gap-x-4 gap-y-2 text-sm">
            <a href="{{ $i->citeUrl() }}" rel="noopener" class="text-brand-blue font-medium hover:underline" data-track="source_click">Open on the AI Incident Database</a>
            <a href="{{ $i->reportsUrl() }}" rel="noopener" class="text-brand-body hover:underline" data-track="source_click">{{ $i->report_count }} {{ \Illuminate\Support\Str::plural('news report', $i->report_count) }}</a>
            <span class="meta">Snapshot {{ $i->snapshot_date?->format('j M Y') ?? '—' }}</span>
        </div>
    </header>
    <div class="mt-8 grid gap-10 lg:grid-cols-3">
        <div class="lg:col-span-2 min-w-0">
            <section aria-labelledby="what-heading">
                <h2 id="what-heading" class="section-title">What happened</h2>
                <p class="prose-policy mt-2">{{ $i->description ?: '—' }}</p>
                <p class="mt-3 text-sm text-brand-muted">Only the incident metadata is stored here. The underlying news reports are on the AI Incident Database (CC BY-SA 4.0); use the links above to read them.</p>
            </section>
            <section aria-labelledby="who-heading" class="mt-8">
                <h2 id="who-heading" class="section-title">Who was involved</h2>
                <dl class="mt-3 grid gap-4 sm:grid-cols-3 text-sm">
                    @foreach([['Alleged deployer', $i->deployers, 'deployer'], ['Alleged developer', $i->developers, 'developer'], ['Alleged harmed party', $i->harmed, null]] as [$label, $vals, $param])
                    <div><dt class="text-brand-muted">{{ $label }}</dt><dd class="mt-1 flex flex-wrap gap-1.5">@forelse($vals ?? [] as $v)@if($param)<a class="chip !min-h-0 !py-1" href="{{ route('risk.incidents.browse', [$param => $v]) }}">{{ $v }}</a>@else<span class="chip !min-h-0 !py-1">{{ $v }}</span>@endif @empty<span>—</span>@endforelse</dd></div>
                    @endforeach
                </dl>
            </section>
            <section aria-labelledby="class-heading" class="mt-8">
                <h2 id="class-heading" class="section-title">Classification (MIT AI Risk Repository taxonomy)</h2>
                <dl class="mt-3 grid gap-x-8 gap-y-3 sm:grid-cols-2 text-sm">
                    <div><dt class="text-brand-muted">Risk domain</dt><dd class="mt-0.5">@if($i->mit_domain)<a href="{{ $domainId ? route('risk.domain', $domainId) : route('risk.incidents.browse', ['domain' => $i->mit_domain]) }}" class="font-medium text-brand-navy">{{ $i->mit_domain }}</a>@else —@endif</dd></div>
                    <div><dt class="text-brand-muted">Risk subdomain</dt><dd class="mt-0.5">@if($i->mit_subdomain)<a href="{{ route('risk.incidents.browse', ['subdomain' => $i->mit_subdomain]) }}" class="font-medium text-brand-navy">{{ $subdomainCode ? $subdomainCode.' ' : '' }}{{ $i->mit_subdomain }}</a>@else —@endif</dd></div>
                    <div><dt class="text-brand-muted">Causal entity</dt><dd class="mt-0.5">{{ $i->entity ?: '—' }}</dd></div>
                    <div><dt class="text-brand-muted">Intent</dt><dd class="mt-0.5">{{ $i->intent ?: '—' }}</dd></div>
                    <div><dt class="text-brand-muted">Timing</dt><dd class="mt-0.5">{{ $i->timing ?: '—' }}</dd></div>
                    <div><dt class="text-brand-muted">Harm level</dt><dd class="mt-0.5">{{ $i->harm_level ?: '—' }}</dd></div>
                    <div><dt class="text-brand-muted">Sectors</dt><dd class="mt-0.5">{{ $i->sectors ? implode(', ', $i->sectors) : '—' }}</dd></div>
                    <div><dt class="text-brand-muted">Countries</dt><dd class="mt-0.5 flex flex-wrap gap-1.5">@forelse($i->countries ?? [] as $c)<a class="chip !min-h-0 !py-0.5 font-mono" href="{{ route('risk.incidents.browse', ['country' => $c]) }}">{{ $c }}</a>@empty —@endforelse</dd></div>
                </dl>
            </section>
            @if($risks->isNotEmpty())
            <section aria-labelledby="risks-heading" class="mt-8">
                <h2 id="risks-heading" class="section-title">Risk entries describing this failure mode</h2>
                <p class="mt-1 text-sm text-brand-muted">Entries from the MIT AI Risk Repository coded to subdomain {{ $subdomainCode }}.</p>
                <ul class="mt-3 divide-y divide-brand-line border-y border-brand-line text-sm">@foreach($risks as $r)<li class="py-3"><a href="{{ $r->url() }}" class="font-medium text-brand-navy">{{ $r->risk_subcategory ?: $r->risk_category }}</a><p class="mt-0.5 text-brand-body">{{ \Illuminate\Support\Str::limit($r->description, 220) }}</p><p class="meta mt-0.5">{{ $r->paper_title }} ({{ $r->quick_ref }})</p></li>@endforeach</ul>
            </section>
            @endif
            @if($sameSubdomain->isNotEmpty())
            <section aria-labelledby="similar-heading" class="mt-8">
                <h2 id="similar-heading" class="section-title">Similar incidents</h2>
                <ul class="mt-3 divide-y divide-brand-line border-y border-brand-line text-sm">@foreach($sameSubdomain as $s)<li class="py-3 flex flex-wrap gap-x-4"><time class="datestamp shrink-0" datetime="{{ $s->occurred_on->toDateString() }}">{{ $s->occurred_on->format('j M Y') }}</time><a href="{{ $s->url() }}" class="text-brand-navy">{{ $s->title }}</a></li>@endforeach</ul>
                <a href="{{ route('risk.incidents.browse', ['subdomain' => $i->mit_subdomain]) }}" class="mt-2 inline-block text-sm text-brand-muted hover:text-brand-navy">All incidents in this subdomain</a>
            </section>
            @endif
            @if($sameDeployer->isNotEmpty())
            <section aria-labelledby="deployer-heading" class="mt-8">
                <h2 id="deployer-heading" class="section-title">Other incidents involving {{ $deployer }}</h2>
                <ul class="mt-3 divide-y divide-brand-line border-y border-brand-line text-sm">@foreach($sameDeployer as $s)<li class="py-3 flex flex-wrap gap-x-4"><time class="datestamp shrink-0" datetime="{{ $s->occurred_on->toDateString() }}">{{ $s->occurred_on->format('j M Y') }}</time><a href="{{ $s->url() }}" class="text-brand-navy">{{ $s->title }}</a></li>@endforeach</ul>
            </section>
            @endif
            <x-site.attribution class="mt-10" :name="$summary['source'] ?? 'AI Incident Database'" :url="$summary['source_url'] ?? 'https://incidentdatabase.ai/'" :license="$summary['license'] ?? 'CC BY-SA 4.0'" :licenseUrl="$summary['license_url'] ?? 'https://creativecommons.org/licenses/by-sa/4.0/'" :citation="$summary['citation'] ?? null" :date="$summary['snapshot_date'] ?? null" note="Title, description and classification are reproduced under CC BY-SA 4.0; report texts are not. Read the reports on the AI Incident Database." />
        </div>
        <aside class="space-y-6">
            <div class="lg:sticky lg:top-4 space-y-6">
                <div class="card-flat p-4 text-sm">
                    <p class="font-semibold text-brand-navy">Record</p>
                    <dl class="mt-2 space-y-1.5 text-brand-body">
                        <div class="flex justify-between gap-2"><dt class="text-brand-muted">Incident ID</dt><dd class="font-mono">{{ $i->incident_id }}</dd></div>
                        <div class="flex justify-between gap-2"><dt class="text-brand-muted">Date</dt><dd>{{ $i->occurred_on->format('j M Y') }}</dd></div>
                        <div class="flex justify-between gap-2"><dt class="text-brand-muted">Reports</dt><dd>{{ $i->report_count }}</dd></div>
                        <div class="flex justify-between gap-2"><dt class="text-brand-muted">Source</dt><dd>AI Incident Database</dd></div>
                        <div class="flex justify-between gap-2"><dt class="text-brand-muted">Licence</dt><dd>CC BY-SA 4.0</dd></div>
                    </dl>
                </div>
                <div class="flex flex-col gap-2 text-sm">
                    <x-site.save-button type="incident" :slug="(string) $i->incident_id" :title="'#'.$i->incident_id.' '.$i->title" :url="$i->url()" meta="AI incident" />
                    <a href="{{ route('risk.incidents.export', ['format' => 'json', 'year' => $i->year]) }}" class="btn-secondary">Export {{ $i->year }} incidents (JSON)</a>
                    <button type="button" class="btn-secondary" data-copy-link>Copy link</button>
                </div>
                @if($domainId)<div class="text-sm"><p class="font-semibold text-brand-navy">Related policy context</p><p class="mt-1 text-brand-body">See which AI policies address this risk domain on the <a href="{{ route('risk.domain', $domainId) }}">domain page</a>.</p></div>@endif
            </div>
        </aside>
    </div>
</div>
@endsection
