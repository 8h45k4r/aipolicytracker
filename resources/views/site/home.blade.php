@extends('site.layouts.app')
@section('content')
{{-- The masthead sits on a tinted wash that fades into the white page, so the hero
     reads as the front of a publication rather than as the first block of a list. --}}
<section class="border-b border-brand-line bg-gradient-to-b from-brand-paper to-white">
    <div class="container-site py-8 sm:py-16 grid gap-8 sm:gap-10 lg:grid-cols-12 lg:items-end">
        <div class="lg:col-span-8">
            {{-- The kicker is part of the heading, so the phrase people search for (an AI
                 regulation or legislation tracker) is what the page says it is. --}}
            <h1 class="font-display font-semibold text-brand-navy"><span class="eyebrow block">Free global AI regulation and legislation tracker</span> <span class="mt-3 block text-4xl sm:text-5xl lg:text-[3.4rem] leading-[1.05] max-w-[18ch]">From regulation to evidence.</span></h1>
            <p class="mt-5 max-w-[60ch] text-lg leading-8 text-brand-body">Track AI laws, regulations and the obligations they create in every jurisdiction on record. Connect them to governance controls, risks and evidence. Every claim links to its source.</p>
            <p class="mt-2 max-w-[60ch] text-sm text-brand-muted"><span class="font-medium text-brand-navy">AI policy, verified at the source.</span> Every record links its official text and states when a person last checked it.</p>
            <form action="{{ route('policies.index') }}" method="get" role="search" class="mt-8 max-w-2xl" data-track="home_search">
                <label for="home-q" class="sr-only">Search AI policies</label>
                <div class="flex gap-2">
                    <input id="home-q" name="q" type="search" class="input flex-1" placeholder="Search laws, regulations, guidance, obligations…" autocomplete="off">
                    <button type="submit" class="btn-primary">Search</button>
                </div>
            </form>
            {{-- Where to go next, by the reason a reader came: one line, next to the search,
                 instead of a section most readers never scrolled to. --}}
            <nav class="mt-5" aria-labelledby="start-heading">
                <h2 id="start-heading" class="eyebrow !text-brand-muted">Where should you start?</h2>
                <ul class="mt-2 grid grid-cols-2 lg:grid-cols-4 gap-x-4 gap-y-2 text-sm">
                    @foreach([
                        ['Compliance & legal', [['Obligations', route('obligations.index')], ['Applicability check', route('tools.applicability')]]],
                        ['Policy & government', [['Compare', route('compare.index')], ['Jurisdictions', route('jurisdictions.index')]]],
                        ['Research', [['Open data', route('open-data')], ['AI risk', route('risk.index')]]],
                        ['Journalists', [['Updates', route('updates.index')], ['Change log', route('changes.index')]]],
                    ] as [$who, $links])
                    <li class="border-l-2 border-brand-line pl-3"><span class="block font-semibold text-brand-navy">{{ $who }}</span>@foreach($links as [$label, $href])<a href="{{ $href }}" data-track="home_persona" data-track-label="{{ $who }}: {{ $label }}">{{ $label }}</a>@if(! $loop->last) <span class="text-brand-muted" aria-hidden="true">·</span> @endif @endforeach</li>
                    @endforeach
                </ul>
            </nav>
            <div class="mt-4 flex flex-wrap gap-2" aria-label="Quick filters">
                @foreach($jurisdictions->take(8) as $j)<a class="chip {{ $loop->index >= 4 ? 'hidden sm:inline-flex' : '' }}" href="{{ route('policies.index', ['jurisdiction' => $j->slug]) }}" data-track="quick_filter" data-track-label="jurisdiction:{{ $j->slug }}">{{ $j->short_name ?: $j->name }}</a>@endforeach
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
    <div class="container-site pb-8 sm:pb-10">
        <x-site.chain :stats="$stats" />
    </div>
</section>

<div class="container-site py-12 grid gap-12 lg:grid-cols-12">
    <x-site.latest-updates class="lg:col-span-8" :changes="$changes" title="Latest AI policy updates" :limit="6" intro="The six most recent dated changes to AI laws and guidance, each linked to the record it changed and to its official source." />
    <aside class="lg:col-span-4" aria-labelledby="deadlines-heading">
        <div class="rule-strong pt-3"><h2 id="deadlines-heading" class="section-title">Which dates are coming up?</h2></div>
        <p class="mt-2 text-sm text-brand-body">The next application and compliance dates on record, each with the instrument it belongs to.</p>
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
        <p class="mt-2 text-sm text-brand-body">The most recent incidents from the AI Incident Database, which this site syncs every day.</p>
        <ul class="mt-2 divide-y divide-brand-line">
            @foreach($latestIncidents as $i)<li class="py-3 text-sm"><time class="datestamp" datetime="{{ $i->occurred_on->toDateString() }}">{{ $i->occurred_on->format('j M Y') }}</time><div class="mt-0.5"><a href="{{ $i->url() }}" class="text-brand-navy no-underline hover:underline font-medium">{{ $i->displayTitle() }}</a></div><div class="meta mt-0.5">{{ $i->mit_domain ?: 'Unclassified' }}</div></li>@endforeach
        </ul>
        <p class="mt-2 meta">AI Incident Database, synced {{ $incidentSnapshot ? \Carbon\Carbon::parse($incidentSnapshot)->format('j M Y') : '—' }} · CC BY-SA 4.0</p>
        @endif
        <x-site.subscribe-form source="home" class="mt-8" />
    </aside>
