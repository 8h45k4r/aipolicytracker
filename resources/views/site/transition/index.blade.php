@extends('site.layouts.app')
@section('content')
<div class="container-site py-8">
    <x-site.breadcrumbs :items="$seo->breadcrumbs" />
    <p class="eyebrow mt-2">AI economic transition</p>
    <h1 class="mt-1 text-2xl sm:text-3xl font-semibold tracking-tight text-brand-navy">{{ $page['h1'] ?? 'AI economic transition tracker: dividends, basic income, AI taxes, layoff disclosure' }}</h1>
    @if($page)<p class="mt-2 max-w-3xl text-brand-body">{{ $page['lead'] }}</p>@endif

    {{-- The answer box: computed from the records, never written by hand. --}}
    <section class="mt-5 card-flat p-5" aria-labelledby="summary-heading">
        <h2 id="summary-heading" class="sr-only">Summary</h2>
        <p class="text-brand-navy text-base sm:text-lg leading-relaxed">{{ $answer }}</p>
        <dl class="mt-4 grid grid-cols-2 gap-4 sm:grid-cols-4">
            <x-site.stat label="Measures" :value="number_format($measures->count())" />
            <x-site.stat label="Verified" :value="number_format($verified->count())" note="read from an official source" />
            <x-site.stat label="Drafts" :value="number_format($measures->count() - $verified->count())" note="listed, not counted" />
            <x-site.stat label="Index snapshots" :value="number_format($index->count())" :href="route('transition.methodology')" />
        </dl>
        @if($seo->modified)<p class="mt-3 text-xs text-brand-muted">Last updated <time datetime="{{ $seo->modified->toAtomString() }}">{{ $seo->modified->format('j M Y') }}</time> · Dataset: <a href="{{ route('api.v1.transition.measures') }}">JSON</a></p>@endif
    </section>

    @unless($page)
    <form method="get" action="{{ route('transition.index') }}" class="mt-5 grid gap-3 sm:grid-cols-4" data-autosubmit aria-label="Filter measures">
        <div><label for="t-type" class="label">Type</label><select id="t-type" name="type" class="input"><option value="">All types</option>@foreach(\App\Models\TransitionMeasure::TYPES as $k => $v)<option value="{{ $k }}" @selected($filters['type'] === $k)>{{ $v }}</option>@endforeach</select></div>
        <div><label for="t-status" class="label">Status</label><select id="t-status" name="status" class="input"><option value="">All</option>@foreach(\App\Models\TransitionMeasure::STATUSES as $k => $v)<option value="{{ $k }}" @selected($filters['status'] === $k)>{{ $v }}</option>@endforeach</select></div>
        <div><label for="t-j" class="label">Jurisdiction</label><select id="t-j" name="jurisdiction" class="input"><option value="">All</option>@foreach($jurisdictions as $j)<option value="{{ $j->slug }}" @selected($filters['jurisdiction'] === $j->slug)>{{ $j->name }}</option>@endforeach</select></div>
        <div class="flex items-end gap-2"><button type="submit" class="btn-primary">Apply</button><a href="{{ route('transition.index') }}" class="btn-secondary">Reset</a></div>
    </form>
    @endunless

    <div class="mt-8 grid gap-10 lg:grid-cols-3">
        <div class="lg:col-span-2 min-w-0">
            <section aria-labelledby="measures-heading">
                <h2 id="measures-heading" class="section-title">Measures <span class="text-sm font-normal text-brand-muted">({{ $measures->count() }})</span></h2>
                <div class="table-wrap mt-3"><table><caption class="sr-only">AI economic transition measures</caption>
                    <thead><tr><th scope="col">Measure</th><th scope="col">Type</th><th scope="col">Jurisdiction</th><th scope="col">Status</th><th scope="col">Record</th></tr></thead>
                    <tbody>@forelse($measures as $m)<tr>
                        <th scope="row" class="font-medium"><a href="{{ $m->url() }}" class="text-brand-navy">{{ $m->title }}</a></th>
                        <td class="whitespace-nowrap">{{ $m->typeLabel() }}</td>
                        <td><a href="{{ $m->jurisdiction->url() }}" class="text-brand-body">{{ $m->jurisdiction->name }}</a></td>
                        <td class="whitespace-nowrap">{{ $m->statusLabel() }}</td>
                        <td>@if($m->isDraft())<span class="badge bg-brand-paper text-brand-body ring-brand-line" title="Not yet read from an official source">Draft</span>@else<span class="badge bg-state-goodbg text-state-good ring-state-good/30">Verified</span>@endif</td>
                    </tr>@empty<tr><td colspan="5" class="text-brand-muted">No measure recorded for these filters.</td></tr>@endforelse</tbody></table></div>
            </section>

            @if($timeline->isNotEmpty())
            <section aria-labelledby="timeline-heading" class="mt-8">
                <h2 id="timeline-heading" class="section-title">Timeline (verified measures)</h2>
                <ol class="mt-3 border-l-2 border-brand-line pl-4 space-y-2 text-sm">@foreach($timeline as $e)<li><time datetime="{{ $e['date']->toDateString() }}" class="font-medium text-brand-navy">{{ $e['date']->format('j M Y') }}</time> · <a href="{{ $e['measure']->url() }}" class="text-brand-body hover:underline">{{ $e['label'] }}</a></li>@endforeach</ol>
            </section>
            @endif

            @if($byType !== [])
            <section aria-labelledby="chart-heading" class="mt-8">
                <h2 id="chart-heading" class="section-title">By type and status</h2>
                <div class="mt-3 grid gap-4 md:grid-cols-2">
                    <x-site.bar-chart :series="$byType" title="Measures by type" :export="false" />
                    <x-site.bar-chart :series="$byStatus" title="Measures by status" :export="false" />
                </div>
                <details class="mt-2 text-sm"><summary class="cursor-pointer text-brand-blue">Data behind the charts</summary><table class="mt-2 text-sm"><caption class="sr-only">Counts by type and status</caption><thead><tr><th scope="col">Type</th><th scope="col">Count</th></tr></thead><tbody>@foreach($byType as $t => $n)<tr><td>{{ $t }}</td><td>{{ $n }}</td></tr>@endforeach</tbody></table></details>
            </section>
            @endif

            <section aria-labelledby="arguments-heading" class="mt-8">
                <h2 id="arguments-heading" class="section-title">Arguments made, for and against</h2>
                <p class="mt-1 text-xs text-brand-muted">Attributed to who made them, from the verified records. The tracker records positions; it does not hold one.</p>
                <div class="mt-3 grid gap-4 md:grid-cols-2">
                    <div><h3 class="text-sm font-semibold text-brand-navy">For</h3>@forelse($arguments['for'] as $a)<blockquote class="mt-2 border-l-2 border-brand-line pl-3 text-sm text-brand-body">{{ $a['claim'] }}<footer class="text-xs text-brand-muted">— {{ $a['attributed_to'] }}, on <a href="{{ $a['measure']->url() }}">{{ $a['measure']->title }}</a>@if(!empty($a['source_url'])) · <a href="{{ $a['source_url'] }}" rel="noopener">source</a>@endif</footer></blockquote>@empty<p class="mt-2 text-sm text-brand-muted">None recorded from a verified source yet.</p>@endforelse</div>
                    <div><h3 class="text-sm font-semibold text-brand-navy">Against</h3>@forelse($arguments['against'] as $a)<blockquote class="mt-2 border-l-2 border-brand-line pl-3 text-sm text-brand-body">{{ $a['claim'] }}<footer class="text-xs text-brand-muted">— {{ $a['attributed_to'] }}, on <a href="{{ $a['measure']->url() }}">{{ $a['measure']->title }}</a>@if(!empty($a['source_url'])) · <a href="{{ $a['source_url'] }}" rel="noopener">source</a>@endif</footer></blockquote>@empty<p class="mt-2 text-sm text-brand-muted">None recorded from a verified source yet.</p>@endforelse</div>
                </div>
            </section>

            <x-site.faq :items="$seo->faqItems()" />
            <x-site.disclaimer class="mt-8" />
        </div>
        <aside class="space-y-6">
            <section aria-labelledby="index-heading">
                <h2 id="index-heading" class="section-title">Displacement policy index</h2>
                <p class="mt-1 text-xs text-brand-muted">0–100 per jurisdiction from verified measures; <a href="{{ route('transition.methodology') }}">method and weights</a> (v{{ \App\Services\Transition\DisplacementPolicyIndex::VERSION }}).</p>
                @if($index->isEmpty())<p class="mt-2 text-sm text-brand-muted">No jurisdiction scores yet: every recorded measure is still a draft.</p>@else
                <ol class="mt-2 space-y-1.5 text-sm">@foreach($index->take(10) as $s)<li class="flex items-baseline justify-between gap-2"><a href="{{ $s->jurisdiction->url() }}" class="text-brand-navy hover:underline">{{ $s->jurisdiction->name }}</a><span class="font-mono text-xs">{{ $s->score }}<span class="text-brand-muted">/100 · {{ $s->quarter }}</span></span></li>@endforeach</ol>
                @endif
            </section>
            <section aria-labelledby="ind-heading">
                <h2 id="ind-heading" class="section-title">Indicators</h2>
                <ul class="mt-2 space-y-2 text-sm">@forelse($indicators as $i)<li><span class="font-medium text-brand-navy">{{ $i->title }}</span> <span class="meta">({{ $i->unit }}@if($i->jurisdiction), {{ $i->jurisdiction->name }}@endif)</span><br>@if($i->latest())<span class="text-brand-body">Latest {{ $i->latest()['period'] }}: {{ number_format($i->latest()['value']) }}</span> · <a href="{{ $i->latest()['source_url'] }}" rel="noopener" class="text-brand-blue">source</a>@else<span class="text-brand-muted">No data points yet; defined, awaiting a verified source.</span>@endif</li>@empty<li class="text-brand-muted">No indicator defined yet.</li>@endforelse</ul>
                <p class="mt-2 text-xs"><a href="{{ route('api.v1.transition.indicators') }}" class="text-brand-blue">Indicators as JSON</a></p>
            </section>
            <section aria-labelledby="land-heading">
                <h2 id="land-heading" class="section-title">By theme</h2>
                <ul class="mt-2 space-y-1.5 text-sm">@foreach(\App\Http\Controllers\Site\TransitionController::LANDINGS as $slug => $l)<li><a href="{{ route('transition.landing', $slug) }}" class="text-brand-navy hover:underline">{{ $l['h1'] }}</a></li>@endforeach<li><a href="{{ route('transition.index') }}" class="text-brand-blue hover:underline">All measures</a> · <a href="{{ route('gaps') }}" class="text-brand-blue hover:underline">Open gaps</a></li></ul>
            </section>
            <x-site.subscribe-form source="transition" />
        </aside>
    </div>
</div>
@endsection
