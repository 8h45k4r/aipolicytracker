@props(['years', 'title', 'note' => null, 'height' => 200, 'partial' => null])
{{--
Classified incidents per year, split by intent: deliberate at the base of each column,
accidental above it, unclear on top. The share coded deliberate is printed over each
column, so the shift between the two reads without hovering.
$years: [2019 => ['Intentional' => n, 'Unintentional' => n, 'Other' => n]].
$partial: a year still in progress, drawn muted.
--}}
@php
    $keys = ['Intentional' => ['Deliberate', '#9B1C2E'], 'Unintentional' => ['Accidental', '#006AAC'], 'Other' => ['Unclear', '#5D6B7E']];
    $labels = array_keys($years);
    $n = max(1, count($labels));
    $max = max(1, (int) max(array_map('array_sum', $years) ?: [1]));
    $w = 760;
    $left = 34;
    $slot = ($w - $left) / $n;
    $bw = min(64, $slot - 14);
    $top = 26;
@endphp
<figure {{ $attributes->merge(['class' => 'card-flat p-4']) }} data-chart="{{ \Illuminate\Support\Str::slug($title) }}">
    <figcaption class="flex flex-wrap items-baseline justify-between gap-x-4 gap-y-1"><span class="text-sm font-medium text-brand-navy">{{ $title }}</span>@if($note)<span class="meta">{{ $note }}</span>@endif</figcaption>
    <div class="mt-2 overflow-x-auto">
    <svg viewBox="0 0 {{ $w }} {{ $height + 30 }}" role="img" aria-label="{{ $title }}" class="w-full min-w-[30rem] h-auto">
        <title>{{ $title }}</title>
        <line x1="{{ $left }}" y1="{{ $height }}" x2="{{ $w }}" y2="{{ $height }}" stroke="#D8DEE8" />
        <text x="0" y="{{ $top + 4 }}" font-size="11" fill="#5D6B7E" font-family="ui-monospace, monospace">{{ $max }}</text>
        @foreach($years as $year => $row)
            @php($x = round($left + $loop->index * $slot + ($slot - $bw) / 2, 1))
            @php($y = $height)
            @php($sum = array_sum($row))
            @php($coded = ($row['Intentional'] ?? 0) + ($row['Unintentional'] ?? 0))
            @foreach($keys as $key => [$label, $fill])
                @php($v = (int) ($row[$key] ?? 0))
                @php($h = $sum ? round(($v / $max) * ($height - $top)) : 0)
                @if($h > 0)
                <rect x="{{ $x }}" y="{{ $y - $h }}" width="{{ round($bw, 1) }}" height="{{ $h }}" fill="{{ $fill }}" @if((string) $year === (string) $partial) opacity="0.55" @endif class="chart-bar" data-tip="{{ $year }}{{ (string) $year === (string) $partial ? ' (part year)' : '' }} · {{ $label }}: {{ $v }} of {{ $sum }}"><title>{{ $year }} · {{ $label }}: {{ $v }} of {{ $sum }}</title></rect>
                @endif
                @php($y -= $h)
            @endforeach
            @if($sum)
            <text x="{{ round($x + $bw / 2, 1) }}" y="{{ $y - 6 }}" font-size="12" font-weight="600" text-anchor="middle" fill="#9B1C2E" font-family="ui-monospace, monospace">{{ round(100 * ($row['Intentional'] ?? 0) / $sum) }}%</text>
            @endif
            <text x="{{ round($x + $bw / 2, 1) }}" y="{{ $height + 17 }}" font-size="11" text-anchor="middle" fill="#5D6B7E" font-family="ui-monospace, monospace">{{ $year }}{{ (string) $year === (string) $partial ? '*' : '' }}</text>
        @endforeach
    </svg>
    </div>
    <ul class="mt-2 flex flex-wrap gap-x-4 gap-y-1 text-xs text-brand-body" aria-label="Legend">
        @foreach($keys as [$label, $fill])<li class="inline-flex items-center gap-1.5"><svg width="10" height="10" aria-hidden="true"><rect width="10" height="10" fill="{{ $fill }}" /></svg>{{ $label }}</li>@endforeach
        <li>Figure over each column: share coded deliberate.@if($partial) * part year.@endif</li>
    </ul>
    <x-site.chart-tools />
    <div class="sr-only"><table><caption>{{ $title }}</caption><thead><tr><th scope="col">Year</th>@foreach($keys as [$label])<th scope="col">{{ $label }}</th>@endforeach</tr></thead><tbody>@foreach($years as $year => $row)<tr><td>{{ $year }}</td>@foreach($keys as $key => $_)<td>{{ $row[$key] ?? 0 }}</td>@endforeach</tr>@endforeach</tbody></table></div>
</figure>
