@extends('site.layouts.app')
@section('content')
<section class="border-b border-slate-200 bg-slate-50">
    <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 py-12 sm:py-16">
        <p class="eyebrow">Open, source-backed AI policy intelligence</p>
        <h1 class="mt-2 text-3xl sm:text-4xl lg:text-5xl font-semibold tracking-tight text-slate-900 max-w-3xl">Track AI policy. Build compliant AI.</h1>
        <p class="mt-4 max-w-2xl text-base sm:text-lg text-slate-700">{{ config('aipolicytracker.supporting') }}</p>
        <form action="{{ route('policies.index') }}" method="get" role="search" class="mt-6 max-w-2xl" data-track="home_search">
            <label for="home-q" class="sr-only">Search AI policies</label>
            <div class="flex gap-2">
                <input id="home-q" name="q" type="search" class="input flex-1" placeholder="Search laws, regulations, standards, obligations…" autocomplete="off">
                <button type="submit" class="btn-primary">Search</button>
            </div>
        </form>
        <div class="mt-4 flex flex-wrap gap-2" aria-label="Quick filters">
            @foreach($options['jurisdictions']->where('featured', true)->take(0) as $j)@endforeach
            @foreach($jurisdictions->take(8) as $j)<a class="chip" href="{{ route('policies.index', ['jurisdiction' => $j->slug]) }}" data-track="quick_filter" data-track-label="jurisdiction:{{ $j->slug }}">{{ $j->short_name ?: $j->name }}</a>@endforeach
            <a class="chip" href="{{ route('policies.index', ['status' => 'in_force']) }}" data-track="quick_filter">In force</a>
            <a class="chip" href="{{ route('policies.index', ['binding' => 'yes']) }}" data-track="quick_filter">Binding only</a>
            <a class="chip" href="{{ route('policies.index', ['use_case' => 'generative_ai']) }}" data-track="quick_filter">Generative AI</a>
            <a class="chip" href="{{ route('policies.index', ['use_case' => 'hiring_and_hr']) }}" data-track="quick_filter">Hiring</a>
            <a class="chip" href="{{ route('policies.index', ['actor' => 'deployer']) }}" data-track="quick_filter">Deployers</a>
            <a class="chip" href="{{ route('policies.index', ['sector' => 'public_services']) }}" data-track="quick_filter">Public sector</a>
        </div>
        <dl class="mt-8 grid grid-cols-2 sm:grid-cols-4 gap-4 max-w-2xl text-sm">
            <div><dt class="text-slate-500">Jurisdictions</dt><dd class="text-xl font-semibold text-slate-900">{{ $stats['jurisdictions'] }}</dd></div>
            <div><dt class="text-slate-500">Policy instruments</dt><dd class="text-xl font-semibold text-slate-900">{{ $stats['policies'] }}</dd></div>
            <div><dt class="text-slate-500">Obligations</dt><dd class="text-xl font-semibold text-slate-900">{{ $stats['obligations'] }}</dd></div>
            <div><dt class="text-slate-500">Human-verified</dt><dd class="text-xl font-semibold text-slate-900">{{ $stats['verified'] }} <span class="text-xs font-normal text-slate-500">of {{ $stats['policies'] }}</span></dd></div>
        </dl>
    </div>
</section>

<div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 py-10 grid gap-10 lg:grid-cols-3">
    <section class="lg:col-span-2" aria-labelledby="changes-heading">
        <div class="flex items-baseline justify-between">
            <h2 id="changes-heading" class="section-title">Latest policy changes</h2>
            <a href="{{ route('changes.index') }}" class="text-sm text-teal-800 hover:underline">All changes and RSS</a>
        </div>
        @forelse($changes as $change)<x-site.change-item :change="$change" compact />@empty<x-site.empty title="No changes recorded yet" />@endforelse
    </section>
    <aside aria-labelledby="deadlines-heading">
        <h2 id="deadlines-heading" class="section-title">Upcoming dates</h2>
        <ul class="mt-3 space-y-3">
            @forelse($deadlines as $d)
            <li class="text-sm">
                <time datetime="{{ $d->due_on->toDateString() }}" class="font-mono text-slate-800">{{ $d->displayDate() }}</time>
                <div><a href="{{ $d->policyInstrument->url() }}" class="text-slate-900 hover:underline">{{ $d->title }}</a></div>
                <div class="text-xs text-slate-500">{{ $d->policyInstrument->jurisdiction->name }} · {{ $d->policyInstrument->short_title ?: $d->policyInstrument->title }}@if($d->confidence_level !== 'high') · confidence: {{ $d->confidence_level }}@endif</div>
            </li>
            @empty<li class="text-sm text-slate-600">No scheduled dates recorded.</li>@endforelse
        </ul>
        <div class="mt-6 rounded-lg border border-slate-200 p-4 text-sm">
            <p class="font-semibold text-slate-900">Trust, by design</p>
            <ul class="mt-2 space-y-1 text-slate-700">
                <li>Primary official source on every record</li>
                <li>Status and last-verified date shown near the top</li>
                <li>Version history in the open <a href="{{ config('aipolicytracker.github_url') }}" rel="noopener" data-track="github_click">GitHub repository</a></li>
                <li><a href="{{ route('methodology') }}">Open methodology</a> and review levels</li>
            </ul>
        </div>
    </aside>
