@extends('site.layouts.app')
@section('content')
<div class="container-site py-8">
    <x-site.breadcrumbs :items="$seo->breadcrumbs" />
    <p class="eyebrow mt-2">Enforcement</p>
    <h1 class="mt-1 text-2xl sm:text-3xl font-semibold tracking-tight text-brand-navy">AI enforcement tracker: fines, orders and court decisions</h1>

    {{-- The answer box: computed from the records, never written by hand. --}}
    <section class="mt-5 card-flat p-5" aria-labelledby="summary-heading">
        <h2 id="summary-heading" class="sr-only">Summary</h2>
        <p class="text-brand-navy text-base sm:text-lg leading-relaxed">{{ $answer }}</p>
        <dl class="mt-4 grid grid-cols-2 gap-4 sm:grid-cols-4">
            <x-site.stat label="Actions" :value="number_format($all->count())" />
            <x-site.stat label="Verified" :value="number_format($verified->count())" note="read from the official source" />
            <x-site.stat label="Awaiting review" :value="number_format($recorded->count() - $verified->count())" />
            <x-site.stat label="Jurisdictions" :value="number_format($jurisdictions->count())" />
        </dl>
        <p class="mt-3 text-xs text-brand-muted">Dataset: <a href="{{ route('api.v1.enforcement') }}">JSON</a> · <a href="{{ route('open-data.csv', 'enforcement') }}">CSV</a> · <a href="{{ route('open-data.ndjson', 'enforcement') }}">NDJSON</a></p>
    </section>

    @if($all->isNotEmpty())
    <form method="get" action="{{ route('enforcement.index') }}" class="mt-5 grid gap-3 sm:grid-cols-4" data-autosubmit aria-label="Filter enforcement actions">
        <div><label for="e-j" class="label">Jurisdiction</label><select id="e-j" name="jurisdiction" class="input"><option value="">All</option>@foreach($jurisdictions as $j)<option value="{{ $j->slug }}" @selected($filters['jurisdiction'] === $j->slug)>{{ $j->name }}</option>@endforeach</select></div>
        <div><label for="e-k" class="label">Kind</label><select id="e-k" name="kind" class="input"><option value="">All kinds</option>@foreach(\App\Models\EnforcementEvent::KINDS as $k => $v)<option value="{{ $k }}" @selected($filters['kind'] === $k)>{{ $v }}</option>@endforeach</select></div>
        <div><label for="e-y" class="label">Year</label><select id="e-y" name="year" class="input"><option value="">All years</option>@foreach($years as $y)<option value="{{ $y }}" @selected((string) $filters['year'] === (string) $y)>{{ $y }}</option>@endforeach</select></div>
        <div class="flex items-end gap-2"><button type="submit" class="btn-primary">Apply</button><a href="{{ route('enforcement.index') }}" class="btn-secondary">Reset</a></div>
    </form>
    @endif

    <section aria-labelledby="events-heading" class="mt-8">
        <h2 id="events-heading" class="section-title">Actions <span class="text-sm font-normal text-brand-muted">({{ $events->count() }})</span></h2>
        <div class="table-wrap mt-3"><table><caption class="sr-only">Enforcement actions</caption>
            <thead><tr><th scope="col">Date</th><th scope="col">Action</th><th scope="col">Kind</th><th scope="col">Regulator</th><th scope="col">Respondent</th><th scope="col">Amount</th><th scope="col">Appeal</th><th scope="col">Record</th></tr></thead>
            <tbody>@forelse($events as $e)<tr id="{{ $e->slug }}">
                <td class="whitespace-nowrap">@if($e->occurred_on)<time datetime="{{ $e->occurred_on->toDateString() }}">{{ $e->occurred_on->format('j M Y') }}</time>@else — @endif</td>
                <th scope="row" class="font-medium">{{ $e->title }}<br><span class="text-xs font-normal text-brand-muted">@if($e->policyInstrument)<a href="{{ $e->policyInstrument->url() }}">{{ $e->policyInstrument->short_title ?: $e->policyInstrument->title }}</a>@endif @if($e->legal_basis) · {{ $e->legal_basis }}@endif @if($e->jurisdiction) · {{ $e->jurisdiction->name }}@endif</span>@if($e->outcome)<br><span class="text-xs font-normal text-brand-body">{{ $e->outcome }}</span>@endif</th>
                <td class="whitespace-nowrap">{{ $e->kindLabel() ?? '—' }}</td>
                <td>{{ $e->regulatorName() ?? '—' }}</td>
                <td>{{ $e->respondent ?? '—' }}</td>
                <td class="whitespace-nowrap">{{ $e->amountLabel() ?? '—' }}</td>
                <td class="whitespace-nowrap">{{ $e->appealLabel() ?? '—' }}</td>
                <td>@if($e->official_source_url)<a href="{{ $e->official_source_url }}" rel="noopener">Source</a>@endif<br>@if($e->isVerified())<span class="badge bg-state-goodbg text-state-good ring-state-good/30">Verified</span>@elseif($e->isDraft())<span class="badge bg-brand-paper text-brand-body ring-brand-line">Draft</span>@else<span class="badge bg-brand-paper text-brand-body ring-brand-line" title="Recorded from a cited source; not yet confirmed by a named reviewer">Pending review</span>@endif</td>
            </tr>@empty<tr><td colspan="8" class="text-brand-muted">No enforcement action recorded{{ array_filter($filters) ? ' for these filters' : ' yet' }}.</td></tr>@endforelse</tbody></table></div>
        <p class="mt-2 text-xs text-brand-muted">A dash means the official source does not state the value. Amounts are as published, in the currency published; nothing is converted or estimated.</p>
    </section>

    @if($byKind !== [] && count($byKind) > 1)
    <section aria-labelledby="chart-heading" class="mt-8">
        <h2 id="chart-heading" class="section-title">By kind</h2>
        <div class="mt-3 max-w-xl"><x-site.bar-chart :series="$byKind" title="Enforcement actions by kind" :export="false" /></div>
    </section>
    @endif

    <x-site.faq :items="$seo->faqItems()" />
    <p class="mt-6 text-sm"><a href="{{ route('contribute') }}" class="text-brand-blue">Report an enforcement action</a> with a link to the regulator's or court's own publication.</p>
    <x-site.disclaimer class="mt-8" />
</div>
@endsection
