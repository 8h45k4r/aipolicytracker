@extends('site.layouts.app')
@section('content')
<div class="container-site py-8">
    <x-site.breadcrumbs :items="$seo->breadcrumbs" />
    <header class="mt-3">
        <div class="flex flex-wrap gap-1.5"><span class="badge bg-brand-navy text-white ring-brand-navy">{{ $measure->typeLabel() }}</span><span class="badge-neutral">{{ $measure->statusLabel() }}</span>@if($measure->isDraft())<span class="badge bg-state-warnbg text-brand-body ring-state-warn/40">Draft: not verified</span>@else<span class="badge bg-state-goodbg text-state-good ring-state-good/30">Verified</span>@endif</div>
        <h1 class="mt-3 text-2xl sm:text-3xl lg:text-4xl font-semibold tracking-tight text-brand-navy">{{ $measure->title }}</h1>
        <p class="mt-2 meta">{{ $measure->jurisdiction->name }} · Last updated <time datetime="{{ $measure->updated_at->toAtomString() }}">{{ $measure->updated_at->format('j M Y') }}</time>@if($measure->last_verified_at) · verified {{ $measure->last_verified_at->format('j M Y') }}@endif</p>
    </header>
    <div class="mt-8 grid gap-10 lg:grid-cols-3">
        <div class="lg:col-span-2 min-w-0">
            <section class="card-flat p-5" aria-labelledby="brief-heading" data-answer-box>
                <h2 id="brief-heading" class="sr-only">In brief</h2>
                <p class="text-brand-navy text-base sm:text-lg leading-relaxed">{{ $answer }}</p>
                <dl class="mt-4 grid gap-2 sm:grid-cols-2 text-sm">@foreach($facts as $k => $v)<div class="flex justify-between gap-3 border-b border-brand-line py-1"><dt class="text-brand-muted">{{ $k }}</dt><dd class="text-right text-brand-body">{{ $v }}</dd></div>@endforeach</dl>
            </section>
            @if($measure->isDraft())
            <section class="mt-8 rounded-sm border border-state-warn/40 bg-state-warnbg p-4 text-sm text-brand-body" aria-labelledby="draft-heading">
                <h2 id="draft-heading" class="font-semibold text-brand-navy">Why this page is nearly empty</h2>
                <p class="mt-1">This tracker never fills a field from memory or a secondary source. Mechanism, funding, trigger, benefit, cost, bill number, sponsors and dates stay empty until a reviewer has read the official text and cites it. The record is listed so the gap is visible on the <a href="{{ route('gaps') }}">open gaps</a> page. <a href="{{ route('contribute') }}">Send the official source</a> if you have it.</p>
            </section>
            @endif
            @foreach(['mechanism' => 'How it works', 'funding' => 'How it is funded', 'trigger' => 'What triggers it', 'benefit' => 'Who receives what', 'cost' => 'Cost'] as $field => $heading)
            @if($measure->{$field})<section class="mt-8" aria-labelledby="{{ $field }}-heading"><h2 id="{{ $field }}-heading" class="section-title">{{ $heading }}</h2><p class="prose-policy mt-2">{{ $measure->{$field} }}</p></section>@endif
            @endforeach
            @if(!empty($measure->arguments_for) || !empty($measure->arguments_against))
            <section class="mt-8" aria-labelledby="args-heading"><h2 id="args-heading" class="section-title">Arguments made</h2>
                <div class="mt-3 grid gap-4 md:grid-cols-2">
                    <div><h3 class="text-sm font-semibold text-brand-navy">For</h3>@foreach($measure->arguments_for ?? [] as $a)<blockquote class="mt-2 border-l-2 border-brand-line pl-3 text-sm">{{ $a['claim'] }}<footer class="text-xs text-brand-muted">— {{ $a['attributed_to'] }}@if(!empty($a['source_url'])) · <a href="{{ $a['source_url'] }}" rel="noopener">source</a>@endif</footer></blockquote>@endforeach</div>
                    <div><h3 class="text-sm font-semibold text-brand-navy">Against</h3>@foreach($measure->arguments_against ?? [] as $a)<blockquote class="mt-2 border-l-2 border-brand-line pl-3 text-sm">{{ $a['claim'] }}<footer class="text-xs text-brand-muted">— {{ $a['attributed_to'] }}@if(!empty($a['source_url'])) · <a href="{{ $a['source_url'] }}" rel="noopener">source</a>@endif</footer></blockquote>@endforeach</div>
                </div>
            </section>
            @endif
            <x-site.source-list :sources="collect($measure->sources ?? [])" title="Sources" class="mt-8" />
            @if($measure->official_source_url)<p class="mt-3 text-sm"><a href="{{ $measure->official_source_url }}" rel="noopener" class="text-brand-blue">Official source</a>@if($measure->source_publisher) · {{ $measure->source_publisher }}@endif</p>@endif
            <x-site.faq :items="$seo->faqItems()" />
            <x-site.disclaimer class="mt-8" />
        </div>
        <aside class="space-y-6">
            @if($index)<div class="card-flat p-4 text-sm"><p class="font-semibold text-brand-navy">Displacement policy index</p><p class="mt-1">{{ $measure->jurisdiction->name }}: <span class="font-mono">{{ $index->score }}/100</span> ({{ $index->quarter }}, v{{ $index->version }})</p><p class="mt-1 text-xs"><a href="{{ route('transition.methodology') }}" class="text-brand-blue">How it is computed</a></p></div>@endif
            @if($related->isNotEmpty())<section aria-labelledby="rel-heading"><h2 id="rel-heading" class="section-title">Related measures</h2><ul class="mt-2 space-y-1.5 text-sm">@foreach($related as $r)<li><a href="{{ $r->url() }}" class="text-brand-navy hover:underline">{{ $r->title }}</a> <span class="meta">{{ $r->typeLabel() }} · {{ $r->jurisdiction->name }}</span></li>@endforeach</ul></section>@endif
            <p class="text-sm"><a href="{{ route('transition.index') }}" class="text-brand-blue">All measures →</a> · <a href="{{ $measure->jurisdiction->url() }}" class="text-brand-blue">AI regulation in {{ $measure->jurisdiction->nameWithArticle() }}</a></p>
            <x-site.correction-cta subject-type="transition_measure" :subject-slug="$measure->slug" :save-title="$measure->title" :save-url="$measure->url()" save-meta="Transition measure" class="flex-col [&>*]:w-full" />
        </aside>
    </div>
</div>
@endsection
