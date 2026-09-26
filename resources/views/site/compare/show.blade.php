@extends('site.layouts.app')
@section('content')
<div class="container-site py-8">
    <x-site.breadcrumbs :items="$seo->breadcrumbs" />
    <h1 class="mt-2 text-2xl sm:text-3xl font-semibold tracking-tight text-brand-navy">{{ $config['title'] }}</h1>
    <p class="mt-3 max-w-3xl prose-policy">{{ $config['intro'] }}</p>
    @include('site.compare._table')

    @if(!empty($overlap))
    <section class="mt-10" aria-labelledby="overlap-heading">
        <h2 id="overlap-heading" class="section-title">Obligation overlap: {{ $a->short_name ?: $a->name }} and {{ $b->short_name ?: $b->name }}</h2>
        <p class="mt-1 text-xs text-brand-muted">Recorded duties by category. A category both sides record is where evidence can be reused; one side only is new work for that side. Binding / voluntary counts.</p>
        <div class="table-wrap mt-3"><table><caption class="sr-only">Obligation categories recorded for {{ $a->name }} and {{ $b->name }}</caption>
            <thead><tr><th scope="col">Category</th><th scope="col">{{ $a->short_name ?: $a->name }}</th><th scope="col">{{ $b->short_name ?: $b->name }}</th><th scope="col">Overlap</th></tr></thead>
            <tbody>@foreach($overlap as $r)<tr>
                <th scope="row" class="font-medium"><a href="{{ route('obligations.index', ['category' => $r['category']]) }}" class="text-brand-navy">{{ $r['name'] }}</a></th>
                <td>{{ $r['a']['binding'] + $r['a']['voluntary'] > 0 ? $r['a']['binding'].' binding / '.$r['a']['voluntary'].' voluntary' : '—' }}</td>
                <td>{{ $r['b']['binding'] + $r['b']['voluntary'] > 0 ? $r['b']['binding'].' binding / '.$r['b']['voluntary'].' voluntary' : '—' }}</td>
                <td>@if($r['both'])<span class="badge bg-state-goodbg text-state-good ring-state-good/30">Both</span>@elseif($r['a']['binding'] + $r['a']['voluntary'] > 0)<span class="badge-neutral">{{ $a->short_name ?: $a->name }} only</span>@else<span class="badge-neutral">{{ $b->short_name ?: $b->name }} only</span>@endif</td>
            </tr>@endforeach</tbody></table></div>
    </section>
    @endif

    @if(isset($left))
    <section class="mt-10" aria-labelledby="left-heading">
        <h2 id="left-heading" class="section-title">If you comply with {{ $a->nameWithArticle() }}, what is left for {{ $b->nameWithArticle() }}?</h2>
        @if($left['new']->isEmpty() && $left['shared']->isEmpty())
        <p class="mt-2 text-sm text-brand-body">No binding duty is recorded for {{ $b->nameWithArticle() }} yet; its instruments are strategies, guidance or existing law. <a href="{{ $b->url() }}">See the record</a>.</p>
        @else
        @if($left['new']->isNotEmpty())
        <h3 class="mt-3 text-sm font-semibold text-brand-navy">New work: {{ $left['new']->count() }} binding {{ \Illuminate\Support\Str::plural('duty', $left['new']->count()) }} in categories {{ $a->nameWithArticle() }} does not bind</h3>
        <ul class="mt-2 divide-y divide-brand-line border-y border-brand-line text-sm">@foreach($left['new']->take(12) as $o)<li class="py-2"><a href="{{ $o->url() }}" class="font-medium text-brand-navy no-underline hover:underline">{{ $o->title }}</a> <span class="meta">· {{ $o->policyInstrument->short_title ?: $o->policyInstrument->title }}@if($o->source_reference) · {{ $o->source_reference }}@endif</span></li>@endforeach</ul>
        @if($left['new']->count() > 12)<p class="mt-2 text-sm"><a href="{{ route('obligations.index', ['jurisdiction' => $b->slug, 'binding' => 1]) }}" class="text-brand-blue hover:underline">All {{ $left['new']->count() }} →</a></p>@endif
        @endif
        @if($left['shared']->isNotEmpty())
        <h3 class="mt-4 text-sm font-semibold text-brand-navy">Check, don't redo: {{ $left['shared']->count() }} binding {{ \Illuminate\Support\Str::plural('duty', $left['shared']->count()) }} in categories both record</h3>
        <p class="mt-1 text-xs text-brand-muted">The same control usually serves both; the provisions differ, so each is listed with its reference.</p>
        <ul class="mt-2 divide-y divide-brand-line border-y border-brand-line text-sm">@foreach($left['shared']->take(12) as $o)<li class="py-2"><a href="{{ $o->url() }}" class="font-medium text-brand-navy no-underline hover:underline">{{ $o->title }}</a> <span class="meta">· {{ $o->policyInstrument->short_title ?: $o->policyInstrument->title }}@if($o->source_reference) · {{ $o->source_reference }}@endif</span></li>@endforeach</ul>
        @endif
        @endif
        <p class="mt-3 text-xs text-brand-muted">Computed from recorded duties by category. Overlap by category is not equivalence: read each provision before treating evidence as reusable. <a href="{{ route('templates.show', 'global-ai-regulatory-applicability-matrix') }}">The applicability matrix template</a> lists every binding instrument by jurisdiction.</p>
    </section>
    @endif
    <div class="mt-6 flex flex-wrap gap-2">@foreach($jurisdictions as $j)<a href="{{ $j->url() }}" class="btn-secondary">AI regulation in {{ $j->nameWithArticle() }}</a>@endforeach<a href="{{ route('compare.index', ['j' => $jurisdictions->pluck('slug')->implode(',')]) }}" class="btn-secondary" rel="nofollow">Adjust comparison</a></div>
    <x-site.faq :items="$faq ?? ($config['faq'] ?? [])" />
    <x-site.disclaimer class="mt-8" />
</div>
@endsection
