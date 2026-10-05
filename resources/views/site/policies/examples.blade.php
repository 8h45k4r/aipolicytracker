@extends('site.layouts.app')
@section('content')
<div class="container-site py-8">
    <x-site.breadcrumbs :items="$seo->breadcrumbs" />
    <h1 class="mt-2 text-2xl sm:text-3xl font-semibold tracking-tight text-brand-navy">AI policy examples</h1>
    <x-site.answer-box :text="$answer" class="mt-4 max-w-3xl" />

    @if($template)
    <section class="mt-8 max-w-3xl rounded-sm border border-brand-line p-4" aria-labelledby="company-heading">
        <h2 id="company-heading" class="section-title !text-lg">A company AI policy</h2>
        <p class="mt-1 text-sm text-brand-body">Looking for your organisation's own policy rather than a government's? The <a href="{{ \App\Services\Templates\TemplateCatalog::url('acceptable-use-policy') }}">company AI policy template</a> (an acceptable use policy) sets out scope, approved and prohibited uses, data rules, disclosure, training, enforcement and review, and cites the recorded duties each section serves. Free, DOCX.</p>
    </section>
    @endif

    <section class="mt-10" aria-labelledby="national-heading">
        <h2 id="national-heading" class="section-title">National AI policies and strategies, by region</h2>
        <p class="mt-1 text-sm text-brand-muted">{{ $policies->count() }} on record, newest first within each region. Each links to its record, with status, dates and the official source.</p>
        <div class="mt-4 grid gap-8 lg:grid-cols-2">
            @foreach($byRegion as $region => $items)
            <section aria-labelledby="r-{{ $loop->index }}">
                <h3 id="r-{{ $loop->index }}" class="rule-strong pt-3 font-semibold text-brand-navy">{{ $region }} <span class="text-sm font-normal text-brand-muted">{{ $items->count() }}</span></h3>
                <ul class="mt-1 divide-y divide-brand-line text-sm">
                    @foreach($items as $p)
                    @php($year = $p->adopted_on ?? $p->published_on ?? $p->in_force_on)
                    <li class="py-2"><a href="{{ $p->url() }}" class="font-medium text-brand-navy no-underline hover:underline">{{ $p->short_title ?: $p->title }}</a><span class="block text-xs text-brand-muted">{{ $p->jurisdiction?->name }}@if($year) · {{ $year->format('Y') }}@endif · {{ $p->statusEnum()->label() }}</span></li>
                    @endforeach
                </ul>
            </section>
            @endforeach
        </div>
    </section>
</div>
@endsection
