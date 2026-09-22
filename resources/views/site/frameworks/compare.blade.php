@extends('site.layouts.app')
@section('content')
<div class="container-site py-8">
    <x-site.breadcrumbs :items="$seo->breadcrumbs" />
    <header class="mt-3 max-w-3xl">
        <p class="eyebrow">Framework relationships</p>
        <h1 class="mt-2 text-2xl sm:text-3xl lg:text-4xl font-semibold tracking-tight text-brand-navy">What can be reused between frameworks?</h1>
        <p class="mt-3 text-lg leading-8 text-brand-body">Organisations are audited against standards but regulated by statutes. This page puts the documents side by side and then, for every category of legal duty, counts the controls that meet those duties and have a home in each framework. It is computed from the recorded mappings, so it moves when they do.</p>
    </header>

    <section class="mt-8" aria-labelledby="kinds-heading">
        <h2 id="kinds-heading" class="section-title">What kind of document each one is</h2>
        <div class="table-wrap table-sticky mt-3"><table>
            <caption class="sr-only">Frameworks compared by type</caption>
            <thead><tr><th scope="col">Dimension</th>@foreach($matrix['frameworks'] as $f)<th scope="col"><a href="{{ route('frameworks.show', $f['slug']) }}" class="text-brand-navy">{{ $f['short'] }}</a></th>@endforeach</tr></thead>
            <tbody>
                <tr><th scope="row" class="bg-white font-medium">Type</th>@foreach($matrix['frameworks'] as $f)<td>{{ $kinds[$f['kind']] ?? ucfirst(str_replace('_', ' ', $f['kind'])) }}</td>@endforeach</tr>
                <tr><th scope="row" class="bg-white font-medium">Legally binding</th>@foreach($matrix['frameworks'] as $f)<td>No</td>@endforeach</tr>
                <tr><th scope="row" class="bg-white font-medium">Certification</th>@foreach($matrix['frameworks'] as $f)<td>{{ $f['certifiable'] ? 'Yes' : 'No' }}</td>@endforeach</tr>
                <tr><th scope="row" class="bg-white font-medium">Publisher</th>@foreach($matrix['frameworks'] as $f)<td class="text-xs">{{ $f['publisher'] }} · {{ $f['published'] }}</td>@endforeach</tr>
                <tr><th scope="row" class="bg-white font-medium">Cited by</th>@foreach($matrix['frameworks'] as $f)<td class="tabular-nums">{{ $matrix['totals'][$f['key']] }} of {{ $matrix['controls'] }} controls</td>@endforeach</tr>
            </tbody>
        </table></div>
        <p class="mt-2 text-xs text-brand-muted">None of these documents is itself law. The statutes and regulations that bind are the instruments in the <a href="{{ route('policies.index', ['binding' => 'yes']) }}">policy explorer</a>; the crosswalks from those to each framework are on the <a href="{{ route('frameworks.index') }}">frameworks page</a>.</p>
    </section>

    <section class="mt-10" aria-labelledby="matrix-heading">
        <h2 id="matrix-heading" class="section-title">Where the work overlaps, by category of duty</h2>
        <p class="mt-1 text-xs text-brand-muted">Each cell: distinct controls that meet at least one recorded duty in the row and cite the column's framework. A filled cell means evidence may be reusable; it never means the framework discharges the duty. Zero means no recorded control cites it.</p>
        <div class="table-wrap table-sticky mt-3"><table>
            <caption class="sr-only">Controls per obligation category and framework</caption>
            <thead><tr><th scope="col">Duty category</th><th scope="col">Duties</th><th scope="col">Controls</th>@foreach($matrix['frameworks'] as $f)<th scope="col">{{ $f['short'] }}</th>@endforeach</tr></thead>
            <tbody>
                @foreach($matrix['rows'] as $row)
                <tr>
                    <th scope="row" class="bg-white font-medium"><a href="{{ route('obligations.index', ['category' => $row['category']]) }}" class="text-brand-navy">{{ $row['name'] }}</a></th>
                    <td class="tabular-nums">{{ $row['obligations'] }}</td>
                    <td class="tabular-nums">{{ $row['controls'] }}</td>
                    @foreach($matrix['frameworks'] as $key => $f)
                    @php($n = $row['cells'][$key])
                    <td class="tabular-nums {{ $n === 0 ? 'text-brand-muted' : ($n >= max(1, (int) ceil($row['controls'] / 2)) ? 'bg-state-goodbg text-state-good font-medium' : 'bg-brand-paper text-brand-navy') }}">{{ $n ?: '–' }}</td>
                    @endforeach
                </tr>
                @endforeach
            </tbody>
        </table></div>
        <p class="mt-2 text-xs text-brand-muted">Shaded green where at least half the controls in the category cite the framework.</p>
    </section>
    <x-site.disclaimer class="mt-10" />
</div>
@endsection
