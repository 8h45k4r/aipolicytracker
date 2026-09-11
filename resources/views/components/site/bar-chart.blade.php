@props(['series', 'title', 'height' => 160, 'note' => null, 'scale' => false, 'export' => true])
@php($tone = fn ($v) => $scale ? ($v / $max >= 0.75 ? '#9B1C2E' : ($v / $max >= 0.5 ? '#B45309' : '#002147')) : '#002147')
@php($values = collect($series))
@php($max = max(1, (int) $values->max()))
@php($n = max(1, $values->count()))
@php($w = 720)
@php($bw = max(6, floor(($w - 40) / $n) - 6))
<figure {{ $attributes->merge(['class' => 'card-flat p-4']) }} data-chart="{{ \Illuminate\Support\Str::slug($title) }}">
    <figcaption class="flex items-baseline justify-between"><span class="text-sm font-medium text-brand-navy">{{ $title }}</span>@if($note)<span class="meta">{{ $note }}</span>@endif</figcaption>
    <svg viewBox="0 0 {{ $w }} {{ $height + 40 }}" role="img" aria-label="{{ $title }}" class="mt-2 w-full max-w-full h-auto">
        <title>{{ $title }}</title>
        <line x1="32" y1="{{ $height }}" x2="{{ $w }}" y2="{{ $height }}" stroke="#D8DEE8" />
        <text x="0" y="12" font-size="11" fill="#5D6B7E" font-family="ui-monospace, monospace">{{ $max }}</text>
        <text x="0" y="{{ $height }}" font-size="11" fill="#5D6B7E" font-family="ui-monospace, monospace">0</text>
        @foreach($values as $label => $value)
            @php($h = round(($value / $max) * ($height - 20)))
            @php($x = 36 + $loop->index * ($bw + 6))
            <rect x="{{ $x }}" y="{{ $height - $h }}" width="{{ $bw }}" height="{{ $h }}" fill="{{ $tone($value) }}" class="chart-bar" data-tip="{{ $label }}: {{ number_format($value) }}{{ $scale ? ' ('.round(100 * $value / $max).'% of peak)' : '' }}"><title>{{ $label }}: {{ $value }}</title></rect>
            @if($n <= 16 || $loop->index % max(1, intdiv($n, 12)) === 0)
            <text x="{{ $x + $bw / 2 }}" y="{{ $height + 16 }}" font-size="11" text-anchor="middle" fill="#5D6B7E" font-family="ui-monospace, monospace">{{ $label }}</text>
            @endif
            @if($n <= 16)<text x="{{ $x + $bw / 2 }}" y="{{ $height - $h - 4 }}" font-size="10" text-anchor="middle" fill="#1E2A3B" font-family="ui-monospace, monospace">{{ $value }}</text>@endif
        @endforeach
    </svg>
    @if($scale)<p class="meta mt-1"><span class="inline-block h-2.5 w-2.5 rounded-sm align-middle" style="background:#002147"></span> below half of peak · <span class="inline-block h-2.5 w-2.5 rounded-sm align-middle" style="background:#B45309"></span> half to three-quarters · <span class="inline-block h-2.5 w-2.5 rounded-sm align-middle" style="background:#9B1C2E"></span> top quarter</p>@endif
    @if($export)<x-site.chart-tools />@endif
    <table class="sr-only"><caption>{{ $title }}</caption><thead><tr><th scope="col">Label</th><th scope="col">Value</th></tr></thead><tbody>@foreach($values as $label => $value)<tr><td>{{ $label }}</td><td>{{ $value }}</td></tr>@endforeach</tbody></table>
</figure>
