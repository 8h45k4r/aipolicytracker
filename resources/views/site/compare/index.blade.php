@extends('site.layouts.app')
@section('content')
<div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 py-8">
    <x-site.breadcrumbs :items="$seo->breadcrumbs" />
    <h1 class="mt-2 text-2xl sm:text-3xl font-semibold tracking-tight text-slate-900">Compare AI regulation across jurisdictions</h1>
    <p class="mt-2 max-w-3xl text-slate-700">Pick two to four jurisdictions. Every cell is derived from published, source-backed records so you can see where binding rules exist, where guidance applies and where nothing is recorded yet.</p>
    <form method="get" action="{{ route('compare.index') }}" class="mt-5 card-flat p-4">
        <fieldset><legend class="label">Jurisdictions (choose 2 to 4)</legend>
            <div class="mt-2 grid gap-2 sm:grid-cols-3 lg:grid-cols-5">
                @foreach($all as $j)<label class="flex items-center gap-2 text-sm min-h-[40px]"><input type="checkbox" name="j[]" value="{{ $j->slug }}" class="rounded border-slate-300 text-teal-700 focus:ring-teal-600" @checked($selected->contains($j->slug))> {{ $j->name }}</label>@endforeach
            </div>
        </fieldset>
        <button type="submit" class="btn-primary mt-4" data-track="compare_submit">Compare</button>
    </form>
    @if($jurisdictions->count() >= 2)
        <h2 class="mt-8 section-title">{{ $jurisdictions->pluck('short_name')->map(fn($s, $i) => $s ?: $jurisdictions[$i]->name)->implode(' vs ') }}</h2>
        @include('site.compare._table')
    @elseif($selected->isNotEmpty())
        <div class="mt-6"><x-site.empty title="Select at least two jurisdictions">Choose two to four jurisdictions above to build a comparison table.</x-site.empty></div>
    @endif
    <section class="mt-10" aria-labelledby="curated-heading">
        <h2 id="curated-heading" class="section-title">Curated comparisons</h2>
        <ul class="mt-3 grid gap-3 sm:grid-cols-2 text-sm">@foreach($curated as $c)<li class="card-flat p-4"><a href="{{ route('compare.show', $c['slug']) }}" class="font-semibold text-slate-900 hover:underline">{{ $c['title'] }}</a><p class="mt-1 text-slate-700">{{ $c['intro'] }}</p></li>@endforeach</ul>
    </section>
    <x-site.disclaimer class="mt-8" />
</div>
@endsection
