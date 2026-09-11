@extends('site.layouts.app')
@section('content')
@php($types = config('resources.types'))
@php($fw = config('resources.frameworks'))
@php($topics = config('resources.topics'))
@php($chip = fn ($key, $val) => route('guides.index', array_filter(array_merge($filters, [$key => $filters[$key] === $val ? null : $val]))))
<div class="container-site py-8">
    <x-site.breadcrumbs :items="$seo->breadcrumbs" />
    <h1 class="mt-2 text-2xl sm:text-3xl font-semibold tracking-tight text-brand-navy">Guides and free tools</h1>
    <p class="mt-2 max-w-[64ch] text-brand-body">Practical, source-backed guides that connect recorded obligations to what a team actually does, plus free templates, checklists and registers you can preview online and download with a free account.</p>

    <form method="get" action="{{ route('guides.index') }}" class="mt-6 card-flat p-4 space-y-3" data-autosubmit role="search" aria-label="Search guides and tools">
        <div class="flex gap-2 max-w-xl"><label for="g-q" class="sr-only">Search guides and tools</label><input id="g-q" type="search" name="q" value="{{ $filters['q'] }}" class="input flex-1" placeholder="Search guides, templates, EU AI Act, risk register…">
            @foreach(['type','framework','topic','access'] as $k)@if($filters[$k])<input type="hidden" name="{{ $k }}" value="{{ $filters[$k] }}">@endif @endforeach
            <button type="submit" class="btn-primary">Search</button></div>
        <div class="flex flex-wrap items-center gap-1.5 text-sm"><span class="meta w-28">Content type</span><a class="chip {{ !$filters['type'] ? 'chip-active' : '' }}" href="{{ $chip('type', null) }}">All</a>@foreach($types as $k => $label)<a class="chip {{ $filters['type'] === $k ? 'chip-active' : '' }}" href="{{ $chip('type', $k) }}">{{ \Illuminate\Support\Str::plural($label) }}</a>@endforeach</div>
        <div class="flex flex-wrap items-center gap-1.5 text-sm"><span class="meta w-28">Framework</span><a class="chip {{ !$filters['framework'] ? 'chip-active' : '' }}" href="{{ $chip('framework', null) }}">All</a>@foreach($fw as $k => $label)<a class="chip {{ $filters['framework'] === $k ? 'chip-active' : '' }}" href="{{ $chip('framework', $k) }}">{{ $label }}</a>@endforeach</div>
        <div class="flex flex-wrap items-center gap-1.5 text-sm"><span class="meta w-28">Topic</span><a class="chip {{ !$filters['topic'] ? 'chip-active' : '' }}" href="{{ $chip('topic', null) }}">All</a>@foreach($topics as $k => $label)<a class="chip {{ $filters['topic'] === $k ? 'chip-active' : '' }}" href="{{ $chip('topic', $k) }}">{{ $label }}</a>@endforeach</div>
        <div class="flex flex-wrap items-center gap-1.5 text-sm"><span class="meta w-28">Access</span><a class="chip {{ !$filters['access'] ? 'chip-active' : '' }}" href="{{ $chip('access', null) }}">All</a><a class="chip {{ $filters['access'] === 'read' ? 'chip-active' : '' }}" href="{{ $chip('access', 'read') }}">Read online</a><a class="chip {{ $filters['access'] === 'download' ? 'chip-active' : '' }}" href="{{ $chip('access', 'download') }}">Free download</a></div>
        @if($filtered)<p class="text-xs text-brand-muted">{{ $guides->count() + $tools->count() }} {{ \Illuminate\Support\Str::plural('result', $guides->count() + $tools->count()) }} · <a href="{{ route('guides.index') }}">Clear filters</a></p>@endif
    </form>

    @if($guides->isEmpty() && $tools->isEmpty())
    <div class="mt-8"><x-site.empty title="No guides or tools match" :reset="route('guides.index')">Try a broader keyword or fewer filters.</x-site.empty></div>
    @endif

    @if($guides->isNotEmpty())
    <section class="mt-10" aria-labelledby="guides-heading">
        <div class="rule-strong pt-3 flex items-baseline justify-between"><h2 id="guides-heading" class="section-title">{{ $filtered ? 'Guides' : 'Featured guides' }}</h2><span class="meta">{{ $guides->count() }}</span></div>
        <ul class="mt-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">@foreach($guides->sortByDesc('featured') as $g)<x-site.resource-card :item="$g" />@endforeach</ul>
    </section>
    @endif

    @if($tools->isNotEmpty())
    <section class="mt-10" aria-labelledby="tools-heading">
        <div class="rule-strong pt-3 flex items-baseline justify-between"><h2 id="tools-heading" class="section-title">Free tools and templates</h2><span class="meta">{{ $tools->count() }}</span></div>
        <p class="mt-2 max-w-[64ch] text-sm text-brand-body">Preview every field online. Downloads are free and need a free account so we can send you the file link and tell you when a related requirement changes. {{ config('resources.license') }}</p>
        <ul class="mt-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">@foreach($tools->sortByDesc('featured') as $t)<x-site.resource-card :item="$t" />@endforeach</ul>
    </section>
    @endif
</div>
@endsection
