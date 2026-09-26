@extends('site.layouts.app')
@section('content')
<div class="container-site py-8 max-w-4xl">
    <x-site.breadcrumbs :items="$seo->breadcrumbs" />
    <h1 class="mt-2 text-2xl sm:text-3xl font-semibold tracking-tight text-brand-navy">Displacement policy index: how it is computed</h1>
    <p class="mt-3 text-brand-body">{{ $explain['rule'] }} Version <code>{{ $explain['version'] }}</code>.</p>
    <section class="mt-8" aria-labelledby="dims-heading"><h2 id="dims-heading" class="section-title">Four dimensions, 25 points each</h2>
        <div class="table-wrap mt-3"><table><caption class="sr-only">Dimensions and the measure types that feed them</caption><thead><tr><th scope="col">Dimension</th><th scope="col">Measure types counted</th></tr></thead><tbody>@foreach($explain['dimensions'] as $key => $label)<tr><th scope="row" class="font-medium">{{ $label }}</th><td>{{ collect($explain['types_by_dimension'][$key] ?? [])->map(fn ($t) => \App\Models\TransitionMeasure::TYPES[$t] ?? $t)->join(', ') }}</td></tr>@endforeach</tbody></table></div>
    </section>
    <section class="mt-8" aria-labelledby="w-heading"><h2 id="w-heading" class="section-title">Status weights</h2>
        <div class="table-wrap mt-3"><table><caption class="sr-only">Points by status</caption><thead><tr><th scope="col">Status</th><th scope="col">Points</th></tr></thead><tbody>@foreach($explain['weights'] as $s => $w)<tr><td>{{ \App\Models\TransitionMeasure::STATUSES[$s] ?? $s }}</td><td>{{ $w }}</td></tr>@endforeach<tr><td>Draft, unverified, withdrawn, expired</td><td>0</td></tr></tbody></table></div>
        <p class="mt-2 text-sm text-brand-body">A dimension takes the strongest measure, not the sum, so ten proposals score the same as one. Measures dated after the quarter's end are ignored for that quarter; an undated verified measure counts from the quarter it was verified.</p>
    </section>
    <section class="mt-8" aria-labelledby="cur-heading"><h2 id="cur-heading" class="section-title">Current scores</h2>
        @if($index->isEmpty())<p class="mt-2 text-sm text-brand-muted">No jurisdiction has a verified measure yet, so no score exists. That is the honest state of the data, not a bug.</p>@else
        <div class="table-wrap mt-3"><table><caption class="sr-only">Index by jurisdiction</caption><thead><tr><th scope="col">Jurisdiction</th><th scope="col">Quarter</th><th scope="col">Score</th>@foreach($explain['dimensions'] as $label)<th scope="col">{{ $label }}</th>@endforeach</tr></thead><tbody>@foreach($index as $s)<tr><th scope="row" class="font-medium"><a href="{{ $s->jurisdiction->url() }}">{{ $s->jurisdiction->name }}</a></th><td>{{ $s->quarter }}</td><td class="font-mono">{{ $s->score }}</td>@foreach(array_keys($explain['dimensions']) as $d)<td>{{ $s->subscores[$d] ?? 0 }}</td>@endforeach</tr>@endforeach</tbody></table></div>
        @endif
        <p class="mt-2 text-xs"><a href="{{ route('api.v1.transition.index') }}" class="text-brand-blue">Index as JSON</a> (every snapshot carries its version, inputs and quarter).</p>
    </section>
    <x-site.faq :items="$seo->faqItems()" />
    <p class="mt-6 text-sm"><a href="{{ route('transition.index') }}" class="text-brand-blue">Back to the tracker</a> · <a href="{{ route('methodology') }}" class="text-brand-blue">Site methodology</a></p>
</div>
@endsection
