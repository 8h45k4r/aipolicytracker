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
                <div class="py-4"><dt class="font-display text-lg text-brand-navy">{{ $s['id'] }} {{ $s['name'] }}</dt><dd class="mt-1 text-sm text-brand-body leading-6">{{ $s['description'] ?: '—' }}</dd></div>
                @endforeach
            </dl>
            <p class="mt-3 text-sm"><a href="{{ ($mit['navigator_url'] ?? 'https://airisk.mit.edu/navigator').'#/domain/'.$domain['id'] }}" rel="noopener">Explore this domain in the MIT AI Risk Navigator</a></p>
        </section>
        <aside class="lg:col-span-5">
            <div class="rule-strong pt-3"><h2 class="section-title">Recorded incidents</h2></div>
            <p class="mt-2 text-sm text-brand-body"><span class="font-mono tabular-nums text-2xl text-brand-navy">{{ $incidents !== null ? number_format($incidents) : '—' }}</span> <span class="meta">incidents classified under "{{ $domain['aiid_domain_label'] ?: $domain['name'] }}" in the AI Incident Database (snapshot {{ $aiid['snapshot_date'] ?? '—' }})</span></p>
            @if($trend->sum() > 0)<x-site.bar-chart :series="$trend" title="Incidents in this domain per year" class="mt-3" />@endif
        </aside>
    </div>

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