</div>

{{-- Roles, featured jurisdictions and featured instruments as compact link lists: the
     full tables live on their own pages, a link away, so the home page stays short. --}}
<section class="container-site py-8 grid gap-8 lg:grid-cols-3 text-sm" aria-label="Browse by role, jurisdiction or instrument">
    <div>
        <div class="flex items-baseline justify-between rule-strong pt-3"><h2 id="audience-heading" class="section-title">By role, sector or use case</h2><a href="{{ route('audiences.index') }}" class="text-sm">All</a></div>
        <p class="mt-2 text-brand-body">Every recorded duty that names your situation, with the controls that meet it.</p>
        <ul class="mt-2 divide-y divide-brand-line">
            @foreach(collect($audiences)->sortByDesc('duties')->take(6) as $a)
            <li class="flex items-baseline justify-between gap-3 py-2"><a href="{{ route('audiences.show', $a['slug']) }}" class="text-brand-navy no-underline hover:underline">{{ $a['h1'] }}</a><span class="shrink-0 font-mono text-xs tabular-nums text-brand-muted">{{ $a['duties'] }} {{ \Illuminate\Support\Str::plural('duty', $a['duties']) }}</span></li>
            @endforeach
        </ul>
    </div>
    <div>
        <div class="flex items-baseline justify-between rule-strong pt-3"><h2 id="jurisdictions-heading" class="section-title">Jurisdictions</h2><a href="{{ route('jurisdictions.index') }}" class="text-sm">All jurisdictions</a></div>
        <p class="mt-2 text-brand-body">The jurisdictions the editors feature, with the number of instruments on record.</p>
        <ul class="mt-2 divide-y divide-brand-line">
            @foreach($jurisdictions->take(6) as $j)
            <li class="flex items-baseline justify-between gap-3 py-2"><a href="{{ $j->url() }}" class="text-brand-navy no-underline hover:underline">{{ $j->name }}</a><span class="shrink-0 font-mono text-xs tabular-nums text-brand-muted">{{ $j->policy_instruments_count ?: '—' }} {{ \Illuminate\Support\Str::plural('instrument', $j->policy_instruments_count) }}</span></li>
            @endforeach
        </ul>
    </div>
    <div>
        <div class="flex items-baseline justify-between rule-strong pt-3"><h2 id="featured-heading" class="section-title">Key instruments</h2><a href="{{ route('policies.index') }}" class="text-sm">All policies</a></div>
        <p class="mt-2 text-brand-body">The instruments the editors feature, each linked to its record and official text.</p>
        <ul class="mt-2 divide-y divide-brand-line">
            @foreach($featuredPolicies as $p)
            <li class="py-2"><a href="{{ $p->url() }}" class="text-brand-navy no-underline hover:underline">{{ $p->short_title ?: $p->title }}</a> <span class="meta">{{ $p->jurisdiction->short_name ?: $p->jurisdiction->name }}</span></li>
            @endforeach
        </ul>
    </div>
</section>

<section class="container-site py-4" aria-labelledby="tools-heading">
    <div class="rule-strong pt-3"><h2 id="tools-heading" class="section-title">What can you do with the records?</h2></div>
    <p class="mt-2 max-w-[64ch] text-sm text-brand-body">Four tools built on the same records: map controls to duties, screen which rules may apply to you, compare jurisdictions side by side, and take the data away.</p>
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
@if($seo->faqItems())
{{-- The same questions the FAQPage block in <head> carries, as disclosure widgets, so
     they stay on the page for readers and crawlers without taking a screen of height.
     Marking the request stops the layout printing them a second time. --}}
@php(request()->attributes->set('faq.rendered', true))
<section class="container-site py-6" aria-labelledby="faq-heading">
    <h2 id="faq-heading" class="section-title">Frequently asked questions</h2>
    <div class="mt-3 divide-y divide-brand-line border-y border-brand-line">
        @foreach($seo->faqItems() as $item)
        <details class="group py-3">
            <summary class="cursor-pointer font-medium text-brand-navy">{{ $item['question'] }}</summary>
            <p class="mt-2 max-w-[70ch] text-sm text-brand-body">{{ trim($item['answer']) }}</p>
        </details>
        @endforeach
    </div>
</section>
@endif
<div class="container-site pb-6"><x-site.disclaimer /></div>
@endsection
