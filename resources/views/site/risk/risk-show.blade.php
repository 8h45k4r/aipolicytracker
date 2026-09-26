@extends('site.layouts.app')
@section('content')
<div class="container-site py-8">
    <x-site.breadcrumbs :items="$seo->breadcrumbs" />
    <header class="mt-3">
        <p class="eyebrow">MIT AI Risk Repository · {{ $r->level }} · <span class="font-mono">{{ $r->ev_id }}</span></p>
        <h1 class="mt-2 text-2xl sm:text-3xl lg:text-4xl font-semibold tracking-tight text-brand-navy">{{ $r->risk_subcategory ?: $r->risk_category ?: 'Risk entry' }}</h1>
        @if($r->risk_subcategory && $r->risk_category)<p class="mt-1 text-sm text-brand-muted">Category: {{ $r->risk_category }}</p>@endif
        <div class="mt-3 flex flex-wrap items-center gap-x-4 gap-y-2 text-sm">
            <a href="{{ $r->navigatorUrl() }}" rel="noopener" class="text-brand-blue font-medium hover:underline" data-track="source_click">Open the MIT Risk Navigator</a>
            <a href="{{ route('risk.risks', ['paper' => $r->quick_ref]) }}" class="text-brand-body hover:underline">All entries from {{ $r->quick_ref }}</a>
        </div>
    </header>
    <div class="mt-8 grid gap-10 lg:grid-cols-3">
        <div class="lg:col-span-2 min-w-0">
            <section aria-labelledby="desc-heading">
                <h2 id="desc-heading" class="section-title">Description</h2>
                <p class="prose-policy mt-2">{{ $r->description ?: '—' }}</p>
                <p class="mt-2 text-sm text-brand-muted">From <span class="font-medium text-brand-body">{{ $r->paper_title }}</span> ({{ $r->quick_ref }}), as extracted by the MIT AI Risk Repository (CC BY 4.0).</p>
            </section>
            <section aria-labelledby="coding-heading" class="mt-8">
                <h2 id="coding-heading" class="section-title">Classification</h2>
                <dl class="mt-3 grid gap-x-8 gap-y-3 sm:grid-cols-2 text-sm">
                    <div><dt class="text-brand-muted">Domain</dt><dd class="mt-0.5">@if($domain)<a href="{{ \App\Support\RiskTaxonomy::domainUrl($domain['id']) }}" class="font-medium text-brand-navy">{{ $domain['id'] }}. {{ $domain['name'] }}</a>@else —@endif</dd></div>
                    <div><dt class="text-brand-muted">Subdomain</dt><dd class="mt-0.5">@if($subdomainMeta)<a href="{{ route('risk.risks', ['subdomain' => $r->subdomain]) }}" class="font-medium text-brand-navy">{{ $subdomainMeta['id'] }} {{ $subdomainMeta['name'] }}</a>@elseif($r->subdomain){{ $r->subdomain }}@else —@endif</dd></div>
                    @foreach(['entity' => 'Causal entity', 'intent' => 'Intent', 'timing' => 'Timing'] as $k => $label)
                    <div><dt class="text-brand-muted">{{ $label }}</dt><dd class="mt-0.5">@if($r->{$k})<a href="{{ route('risk.risks', [$k => $r->{$k}]) }}">{{ $r->{$k} }}</a>@else —@endif</dd></div>
                    @endforeach
                </dl>
                @if($subdomainMeta && !empty($subdomainMeta['description']))<p class="mt-4 rounded-sm bg-brand-paper border border-brand-line px-3 py-2 text-sm text-brand-body"><span class="font-medium">Subdomain definition:</span> {{ $subdomainMeta['description'] }}</p>@endif
            </section>
            @if($incidents->isNotEmpty())
            <section aria-labelledby="inc-heading" class="mt-8">
                <h2 id="inc-heading" class="section-title">Real-world incidents in this subdomain</h2>
                <ul class="mt-3 divide-y divide-brand-line border-y border-brand-line text-sm">@foreach($incidents as $i)<li class="py-3 flex flex-wrap gap-x-4"><time class="datestamp shrink-0" datetime="{{ $i->occurred_on->toDateString() }}">{{ $i->occurred_on->format('j M Y') }}</time><a href="{{ $i->url() }}" class="text-brand-navy">{{ $i->title }}</a></li>@endforeach</ul>
                <a href="{{ route('risk.incidents.browse', ['subdomain' => $subdomainMeta['name']]) }}" class="mt-2 inline-block text-sm text-brand-muted hover:text-brand-navy">Browse all incidents in this subdomain</a>
            </section>
            @endif
            @if($peers->isNotEmpty())
            <section aria-labelledby="peers-heading" class="mt-8">
                <h2 id="peers-heading" class="section-title">How other frameworks describe this risk</h2>
                <ul class="mt-3 divide-y divide-brand-line border-y border-brand-line text-sm">@foreach($peers as $p)<li class="py-3"><a href="{{ $p->url() }}" class="font-medium text-brand-navy">{{ $p->risk_subcategory ?: $p->risk_category }}</a><p class="meta mt-0.5">{{ $p->paper_title }} ({{ $p->quick_ref }})</p></li>@endforeach</ul>
            </section>
            @endif
            @if($siblings->isNotEmpty())
            <section aria-labelledby="sib-heading" class="mt-8">
                <h2 id="sib-heading" class="section-title">Other entries from {{ $r->quick_ref }}</h2>
                <ul class="mt-3 divide-y divide-brand-line border-y border-brand-line text-sm">@foreach($siblings as $p)<li class="py-2"><a href="{{ $p->url() }}" class="text-brand-navy">{{ $p->risk_subcategory ?: $p->risk_category ?: $p->ev_id }}</a> <span class="meta">{{ $p->level }}@if($p->subdomain) · {{ $p->subdomain }}@endif</span></li>@endforeach</ul>
            </section>
            @endif
            @php($mit = app(\App\Services\ExternalData\ExternalDataset::class)->mitRisk())
            <x-site.attribution class="mt-10" :name="$mit['source'] ?? 'MIT AI Risk Repository'" :url="$mit['source_url'] ?? 'https://airisk.mit.edu/'" :license="$mit['license'] ?? 'CC BY 4.0'" :licenseUrl="$mit['license_url'] ?? 'https://creativecommons.org/licenses/by/4.0/'" :citation="$mit['citation'] ?? null" note="The description is the repository's extracted evidence for this entry, reproduced under CC BY 4.0; the paper itself is not reproduced." />
        </div>
        <aside class="space-y-6">
            <div class="lg:sticky lg:top-4 space-y-6">
                <div class="card-flat p-4 text-sm">
                    <p class="font-semibold text-brand-navy">Record</p>
                    <dl class="mt-2 space-y-1.5 text-brand-body">
                        <div class="flex justify-between gap-2"><dt class="text-brand-muted">Entry ID</dt><dd class="font-mono">{{ $r->ev_id }}</dd></div>
                        <div class="flex justify-between gap-2"><dt class="text-brand-muted">Level</dt><dd>{{ $r->level }}</dd></div>
                        <div class="flex justify-between gap-2"><dt class="text-brand-muted">Paper</dt><dd class="text-right">{{ $r->quick_ref }}</dd></div>
                        @if($paper)<div class="flex justify-between gap-2"><dt class="text-brand-muted">Entries in paper</dt><dd>{{ $paper['risks'] ?? '—' }}</dd></div>@endif
                        <div class="flex justify-between gap-2"><dt class="text-brand-muted">Licence</dt><dd>CC BY 4.0</dd></div>
                    </dl>
                </div>
                <div class="flex flex-col gap-2 text-sm">
                    <x-site.save-button type="risk" :slug="$ev" :title="($r->risk_subcategory ?: $r->risk_category ?: 'Risk entry')" :url="$r->url()" :meta="\App\Support\PageTitle::citation($r->quick_ref)" />
                    <a href="{{ route('risk.risks.export', ['format' => 'json', 'paper' => $r->quick_ref]) }}" class="btn-secondary">Export this paper's entries (JSON)</a>
                    <a href="{{ route('contribute', ['type' => 'correction', 'subject_type' => 'risk', 'subject_slug' => str_replace('#', '--', $r->ev_id)]) }}" class="btn-secondary" data-track="correction_click">Report a correction</a>
                    <button type="button" class="btn-secondary" data-copy-link>Copy link</button>
                </div>
            </div>
        </aside>
    </div>
</div>
@endsection
