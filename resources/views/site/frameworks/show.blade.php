@extends('site.layouts.app')
@section('content')
<div class="container-site py-8">
    <x-site.breadcrumbs :items="$seo->breadcrumbs" />
    <header class="mt-3">
        <div class="flex flex-wrap items-center gap-2 text-sm">
            <span class="badge {{ $meta['certifiable'] ? 'bg-brand-navy text-white ring-brand-navy' : 'bg-brand-paper text-brand-body ring-brand-line' }}">{{ $meta['certifiable'] ? 'Certifiable' : 'Voluntary' }}</span>
            <span class="text-brand-muted">{{ $meta['publisher'] }} &middot; {{ $meta['published'] }}</span>
        </div>
        <h1 class="mt-2 text-2xl sm:text-3xl font-semibold tracking-tight text-brand-navy">{{ $meta['name'] }}</h1>
        <p class="mt-2 max-w-3xl text-brand-body">{{ $meta['summary'] }}</p>
    </header>

    <div class="mt-8 grid gap-10 lg:grid-cols-3">
        <div class="lg:col-span-2 min-w-0">
            <dl class="grid grid-cols-2 sm:grid-cols-4 gap-3 text-center">
                <div class="card-flat py-3"><dt class="text-xs text-brand-muted">Duties mapped</dt><dd class="text-2xl font-semibold text-brand-navy">{{ $data['obligations'] }}</dd></div>
                <div class="card-flat py-3"><dt class="text-xs text-brand-muted">Mappings</dt><dd class="text-2xl font-semibold text-brand-navy">{{ $data['mappings'] }}</dd></div>
                <div class="card-flat py-3"><dt class="text-xs text-brand-muted">Jurisdictions</dt><dd class="text-2xl font-semibold text-brand-navy">{{ $data['jurisdictions']->count() }}</dd></div>
                <div class="card-flat py-3"><dt class="text-xs text-brand-muted">{{ $meta['unit'] }}s used</dt><dd class="text-2xl font-semibold text-brand-navy">{{ $data['families']->count() }}</dd></div>
            </dl>

            <section aria-labelledby="structure-heading" class="mt-8">
                <h2 id="structure-heading" class="section-title">How it is structured</h2>
                <p class="prose-policy mt-2">{{ $meta['structure'] }}</p>
                <p class="prose-policy mt-2">{{ $meta['why'] }}</p>
            </section>

            <section aria-labelledby="mapped-heading" class="mt-8">
                <h2 id="mapped-heading" class="section-title">Legal duties by {{ strtolower($meta['unit']) }}</h2>
                <p class="mt-1 text-xs text-brand-muted">A duty appears under every {{ strtolower($meta['unit']) }} its mapping cites, so the totals below exceed the {{ $data['obligations'] }} distinct duties. References that name no single {{ strtolower($meta['unit']) }} are grouped at the end rather than dropped.</p>
                @foreach($data['families'] as $family => $rows)
                <div class="mt-5">
                    <h3 class="flex items-baseline gap-2 text-sm font-semibold text-brand-navy">{{ $family }} <span class="text-xs font-normal text-brand-muted">{{ $rows->count() }} {{ \Illuminate\Support\Str::plural('duty', $rows->count()) }}</span></h3>
                    <ul class="mt-2 space-y-2 text-sm">
                        @foreach($rows as $m)
                        <li class="border-l-2 border-brand-line pl-3">
                            <a href="{{ $m->obligation->url() }}" class="font-medium text-brand-navy hover:underline">{{ $m->obligation->title }}</a>
                            <p class="text-xs text-brand-muted">{{ $m->obligation->policyInstrument->short_title ?: $m->obligation->policyInstrument->title }} &middot; {{ $m->obligation->policyInstrument->jurisdiction->name }}{{ $m->obligation->is_binding ? '' : ' (voluntary)' }} &middot; cites {{ $m->reference }}</p>
                            @if($m->note)<p class="mt-0.5 text-brand-body">{{ $m->note }}</p>@endif
                        </li>
                        @endforeach
                    </ul>
                </div>
                @endforeach
            </section>
            <x-site.disclaimer class="mt-8" />
        </div>

        <aside class="space-y-6">
            <div class="lg:sticky lg:top-4 space-y-6">
                <div class="card-flat p-4 text-sm">
                    <p class="font-semibold text-brand-navy">By jurisdiction</p>
                    <ul class="mt-2 space-y-1.5">
                        @foreach($data['jurisdictions'] as $row)
                        <li class="flex items-baseline justify-between gap-2">
                            <a href="{{ route('frameworks.crosswalk', [$meta['slug'], $row['jurisdiction']->slug]) }}" class="text-brand-navy hover:underline">{{ $row['jurisdiction']->name }}</a>
                            <span class="text-xs text-brand-muted">{{ $row['rows'] }}</span>
                        </li>
                        @endforeach
                    </ul>
                </div>
                @if(! empty($meta['guide']) && config('content.guides.'.$meta['guide']['slug']))
                <div class="card-flat p-4 text-sm">
                    <p class="font-semibold text-brand-navy">Written comparison</p>
                    <p class="mt-1 text-brand-body">This page is the data. The guide explains what {{ $meta['short'] }} does and does not settle for a regulated organisation.</p>
                    <a href="{{ route('guides.show', $meta['guide']['slug']) }}" class="mt-2 inline-block text-brand-blue hover:underline">{{ config('content.guides.'.$meta['guide']['slug'].'.h1') }} &rarr;</a>
                </div>
                @endif
                <div class="card-flat p-4 text-sm">
                    <p class="font-semibold text-brand-navy">The standard itself</p>
                    <p class="mt-1 text-brand-body">This platform records clause numbers only. The text of the standard is published by {{ $meta['publisher'] }}.</p>
                    <a href="{{ $meta['url'] }}" rel="noopener nofollow" class="mt-2 inline-block text-brand-blue hover:underline break-all" data-track="source_click">{{ $meta['publisher'] }} &rarr;</a>
                </div>
                <x-site.certifyi-cta label="Track these clauses as controls" />
            </div>
        </aside>
    </div>
</div>
@endsection
