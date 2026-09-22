@extends('site.layouts.app')
@section('content')
<div class="container-site py-8">
    <x-site.breadcrumbs :items="$seo->breadcrumbs" />
    <header class="mt-3">
        @php($impact = $change->impactEnum())
        <div class="flex flex-wrap items-center gap-2 text-xs">
            <time datetime="{{ $change->occurred_on->toDateString() }}" class="datestamp">{{ $change->occurred_on->format('j M Y') }}</time>
            @if($change->jurisdiction)<a href="{{ $change->jurisdiction->url() }}" class="font-medium text-brand-body hover:text-brand-navy">{{ $change->jurisdiction->name }}</a>@endif
            <span class="badge {{ $impact->value === 'urgent' ? 'bg-state-badbg text-state-bad ring-state-bad/20' : ($impact->value === 'high' ? 'bg-state-warnbg text-state-warn ring-state-warn/20' : 'bg-state-neutralbg text-brand-body ring-brand-line') }}">{{ $impact->label() }}</span>
            @if($change->statusAfterEnum())<x-site.status-badge :status="$change->statusAfterEnum()" />@endif
        </div>
        <h1 class="mt-2 text-2xl sm:text-3xl lg:text-4xl font-semibold tracking-tight text-brand-navy">{{ $change->title }}</h1>
        @if($instrument)<p class="mt-1 text-sm text-brand-muted">Concerns <a href="{{ $instrument->url() }}" class="font-medium text-brand-body">{{ $instrument->short_title ?: $instrument->title }}</a></p>@endif
        <div class="mt-3 flex flex-wrap items-center gap-x-4 gap-y-2 text-sm">
            <a href="{{ route('verification') }}" class="no-underline" title="How current this record has to be, and how many are past that date"><x-site.verified :record="$change" class="!text-sm" /></a>
            @if($change->official_source_url)<a href="{{ $change->official_source_url }}" rel="noopener" class="text-brand-blue font-medium hover:underline" data-track="source_click">Open official source</a>@endif
            <a href="{{ route('changes.context', $change->slug) }}" class="text-brand-muted hover:text-brand-navy" title="This change as one Markdown file, with its provenance">Context file</a>
        </div>
    </header>

    <div class="mt-8 grid gap-10 lg:grid-cols-3">
        <div class="lg:col-span-2 min-w-0">
            <section aria-labelledby="what-heading"><h2 id="what-heading" class="section-title">What changed?</h2><p class="prose-policy mt-2">{{ $change->what_changed }}</p></section>
            @if($change->practical_impact)<section aria-labelledby="impact-heading" class="mt-8"><h2 id="impact-heading" class="section-title">What does it mean in practice?</h2><p class="prose-policy mt-2">{{ $change->practical_impact }}</p></section>@endif
            @if($change->official_source_url)
            <section aria-labelledby="sources-heading" class="mt-8">
                <h2 id="sources-heading" class="section-title">Official source</h2>
                <p class="mt-2 text-sm"><a href="{{ $change->official_source_url }}" rel="noopener" class="font-medium text-brand-blue hover:underline break-words" data-track="source_click">{{ $change->source_title ?: $change->official_source_url }}</a>@if($change->source_publisher)<span class="block text-xs text-brand-muted">{{ $change->source_publisher }}@if($change->source_document_date) · {{ $change->source_document_date->format('j M Y') }}@endif</span>@endif</p>
            </section>
            @endif
            <x-site.cite :title="$change->title" :url="$change->url()" :source-url="$change->official_source_url" :source-title="$change->source_title" :publisher="$change->source_publisher" class="mt-8" />
            @if($related->isNotEmpty())
            <section aria-labelledby="related-heading" class="mt-8">
                <h2 id="related-heading" class="section-title">Related changes</h2>
                <div class="mt-1 divide-y divide-brand-line border-y border-brand-line">@foreach($related as $r)<x-site.change-item :change="$r" :compact="true" />@endforeach</div>
            </section>
            @endif
            <x-site.disclaimer class="mt-8" />
        </div>
        <aside class="space-y-6">
            <div class="lg:sticky lg:top-4 space-y-6">
                <div class="card-flat p-4 text-sm">
                    <p class="font-semibold text-brand-navy">At a glance</p>
                    <dl class="mt-2 space-y-1.5 text-brand-body">
                        <div class="flex justify-between gap-2"><dt class="text-brand-muted">Date</dt><dd>{{ $change->occurred_on->format('j M Y') }}</dd></div>
                        @if($change->jurisdiction)<div class="flex justify-between gap-2"><dt class="text-brand-muted">Jurisdiction</dt><dd class="text-right"><a href="{{ $change->jurisdiction->url() }}">{{ $change->jurisdiction->name }}</a></dd></div>@endif
                        <div class="flex justify-between gap-2"><dt class="text-brand-muted">Impact</dt><dd>{{ $impact->label() }}</dd></div>
                        @if($change->statusAfterEnum())<div class="flex justify-between gap-2"><dt class="text-brand-muted">Status after</dt><dd>{{ $change->statusAfterEnum()->label() }}</dd></div>@endif
                        <div class="flex justify-between gap-2"><dt class="text-brand-muted">Review status</dt><dd>{{ $change->reviewStatusEnum()->label() }}</dd></div>
                    </dl>
                    <p class="mt-3 text-xs"><a href="{{ route('changes.year', $change->occurred_on->year) }}">All changes in {{ $change->occurred_on->year }}</a> · <a href="{{ route('changes.feed') }}">RSS</a></p>
                </div>
                <x-site.correction-cta subject-type="change" :subject-slug="$change->slug" :save-title="$change->title" :save-url="$change->url()" :save-meta="$change->jurisdiction?->name" class="flex-col [&>*]:w-full" />
            </div>
        </aside>
    </div>
</div>
@endsection
