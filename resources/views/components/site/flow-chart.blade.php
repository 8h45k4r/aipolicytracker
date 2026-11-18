@props(['left', 'right', 'domains', 'title', 'note' => null, 'height' => 370])
{{--
Three columns joined by bands whose thickness is a count: who was responsible, whether
the outcome was intended, and the risk domain it was classified under.
$left: [entity => [intent => n]], $right: [intent => [domainId => n]], $domains: [id => name].
A band is drawn in the colour of its intent, so deliberate and accidental can be followed
from one side of the chart to the other.
--}}
@php
    $entityOrder = ['Human', 'AI', 'Other'];
    $intentOrder = ['Intentional', 'Unintentional', 'Other'];
    $intentName = ['Intentional' => 'Deliberate', 'Unintentional' => 'Accidental', 'Other' => 'Unclear'];
    $tone = ['Intentional' => '#9B1C2E', 'Unintentional' => '#006AAC', 'Other' => '#5D6B7E'];
    $cols = [[], [], []];
    foreach ($entityOrder as $e) {
        if ($v = array_sum($left[$e] ?? [])) { $cols[0][$e] = $v; }
    }
    foreach ($intentOrder as $i) {
        if ($v = array_sum(array_column($left, $i))) { $cols[1][$i] = $v; }
    }
    foreach ($domains as $id => $name) {
        if ($v = array_sum(array_map(fn ($row) => $row[$id] ?? 0, $right))) { $cols[2][$id] = $v; }
    }
    $total = max(1, array_sum($cols[0]));
    $gap = 20;
    $top = 44;
    $w = 760;
    $nw = 12;
    $xs = [64, 300, 536];
    $scale = ($height - $top - $gap * (max(1, max(array_map('count', $cols))) - 1)) / $total;
    $pos = [[], [], []];
    foreach ($cols as $c => $nodes) {
        $y = $top;
        foreach ($nodes as $key => $v) {
            $h = max(2, $v * $scale);
            $pos[$c][$key] = ['y' => $y, 'h' => $h, 'out' => $y, 'in' => $y];
            $y += $h + $gap;
        }
    }
    $path = function (float $x0, float $y0, float $x1, float $y1, float $h): string {
        $mx = ($x0 + $x1) / 2;

        return sprintf('M%.1f,%.1f C%.1f,%.1f %.1f,%.1f %.1f,%.1f L%.1f,%.1f C%.1f,%.1f %.1f,%.1f %.1f,%.1f Z', $x0, $y0, $mx, $y0, $mx, $y1, $x1, $y1, $x1, $y1 + $h, $mx, $y1 + $h, $mx, $y0 + $h, $x0, $y0 + $h);
    };
    $bands = [];
    foreach ($entityOrder as $e) {
        foreach ($intentOrder as $i) {
            $v = $left[$e][$i] ?? 0;
            if ($v && isset($pos[0][$e], $pos[1][$i])) {
                $h = max(1, $v * $scale);
                $bands[] = ['d' => $path($xs[0] + $nw, $pos[0][$e]['out'], $xs[1], $pos[1][$i]['in'], $h), 'fill' => $tone[$i], 'tip' => $e.' → '.$intentName[$i].': '.number_format($v).' ('.round(100 * $v / $total).'%)'];
                $pos[0][$e]['out'] += $h;
                $pos[1][$i]['in'] += $h;
            }
        }
    }
    foreach ($intentOrder as $i) {
        foreach ($domains as $id => $name) {
            $v = $right[$i][$id] ?? 0;
            if ($v && isset($pos[1][$i], $pos[2][$id])) {
                $h = max(1, $v * $scale);
                $bands[] = ['d' => $path($xs[1] + $nw, $pos[1][$i]['out'], $xs[2], $pos[2][$id]['in'], $h), 'fill' => $tone[$i], 'tip' => $intentName[$i].' → '.$name.': '.number_format($v).' ('.round(100 * $v / $total).'%)'];
                $pos[1][$i]['out'] += $h;
                $pos[2][$id]['in'] += $h;
            }
        }
    }
