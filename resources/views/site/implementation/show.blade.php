@extends('site.layouts.app')
@section('content')
<div class="container-site py-8">
    <x-site.breadcrumbs :items="$seo->breadcrumbs" />
    <p class="eyebrow mt-2">Implementation tracker</p>
    <h1 class="mt-1 text-2xl sm:text-3xl font-semibold tracking-tight text-brand-navy">{{ $name }} implementation: guidelines, codes of practice, acts and templates</h1>

    <section class="mt-5 card-flat p-5" aria-labelledby="summary-heading">
        <h2 id="summary-heading" class="sr-only">Summary</h2>
        <p class="text-brand-navy text-base sm:text-lg leading-relaxed">{{ $answer }}</p>
        <dl class="mt-4 grid grid-cols-2 gap-4 sm:grid-cols-4">
            <x-site.stat label="Measures" :value="number_format($measures->count())" />
            <x-site.stat label="Recorded" :value="number_format($counts['recorded'])" note="from a cited source" />
            <x-site.stat label="Drafts" :value="number_format($counts['drafts'])" note="listed, facts empty" />
            <x-site.stat label="Overdue" :value="number_format($counts['overdue'])" note="derived from today's date" />
        </dl>
        <p class="mt-3 text-xs text-brand-muted">@if($seo->modified)Last updated <time datetime="{{ $seo->modified->toAtomString() }}">{{ $seo->modified->format('j M Y') }}</time> · @endif Dataset: <a href="{{ route('api.v1.implementation', ['instrument' => $instrument->slug]) }}">JSON</a> · <a href="{{ route('open-data.csv', 'implementation') }}">CSV</a> · <a href="{{ route('open-data.ndjson', 'implementation') }}">NDJSON</a></p>
    </section>

    <section aria-labelledby="measures-heading" class="mt-8">
        <h2 id="measures-heading" class="section-title">Measures <span class="text-sm font-normal text-brand-muted">({{ $measures->count() }})</span></h2>
        @include('site.implementation._table', ['measures' => $measures, 'caption' => $name.' implementation measures', 'standards' => false])
        <p class="mt-2 text-xs text-brand-muted">"Overdue" is computed each time this page is served: the date the instrument sets has passed and the measure has been neither adopted nor published. A dash means the date has not been read from an official source.</p>
    </section>

    @if($deadlines->isNotEmpty())
    <section aria-labelledby="dates-heading" class="mt-8">
        <h2 id="dates-heading" class="section-title">Application dates on the {{ $name }} record</h2>
        <p class="mt-1 text-xs text-brand-muted">From the <a href="{{ $instrument->url() }}">{{ $name }} record</a>, with its own review status; shown here so each measure can be read against the date it serves.</p>
        <ol class="mt-3 border-l-2 border-brand-line pl-4 space-y-2 text-sm">@foreach($deadlines as $d)<li>@if($d->due_on)<time datetime="{{ $d->due_on->toDateString() }}" class="font-medium text-brand-navy">{{ $d->due_on->format('j M Y') }}</time>@else<span class="font-medium text-brand-navy">{{ $d->date_label ?? 'Date to be set' }}</span>@endif · {{ $d->title }}@if($d->source_reference) <span class="text-brand-muted">({{ $d->source_reference }})</span>@endif</li>@endforeach</ol>
    </section>
    @endif

    <section aria-labelledby="standards-heading" class="mt-8">
        <h2 id="standards-heading" class="section-title">Standards</h2>
        <p class="mt-1 text-sm text-brand-body">Harmonised standards and ISO/IEC standards are tracked separately, metadata only, on the <a href="{{ route('standards.index') }}">AI standards tracker</a>.</p>
    </section>

    <x-site.faq :items="$seo->faqItems()" />
    <p class="mt-6 text-sm"><a href="{{ route('contribute') }}" class="text-brand-blue">Suggest a correction or a missing measure</a> with a link to the official source.</p>
    <x-site.disclaimer class="mt-8" />
</div>
@endsection
