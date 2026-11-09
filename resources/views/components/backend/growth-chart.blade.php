@props(['title', 'series', 'href' => null, 'note' => null, 'id'])
{{--
    One measure over the last weeks: a 2px line over a light area, three recessive
    gridlines, the last value labelled, a tooltip on every week (a large invisible hit
    target, so the point is easy to reach) and the same figures as a table. One series,
    so the title names it and no legend is drawn.
--}}
@php($values = array_values($series))
@php($dates = array_keys($series))
@php($n = count($values))
@php($max = max(1, ...$values))
{{-- A round scale: the midline is a round number and the top twice it (131 → 70, 140; 432 → 250, 500). --}}
@php($half = $max / 2)
@php($unit = $half < 5 ? 1 : 10 ** floor(log10($half)) / 2)
@php($top = (int) (2 * max(1, ceil($half / $unit) * $unit)))
@php([$w, $h, $l, $r, $t, $b] = [560, 180, 40, 16, 12, 26])
@php($x = fn ($i) => $l + ($n > 1 ? $i * ($w - $l - $r) / ($n - 1) : 0))
@php($y = fn ($v) => $t + ($h - $t - $b) * (1 - $v / $top))
@php($line = collect($values)->map(fn ($v, $i) => round($x($i), 1).','.round($y($v), 1))->implode(' '))
@php($last = end($values))
@php($first = reset($values))
<figure {{ $attributes->merge(['class' => 'adm-chart card-flat p-4']) }} aria-labelledby="{{ $id }}-title">
    <figcaption class="flex items-baseline justify-between gap-3">
        <span id="{{ $id }}-title" class="text-sm font-semibold text-brand-navy">{{ $title }}</span>
        @if($href)<a href="{{ $href }}" class="text-sm no-underline hover:underline">Open</a>@endif
    </figcaption>
    <p class="mt-1 text-2xl font-semibold tabular-nums text-brand-navy">{{ number_format($last) }}
        <span class="text-sm font-normal text-brand-muted">{{ $last >= $first ? '+' : '−' }}{{ number_format(abs($last - $first)) }} in {{ $n }} weeks</span></p>
    <svg viewBox="0 0 {{ $w }} {{ $h }}" class="mt-2 w-full h-auto" role="img" aria-label="{{ $title }}: {{ number_format($first) }} to {{ number_format($last) }} over {{ $n }} weeks">
        @foreach([0, $top / 2, $top] as $g)
        <line x1="{{ $l }}" x2="{{ $w - $r }}" y1="{{ round($y($g), 1) }}" y2="{{ round($y($g), 1) }}" class="adm-chart-grid"/>
        <text x="{{ $l - 6 }}" y="{{ round($y($g), 1) + 4 }}" text-anchor="end" class="adm-chart-label">{{ number_format($g) }}</text>
        @endforeach
        <polygon points="{{ round($x(0), 1) }},{{ round($y(0), 1) }} {{ $line }} {{ round($x($n - 1), 1) }},{{ round($y(0), 1) }}" class="adm-chart-area"/>
        <polyline points="{{ $line }}" class="adm-chart-line"/>
        <circle cx="{{ round($x($n - 1), 1) }}" cy="{{ round($y($last), 1) }}" r="4" class="adm-chart-end"/>
        @foreach($values as $i => $v)
        <g class="adm-chart-hit"><title>Week to {{ \Carbon\Carbon::parse($dates[$i])->format('j M') }}: {{ number_format($v) }}</title><rect x="{{ round($x($i) - ($w - $l - $r) / max(1, $n - 1) / 2, 1) }}" y="{{ $t }}" width="{{ round(($w - $l - $r) / max(1, $n - 1), 1) }}" height="{{ $h - $t - $b }}" fill="transparent"/><circle cx="{{ round($x($i), 1) }}" cy="{{ round($y($v), 1) }}" r="4" class="adm-chart-dot"/></g>
        @endforeach
        <text x="{{ $l }}" y="{{ $h - 6 }}" class="adm-chart-label">{{ \Carbon\Carbon::parse($dates[0])->format('j M') }}</text>
        <text x="{{ $w - $r }}" y="{{ $h - 6 }}" text-anchor="end" class="adm-chart-label">{{ \Carbon\Carbon::parse(end($dates))->format('j M') }}</text>
    </svg>
    @if($note)<p class="mt-1 text-xs text-brand-muted">{{ $note }}</p>@endif
    <details class="mt-2 text-sm"><summary class="cursor-pointer text-brand-blue">Show as a table</summary>
        <table class="mt-2 w-full text-sm"><thead><tr><th class="text-left font-medium text-brand-muted">Week to</th><th class="text-right font-medium text-brand-muted">{{ $title }}</th></tr></thead>
        <tbody>@foreach($series as $d => $v)<tr><td>{{ \Carbon\Carbon::parse($d)->format('j M Y') }}</td><td class="text-right tabular-nums">{{ number_format($v) }}</td></tr>@endforeach</tbody></table>
    </details>
</figure>