@endphp
<figure {{ $attributes->merge(['class' => 'card-flat p-4']) }} data-chart="{{ \Illuminate\Support\Str::slug($title) }}">
    <figcaption class="flex flex-wrap items-baseline justify-between gap-x-4 gap-y-1"><span class="text-sm font-medium text-brand-navy">{{ $title }}</span>@if($note)<span class="meta">{{ $note }}</span>@endif</figcaption>
    <div class="mt-2 overflow-x-auto">
    <svg viewBox="0 0 {{ $w }} {{ $height + 8 }}" role="img" aria-label="{{ $title }}" class="flow w-full min-w-[40rem] h-auto">
        <title>{{ $title }}</title>
        @foreach([['Responsible', 0, 'start'], ['Intent', 1, 'middle'], ['Risk domain', 2, 'start']] as [$head, $c, $anchor])
        <text x="{{ $c === 1 ? $xs[1] + $nw / 2 : $xs[$c] }}" y="10" font-size="11" text-anchor="{{ $anchor }}" fill="#5D6B7E" letter-spacing="0.6">{{ mb_strtoupper($head) }}</text>
        @endforeach
        @foreach($bands as $bnd)
        <path d="{{ $bnd['d'] }}" fill="{{ $bnd['fill'] }}" class="flow-band" data-tip="{{ $bnd['tip'] }}"><title>{{ $bnd['tip'] }}</title></path>
        @endforeach
        @foreach($cols[0] as $e => $v)
        <rect x="{{ $xs[0] }}" y="{{ round($pos[0][$e]['y'], 1) }}" width="{{ $nw }}" height="{{ round($pos[0][$e]['h'], 1) }}" fill="#002147" />
        <text x="{{ $xs[0] - 6 }}" y="{{ round($pos[0][$e]['y'] + min(14, $pos[0][$e]['h'] / 2 + 4), 1) }}" font-size="12" text-anchor="end" fill="#002147" font-weight="600">{{ $e }}</text>
        <text x="{{ $xs[0] - 6 }}" y="{{ round($pos[0][$e]['y'] + min(14, $pos[0][$e]['h'] / 2 + 4) + 13, 1) }}" font-size="11" text-anchor="end" fill="#5D6B7E" font-family="ui-monospace, monospace">{{ number_format($v) }}</text>
        @endforeach
        @foreach($cols[1] as $i => $v)
        <rect x="{{ $xs[1] }}" y="{{ round($pos[1][$i]['y'], 1) }}" width="{{ $nw }}" height="{{ round($pos[1][$i]['h'], 1) }}" fill="{{ $tone[$i] }}" />
        <text x="{{ $xs[1] + $nw / 2 }}" y="{{ round($pos[1][$i]['y'] - 5, 1) }}" font-size="12" text-anchor="middle" fill="#002147" font-weight="600" stroke="#ffffff" stroke-width="4" stroke-linejoin="round" paint-order="stroke">{{ $intentName[$i] }} <tspan fill="#5D6B7E" font-weight="400" font-family="ui-monospace, monospace" font-size="11">{{ number_format($v) }} · {{ round(100 * $v / $total) }}%</tspan></text>
        @endforeach
        @foreach($cols[2] as $id => $v)
        <rect x="{{ $xs[2] }}" y="{{ round($pos[2][$id]['y'], 1) }}" width="{{ $nw }}" height="{{ round($pos[2][$id]['h'], 1) }}" fill="#002147" />
        <text x="{{ $xs[2] + $nw + 6 }}" y="{{ round($pos[2][$id]['y'] + min(12, $pos[2][$id]['h'] / 2 + 4), 1) }}" font-size="12" fill="#002147">{{ $id }} {{ \Illuminate\Support\Str::limit($domains[$id], 26) }} <tspan fill="#5D6B7E" font-family="ui-monospace, monospace" font-size="11">{{ number_format($v) }}</tspan></text>
        @endforeach
    </svg>
    </div>
    <ul class="mt-2 flex flex-wrap gap-x-4 gap-y-1 text-xs text-brand-body" aria-label="Legend">
        @foreach($cols[1] as $i => $v)<li class="inline-flex items-center gap-1.5"><svg width="10" height="10" aria-hidden="true"><rect width="10" height="10" fill="{{ $tone[$i] }}" /></svg>{{ $intentName[$i] }} (coded {{ $i }})</li>@endforeach
    </ul>
    <x-site.chart-tools />
    <div class="sr-only"><table><caption>{{ $title }}</caption><thead><tr><th scope="col">From</th><th scope="col">To</th><th scope="col">Incidents</th></tr></thead><tbody>
        @foreach($left as $e => $row) @foreach($row as $i => $v)<tr><td>{{ $e }}</td><td>{{ $intentName[$i] ?? $i }}</td><td>{{ $v }}</td></tr>@endforeach @endforeach
        @foreach($right as $i => $row) @foreach($row as $id => $v)<tr><td>{{ $intentName[$i] ?? $i }}</td><td>{{ $domains[$id] ?? $id }}</td><td>{{ $v }}</td></tr>@endforeach @endforeach
    </tbody></table></div>
</figure>
