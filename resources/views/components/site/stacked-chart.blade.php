@props(['series', 'keys', 'title', 'height' => 200, 'note' => null])
{{-- $series: [label => [key => value]] ; $keys: ordered list of stack keys --}}
@php($labels = array_keys($series))
@php($totals = array_map(fn ($row) => array_sum(array_intersect_key($row, array_flip($keys))), $series))
@php($max = max(1, (int) max($totals ?: [1])))
@php($n = max(1, count($labels)))
@php($w = 720)
@php($bw = max(8, floor(($w - 40) / $n) - 6))
@php($palette = ['#002147', '#006AAC', '#009CE0', '#5D6B7E', '#0B6B4F', '#7A4B00', '#9B1C2E', '#8F81C7'])
<figure {{ $attributes->merge(['class' => 'card-flat p-4']) }} data-chart="stacked">
    <figcaption class="flex items-baseline justify-between"><span class="text-sm font-medium text-brand-navy">{{ $title }}</span>@if($note)<span class="meta">{{ $note }}</span>@endif</figcaption>
    <svg viewBox="0 0 {{ $w }} {{ $height + 40 }}" role="img" aria-label="{{ $title }}" class="mt-2 w-full max-w-full h-auto">
        <title>{{ $title }}</title>
        <line x1="32" y1="{{ $height }}" x2="{{ $w }}" y2="{{ $height }}" stroke="#D8DEE8" />
        <text x="0" y="12" font-size="11" fill="#5D6B7E" font-family="ui-monospace, monospace">{{ $max }}</text>
        @foreach($labels as $i => $label)
            @php($x = 36 + $i * ($bw + 6))
            @php($y = $height)
            @foreach($keys as $k => $key)
                @php($v = (int) ($series[$label][$key] ?? 0))
                @php($h = round(($v / $max) * ($height - 20)))
                @if($h > 0)<rect x="{{ $x }}" y="{{ $y - $h }}" width="{{ $bw }}" height="{{ $h }}" fill="{{ $palette[$k % count($palette)] }}" class="chart-bar" data-tip="{{ $label }} · {{ $key }}: {{ $v }}"><title>{{ $label }} · {{ $key }}: {{ $v }}</title></rect>@endif
                @php($y -= $h)
            @endforeach
            <text x="{{ $x + $bw / 2 }}" y="{{ $height + 16 }}" font-size="11" text-anchor="middle" fill="#5D6B7E" font-family="ui-monospace, monospace">{{ $label }}</text>
        @endforeach
    </svg>
    <ul class="mt-2 flex flex-wrap gap-x-4 gap-y-1 text-xs text-brand-body" aria-label="Legend">@foreach($keys as $k => $key)<li class="inline-flex items-center gap-1"><span aria-hidden="true" class="inline-block h-2.5 w-2.5" style="background: {{ $palette[$k % count($palette)] }}"></span>{{ $key }}</li>@endforeach</ul>
    <table class="sr-only"><caption>{{ $title }}</caption><thead><tr><th scope="col">Label</th>@foreach($keys as $key)<th scope="col">{{ $key }}</th>@endforeach</tr></thead><tbody>@foreach($labels as $label)<tr><td>{{ $label }}</td>@foreach($keys as $key)<td>{{ $series[$label][$key] ?? 0 }}</td>@endforeach</tr>@endforeach</tbody></table>
</figure>
