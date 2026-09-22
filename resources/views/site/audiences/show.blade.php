@extends('site.layouts.app')
@section('content')
<div class="container-site py-8">
    <x-site.breadcrumbs :items="$seo->breadcrumbs" />
    <header class="mt-3">
        <p class="eyebrow">{{ ['actor' => 'By role', 'sector' => 'By sector', 'use_case' => 'By use case'][$page['taxonomy']] ?? 'Audience' }}@if($term) · {{ $term->name }}@endif</p>
        <h1 class="mt-2 text-2xl sm:text-3xl lg:text-4xl font-semibold tracking-tight text-brand-navy">{{ $page['h1'] }}</h1>
        <p class="mt-3 max-w-[68ch] prose-policy text-base">{{ $page['answer'] }}</p>
    </header>

    <dl class="mt-8 grid grid-cols-2 sm:grid-cols-4 gap-3">
        <x-site.stat label="Recorded duties" :value="$duties->count()" href="#duties-heading" :note="$duties->where('is_binding', true)->count().' legally binding'" />
        <x-site.stat label="Jurisdictions" :value="$jurisdictions->count()" />
        <x-site.stat label="Controls" :value="$controls->count()" href="#controls-heading" note="that meet these duties" />
        <x-site.stat label="Evidence items" :value="$evidence->count()" href="#evidence-heading" />
    </dl>

    <div class="mt-8 grid gap-10 lg:grid-cols-3">
        <div class="lg:col-span-2 min-w-0">
            @foreach($page['sections'] as $s)
            <section class="{{ $loop->first ? '' : 'mt-8' }}" aria-labelledby="s-{{ $loop->index }}"><h2 id="s-{{ $loop->index }}" class="section-title">{{ $s['heading'] }}</h2><p class="prose-policy mt-2">{{ $s['body'] }}</p></section>
            @endforeach

            <section class="mt-8" aria-labelledby="controls-heading">
                <h2 id="controls-heading" class="section-title">Which controls meet these duties?</h2>
                <p class="mt-1 text-xs text-brand-muted">Sorted by how many of the duties on this page each control satisfies, so the ones worth building first are at the top. A control page lists every other duty it serves, in every jurisdiction.</p>
                <div class="table-wrap mt-3"><table><caption class="sr-only">Controls for this audience</caption>
                    <thead><tr><th scope="col">Control</th><th scope="col">Satisfies</th><th scope="col">Supports</th><th scope="col">Owner · frequency</th></tr></thead>
                    <tbody>@forelse($controls as $r)<tr><td><a href="{{ $r['control']->url() }}" class="font-medium text-brand-navy hover:underline">{{ $r['control']->title }}</a><div class="text-xs text-brand-muted">{{ $r['control']->kindLabel() }}</div></td><td class="tabular-nums">{{ $r['satisfies'] }}</td><td class="tabular-nums">{{ $r['supports'] }}</td><td class="text-xs">{{ $r['control']->owner_role }} · {{ strtolower($r['control']->frequencyLabel()) }}</td></tr>@empty<tr><td colspan="4" class="text-brand-muted">No control is linked to these duties yet.</td></tr>@endforelse</tbody>
                </table></div>
            </section>

            <section class="mt-8" aria-labelledby="duties-heading">
                <h2 id="duties-heading" class="section-title">Which duties are recorded?</h2>
                <p class="mt-1 text-xs text-brand-muted">Every published duty whose record names this audience. It is the recorded set, not every rule in the world; a jurisdiction missing here may simply not be mapped yet (<a href="{{ route('gaps') }}">open gaps</a>).</p>
                @foreach($byJurisdiction as $name => $rows)
                <div class="mt-5">
                    <h3 class="flex items-baseline gap-2 text-sm font-semibold text-brand-navy"><a href="{{ $rows->first()->policyInstrument->jurisdiction->url() }}" class="no-underline hover:underline">{{ $name }}</a> <span class="text-xs font-normal text-brand-muted">{{ $rows->count() }} {{ \Illuminate\Support\Str::plural('duty', $rows->count()) }}</span></h3>
                    <ul class="mt-2 divide-y divide-brand-line border-y border-brand-line text-sm">
                        @foreach($rows as $o)
                        <li class="py-2.5">
                            <div class="flex flex-wrap items-center gap-2 text-xs"><span class="badge {{ $o->is_binding ? 'bg-brand-navy text-white ring-brand-navy' : 'bg-brand-paper text-brand-body ring-brand-line' }}">{{ $o->is_binding ? 'Legal requirement' : 'Voluntary' }}</span><span class="text-brand-muted">{{ $o->policyInstrument->short_title ?: $o->policyInstrument->title }}@if($o->source_reference) · {{ $o->source_reference }}@endif</span>@if($o->applies_from)<span class="text-brand-muted">applies from {{ $o->applies_from->format('j M Y') }}</span>@endif</div>
                            <a href="{{ $o->url() }}" class="mt-1 block font-medium text-brand-navy no-underline hover:underline">{{ $o->title }}</a>
                        </li>
                        @endforeach
                    </ul>
                </div>
                @endforeach
            </section>

            @if($evidence->isNotEmpty())
            <section class="mt-8" aria-labelledby="evidence-heading">
                <h2 id="evidence-heading" class="section-title">What evidence would a reviewer expect?</h2>
                <ul class="mt-2 grid gap-x-6 gap-y-1.5 sm:grid-cols-2 text-sm text-brand-body">@foreach($evidence as $e)<li><span class="font-medium text-brand-navy">{{ $e->title }}</span> <span class="text-xs text-brand-muted">{{ $evidenceTypes[$e->evidence_type] ?? $e->evidence_type }}</span></li>@endforeach</ul>
            </section>
            @endif

            @if($changes->isNotEmpty())
            <section class="mt-8" aria-labelledby="changes-heading"><h2 id="changes-heading" class="section-title">Latest changes to these instruments</h2><div class="mt-1 divide-y divide-brand-line border-y border-brand-line">@foreach($changes as $c)<x-site.change-item :change="$c" compact />@endforeach</div></section>
            @endif
            <x-site.disclaimer class="mt-8" />
        </div>
        <aside class="space-y-6"><div class="lg:sticky lg:top-4 space-y-6">
            <div class="card-flat p-4 text-sm">
                <p class="font-semibold text-brand-navy">Governance card</p>
                <dl class="mt-2 space-y-1.5 text-brand-body">
                    <div class="flex justify-between gap-2"><dt class="text-brand-muted">Applies to</dt><dd class="text-right">{{ $term?->name ?? $page['h1'] }}</dd></div>
                    <div class="flex justify-between gap-2"><dt class="text-brand-muted">Binding duties</dt><dd>{{ $duties->where('is_binding', true)->count() }} of {{ $duties->count() }}</dd></div>
                    <div class="flex justify-between gap-2"><dt class="text-brand-muted">Jurisdictions</dt><dd class="text-right">{{ $jurisdictions->pluck('name')->take(4)->join(', ') }}{{ $jurisdictions->count() > 4 ? ' +'.($jurisdictions->count() - 4) : '' }}</dd></div>
                    <div class="flex justify-between gap-2"><dt class="text-brand-muted">Instruments</dt><dd>{{ $instruments->count() }}</dd></div>
                </dl>
                <ol class="mt-3 list-decimal pl-4 text-xs text-brand-body space-y-1">
                    <li>Inventory the systems in this situation.</li>
                    <li>Screen them with the <a href="{{ route('tools.applicability') }}" rel="nofollow">applicability check</a>.</li>
                    <li>Build the top controls above; keep the evidence listed.</li>
                    <li>Follow the <a href="{{ route('changes.index') }}">change log</a> for the instruments below.</li>
                </ol>
            </div>
            <div class="card-flat p-4 text-sm"><p class="font-semibold text-brand-navy">Instruments recorded</p><ul class="mt-2 space-y-1.5">@foreach($instruments as $p)<li><a href="{{ $p->url() }}" class="text-brand-navy hover:underline">{{ $p->short_title ?: $p->title }}</a> <span class="text-xs text-brand-muted">{{ $p->jurisdiction->name }}</span></li>@endforeach</ul></div>
            @if($deadlines->isNotEmpty())<div class="text-sm"><p class="font-semibold text-brand-navy">Upcoming dates</p><ul class="mt-2 space-y-2">@foreach($deadlines as $d)<li><time class="font-mono text-xs text-brand-muted" datetime="{{ $d->due_on->toDateString() }}">{{ $d->displayDate() }}</time><div><a href="{{ $d->policyInstrument->url() }}" class="text-brand-navy hover:underline">{{ $d->title }}</a></div></li>@endforeach</ul></div>@endif
            <x-site.certifyi-cta />
        </div></aside>
    </div>
</div>
@endsection
