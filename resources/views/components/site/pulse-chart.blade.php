@props(['months', 'title', 'lensLabel', 'allLabel' => 'All recorded incidents', 'note' => null, 'height' => 190])
{{--
Incidents per month: the whole record as the pale bar, the part in view as the dark bar
in front of it. $months: ['2026-03' => ['all' => 25, 'lens' => 12, 'settled' => true]].
Months after the last settled one are drawn muted and marked, because their incidents
are recorded but most are not classified yet: a short dark bar there is a gap in the
coding, not a fall.
--}}
@php
    $keys = array_keys($months);
    $n = max(1, count($keys));
    $max = max(1, (int) collect($months)->max('all'));
    $w = 760;
    $left = 34;
    $slot = ($w - $left) / $n;
    $bw = max(4, $slot - 5);
    $firstOpen = collect($keys)->search(fn ($k) => ! $months[$k]['settled']);
    $lastSettled = collect($keys)->filter(fn ($k) => $months[$k]['settled'])->last();
    $month = fn (string $k) => \Illuminate\Support\Carbon::parse($k.'-01');
@endphp
<figure {{ $attributes->merge(['class' => 'card-flat p-4']) }} data-chart="{{ \Illuminate\Support\Str::slug($title) }}">
    <figcaption class="flex flex-wrap items-baseline justify-between gap-x-4 gap-y-1"><span class="text-sm font-medium text-brand-navy">{{ $title }}</span>@if($note)<span class="meta">{{ $note }}</span>@endif</figcaption>
    <div class="mt-2 overflow-x-auto">
    <svg viewBox="0 0 {{ $w }} {{ $height + 44 }}" role="img" aria-label="{{ $title }}" class="w-full min-w-[34rem] h-auto">
        <title>{{ $title }}</title>
        @foreach([1, 0.5] as $f)
        <line x1="{{ $left }}" y1="{{ round($height - $f * ($height - 20)) }}" x2="{{ $w }}" y2="{{ round($height - $f * ($height - 20)) }}" stroke="#D8DEE8" stroke-dasharray="2 4" />
        <text x="0" y="{{ round($height - $f * ($height - 20)) + 4 }}" font-size="11" fill="#5D6B7E" font-family="ui-monospace, monospace">{{ (int) round($max * $f) }}</text>
        @endforeach
        <line x1="{{ $left }}" y1="{{ $height }}" x2="{{ $w }}" y2="{{ $height }}" stroke="#D8DEE8" />
        @if($firstOpen !== false && $firstOpen > 0)
        @php($fx = round($left + $firstOpen * $slot - 2.5, 1))
        <rect x="{{ $fx }}" y="14" width="{{ round($w - $fx, 1) }}" height="{{ $height - 14 }}" fill="#D8DEE8" opacity="0.35" />
        <line x1="{{ $fx }}" y1="6" x2="{{ $fx }}" y2="{{ $height }}" stroke="#5D6B7E" stroke-dasharray="3 3" />
        <text x="{{ $fx - 6 }}" y="12" font-size="11" text-anchor="end" fill="#5D6B7E">classified to {{ $month($lastSettled)->format('M Y') }}</text>
        @endif
        @foreach($keys as $i => $k)
            @php($m = $months[$k])
            @php($x = round($left + $i * $slot, 1))
            @php($ha = round(($m['all'] / $max) * ($height - 20)))
            @php($hl = round(($m['lens'] / $max) * ($height - 20)))
            @php($tip = $month($k)->format('M Y').': '.$m['lens'].' '.mb_strtolower($lensLabel).' of '.$m['all'].' recorded'.($m['settled'] ? '' : ' (classification incomplete)'))
            @if($ha > 0)<rect x="{{ $x }}" y="{{ $height - $ha }}" width="{{ round($bw, 1) }}" height="{{ $ha }}" fill="#D8DEE8" class="chart-bar" data-tip="{{ $tip }}"><title>{{ $tip }}</title></rect>@endif
            @if($hl > 0)<rect x="{{ $x }}" y="{{ $height - $hl }}" width="{{ round($bw, 1) }}" height="{{ $hl }}" fill="{{ $m['settled'] ? '#002147' : '#5D6B7E' }}" class="chart-bar" data-tip="{{ $tip }}"><title>{{ $tip }}</title></rect>@endif
            @if($month($k)->month === 1 || $month($k)->month === 7)
            <text x="{{ round($x + $bw / 2, 1) }}" y="{{ $height + 16 }}" font-size="11" text-anchor="middle" fill="#5D6B7E" font-family="ui-monospace, monospace">{{ $month($k)->format('M') }}</text>
            @if($month($k)->month === 1 || $i < 6)<text x="{{ round($x + $bw / 2, 1) }}" y="{{ $height + 31 }}" font-size="11" text-anchor="middle" fill="#5D6B7E" font-family="ui-monospace, monospace">{{ $month($k)->format('Y') }}</text>@endif
            @endif
        @endforeach
    </svg>
    </div>
    <ul class="mt-2 flex flex-wrap gap-x-4 gap-y-1 text-xs text-brand-body" aria-label="Legend">
        <li class="inline-flex items-center gap-1.5"><svg width="10" height="10" aria-hidden="true"><rect width="10" height="10" fill="#002147" /></svg>{{ $lensLabel }}</li>
        <li class="inline-flex items-center gap-1.5"><svg width="10" height="10" aria-hidden="true"><rect width="10" height="10" fill="#D8DEE8" /></svg>{{ $allLabel }}</li>
        @if($firstOpen !== false)<li class="inline-flex items-center gap-1.5"><svg width="10" height="10" aria-hidden="true"><rect width="10" height="10" fill="#5D6B7E" /></svg>Classification incomplete</li>@endif
    </ul>
    <x-site.chart-tools />
    <div class="sr-only"><table><caption>{{ $title }}</caption><thead><tr><th scope="col">Month</th><th scope="col">{{ $lensLabel }}</th><th scope="col">{{ $allLabel }}</th><th scope="col">Classification</th></tr></thead><tbody>@foreach($months as $k => $m)<tr><td>{{ $k }}</td><td>{{ $m['lens'] }}</td><td>{{ $m['all'] }}</td><td>{{ $m['settled'] ? 'complete' : 'incomplete' }}</td></tr>@endforeach</tbody></table></div>
</figure>
