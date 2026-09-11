@props(['series', 'milestones' => [], 'title', 'note' => null, 'height' => 180])
{{-- Bars per year with policy milestones marked above the year they fall in. Every bar links to the incident browser for that year. --}}
@php($values = collect($series))
@php($max = max(1, (int) $values->max()))
@php($tone = fn ($v) => $v / $max >= 0.75 ? '#9B1C2E' : ($v / $max >= 0.5 ? '#B45309' : '#002147'))
@php($n = max(1, $values->count()))
@php($w = 720)
@php($bw = $w / $n)
@php($years = $values->keys()->values())
<figure {{ $attributes->merge(['class' => 'card-flat p-4']) }} data-chart="{{ \Illuminate\Support\Str::slug($title) }}">
    <figcaption class="text-sm font-semibold text-brand-navy">{{ $title }}</figcaption>
    <svg viewBox="0 0 {{ $w }} {{ $height + 70 }}" role="img" aria-label="{{ $title }}" class="mt-2 w-full max-w-full h-auto">
        @foreach($values as $year => $v)
        @php($x = $loop->index * $bw)
        @php($h = $height * $v / $max)
        <a href="{{ route('risk.incidents.browse', ['year' => $year]) }}"><rect x="{{ $x + 3 }}" y="{{ 40 + $height - $h }}" width="{{ $bw - 6 }}" height="{{ $h }}" fill="{{ $tone($v) }}" class="chart-bar" data-tip="{{ $year }}: {{ number_format($v) }} incidents ({{ round(100 * $v / $max) }}% of peak year){{ $year == now()->year ? ' · partial year' : '' }}"><title>{{ $year }}: {{ number_format($v) }} incidents</title></rect></a>
        <text x="{{ $x + $bw / 2 }}" y="{{ $height + 56 }}" text-anchor="middle" font-size="10" fill="#5D6B7E">{{ $year }}</text>
        @if($v > 0)<text x="{{ $x + $bw / 2 }}" y="{{ 36 + $height - $h }}" text-anchor="middle" font-size="9" fill="#1E2A3B">{{ $v }}</text>@endif
        @endforeach
        @foreach($milestones as $i => $m)
        @php($idx = $years->search((int) substr($m[0], 0, 4)))
        @if($idx !== false)
        @php($x = $idx * $bw + $bw * (((int) substr($m[0], 5, 2)) - 0.5) / 12)
        <line x1="{{ $x }}" y1="{{ 40 }}" x2="{{ $x }}" y2="{{ $height + 40 }}" stroke="#00A3E0" stroke-width="1" stroke-dasharray="3 3" />
        <circle cx="{{ $x }}" cy="{{ 14 + ($i % 3) * 9 }}" r="4" fill="#00A3E0" class="chart-dot" data-tip="{{ $m[0] }}: {{ $m[1] }}"><title>{{ $m[0] }}: {{ $m[1] }}</title></circle>
        @endif
        @endforeach
    </svg>
    <p class="meta mt-2"><span class="inline-block h-2.5 w-2.5 rounded-sm align-middle" style="background:#002147"></span> below half of peak year · <span class="inline-block h-2.5 w-2.5 rounded-sm align-middle" style="background:#B45309"></span> half to three-quarters · <span class="inline-block h-2.5 w-2.5 rounded-sm align-middle" style="background:#9B1C2E"></span> top quarter · <span class="inline-block h-2.5 w-2.5 rounded-full align-middle" style="background:#00A3E0"></span> policy milestone (hover for details)</p>
    <x-site.chart-tools />
    <table class="sr-only"><caption>{{ $title }}</caption><thead><tr><th scope="col">Year</th><th scope="col">Incidents</th></tr></thead><tbody>@foreach($values as $year => $v)<tr><td>{{ $year }}</td><td>{{ $v }}</td></tr>@endforeach</tbody></table>
    <ol class="mt-2 grid gap-x-4 gap-y-1 sm:grid-cols-2 text-xs text-brand-muted">@foreach($milestones as $m)<li><span class="font-mono text-brand-navy">{{ substr($m[0], 0, 7) }}</span> @if(!empty($m[2]))<a href="{{ route('policies.show', $m[2]) }}">{{ $m[1] }}</a>@else{{ $m[1] }}@endif</li>@endforeach</ol>
    @if($note)<p class="mt-2 meta">{{ $note }}</p>@endif
</figure>
