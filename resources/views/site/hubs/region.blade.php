@extends('site.layouts.app')
@section('content')
<div class="container-site py-8">
    <x-site.breadcrumbs :items="$seo->breadcrumbs" />
    <p class="eyebrow mt-2">Regional hub</p>
    <h1 class="mt-1 text-2xl sm:text-3xl font-semibold tracking-tight text-brand-navy">AI regulation in {{ $regionName }}</h1>

    {{-- The answer box: computed from the records on the page, never written by hand. --}}
    <section class="mt-5 card-flat p-5" aria-labelledby="summary-heading">
        <h2 id="summary-heading" class="sr-only">Summary</h2>
        <p class="text-brand-navy text-base sm:text-lg leading-relaxed">{{ $answer }}</p>
        <dl class="mt-4 grid grid-cols-2 gap-4 sm:grid-cols-4">
            <x-site.stat label="Jurisdictions with records" :value="number_format($withInstruments->count())" />
            <x-site.stat label="With binding AI law" :value="number_format($withBinding->count())" />
            <x-site.stat label="Instruments" :value="number_format($policyIds->count())" :href="route('policies.index')" />
            <x-site.stat label="Recorded duties" :value="number_format($duties)" :href="route('obligations.index')" />
        </dl>
        @if($seo->modified)<p class="mt-3 text-xs text-brand-muted">Last updated <time datetime="{{ $seo->modified->toAtomString() }}">{{ $seo->modified->format('j M Y') }}</time></p>@endif
    </section>

    <div class="mt-8 grid gap-10 lg:grid-cols-3">
        <div class="lg:col-span-2 min-w-0">
            <section aria-labelledby="countries-heading">
                <h2 id="countries-heading" class="section-title">Country by country</h2>
                <div class="table-wrap mt-3"><table><caption class="sr-only">Jurisdictions in {{ $regionName }} with recorded AI policy</caption>
                    <thead><tr><th scope="col">Jurisdiction</th><th scope="col">Instruments</th><th scope="col">Binding</th><th scope="col">Status</th></tr></thead>
                    <tbody>@foreach($withInstruments as $j)<tr><th scope="row" class="font-medium"><a href="{{ $j->url() }}" class="text-brand-navy">{{ $j->name }}</a></th><td>{{ $j->instruments_count }}</td><td>{{ $j->binding_count > 0 ? $j->binding_count.' binding' : 'Guidance and strategy' }}</td><td class="text-sm">{{ \Illuminate\Support\Str::limit($j->regulatory_status_summary, 140) }}</td></tr>@endforeach</tbody></table></div>
                @php($without = $members->filter(fn ($j) => $j->instruments_count === 0))
                @if($without->isNotEmpty())<p class="mt-2 text-xs text-brand-muted">No instrument recorded yet for: {{ $without->pluck('name')->join(', ') }}. <a href="{{ route('gaps') }}">See the open gaps</a>.</p>@endif
            </section>

            @if($binding->isNotEmpty())
            <section aria-labelledby="binding-heading" class="mt-8">
                <h2 id="binding-heading" class="section-title">Binding AI instruments in {{ $regionName }}</h2>
                <ul class="mt-2 divide-y divide-brand-line border-y border-brand-line text-sm">@foreach($binding as $p)<li class="py-2"><a href="{{ $p->url() }}" class="font-medium text-brand-navy no-underline hover:underline">{{ $p->short_title ?: $p->title }}</a> <span class="meta">· {{ $p->jurisdiction->name }} · {{ $p->statusEnum()->label() }}@if($p->applies_from) · applies from {{ $p->applies_from->format('j M Y') }}@endif</span></li>@endforeach</ul>
            </section>
            @endif

            <section aria-labelledby="changes-heading" class="mt-8">
                <h2 id="changes-heading" class="section-title">Latest changes in {{ $regionName }}</h2>
                <div class="mt-1 divide-y divide-brand-line border-y border-brand-line">@forelse($changes as $c)<x-site.change-item :change="$c" />@empty<p class="py-4 text-sm text-brand-muted">No change recorded yet.</p>@endforelse</div>
                <p class="mt-2 text-sm"><a href="{{ route('updates.index') }}" class="text-brand-blue hover:underline">All AI policy updates →</a></p>
            </section>

            <x-site.faq :items="$seo->faqItems()" />
            <x-site.disclaimer class="mt-8" />
        </div>
        <aside class="space-y-6">
            @if($deadlines->isNotEmpty())
            <section aria-labelledby="deadlines-heading"><h2 id="deadlines-heading" class="section-title">Upcoming deadlines</h2>
                <ul class="mt-2 space-y-2 text-sm">@foreach($deadlines as $d)<li><time datetime="{{ $d->due_on?->toDateString() }}" class="font-medium text-brand-navy">{{ $d->displayDate() }}</time> · <a href="{{ $d->policyInstrument->url() }}" class="text-brand-body hover:underline">{{ $d->title }}</a> <span class="meta">{{ $d->policyInstrument->jurisdiction->name }}</span></li>@endforeach</ul>
                <p class="mt-2 text-sm"><a href="{{ route('calendar') }}" class="text-brand-blue hover:underline">Deadline calendar →</a></p>
            </section>
            @endif
            <section aria-labelledby="regions-heading"><h2 id="regions-heading" class="section-title">Other regions</h2>
                <ul class="mt-2 space-y-1.5 text-sm">@foreach(\App\Services\Hubs\HubCatalog::regions() as $slug => $name)@continue($name === $regionName)<li><a href="{{ \App\Services\Hubs\HubCatalog::regionUrl($slug) }}" class="text-brand-navy hover:underline">AI regulation in {{ $name }}</a></li>@endforeach</ul>
                <p class="mt-2 text-sm"><a href="{{ route('jurisdictions.index') }}" class="text-brand-blue hover:underline">All jurisdictions →</a> · <a href="{{ route('compare.index') }}" class="text-brand-blue hover:underline">Compare →</a></p>
            </section>
            <x-site.subscribe-form source="region" />
        </aside>
    </div>
</div>
@endsection
