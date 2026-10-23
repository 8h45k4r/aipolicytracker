@extends('site.layouts.app')
@section('content')
<div class="container-site py-8">
    <x-site.breadcrumbs :items="$seo->breadcrumbs" />
    <h1 class="mt-2 text-2xl sm:text-3xl font-semibold tracking-tight text-brand-navy">AI regulations around the world</h1>
    <p class="mt-2 max-w-3xl text-brand-body">Each jurisdiction page answers the same questions: what is binding, what is guidance, what applies when, and where the official sources are. Coverage grows only as records are source-backed and reviewed.</p>
    <form action="{{ route('policies.index') }}" method="get" class="mt-4 max-w-md flex gap-2" role="search"><label for="j-q" class="sr-only">Search policies</label><input id="j-q" name="q" type="search" class="input" placeholder="Search policies"><button class="btn-primary" type="submit">Search</button></form>

    {{-- Type filter pills --}}
    <nav class="mt-4 flex flex-wrap gap-2" aria-label="Filter by jurisdiction type">
        <a href="{{ route('jurisdictions.index', array_filter(['region' => $regionFilter])) }}" class="inline-flex items-center rounded-full px-3 py-1 text-sm font-medium transition {{ !$typeFilter ? 'bg-brand-navy text-white' : 'bg-brand-paper text-brand-body hover:bg-brand-line' }}">All <span class="ml-1 text-xs opacity-75">({{ $typeCounts->sum() }})</span></a>
        @foreach([['country', 'Countries'], ['state', 'States'], ['supranational', 'Supranational'], ['territory', 'Territories'], ['region', 'Regions']] as [$type, $label])
            @if(($typeCounts[$type] ?? 0) > 0)
            <a href="{{ route('jurisdictions.index', array_filter(['type' => $type, 'region' => $regionFilter])) }}" class="inline-flex items-center rounded-full px-3 py-1 text-sm font-medium transition {{ $typeFilter === $type ? 'bg-brand-navy text-white' : 'bg-brand-paper text-brand-body hover:bg-brand-line' }}">{{ $label }} <span class="ml-1 text-xs opacity-75">({{ $typeCounts[$type] }})</span></a>
            @endif
        @endforeach
    </nav>

    {{-- Region filter pills --}}
    <nav class="mt-2 flex flex-wrap gap-2" aria-label="Filter by region">
        <a href="{{ route('jurisdictions.index', array_filter(['type' => $typeFilter])) }}" class="inline-flex items-center rounded-full px-3 py-1 text-sm font-medium transition {{ !$regionFilter ? 'bg-brand-navy text-white' : 'bg-brand-paper text-brand-body hover:bg-brand-line' }}">All regions <span class="ml-1 text-xs opacity-75">({{ $regionCounts->sum() }})</span></a>
        @foreach(\App\Http\Controllers\Site\JurisdictionController::REGIONS as $r)
            @if(($regionCounts[$r] ?? 0) > 0)
            <a href="{{ route('jurisdictions.index', array_filter(['type' => $typeFilter, 'region' => $r])) }}" class="inline-flex items-center rounded-full px-3 py-1 text-sm font-medium transition {{ $regionFilter === $r ? 'bg-brand-navy text-white' : 'bg-brand-paper text-brand-body hover:bg-brand-line' }}">{{ $r }} <span class="ml-1 text-xs opacity-75">({{ $regionCounts[$r] }})</span></a>
            @endif
        @endforeach
    </nav>

    {{-- Sort and region jump links --}}
    <div class="mt-4 flex flex-wrap items-center gap-x-6 gap-y-2">
        <div class="flex items-center gap-2 text-sm">
            <label for="j-sort" class="text-brand-muted">Sort:</label>
            <select id="j-sort" onchange="location.href=this.value" class="input !py-1 !text-sm !min-h-0">
                @foreach(\App\Http\Controllers\Site\JurisdictionController::SORTS as $key => $label)
                <option value="{{ route('jurisdictions.index', array_filter(['type' => $typeFilter, 'region' => $regionFilter, 'sort' => $key === 'name' ? null : $key])) }}" @selected($sort === $key)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        @if($byRegion->count() > 1)
        <nav class="flex flex-wrap gap-x-4 gap-y-1 text-sm" aria-label="Jump to region">
            <span class="text-brand-muted">Jump to:</span>
            @foreach($byRegion->keys() as $region)
            <a href="#region-{{ \Illuminate\Support\Str::slug($region) }}" class="text-brand-navy hover:underline">{{ $region }}</a>
            @endforeach
        </nav>
        @endif
    </div>

    {{-- On a phone the list comes first and the map last: the tables answer the page's
         question in text, and the map script is fetched only if a reader scrolls to it. --}}
    <div class="flex flex-col">
    <x-site.world-map class="order-last mt-8 sm:order-none sm:mt-6" title="AI regulation around the world" />
    @foreach($byRegion as $region => $items)
    <section class="mt-8" aria-labelledby="region-{{ \Illuminate\Support\Str::slug($region) }}">
        <h2 id="region-{{ \Illuminate\Support\Str::slug($region) }}" class="section-title">{{ $region }}</h2>
        {{-- Below sm a five-column table only scrolls sideways, so each row becomes a stacked card;
             the table stays for wider screens, where public.js makes it a region if it overflows. --}}
        <ul class="mt-3 divide-y divide-brand-line border-y border-brand-line sm:hidden" aria-label="Jurisdictions in {{ $region }}">
            @foreach($items as $j)
            <li class="py-3 text-sm">
                <a href="{{ $j->url() }}" class="font-medium text-brand-navy hover:underline">{{ $j->name }}</a>
                <p class="text-xs text-brand-muted">{{ ucfirst($j->jurisdiction_type) }}@if($j->parent) · part of {{ $j->parent->name }}@endif · {{ $j->policies_count }} {{ \Illuminate\Support\Str::plural('instrument', $j->policies_count) }}</p>
                <p class="mt-1 text-brand-body">{{ \Illuminate\Support\Str::limit($j->regulatory_status_summary, 180) }}</p>
                <div class="mt-1"><x-site.verified :record="$j" compact /></div>
            </li>
            @endforeach
        </ul>
        <div class="table-wrap mt-3 table-sticky hidden sm:block">
            <table>
                <caption class="sr-only">Jurisdictions in {{ $region }}</caption>
                <thead><tr><th scope="col">Jurisdiction</th><th scope="col">Type</th><th scope="col">Regulatory status</th><th scope="col">Instruments</th><th scope="col">Verification</th></tr></thead>
                <tbody>
                @foreach($items as $j)
                <tr>
                    <th scope="row" class="font-medium"><a href="{{ $j->url() }}" class="text-brand-navy hover:underline">{{ $j->name }}</a>@if($j->parent)<div class="text-xs text-brand-muted">part of {{ $j->parent->name }}</div>@endif</th>
                    <td class="whitespace-nowrap">{{ ucfirst($j->jurisdiction_type) }}</td>
                    <td class="min-w-[18rem]">{{ \Illuminate\Support\Str::limit($j->regulatory_status_summary, 180) }}</td>
                    <td class="whitespace-nowrap">{{ $j->policies_count }}</td>
                    <td class="whitespace-nowrap"><x-site.verified :record="$j" compact /></td>
                </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    </section>
    @endforeach
    </div>
    <p class="mt-8 text-sm text-brand-muted">Showing {{ $jurisdictions->count() }} jurisdiction{{ $jurisdictions->count() !== 1 ? 's' : '' }}@if($typeFilter) ({{ $typeFilter }})@endif @if($regionFilter) in {{ $regionFilter }}@else across {{ $byRegion->count() }} region{{ $byRegion->count() !== 1 ? 's' : '' }}@endif, with {{ $jurisdictions->sum('policies_count') }} recorded instrument{{ $jurisdictions->sum('policies_count') !== 1 ? 's' : '' }}.</p>
    <p class="mt-2 text-sm text-brand-muted">Missing a jurisdiction? <a href="{{ route('contribute', ['type' => 'new_policy']) }}">Propose one with an official source</a>.</p>
</div>
@endsection
