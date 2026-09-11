@extends('site.layouts.app')
@section('content')
<div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 py-8">
    <x-site.breadcrumbs :items="$seo->breadcrumbs" />
    <h1 class="mt-2 text-2xl sm:text-3xl font-semibold tracking-tight text-slate-900">AI regulation by country and region</h1>
    <p class="mt-2 max-w-3xl text-slate-700">Each jurisdiction page answers the same questions: what is binding, what is guidance, what applies when, and where the official sources are. Coverage grows only as records are source-backed and reviewed.</p>
    <form action="{{ route('policies.index') }}" method="get" class="mt-4 max-w-md flex gap-2" role="search"><label for="j-q" class="sr-only">Search policies</label><input id="j-q" name="q" type="search" class="input" placeholder="Search policies across jurisdictions"><button class="btn-primary" type="submit">Search</button></form>
    @foreach($byRegion as $region => $items)
    <section class="mt-8" aria-labelledby="region-{{ \Illuminate\Support\Str::slug($region) }}">
        <h2 id="region-{{ \Illuminate\Support\Str::slug($region) }}" class="section-title">{{ $region }}</h2>
        <div class="table-wrap mt-3 table-sticky">
            <table>
                <caption class="sr-only">Jurisdictions in {{ $region }}</caption>
                <thead><tr><th scope="col">Jurisdiction</th><th scope="col">Type</th><th scope="col">Regulatory status</th><th scope="col">Instruments</th><th scope="col">Verification</th></tr></thead>
                <tbody>
                @foreach($items as $j)
                <tr>
                    <th scope="row" class="font-medium"><a href="{{ $j->url() }}" class="text-slate-900 hover:underline">{{ $j->name }}</a>@if($j->parent)<div class="text-xs text-slate-500">part of {{ $j->parent->name }}</div>@endif</th>
                    <td class="whitespace-nowrap">{{ ucfirst($j->jurisdiction_type) }}</td>
                    <td class="min-w-[18rem]">{{ \Illuminate\Support\Str::limit($j->regulatory_status_summary, 180) }}</td>
                    <td class="whitespace-nowrap">{{ $j->policies_count }}</td>
                    <td class="whitespace-nowrap"><x-site.verified :record="$j" /></td>
                </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    </section>
    @endforeach
    <p class="mt-8 text-sm text-slate-600">Missing a jurisdiction? <a href="{{ route('contribute', ['type' => 'new_policy']) }}">Propose one with an official source</a>.</p>
</div>
@endsection
