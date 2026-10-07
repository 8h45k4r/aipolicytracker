@extends('site.layouts.app')
@section('content')
<div class="container-site py-8">
    <x-site.breadcrumbs :items="$seo->breadcrumbs" />
    <p class="eyebrow mt-2">Standards tracker</p>
    <h1 class="mt-1 text-2xl sm:text-3xl font-semibold tracking-tight text-brand-navy">AI standards tracker: harmonised standards and ISO/IEC work items</h1>

    <section class="mt-5 card-flat p-5" aria-labelledby="summary-heading">
        <h2 id="summary-heading" class="sr-only">Summary</h2>
        <p class="text-brand-navy text-base sm:text-lg leading-relaxed">{{ $answer }}</p>
        <dl class="mt-4 grid grid-cols-2 gap-4 sm:grid-cols-4">
            <x-site.stat label="Standards" :value="number_format($all->count())" />
            <x-site.stat label="Recorded" :value="number_format($counts['recorded'])" note="from a cited source" />
            <x-site.stat label="Verified" :value="number_format($counts['verified'])" note="by a named reviewer" />
            <x-site.stat label="Drafts" :value="number_format($counts['drafts'])" />
        </dl>
        <p class="mt-3 text-xs text-brand-muted">Dataset: <a href="{{ route('api.v1.implementation', ['standards' => 1]) }}">JSON</a> · <a href="{{ route('open-data.csv', 'implementation') }}">CSV</a> · <a href="{{ route('open-data.ndjson', 'implementation') }}">NDJSON</a></p>
    </section>

    <form method="get" action="{{ route('standards.index') }}" class="mt-5 grid gap-3 sm:grid-cols-4" data-autosubmit aria-label="Filter standards">
        <div><label for="s-body" class="label">Body</label><select id="s-body" name="body" class="input"><option value="">All bodies</option>@foreach(['cen_cenelec_jtc_21', 'iso_iec_jtc_1_sc_42'] as $k)<option value="{{ $k }}" @selected($filters['body'] === $k)>{{ \App\Models\ImplementationMeasure::BODIES[$k] }}</option>@endforeach</select></div>
        <div class="flex items-end gap-2"><button type="submit" class="btn-primary">Apply</button><a href="{{ route('standards.index') }}" class="btn-secondary">Reset</a></div>
    </form>

    <section aria-labelledby="standards-heading" class="mt-8">
        <h2 id="standards-heading" class="section-title">Standards <span class="text-sm font-normal text-brand-muted">({{ $measures->count() }})</span></h2>
        @include('site.implementation._table', ['measures' => $measures, 'caption' => 'AI standards', 'standards' => true])
        <p class="mt-2 text-xs text-brand-muted">Metadata only. Standards are sold under licence by their publishers; follow the title to the publisher's page. A dash means the value has not been read from the publisher's page.</p>
    </section>

    <x-site.faq :items="$seo->faqItems()" />
    <x-site.disclaimer class="mt-8" />
</div>
@endsection
