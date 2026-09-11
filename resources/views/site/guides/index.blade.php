@extends('site.layouts.app')
@section('content')
@php($types = \App\Models\Tool::TYPES)
@php($fw = config('resources.frameworks'))
@php($topics = config('resources.topics'))
<div class="container-site py-8">
    <x-site.breadcrumbs :items="$seo->breadcrumbs" />
    <h1 class="mt-2 text-2xl sm:text-3xl font-semibold tracking-tight text-brand-navy">Guides and free tools</h1>
    <p class="mt-2 max-w-[64ch] text-brand-body">Practical, source-backed guides that connect recorded obligations to what a team actually does, plus free templates, checklists and registers you can preview online and download with a free account.</p>

    <form method="get" action="{{ route('guides.index') }}" class="mt-6 card-flat p-4" data-autosubmit role="search" aria-label="Search and filter guides and tools">
        <div class="grid gap-3 md:grid-cols-12 md:items-end">
            <div class="md:col-span-4"><label for="g-q" class="label">Search</label><input id="g-q" type="search" name="q" value="{{ $filters['q'] }}" class="input" placeholder="EU AI Act, risk register, inventory…"></div>
            <div class="md:col-span-2"><label for="g-type" class="label">Content type</label><select id="g-type" name="type" class="input"><option value="">All types</option>@foreach($types as $k => $label)<option value="{{ $k }}" @selected($filters['type'] === $k)>{{ \Illuminate\Support\Str::plural($label) }}</option>@endforeach</select></div>
            <div class="md:col-span-2"><span class="label">Framework</span><x-site.multi-select name="framework" label="Framework" :options="$fw" :selected="$filters['framework']" /></div>
            <div class="md:col-span-2"><span class="label">Topic</span><x-site.multi-select name="topic" label="Topic" :options="$topics" :selected="$filters['topic']" /></div>
            <div class="md:col-span-1"><label for="g-access" class="label">Access</label><select id="g-access" name="access" class="input"><option value="">All</option><option value="read" @selected($filters['access'] === 'read')>Read online</option><option value="download" @selected($filters['access'] === 'download')>Free download</option></select></div>
            <div class="md:col-span-1 flex gap-2"><button type="submit" class="btn-primary w-full">Apply</button></div>
        </div>
        @if($filtered)
        <div class="mt-3 flex flex-wrap items-center gap-1.5 text-xs">
            <span class="meta">Active:</span>
            @if($filters['q'])<span class="chip !min-h-0 !py-0.5">“{{ $filters['q'] }}”</span>@endif
            @if($filters['type'])<span class="chip !min-h-0 !py-0.5">{{ \Illuminate\Support\Str::plural($types[$filters['type']]) }}</span>@endif
            @foreach($filters['framework'] as $f)<span class="chip !min-h-0 !py-0.5">{{ $fw[$f] }}</span>@endforeach
            @foreach($filters['topic'] as $t)<span class="chip !min-h-0 !py-0.5">{{ $topics[$t] }}</span>@endforeach
            @if($filters['access'])<span class="chip !min-h-0 !py-0.5">{{ $filters['access'] === 'read' ? 'Read online' : 'Free download' }}</span>@endif
            <span class="meta">· {{ $guides->count() + $tools->count() }} {{ \Illuminate\Support\Str::plural('result', $guides->count() + $tools->count()) }}</span>
            <a href="{{ route('guides.index') }}" class="chip !min-h-0 !py-0.5">Clear</a>
        </div>
        @endif
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
