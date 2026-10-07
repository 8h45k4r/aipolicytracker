@props(['policy'])
{{--
    A pointer from an instrument's page to its implementation tracker
    (/policies/{slug}/implementation). Renders nothing for an instrument with no
    implementation measure recorded, so it can be included on every policy page.
--}}
@php
    $implementationMeasures = \App\Services\Implementation\Trackers::measureQuery(['instrument' => $policy->slug])
        ->whereNotIn('kind', \App\Models\ImplementationMeasure::STANDARD_KINDS)->get();
    $implementationOverdue = $implementationMeasures->filter(fn ($m) => $m->effectiveStatus() === 'overdue')->count();
@endphp
@if($implementationMeasures->isNotEmpty())
<aside {{ $attributes->merge(['class' => 'card-flat p-4 text-sm']) }} aria-labelledby="implementation-link-heading">
    <h2 id="implementation-link-heading" class="font-semibold text-brand-navy">Implementation tracker</h2>
    <p class="mt-1 text-brand-body">{{ $implementationMeasures->count() }} implementation {{ $implementationMeasures->count() === 1 ? 'measure' : 'measures' }} (guidelines, codes of practice, acts, templates) tracked, {{ $implementationMeasures->reject->isDraft()->count() }} recorded from a cited source{{ $implementationOverdue > 0 ? ", {$implementationOverdue} overdue" : "" }}.</p>
    <p class="mt-2"><a href="{{ route('policies.implementation', $policy->slug) }}" class="text-brand-blue">See what the {{ $policy->short_title ?: $policy->title }} still depends on</a></p>
</aside>
@endif
