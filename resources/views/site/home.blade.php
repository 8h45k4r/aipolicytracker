@extends('site.layouts.app')
@section('content')
{{-- The masthead sits on a tinted wash that fades into the white page, so the hero
     reads as the front of a publication rather than as the first block of a list. --}}
<section class="border-b border-brand-line bg-gradient-to-b from-brand-paper to-white">
    <div class="container-site py-12 sm:py-16 grid gap-10 lg:grid-cols-12 lg:items-end">
        <div class="lg:col-span-8">
            <p class="eyebrow">AI governance intelligence</p>
            <h1 class="mt-3 font-display text-4xl sm:text-5xl lg:text-[3.4rem] leading-[1.05] font-semibold text-brand-navy max-w-[18ch]">From regulation to evidence.</h1>
            <p class="mt-5 max-w-[60ch] text-lg leading-8 text-brand-body">Track AI regulations and obligations, connect them to governance controls, risks and evidence, with every claim traceable to its source.</p>
            <p class="mt-2 max-w-[60ch] text-sm text-brand-muted"><span class="font-medium text-brand-navy">AI policy, verified at the source.</span> Every record links its official text and states when a person last checked it.</p>
            <form action="{{ route('policies.index') }}" method="get" role="search" class="mt-8 max-w-2xl" data-track="home_search">
                <label for="home-q" class="sr-only">Search AI policies</label>
                <div class="flex gap-2">
                    <input id="home-q" name="q" type="search" class="input flex-1" placeholder="Search laws, regulations, guidance, obligations…" autocomplete="off">
                    <button type="submit" class="btn-primary">Search</button>
                </div>
            </form>
            <div class="mt-4 flex flex-wrap gap-2" aria-label="Quick filters">
                @foreach($jurisdictions->take(8) as $j)<a class="chip" href="{{ route('policies.index', ['jurisdiction' => $j->slug]) }}" data-track="quick_filter" data-track-label="jurisdiction:{{ $j->slug }}">{{ $j->short_name ?: $j->name }}</a>@endforeach
                <a class="chip" href="{{ route('policies.index', ['status' => 'in_force']) }}" data-track="quick_filter">In force</a>
                <a class="chip" href="{{ route('policies.index', ['binding' => 'yes']) }}" data-track="quick_filter">Binding only</a>
                <a class="chip" href="{{ route('policies.index', ['use_case' => 'generative_ai']) }}" data-track="quick_filter">Generative AI</a>
                <a class="chip" href="{{ route('policies.index', ['sector' => 'public_services']) }}" data-track="quick_filter">Public sector</a>
            </div>
        </div>
        <aside class="lg:col-span-4 lg:border-l lg:border-brand-line lg:pl-8" aria-label="How to read this site">
            <p class="eyebrow">How it fits together</p>
            <p class="mt-3 text-sm leading-6 text-brand-body">A law creates a <a href="{{ route('obligations.index') }}">duty</a>. A duty is met by a <a href="{{ route('controls.index') }}">control</a>. A control produces <a href="{{ route('controls.index') }}#evidence">evidence</a>. Every record links its official source and states when a person last checked it.</p>
            <dl class="mt-4 divide-y divide-brand-line text-sm">
                <div class="flex items-baseline justify-between py-2"><dt class="text-brand-muted">Instruments linked to an official source</dt><dd class="font-mono tabular-nums text-brand-navy">{{ (int) ($stats['sourced'] ?? 0) }}<span class="text-brand-muted"> / {{ $stats['policies'] ?: '—' }}</span></dd></div>
                <div class="flex items-baseline justify-between py-2"><dt class="text-brand-muted"><a href="{{ route('verification') }}" class="no-underline hover:underline">Confirmed by a named reviewer</a></dt><dd class="font-mono tabular-nums text-brand-navy">{{ (int) $stats['verified'] }}<span class="text-brand-muted"> / {{ $stats['policies'] ?: '—' }}</span></dd></div>
                <div class="flex items-baseline justify-between py-2"><dt class="text-brand-muted">Corpus last updated</dt><dd class="font-mono tabular-nums text-brand-navy">{{ $stats['last_updated'] ? \Illuminate\Support\Carbon::parse($stats['last_updated'])->format('j M Y') : '—' }}</dd></div>
            </dl>
            <p class="mt-3 meta"><a href="{{ route('methodology') }}">How records are verified</a> · <a href="{{ route('coverage') }}">What is still missing</a></p>
        </aside>
    </div>
    <div class="container-site pb-10">
        <x-site.chain :stats="$stats" />
    </div>
