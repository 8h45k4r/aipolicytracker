@extends('site.layouts.app')
@section('content')
<div class="container-site py-8">
    <x-site.breadcrumbs :items="$seo->breadcrumbs" />
    <h1 class="mt-2 text-2xl sm:text-3xl font-semibold tracking-tight text-brand-navy">Compare AI regulation across jurisdictions</h1>
    <p class="mt-2 max-w-3xl text-brand-body">Pick two to four jurisdictions. Every cell is derived from published, source-backed records so you can see where binding rules exist, where guidance applies and where nothing is recorded yet.</p>
    <form method="get" action="{{ route('compare.index') }}" class="mt-5 card-flat p-4">
        <x-site.jurisdiction-picker name="j" :jurisdictions="$all" :selected="$selected->all()" :max="4"
            legend="Jurisdictions (choose 2 to 4)"
            hint="{{ $all->count() }} recorded. The number beside a name is how many instruments it has; a dash means nothing AI-specific is recorded yet, which a comparison will show as gaps." />
        <div class="mt-4 flex flex-wrap gap-2">
            <button type="submit" class="btn-primary" data-track="compare_submit">Compare</button>
            @if($selected->isNotEmpty())<a href="{{ route('compare.index') }}" class="btn-secondary">Start again</a>@endif
        </div>
    </form>
    @if($jurisdictions->count() >= 2)
        <h2 class="mt-8 section-title">{{ $jurisdictions->pluck('short_name')->map(fn($s, $i) => $s ?: $jurisdictions[$i]->name)->implode(' vs ') }}</h2>
        @include('site.compare._table')
    @elseif($selected->isNotEmpty())
        <div class="mt-6"><x-site.empty title="Select at least two jurisdictions">Choose two to four jurisdictions above to build a comparison table.</x-site.empty></div>
    @endif
    <section class="mt-10" aria-labelledby="curated-heading">
        <h2 id="curated-heading" class="section-title">Curated comparisons</h2>
        <ul class="mt-3 grid gap-3 sm:grid-cols-2 text-sm">@foreach($curated as $c)<li class="card-flat p-4"><a href="{{ route('compare.show', $c['slug']) }}" class="font-semibold text-brand-navy hover:underline">{{ $c['title'] }}</a><p class="mt-1 text-brand-body">{{ $c['intro'] }}</p></li>@endforeach</ul>
    </section>
    <x-site.disclaimer class="mt-8" />
</div>
@endsection
