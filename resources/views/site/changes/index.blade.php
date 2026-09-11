@extends('site.layouts.app')
@section('content')
<div class="container-site py-8">
    <x-site.breadcrumbs :items="$seo->breadcrumbs" />
    <div class="mt-2 flex flex-wrap items-end justify-between gap-3">
        <div><h1 class="text-2xl sm:text-3xl font-semibold tracking-tight text-brand-navy">AI policy change log</h1><p class="mt-2 max-w-3xl text-brand-body">Dated, source-backed updates: what changed, the practical impact, the status after the change and the official source. Urgent and high-impact items are separated from routine updates.</p></div>
        <div class="flex gap-2"><a href="{{ route('changes.feed') }}" class="btn-secondary" data-track="rss_click">RSS feed</a>
            <a href="#subscribe" class="btn-primary" data-track="newsletter_click">Email digest</a>
        </div>
    </div>
    <form method="get" action="{{ route('changes.index') }}" class="mt-5 grid gap-3 sm:grid-cols-4" data-autosubmit aria-label="Filter changes">
        <div class="sm:col-span-2"><label for="c-q" class="label">Keyword</label><input id="c-q" type="search" name="q" value="{{ $filters['q'] ?? '' }}" class="input" placeholder="e.g. deadline, GPAI, consultation"></div>
        <div><label for="c-j" class="label">Jurisdiction</label><select id="c-j" name="jurisdiction" class="input"><option value="">All</option>@foreach($jurisdictions as $j)<option value="{{ $j->slug }}" @selected(($filters['jurisdiction'] ?? '') === $j->slug)>{{ $j->name }}</option>@endforeach</select></div>
        <div><label for="c-i" class="label">Impact</label><select id="c-i" name="impact" class="input"><option value="">All</option>@foreach(['urgent','high','routine'] as $i)<option value="{{ $i }}" @selected($impact === $i)>{{ ucfirst($i) }}</option>@endforeach</select></div>
        <div class="sm:col-span-4 flex gap-2"><button type="submit" class="btn-primary">Apply</button><a href="{{ route('changes.index') }}" class="btn-secondary">Reset</a></div>
    </form>
    <x-site.subscribe-form id="subscribe" source="changes" class="mt-6" />
    <div class="mt-4 flex flex-wrap gap-2 text-sm" aria-label="Browse by year">@foreach($years as $y)<a class="chip" href="{{ route('changes.year', $y) }}">{{ $y }}</a>@endforeach</div>

    <div class="mt-8 grid gap-10 lg:grid-cols-3">
        <section class="lg:col-span-2 min-w-0" aria-labelledby="all-heading">
            <h2 id="all-heading" class="section-title">All changes <span class="text-sm font-normal text-brand-muted">({{ $changes->total() }})</span></h2>
            <div class="mt-1 divide-y divide-brand-line border-y border-brand-line">@forelse($changes as $c)<x-site.change-item :change="$c" />@empty<div class="py-6"><x-site.empty :reset="route('changes.index')" /></div>@endforelse</div>
            <nav class="mt-6" aria-label="Pagination">{{ $changes->links() }}</nav>
        </section>
        <aside aria-labelledby="urgent-heading">
            <h2 id="urgent-heading" class="section-title">Urgent and high-impact</h2>
            <ul class="mt-2 space-y-3 text-sm">@foreach($urgent as $u)<li><time class="font-mono text-xs text-brand-muted" datetime="{{ $u->occurred_on->toDateString() }}">{{ $u->occurred_on->format('j M Y') }}</time><div><a href="#{{ $u->slug }}" class="text-brand-navy hover:underline">{{ $u->title }}</a></div><div class="text-xs text-brand-muted">{{ $u->jurisdiction->name }}</div></li>@endforeach</ul>
            <div class="mt-6 card-flat p-4 text-sm"><p class="font-semibold text-brand-navy">How changes are detected</p><p class="mt-1 text-brand-body">Maintainers and contributors monitor official journals, regulator pages and consultations; every entry is recorded with its source and reviewed before publication. See the <a href="{{ route('methodology') }}">methodology</a>.</p></div>
            <div class="mt-4 text-sm"><a href="{{ url('/api/v1/changes') }}" class="text-brand-blue hover:underline">JSON API for changes</a></div>
        </aside>
    </div>
    <x-site.disclaimer class="mt-8" />
</div>
@endsection