</section>

<div class="container-site py-12 grid gap-12 lg:grid-cols-12">
    <x-site.latest-updates class="lg:col-span-8" :changes="$changes" title="Latest AI policy updates" :limit="6" />
    <aside class="lg:col-span-4" aria-labelledby="deadlines-heading">
        <div class="rule-strong pt-3"><h2 id="deadlines-heading" class="section-title">Upcoming dates</h2></div>
        <ul class="mt-2 divide-y divide-brand-line">
            @forelse($deadlines as $d)
            <li class="py-3 text-sm">
                <time datetime="{{ $d->due_on->toDateString() }}" class="datestamp">{{ $d->displayDate() }}</time>
                <div class="mt-0.5"><a href="{{ $d->policyInstrument->url() }}" class="text-brand-navy no-underline hover:underline font-medium">{{ $d->title }}</a></div>
                <div class="meta mt-0.5">{{ $d->policyInstrument->jurisdiction->name }} · {{ $d->policyInstrument->short_title ?: $d->policyInstrument->title }}@if($d->confidence_level !== 'high') · confidence {{ $d->confidence_level }}@endif</div>
            </li>
            @empty<li class="py-3 text-sm text-brand-muted">No scheduled dates recorded.</li>@endforelse
        </ul>
        @if(!empty($latestIncidents) && $latestIncidents->isNotEmpty())
        <div class="rule-strong pt-3 mt-8 flex items-baseline justify-between"><h2 class="section-title">Latest AI incidents</h2><a href="{{ route('risk.incidents.browse') }}" class="text-sm">Browse all</a></div>
        <ul class="mt-2 divide-y divide-brand-line">
            @foreach($latestIncidents as $i)<li class="py-3 text-sm"><time class="datestamp" datetime="{{ $i->occurred_on->toDateString() }}">{{ $i->occurred_on->format('j M Y') }}</time><div class="mt-0.5"><a href="{{ $i->url() }}" class="text-brand-navy no-underline hover:underline font-medium">{{ $i->displayTitle() }}</a></div><div class="meta mt-0.5">{{ $i->mit_domain ?: 'Unclassified' }}</div></li>@endforeach
        </ul>
        <p class="mt-2 meta">AI Incident Database, synced {{ $incidentSnapshot ? \Carbon\Carbon::parse($incidentSnapshot)->format('j M Y') : '—' }} · CC BY-SA 4.0</p>
        @endif
        <x-site.subscribe-form source="home" class="mt-8" />
    </aside>
</div>

<section class="container-site py-8" aria-labelledby="audience-heading">
    <div class="flex items-baseline justify-between rule-strong pt-3">
        <h2 id="audience-heading" class="section-title">Start from who you are</h2>
        <a href="{{ route('audiences.index') }}" class="text-sm">All roles, sectors and use cases</a>
    </div>
    <p class="mt-2 max-w-[64ch] text-sm text-brand-body">Every recorded duty that names your situation, the controls that meet them and the evidence a reviewer would expect. Generated from the records, so it moves when they do.</p>
    <ul class="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-4 text-sm">
        @foreach(collect($audiences)->sortByDesc('duties')->take(8) as $a)
        <li><a href="{{ route('audiences.show', $a['slug']) }}" class="card-link block p-4 no-underline h-full"><p class="eyebrow !text-brand-muted">{{ ['actor' => 'Role', 'sector' => 'Sector', 'use_case' => 'Use case'][$a['taxonomy']] ?? '' }}</p><p class="mt-1 font-semibold text-brand-navy leading-snug">{{ $a['h1'] }}</p><p class="mt-2 font-mono text-xs tabular-nums text-brand-muted">{{ $a['duties'] }} recorded {{ \Illuminate\Support\Str::plural('duty', $a['duties']) }}</p></a></li>
        @endforeach
    </ul>
    <div class="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-5 text-sm">
        @foreach([['Compliance or CISO', 'Which controls meet which duties, and the evidence to keep.', route('controls.index')], ['Researcher', 'Incidents, risk taxonomy and exports with citation.', route('risk.index')], ['Policymaker', 'Compare jurisdictions and track dated changes.', route('compare.index')], ['Civil society or journalist', 'Who is harmed, who deploys, where rules are missing.', route('risk.index').'#gap-heading'], ['Founder or product lead', 'Screen applicability, then a 90-day readiness path.', route('tools.applicability')]] as [$who, $what, $href])
        <a href="{{ $href }}" class="card-link p-4 no-underline"><p class="font-semibold text-brand-navy">{{ $who }}</p><p class="mt-1 text-brand-body">{{ $what }}</p></a>
        @endforeach
    </div>
