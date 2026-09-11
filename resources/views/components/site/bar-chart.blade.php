@props(['series', 'title', 'height' => 160, 'note' => null])
@php($values = collect($series))
@php($max = max(1, (int) $values->max()))
@php($n = max(1, $values->count()))
@php($w = 720)
@php($bw = max(6, floor(($w - 40) / $n) - 6))
<figure {{ $attributes->merge(['class' => 'card-flat p-4']) }}>
    <figcaption class="flex items-baseline justify-between"><span class="text-sm font-medium text-brand-navy">{{ $title }}</span>@if($note)<span class="meta">{{ $note }}</span>@endif</figcaption>
    <svg viewBox="0 0 {{ $w }} {{ $height + 40 }}" role="img" aria-label="{{ $title }}" class="mt-2 w-full max-w-full h-auto">
        <title>{{ $title }}</title>
        <line x1="32" y1="{{ $height }}" x2="{{ $w }}" y2="{{ $height }}" stroke="#D8DEE8" />
        <text x="0" y="12" font-size="11" fill="#5D6B7E" font-family="ui-monospace, monospace">{{ $max }}</text>
        <text x="0" y="{{ $height }}" font-size="11" fill="#5D6B7E" font-family="ui-monospace, monospace">0</text>
        @foreach($values as $label => $value)
            @php($h = round(($value / $max) * ($height - 20)))
            @php($x = 36 + $loop->index * ($bw + 6))
            <rect x="{{ $x }}" y="{{ $height - $h }}" width="{{ $bw }}" height="{{ $h }}" fill="#002147"><title>{{ $label }}: {{ $value }}</title></rect>
            @if($n <= 16 || $loop->index % max(1, intdiv($n, 12)) === 0)
            <text x="{{ $x + $bw / 2 }}" y="{{ $height + 16 }}" font-size="11" text-anchor="middle" fill="#5D6B7E" font-family="ui-monospace, monospace">{{ $label }}</text>
            @endif
            @if($n <= 16)<text x="{{ $x + $bw / 2 }}" y="{{ $height - $h - 4 }}" font-size="10" text-anchor="middle" fill="#1E2A3B" font-family="ui-monospace, monospace">{{ $value }}</text>@endif
        @endforeach
    </svg>
    <table class="sr-only"><caption>{{ $title }}</caption><thead><tr><th scope="col">Label</th><th scope="col">Value</th></tr></thead><tbody>@foreach($values as $label => $value)<tr><td>{{ $label }}</td><td>{{ $value }}</td></tr>@endforeach</tbody></table>
</figure>
