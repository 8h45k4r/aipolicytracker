@extends('site.layouts.app')
@section('content')
<div class="container-site py-8">
    <x-site.breadcrumbs :items="$seo->breadcrumbs" />
    <h1 class="mt-2 text-2xl sm:text-3xl font-semibold tracking-tight text-brand-navy">Search</h1>
    <form action="{{ route('search') }}" method="get" role="search" class="mt-4 max-w-2xl">
        <label for="search-page-q" class="sr-only">Search the site</label>
        <div class="flex gap-2">
            <input id="search-page-q" name="q" type="search" value="{{ $q }}" class="input flex-1" placeholder="A law, a duty, a country, a template…" autocomplete="off" @if($q === '') autofocus @endif>
            <button type="submit" class="btn-primary">Search</button>
        </div>
    </form>

    @if($q !== '')
        <p class="mt-4 text-sm text-brand-muted" role="status">{{ $total ? number_format($total).' '.\Illuminate\Support\Str::plural('result', $total).' for' : 'Nothing found for' }} <span class="font-medium text-brand-navy">“{{ $q }}”</span>.@unless($total) Try a shorter word, a country name or a law's short title.@endunless</p>
        <div class="mt-6 grid gap-8 lg:grid-cols-2">
            @foreach($groups as $g)
            <section aria-labelledby="g-{{ $loop->index }}">
                <div class="rule-strong flex items-baseline justify-between pt-3">
                    <h2 id="g-{{ $loop->index }}" class="section-title !text-lg">{{ $g['label'] }} <span class="text-sm font-normal text-brand-muted">{{ $g['total'] }}</span></h2>
                    @if($g['more'] && $g['total'] > count($g['items']))<a href="{{ $g['more'] }}" class="text-sm">See all</a>@endif
                </div>
                <ul class="mt-1 divide-y divide-brand-line">
                    @foreach($g['items'] as $item)
                    <li class="py-2.5"><a href="{{ $item['url'] }}" class="font-medium text-brand-navy no-underline hover:underline">{{ $item['title'] }}</a>@if($item['meta'])<span class="block text-xs text-brand-muted">{{ $item['meta'] }}</span>@endif</li>
                    @endforeach
                </ul>
            </section>
            @endforeach
        </div>
    @else
        <p class="mt-4 text-sm text-brand-body">Search every jurisdiction, law, obligation, control, template, guide and glossary term on the site. Or start from <a href="{{ route('policies.index') }}">the policy explorer</a>, <a href="{{ route('jurisdictions.index') }}">a jurisdiction</a> or <a href="{{ route('templates.index') }}">the templates</a>.</p>
    @endif
</div>
@endsection