</div>

<section class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 py-4" aria-labelledby="jurisdictions-heading">
    <div class="flex items-baseline justify-between">
        <h2 id="jurisdictions-heading" class="section-title">Featured jurisdictions</h2>
        <a href="{{ route('jurisdictions.index') }}" class="text-sm text-teal-800 hover:underline">All jurisdictions</a>
    </div>
    <ul class="mt-3 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
        @foreach($jurisdictions as $j)
        <li class="card-flat p-4">
            <a href="{{ $j->url() }}" class="font-semibold text-slate-900 hover:underline">{{ $j->name }}</a>
            <p class="mt-1 text-xs text-slate-500">{{ $j->policy_instruments_count }} {{ \Illuminate\Support\Str::plural('instrument', $j->policy_instruments_count) }} · {{ $j->region }}</p>
            <p class="mt-2 text-sm text-slate-700 line-clamp-3">{{ $j->regulatory_status_summary }}</p>
        </li>
        @endforeach
    </ul>
</section>

<section class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 py-10" aria-labelledby="featured-heading">
    <h2 id="featured-heading" class="section-title">Key instruments</h2>
    <div class="mt-2 divide-y divide-slate-200 border-y border-slate-200">
        @foreach($featuredPolicies as $p)<x-site.policy-row :policy="$p" />@endforeach
    </div>
</section>

<section class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 py-4" aria-labelledby="tools-heading">
    <h2 id="tools-heading" class="section-title">Tools</h2>
    <ul class="mt-3 grid gap-3 sm:grid-cols-2 lg:grid-cols-4 text-sm">
        <li class="card-flat p-4"><a href="{{ route('compare.index') }}" class="font-semibold text-slate-900 hover:underline">Compare jurisdictions</a><p class="mt-1 text-slate-700">Side-by-side status, binding rules, high-risk and generative AI, oversight and dates.</p></li>
        <li class="card-flat p-4"><a href="{{ route('tools.applicability') }}" class="font-semibold text-slate-900 hover:underline">Applicability check</a><p class="mt-1 text-slate-700">Educational screening of which policies and obligations may be relevant. Not legal advice.</p></li>
        <li class="card-flat p-4"><a href="{{ route('changes.index') }}" class="font-semibold text-slate-900 hover:underline">Change log</a><p class="mt-1 text-slate-700">Dated, source-backed updates with practical impact, filters and RSS.</p></li>
        <li class="card-flat p-4"><a href="{{ route('open-data') }}" class="font-semibold text-slate-900 hover:underline">Open data and API</a><p class="mt-1 text-slate-700">CC BY 4.0 dataset, JSON Schema, read-only API and citation guidance.</p></li>
    </ul>
</section>

<section class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 py-10 grid gap-6 md:grid-cols-2">
    <div class="rounded-lg border border-slate-200 p-5 text-sm">
        <p class="font-semibold text-slate-900">Open source, open data</p>
        <p class="mt-2 text-slate-700">Records live as reviewable YAML in a public repository. Propose corrections and sources through pull requests, or use the <a href="{{ route('contribute') }}">contribution form</a>.</p>
        <a href="{{ config('aipolicytracker.github_url') }}" rel="noopener" class="btn-secondary mt-4" data-track="github_click">View on GitHub</a>
    </div>
    <x-site.certifyi-cta label="Export obligations to a workflow tool" class="self-start" />
</section>
<div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 pb-6"><x-site.disclaimer /></div>
@endsection
