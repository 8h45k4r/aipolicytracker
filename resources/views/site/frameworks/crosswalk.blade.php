@extends('site.layouts.app')
@php($mapped = $data['rows']->count())
@php($total = max($data['total_obligations'], $mapped))
@php($pct = $total > 0 ? (int) round($mapped / $total * 100) : 0)
@section('content')
<div class="container-site py-8">
    <x-site.breadcrumbs :items="$seo->breadcrumbs" />
    <header class="mt-3">
        <div class="flex flex-wrap items-center gap-2 text-sm">
            <a href="{{ $j->url() }}" class="text-brand-body hover:text-brand-navy">{{ $j->name }}</a>
            <span class="text-brand-muted" aria-hidden="true">&times;</span>
            <a href="{{ route('frameworks.show', $meta['slug']) }}" class="text-brand-body hover:text-brand-navy">{{ $meta['short'] }}</a>
        </div>
        <h1 class="mt-2 text-2xl sm:text-3xl font-semibold tracking-tight text-brand-navy">{{ $j->name }} AI rules mapped to {{ $meta['short'] }}</h1>
        <p class="mt-2 max-w-3xl text-brand-body">Each row is one legal duty recorded for {{ $j->name }} and the {{ strtolower($meta['unit']) }} of {{ $meta['name'] }} it corresponds to. Use it to find which duties your existing evidence already reaches.</p>
    </header>

    <div class="mt-6 card-flat p-5">
        <div class="flex flex-wrap items-baseline justify-between gap-2">
            <p class="text-sm font-semibold text-brand-navy">Coverage of this crosswalk</p>
            <p class="text-sm text-brand-body"><span class="text-2xl font-semibold text-brand-navy">{{ $mapped }}</span> of {{ $total }} recorded {{ $j->name }} duties carry a mapping</p>
        </div>
        <div class="mt-3 h-2 w-full overflow-hidden rounded-full bg-brand-paper" role="img" aria-label="{{ $pct }} per cent of recorded duties are mapped">
            <div class="h-full rounded-full bg-brand-navy" style="width: {{ $pct }}%"></div>
        </div>
        <p class="mt-2 text-xs text-brand-muted">The denominator is the number of duties this platform has broken out for {{ $j->name }}, not the number of duties the law contains. Unmapped duties are ones no reviewer has crosswalked yet, not ones the standard fails to address.</p>
    </div>

    <section aria-labelledby="table-heading" class="mt-8">
        <h2 id="table-heading" class="section-title">The mapping</h2>
        <div class="table-wrap mt-3">
            <table>
                <caption class="sr-only">{{ $j->name }} AI duties mapped to {{ $meta['name'] }}</caption>
                <thead><tr><th scope="col">Legal duty</th><th scope="col">Binding?</th><th scope="col">{{ $meta['short'] }} {{ strtolower($meta['unit']) }}</th><th scope="col">Why they correspond</th><th scope="col">Confidence</th></tr></thead>
                <tbody>
                @foreach($data['rows'] as $m)
                <tr>
                    <td>
                        <a href="{{ $m->obligation->url() }}" class="font-medium text-brand-navy hover:underline">{{ $m->obligation->title }}</a>
                        <span class="block text-xs text-brand-muted">{{ $m->obligation->policyInstrument->short_title ?: $m->obligation->policyInstrument->title }}@if($m->obligation->source_reference), {{ $m->obligation->source_reference }}@endif</span>
                    </td>
                    <td class="whitespace-nowrap text-xs">{{ $m->obligation->is_binding ? 'Legal requirement' : 'Voluntary' }}</td>
                    <td>{{ $m->reference }}</td>
                    <td>{{ $m->note }}</td>
                    <td class="whitespace-nowrap text-xs">{{ $m->confidence_level }}</td>
                </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    </section>

    <section aria-labelledby="means-heading" class="mt-8 grid gap-4 md:grid-cols-2">
        <div class="card-flat p-5">
            <h2 id="means-heading" class="text-sm font-semibold text-brand-navy">What a mapping means</h2>
            <p class="mt-2 text-sm text-brand-body">The duty and the {{ strtolower($meta['unit']) }} ask for overlapping work, so evidence produced for one is likely to be reusable for the other. Confidence records how direct that overlap is.</p>
        </div>
        <div class="card-flat p-5">
            <h2 class="text-sm font-semibold text-brand-navy">What it does not mean</h2>
            <p class="mt-2 text-sm text-brand-body">{{ $meta['short'] }} {{ $meta['certifiable'] ? 'certification' : 'adoption' }} does not discharge a legal duty and carries no force in {{ $j->name }}. A mapped row still has to be complied with on the statute's own terms.</p>
        </div>
    </section>

    <section aria-labelledby="instruments-heading" class="mt-8">
        <h2 id="instruments-heading" class="section-title">Instruments in this crosswalk</h2>
        <ul class="mt-2 space-y-1.5 text-sm">
            @foreach($data['instruments'] as $p)
            <li><a href="{{ $p->url() }}" class="text-brand-navy hover:underline">{{ $p->short_title ?: $p->title }}</a> <span class="text-xs text-brand-muted">&mdash; {{ $j->name }}</span></li>
            @endforeach
        </ul>
        <p class="mt-4 text-sm"><a href="{{ route('frameworks.show', $meta['slug']) }}" class="text-brand-blue hover:underline">All {{ $meta['short'] }} mappings across every jurisdiction &rarr;</a></p>
    </section>
    <x-site.disclaimer class="mt-8" />
</div>
@endsection