</section>
<section class="container-site py-4" aria-labelledby="jurisdictions-heading">
    <div class="flex items-baseline justify-between rule-strong pt-3">
        <h2 id="jurisdictions-heading" class="section-title">Jurisdictions</h2>
        <a href="{{ route('jurisdictions.index') }}" class="text-sm">All jurisdictions</a>
    </div>
    <ul class="mt-2 divide-y divide-brand-line">
        @foreach($jurisdictions as $j)
        <li class="grid gap-1 py-3 sm:grid-cols-12 sm:gap-6 text-sm">
            <div class="sm:col-span-3"><a href="{{ $j->url() }}" class="font-display text-lg text-brand-navy no-underline hover:underline">{{ $j->name }}</a><p class="meta">{{ $j->region }} · {{ $j->policy_instruments_count ?: '—' }} {{ \Illuminate\Support\Str::plural('instrument', $j->policy_instruments_count) }}</p></div>
            <p class="sm:col-span-9 text-brand-body line-clamp-2">{{ $j->regulatory_status_summary }}</p>
        </li>
        @endforeach
    </ul>
</section>

<section class="container-site py-10" aria-labelledby="featured-heading">
    <div class="rule-strong pt-3"><h2 id="featured-heading" class="section-title">Key instruments</h2></div>
    <div class="mt-2 divide-y divide-brand-line border-b border-brand-line">
        @foreach($featuredPolicies as $p)<x-site.policy-row :policy="$p" />@endforeach
    </div>
</section>

<section class="container-site py-4" aria-labelledby="tools-heading">
    <div class="rule-strong pt-3"><h2 id="tools-heading" class="section-title">Tools</h2></div>
    <ul class="mt-2 grid sm:grid-cols-2 lg:grid-cols-4 divide-y sm:divide-y-0 sm:divide-x divide-brand-line border-b border-brand-line text-sm">
        <li class="py-4 sm:pr-6"><a href="{{ route('controls.index') }}" class="font-display text-lg text-brand-navy no-underline hover:underline">Controls and evidence</a><p class="mt-1 text-brand-body">One control, every duty it serves, and the evidence that shows it is operating.</p></li>
        <li class="py-4 sm:px-6"><a href="{{ route('tools.applicability') }}" class="font-display text-lg text-brand-navy no-underline hover:underline">Applicability check</a><p class="mt-1 text-brand-body">Educational screening of which policies and obligations may be relevant. Not legal advice.</p></li>
        <li class="py-4 sm:px-6"><a href="{{ route('compare.index') }}" class="font-display text-lg text-brand-navy no-underline hover:underline">Compare jurisdictions</a><p class="mt-1 text-brand-body">Side-by-side status, binding rules, high-risk and generative AI, oversight and dates.</p></li>
        <li class="py-4 sm:pl-6"><a href="{{ route('open-data') }}" class="font-display text-lg text-brand-navy no-underline hover:underline">Open data and API</a><p class="mt-1 text-brand-body">CC BY 4.0 dataset, JSON Schema, read-only API and citation guidance.</p></li>
    </ul>
</section>

<section class="container-site py-10 grid gap-8 md:grid-cols-2 text-sm">
    <div>
        <p class="eyebrow">Open source, open data</p>
        <p class="mt-2 max-w-[60ch] text-brand-body">Records live as reviewable YAML in a public repository. Propose corrections and sources through pull requests, or use the <a href="{{ route('contribute') }}">contribution form</a>.</p>
        <a href="{{ config('aipolicytracker.github_url') }}" rel="noopener" class="btn-secondary mt-4" data-track="github_click">View on GitHub</a>
    </div>
    <x-site.certifyi-cta label="Export obligations to a workflow tool" class="self-start" />
</section>
<div class="container-site pb-6"><x-site.disclaimer /></div>
@endsection
