{{-- The interactive world map (amCharts 5, loaded only where this component appears).
     The legend and the country list beneath it carry the same information in text, so
     the map is a view of the data, not the only way to it. --}}
@props(['title' => 'Where AI is regulated'])
@php($countries = \App\Services\Hubs\WorldMap::countries())
@php($totals = \App\Services\Hubs\WorldMap::totals())
<figure {{ $attributes->merge(['class' => 'card-flat p-3 sm:p-4']) }}>
    <figcaption class="flex flex-wrap items-baseline justify-between gap-2">
        <span class="font-semibold text-brand-navy">{{ $title }}</span>
        <span class="meta">Select a country to open its page. EU member states include the EU AI Act.</span>
    </figcaption>
    <div class="mt-3 aspect-[2/1] w-full" data-world-map="world-map-data" role="img" aria-label="World map of AI regulation: {{ $totals['in_force'] }} countries with binding AI law in force, {{ $totals['binding'] }} with binding law adopted, {{ $totals['guidance'] }} with strategy or guidance only."></div>
    <ul class="mt-3 flex flex-wrap gap-x-4 gap-y-1 text-xs text-brand-body">
        @foreach(\App\Services\Hubs\WorldMap::LEVELS as $level => $label)
        <li class="flex items-center gap-1.5"><span class="inline-block h-3 w-3 rounded-sm map-swatch-{{ $level }}" style="background: {{ \App\Services\Hubs\WorldMap::COLOURS[$level] }}" aria-hidden="true"></span>{{ $label }} <span class="font-mono text-brand-muted">{{ $totals[$level] }}</span></li>
        @endforeach
    </ul>
    <script type="application/json" id="world-map-data">@json($countries)</script>
</figure>
