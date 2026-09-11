@extends('site.layouts.app')
@section('content')
<div class="container-site py-8">
    <x-site.breadcrumbs :items="$seo->breadcrumbs" />
    <p class="eyebrow mt-3">AI risk domain {{ $domain['id'] }}</p>
    <h1 class="mt-2 font-display text-3xl sm:text-4xl font-semibold text-brand-navy max-w-[24ch]">{{ $domain['name'] }}</h1>
    @if($domain['description'])<p class="mt-4 max-w-[64ch] text-brand-body leading-7">{{ $domain['description'] }}</p>@endif

    <div class="mt-10 grid gap-10 lg:grid-cols-12">
        <section class="lg:col-span-7" aria-labelledby="sub-heading">
            <div class="rule-strong pt-3"><h2 id="sub-heading" class="section-title">Subdomains</h2></div>
            <dl class="mt-2 divide-y divide-brand-line">
                @foreach($domain['subdomains'] as $s)
                <div class="py-4"><dt class="font-display text-lg text-brand-navy"><a href="{{ route('risk.subdomain', [$domain['id'], $s['id']]) }}" class="no-underline hover:underline">{{ $s['id'] }} {{ $s['name'] }}</a></dt><dd class="mt-1 text-sm text-brand-body leading-6">{{ $s['description'] ?: '—' }}</dd>
                    <dd class="mt-2 flex flex-wrap gap-2 text-xs"><a class="chip chip-active !min-h-0 !py-0.5" href="{{ route('risk.subdomain', [$domain['id'], $s['id']]) }}">Profile and drilldown</a><a class="chip !min-h-0 !py-0.5" href="{{ route('risk.risks', ['subdomain' => $s['id']]) }}">{{ isset($subRisks[$s['id']]) ? number_format($subRisks[$s['id']]) : '—' }} risk entries</a><a class="chip !min-h-0 !py-0.5" href="{{ route('risk.incidents.browse', ['subdomain' => $s['name']]) }}">{{ isset($subIncidents[$s['name']]) ? number_format($subIncidents[$s['name']]) : '—' }} incidents</a></dd></div>
                @endforeach
            </dl>
            <p class="mt-3 text-sm"><a href="{{ ($mit['navigator_url'] ?? 'https://airisk.mit.edu/navigator').'#/domain/'.$domain['id'] }}" rel="noopener">Explore this domain in the MIT AI Risk Navigator</a></p>
        </section>
        <aside class="lg:col-span-5">
            <div class="rule-strong pt-3"><h2 class="section-title">Recorded incidents</h2></div>
            <p class="mt-2 text-sm text-brand-body"><span class="font-mono tabular-nums text-2xl text-brand-navy">{{ $incidents !== null ? number_format($incidents) : '—' }}</span> <span class="meta">incidents classified under "{{ $domain['aiid_domain_label'] ?: $domain['name'] }}" in the AI Incident Database (snapshot {{ $aiid['snapshot_date'] ?? '—' }})</span></p>
            @if($trend->sum() > 0)<x-site.bar-chart :series="$trend" title="Incidents in this domain per year" class="mt-3" />@endif
            <p class="mt-3 text-sm"><a href="{{ route('risk.incidents.browse', ['domain' => $domain['aiid_domain_label']]) }}">Browse and export these incidents</a></p>
            <div class="rule-strong pt-3 mt-8"><h2 class="section-title">Risk entries</h2></div>
            <p class="mt-2 text-sm text-brand-body"><span class="font-mono tabular-nums text-2xl text-brand-navy">{{ $riskCount ? number_format($riskCount) : '—' }}</span> <span class="meta">entries coded to this domain in the MIT database</span></p>
            @if($topPapers->isNotEmpty())<p class="mt-2 meta">Most-cited frameworks here:</p><ul class="mt-1 text-sm divide-y divide-brand-line">@foreach($topPapers as $p)<li class="py-1.5 flex justify-between gap-3"><a href="{{ route('risk.risks', ['paper' => $p->quick_ref, 'domain' => $domain['id']]) }}">{{ \Illuminate\Support\Str::limit($p->title, 60) }}</a><span class="font-mono tabular-nums text-brand-navy">{{ $p->n }}</span></li>@endforeach</ul>@endif
            <p class="mt-3 text-sm"><a href="{{ route('risk.risks', ['domain' => $domain['id']]) }}">Browse and export these risk entries</a></p>
        </aside>
    </div>

    @if($recent->isNotEmpty())
    <section class="mt-12" aria-labelledby="recent-heading">
        <div class="rule-strong pt-3"><h2 id="recent-heading" class="section-title">Recent incidents in this domain</h2></div>
        <ol class="mt-2 divide-y divide-brand-line">@foreach($recent as $i)<li class="py-3 grid gap-1 sm:grid-cols-12 sm:gap-4 text-sm"><time class="datestamp sm:col-span-2" datetime="{{ $i->occurred_on->toDateString() }}">{{ $i->occurred_on->format('j M Y') }}</time><div class="sm:col-span-8"><a href="{{ $i->url() }}" class="text-brand-navy no-underline hover:underline">{{ $i->title }}</a></div><div class="sm:col-span-2 meta">{{ \Illuminate\Support\Str::limit($i->mit_subdomain ?: '—', 34) }}</div></li>@endforeach</ol>
    </section>
    @endif

    <section class="mt-12" aria-labelledby="policies-heading">
        <div class="rule-strong pt-3 flex items-baseline justify-between"><h2 id="policies-heading" class="section-title">Policies addressing related use cases</h2><span class="meta">Editorial mapping by AIPolicyTracker</span></div>
        @if($domain['use_cases'])<p class="mt-2 meta">Use cases: @foreach($domain['use_cases'] as $u)<a class="chip !min-h-0 !py-0.5 mr-1" href="{{ route('policies.index', ['use_case' => $u]) }}">{{ str_replace('_', ' ', $u) }}</a>@endforeach</p>@endif
        <div class="mt-2 divide-y divide-brand-line border-b border-brand-line">
            @forelse($policies as $p)<x-site.policy-row :policy="$p" />@empty<div class="py-6"><x-site.empty title="No mapped policies yet">Records tagged with these use cases will appear here as reviewers enrich the dataset.</x-site.empty></div>@endforelse
        </div>
    </section>

    <x-site.attribution class="mt-12" :name="$mit['source'] ?? 'MIT AI Risk Repository'" :url="$mit['source_url'] ?? 'https://airisk.mit.edu/'" :license="$mit['license'] ?? 'CC BY 4.0'" :licenseUrl="$mit['license_url'] ?? 'https://creativecommons.org/licenses/by/4.0/'" :citation="$mit['citation'] ?? null" :note="$mit['changes_note'] ?? null" />
    <x-site.disclaimer class="mt-6" />
</div>
@endsection
