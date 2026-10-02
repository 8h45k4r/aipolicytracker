@extends('site.layouts.app')
@section('content')
<div class="container-site py-8">
    <x-site.breadcrumbs :items="$seo->breadcrumbs" />
    <h1 class="mt-2 text-2xl sm:text-3xl font-semibold tracking-tight text-brand-navy">{{ $group['label'] }}</h1>
    <p class="mt-2 max-w-2xl text-brand-body">{{ $group['summary'] }} {{ $count }} pages.</p>
    <div class="mt-8 grid gap-8 md:grid-cols-2 lg:grid-cols-3">
        @foreach($group['sections'] as $section)
        <section aria-labelledby="s-{{ $loop->index }}">
            <h2 id="s-{{ $loop->index }}" class="rule-strong pt-3 section-title !text-lg">{{ $section['heading'] }}</h2>
            <ul class="mt-1 divide-y divide-brand-line">
                @foreach($section['items'] as $item)
                <li class="py-2.5"><a href="{{ \App\Support\Navigation::url($item) }}" class="font-medium text-brand-navy no-underline hover:underline">{{ $item['label'] }}</a>@if(! empty($item['note']))<span class="block text-sm text-brand-muted">{{ $item['note'] }}</span>@endif</li>
                @endforeach
            </ul>
        </section>
        @endforeach
    </div>
</div>
@endsection
