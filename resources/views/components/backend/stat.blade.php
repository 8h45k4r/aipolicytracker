@props(['label', 'value', 'href' => null, 'hint' => null, 'trend' => null, 'tone' => null])
{{-- A figure that is also a way in: the whole card links to the rows behind the number.
     $trend is a list of daily counts, drawn as a small line so a change of pace shows. --}}
@php($tag = $href ? 'a' : 'div')
<{{ $tag }} @if($href) href="{{ $href }}" @endif {{ $attributes->merge(['class' => 'adm-stat card-flat block p-4 no-underline'.($href ? ' hover:border-brand-navy' : '')]) }}>
    <p class="text-xs font-medium text-brand-muted">{{ $label }}</p>
    <p class="mt-1 flex items-end justify-between gap-3">
        <span class="text-2xl font-semibold tabular-nums {{ $tone ?? 'text-brand-navy' }}">{{ $value }}</span>
        @if(is_array($trend) && count($trend) > 1)
            @php($max = max(1, max($trend)))
            @php($w = 96)
            @php($h = 28)
            @php($step = $w / (count($trend) - 1))
            @php($points = collect($trend)->values()->map(fn ($v, $i) => round($i * $step, 1).','.round($h - ($v / $max) * ($h - 2) - 1, 1))->implode(' '))
            <svg width="{{ $w }}" height="{{ $h }}" viewBox="0 0 {{ $w }} {{ $h }}" class="shrink-0 text-brand-blue" role="img" aria-label="{{ array_sum($trend) }} over the last {{ count($trend) }} days"><polyline points="{{ $points }}" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linejoin="round" stroke-linecap="round"/></svg>
        @endif
    </p>
    @if($hint)<p class="mt-1 text-xs text-brand-muted">{{ $hint }}</p>@endif
</{{ $tag }}>
