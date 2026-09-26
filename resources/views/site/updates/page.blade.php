@extends('site.layouts.app')
@section('content')
@php($isPaginated = $changes instanceof \Illuminate\Contracts\Pagination\Paginator)
@php($list = $isPaginated ? $changes->getCollection() : $changes)
<div class="container-site py-8">
    <x-site.breadcrumbs :items="$seo->breadcrumbs" />
    <div class="mt-2 flex flex-wrap items-end justify-between gap-3">
        <div>
            <p class="eyebrow">AI policy updates</p>
            <h1 class="mt-1 text-2xl sm:text-3xl font-semibold tracking-tight text-brand-navy">{{ $heading }}</h1>
        </div>
        <div class="flex gap-2">
            <a href="{{ $feedUrl }}" class="btn-secondary" data-track="rss_click">RSS{{ $context === 'jurisdiction' ? ' for '.($jurisdiction->short_name ?: $jurisdiction->name) : '' }}</a>
            <a href="#subscribe" class="btn-primary" data-track="newsletter_click">Weekly digest</a>
        </div>
    </div>

    {{-- The answer box: computed from the records on the page, never written by hand. --}}
    <section class="mt-5 card-flat p-5" aria-labelledby="summary-heading">
        <h2 id="summary-heading" class="sr-only">Summary</h2>
        <p class="text-brand-navy text-base sm:text-lg leading-relaxed">{{ $summary['sentence'] }}</p>
        <dl class="mt-4 grid grid-cols-2 gap-4 sm:grid-cols-4">
            <x-site.stat label="Changes" :value="number_format($summary['total'])" />
            <x-site.stat label="Jurisdictions" :value="number_format($summary['jurisdictions'])" />
            <x-site.stat label="Entered into force" :value="number_format($summary['in_force'])" />
            <x-site.stat label="Urgent or high impact" :value="number_format($summary['urgent'] + $summary['high'])" />
        </dl>
        @if($summary['updated_at'])
        <p class="mt-3 text-xs text-brand-muted">Last updated <time datetime="{{ $summary['updated_at']->toAtomString() }}">{{ $summary['updated_at']->format('j M Y, H:i') }} UTC</time>@if($summary['latest_on']) · most recent change {{ $summary['latest_on']->format('j M Y') }}@endif</p>
        @endif
    </section>

    @if($context === 'hub')
    <form method="get" action="{{ route('updates.index') }}" class="mt-5 grid gap-3 sm:grid-cols-4" data-autosubmit aria-label="Filter updates">
        <div class="sm:col-span-2"><label for="u-j" class="label">Jurisdiction</label><select id="u-j" name="jurisdiction" class="input"><option value="">All jurisdictions</option>@foreach($jurisdictions as $j)<option value="{{ $j->slug }}" @selected(($filters['jurisdiction'] ?? '') === $j->slug)>{{ $j->name }}</option>@endforeach</select></div>
        <div><label for="u-i" class="label">Impact</label><select id="u-i" name="impact" class="input"><option value="">All</option>@foreach(['urgent','high','routine'] as $i)<option value="{{ $i }}" @selected(($filters['impact'] ?? '') === $i)>{{ ucfirst($i) }}</option>@endforeach</select></div>
        <div class="flex items-end gap-2"><button type="submit" class="btn-primary">Apply</button><a href="{{ route('updates.index') }}" class="btn-secondary">Reset</a></div>
    </form>
    @endif

    @if(isset($prev) || isset($next))
    <nav class="mt-4 flex flex-wrap justify-between gap-2 text-sm" aria-label="Adjacent months">
        <span>@if(!empty($prev))<a href="{{ $prev['url'] }}" class="chip">← {{ $prev['label'] }}</a>@endif</span>
        <span>@if(!empty($next))<a href="{{ $next['url'] }}" class="chip">{{ $next['label'] }} →</a>@endif</span>
    </nav>
    @endif

    <div class="mt-8 grid gap-10 lg:grid-cols-3">
        <section class="lg:col-span-2 min-w-0" aria-labelledby="list-heading">
            <h2 id="list-heading" class="section-title">{{ $context === 'hub' ? 'Latest' : 'All' }} changes <span class="text-sm font-normal text-brand-muted">({{ $isPaginated ? $changes->total() : $list->count() }})</span></h2>
            <div class="mt-1 divide-y divide-brand-line border-y border-brand-line">
                @forelse($list as $c)
                    <x-site.change-item :change="$c" />
                @empty
                    <div class="py-6"><x-site.empty :reset="route('updates.index')" /></div>
                @endforelse
            </div>
            @if($isPaginated)<nav class="mt-6" aria-label="Pagination">{{ $changes->links() }}</nav>@endif
        </section>
        <aside>
            @if($summary['top']->isNotEmpty())
            <section aria-labelledby="top-heading">
                <h2 id="top-heading" class="section-title">Top stories</h2>
                <ol class="mt-2 space-y-3 text-sm">
                    @foreach($summary['top'] as $t)
                    <li class="flex gap-3">
                        <span class="shrink-0 rounded-sm bg-brand-navy px-1.5 py-0.5 font-mono text-[11px] font-semibold text-white" title="Significance, 0 to 100" aria-label="Significance {{ $scoreOf($t) }} of 100">{{ $scoreOf($t) }}</span>
                        <div>
                            <a href="{{ $t->url() }}" class="text-brand-navy hover:underline">{{ $t->title }}</a>
                            <div class="text-xs text-brand-muted"><time datetime="{{ $t->occurred_on->toDateString() }}">{{ $t->occurred_on->format('j M Y') }}</time> · {{ $t->jurisdiction?->name }}</div>
                        </div>
                    </li>
                    @endforeach
                </ol>
                <p class="mt-2 text-xs text-brand-muted">Ranked by a <a href="{{ route('methodology') }}#significance">published rule</a>: impact, binding force, status, review and recency.</p>
            </section>
            @endif

            <section aria-labelledby="months-heading" class="mt-8">
                <h2 id="months-heading" class="section-title">By month</h2>
                <ul class="mt-2 flex flex-wrap gap-2 text-sm">
                    @foreach($months->take(18) as $key => $m)
                    <li><a class="chip {{ ($context === 'month' && str_contains(url()->current(), $key)) ? 'chip-active' : '' }}" href="{{ route('updates.month', $key) }}">{{ \Carbon\Carbon::parse($key.'-01')->format('M Y') }} <span class="text-brand-muted">{{ $m['count'] }}</span></a></li>
                    @endforeach
                </ul>
                <p class="mt-2 text-sm"><a href="{{ route('changes.index') }}" class="text-brand-blue hover:underline">Full change log</a> · <a href="{{ route('newsletter.index') }}" class="text-brand-blue hover:underline">Digest archive</a> · <a href="{{ route('calendar') }}" class="text-brand-blue hover:underline">Deadline calendar</a></p>
            </section>

            @if($context !== 'jurisdiction')
            <section aria-labelledby="places-heading" class="mt-8">
                <h2 id="places-heading" class="section-title">By jurisdiction</h2>
                <ul class="mt-2 flex flex-wrap gap-2 text-sm">
                    @foreach($list->pluck('jurisdiction')->filter()->unique('id')->sortBy('name')->take(12) as $j)
                    <li><a class="chip" href="{{ route('updates.jurisdiction', $j->slug) }}">{{ $j->short_name ?: $j->name }}</a></li>
                    @endforeach
                </ul>
            </section>
            @else
            <section aria-labelledby="place-heading" class="mt-8">
                <h2 id="place-heading" class="section-title">{{ $jurisdiction->name }}</h2>
                <ul class="mt-2 space-y-1 text-sm">
                    <li><a href="{{ $jurisdiction->url() }}" class="text-brand-blue hover:underline">AI regulation in {{ $jurisdiction->nameWithArticle() }}</a></li>
                    <li><a href="{{ route('policies.index', ['jurisdiction' => $jurisdiction->slug]) }}" class="text-brand-blue hover:underline">Every recorded instrument</a></li>
                    <li><a href="{{ route('calendar.feed.jurisdiction', $jurisdiction->slug) }}" class="text-brand-blue hover:underline">Deadline calendar (.ics)</a></li>
                </ul>
            </section>
            @endif

            <x-site.subscribe-form id="subscribe" source="updates" class="mt-8" />
        </aside>
    </div>

    <x-site.faq :items="$seo->faqItems()" />
    <x-site.disclaimer class="mt-8" />
</div>
@endsection
