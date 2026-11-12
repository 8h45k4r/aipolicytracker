@extends('site.layouts.app')
@section('content')
<div class="container-site py-8 max-w-4xl">
    <x-site.breadcrumbs :items="$seo->breadcrumbs" />
    <p class="eyebrow mt-3">News</p>
    <h1 class="mt-2 text-2xl sm:text-3xl lg:text-4xl font-semibold tracking-tight text-brand-navy">AI policy news from official sources</h1>
    <p class="mt-3 max-w-[68ch] text-brand-body leading-7">Headlines from regulators and governments' own feeds, the laws and changes this tracker records, and recorded AI incidents, newest first. Each item links to its source; nothing is rewritten or summarised here.</p>

    {{-- Where the items come from, as a picture: three streams into one list. --}}
    <x-site.news-sources :sources="$counts['sources']" />

    <nav class="mt-6" aria-label="Filter news">
        <p class="flex flex-wrap gap-2 text-sm">
            <a href="{{ route('news.index', array_filter(['kind' => $kind])) }}" class="chip {{ $topic ? '' : 'chip-active' }}" @unless($topic) aria-current="page" @endunless>All topics</a>
            @foreach($topics as $key => $t)
            <a href="{{ route('news.index', array_filter(['topic' => $key, 'kind' => $kind])) }}" class="chip {{ $topic === $key ? 'chip-active' : '' }}" rel="nofollow" @if($topic === $key) aria-current="page" @endif>{{ $t['label'] }}</a>
            @endforeach
        </p>
        <p class="mt-2 flex flex-wrap gap-2 text-sm">
            <a href="{{ route('news.index', array_filter(['topic' => $topic])) }}" class="chip {{ $kind ? '' : 'chip-active' }}">All sources</a>
            @foreach(\App\Http\Controllers\Site\NewsController::KINDS as $key => $label)
            <a href="{{ route('news.index', array_filter(['topic' => $topic, 'kind' => $key])) }}" class="chip {{ $kind === $key ? 'chip-active' : '' }}" rel="nofollow" @if($kind === $key) aria-current="page" @endif>{{ $label }}</a>
            @endforeach
        </p>
    </nav>

    @if($pageItems->isEmpty())
    <p class="mt-8 text-brand-muted">Nothing matches these filters in the last six months.</p>
    @else
    <ol class="mt-6 divide-y divide-brand-line border-y border-brand-line" data-news-list>
        @foreach($pageItems as $item)
        <li class="grid gap-1 py-3 sm:grid-cols-[7.5rem_1fr]">
            <time datetime="{{ $item['date']->toDateString() }}" class="font-mono text-xs text-brand-muted pt-1">{{ $item['date']->format('j M Y') }}</time>
            <div>
                <a href="{{ $item['url'] }}" class="font-medium text-brand-navy hover:underline" @if($item['external']) rel="nofollow noopener" target="_blank" @endif data-track="news_click_{{ $item['kind'] }}">{{ $item['title'] }}@if($item['external'])<span class="sr-only"> (opens the publisher's page)</span>@endif</a>
                <p class="mt-0.5 meta"><span class="badge {{ $item['kind'] === 'official' ? 'bg-brand-blue/10 text-brand-navy' : ($item['kind'] === 'incidents' ? 'bg-state-warnbg text-brand-navy' : 'bg-brand-paper text-brand-navy') }}">{{ \App\Http\Controllers\Site\NewsController::KINDS[$item['kind']] }}</span> {{ $item['by'] }}@foreach($item['topics'] as $t) · {{ $topics[$t]['label'] ?? $t }}@endforeach</p>
            </div>
        </li>
        @endforeach
    </ol>
    <p class="mt-4 flex justify-between text-sm">
        <span>@if($page > 1)<a href="{{ route('news.index', array_filter(['topic' => $topic, 'kind' => $kind, 'page' => $page - 1])) }}" rel="nofollow">&larr; Newer</a>@endif</span>
        <span class="meta">{{ $total }} {{ \Illuminate\Support\Str::plural('item', $total) }} in the last six months</span>
        <span>@if($page * \App\Http\Controllers\Site\NewsController::PER_PAGE < $total)<a href="{{ route('news.index', array_filter(['topic' => $topic, 'kind' => $kind, 'page' => $page + 1])) }}" rel="nofollow">Older &rarr;</a>@endif</span>
    </p>
    @endif

    <section class="mt-10 text-sm text-brand-body" aria-labelledby="news-how">
        <h2 id="news-how" class="section-title">Where this comes from</h2>
        <p class="mt-2">Official sources only: {{ collect(config('news.sources'))->pluck('publisher')->implode(', ') }}. A headline is kept when its title is about AI, or its summary names AI specifically. Topics are matched from the headline's own words and can be wrong. Recorded changes come from this tracker's <a href="{{ route('changes.index') }}">change log</a>; incidents from the <a href="{{ route('risk.incidents') }}">AI Incident Database records</a> kept here. Policy updates by country are on <a href="{{ route('updates.index') }}">AI policy updates</a>.</p>
    </section>
    <x-site.disclaimer class="mt-8" />
</div>
@endsection
