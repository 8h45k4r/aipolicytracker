@props(['points', 'title', 'xLabel', 'yLabel', 'note' => null, 'height' => 400, 'colours' => []])
{{--
Each bubble is one risk subdomain: across, its share of the risk entries that frameworks
describe; up, its share of the incidents recorded. On the diagonal the two shares are
equal. Above it a risk is recorded more than it is described, below it the reverse.
Both axes are on a square-root scale, so the many small subdomains do not pile up in
the corner under the one large one.
$points: [['id', 'name', 'url', 'risk_share', 'incident_share', 'incidents', 'risks', 'gap']].
--}}
@php
    $w = 760;
    $l = 52; $r = 16; $t = 18; $b = 44;
    $pw = $w - $l - $r;
    $ph = $height - $t - $b;
    $max = max(5, (float) collect($points)->max(fn ($p) => max($p['risk_share'], $p['incident_share']))) * 1.3;
    $sx = fn (float $v) => round($l + sqrt($v / $max) * $pw, 1);
    $sy = fn (float $v) => round($t + $ph - sqrt($v / $max) * $ph, 1);
    $maxInc = max(1, (int) collect($points)->max('incidents'));
    $rad = fn (int $n) => round(4 + 16 * sqrt($n / $maxInc), 1);
    $ticks = array_values(array_filter([1, 5, 10, 20, 40, 60, 80], fn ($v) => $v <= $max));
    $sorted = collect($points)->sortByDesc('incidents')->values();
    // A label is printed above its bubble unless it would sit on a label already placed
    // for a larger bubble; every bubble is still named by its tooltip.
    $labelled = [];
    $placed = [];
    foreach ($sorted as $p) {
        if ($p['incident_share'] < 1 && $p['risk_share'] < 5) {
            continue;
        }
        $lx = $sx($p['risk_share']);
        $ly = $sy($p['incident_share']) - $rad($p['incidents']) - 3;
        $clear = collect($placed)->every(fn ($q) => abs($q[0] - $lx) > 30 || abs($q[1] - $ly) > 16)
            // nor on another bubble
            && $sorted->every(fn ($o) => $o['id'] === $p['id'] || hypot($lx - $sx($o['risk_share']), $ly - 4 - $sy($o['incident_share'])) > $rad($o['incidents']) + 7);
        if ($clear) {
            $placed[] = [$lx, $ly];
            $labelled[$p['id']] = true;
        }
    }
@endphp
<figure {{ $attributes->merge(['class' => 'card-flat p-4']) }} data-chart="{{ \Illuminate\Support\Str::slug($title) }}">
    <figcaption class="flex flex-wrap items-baseline justify-between gap-x-4 gap-y-1"><span class="text-sm font-medium text-brand-navy">{{ $title }}</span>@if($note)<span class="meta">{{ $note }}</span>@endif</figcaption>
    <div class="mt-2 overflow-x-auto">
    <svg viewBox="0 0 {{ $w }} {{ $height }}" role="group" aria-label="{{ $title }}" class="w-full min-w-[36rem] h-auto">
        <title>{{ $title }}</title>
        <path d="M{{ $l }},{{ $t }} L{{ $l + $pw }},{{ $t }} L{{ $l }},{{ $t + $ph }} Z" fill="#9B1C2E" opacity="0.05" />
        <path d="M{{ $l + $pw }},{{ $t }} L{{ $l + $pw }},{{ $t + $ph }} L{{ $l }},{{ $t + $ph }} Z" fill="#006AAC" opacity="0.05" />
        @foreach($ticks as $v)
        <line x1="{{ $sx($v) }}" y1="{{ $t }}" x2="{{ $sx($v) }}" y2="{{ $t + $ph }}" stroke="#D8DEE8" stroke-dasharray="2 4" />
        <line x1="{{ $l }}" y1="{{ $sy($v) }}" x2="{{ $l + $pw }}" y2="{{ $sy($v) }}" stroke="#D8DEE8" stroke-dasharray="2 4" />
        <text x="{{ $sx($v) }}" y="{{ $t + $ph + 15 }}" font-size="11" text-anchor="middle" fill="#5D6B7E" font-family="ui-monospace, monospace">{{ $v }}%</text>
        <text x="{{ $l - 6 }}" y="{{ $sy($v) + 4 }}" font-size="11" text-anchor="end" fill="#5D6B7E" font-family="ui-monospace, monospace">{{ $v }}%</text>
        @endforeach
        <line x1="{{ $l }}" y1="{{ $t + $ph }}" x2="{{ $l + $pw }}" y2="{{ $t + $ph }}" stroke="#D8DEE8" />
        <line x1="{{ $l }}" y1="{{ $t }}" x2="{{ $l }}" y2="{{ $t + $ph }}" stroke="#D8DEE8" />
        <line x1="{{ $l }}" y1="{{ $t + $ph }}" x2="{{ $l + $pw }}" y2="{{ $t }}" stroke="#5D6B7E" stroke-dasharray="5 4" />
        <text x="{{ $l + 10 }}" y="{{ $t + 16 }}" font-size="12" fill="#9B1C2E" font-weight="600">Recorded more than described</text>
        <text x="{{ $l + $pw - 10 }}" y="{{ $t + $ph - 10 }}" font-size="12" text-anchor="end" fill="#006AAC" font-weight="600">Described more than recorded</text>
        <text x="{{ $l + $pw / 2 }}" y="{{ $height - 6 }}" font-size="12" text-anchor="middle" fill="#5D6B7E">{{ $xLabel }} →</text>
        <text transform="translate(13 {{ $t + $ph / 2 }}) rotate(-90)" font-size="12" text-anchor="middle" fill="#5D6B7E">{{ $yLabel }} →</text>
        @foreach($sorted as $p)
        @php($tip = $p['id'].' '.$p['name'].': '.$p['incident_share'].'% of incidents ('.number_format($p['incidents']).'), '.$p['risk_share'].'% of risk entries ('.number_format($p['risks']).')')
        <a href="{{ $p['url'] }}" aria-label="{{ $tip }}">
            <circle cx="{{ $sx($p['risk_share']) }}" cy="{{ $sy($p['incident_share']) }}" r="{{ $rad($p['incidents']) }}" fill="{{ $colours[$p['domain']] ?? ($p['gap'] > 0 ? '#9B1C2E' : '#006AAC') }}" fill-opacity="0.6" stroke="#ffffff" stroke-width="1" class="chart-dot" data-tip="{{ $tip }}"><title>{{ $tip }}</title></circle>
            {{-- The crowd of small subdomains near the axis is named by its tooltips; a label on each would only overlap. --}}
            @if(isset($labelled[$p['id']]))<text x="{{ $sx($p['risk_share']) }}" y="{{ $sy($p['incident_share']) - $rad($p['incidents']) - 3 }}" font-size="11" text-anchor="middle" fill="#002147" font-family="ui-monospace, monospace" stroke="#ffffff" stroke-width="3" stroke-linejoin="round" paint-order="stroke">{{ $p['id'] }}</text>@endif
        </a>
        @endforeach
    </svg>
    </div>
    <ul class="mt-2 flex flex-wrap gap-x-4 gap-y-1 text-xs text-brand-body" aria-label="Legend">
        @if($colours)
        @foreach(collect($points)->pluck('domain_name', 'domain')->sortKeys() as $id => $name)<li class="inline-flex items-center gap-1.5"><svg width="10" height="10" aria-hidden="true"><circle cx="5" cy="5" r="5" fill="{{ $colours[$id] ?? '#5D6B7E' }}" /></svg>{{ $id }} {{ $name }}</li>@endforeach
        @else
        <li class="inline-flex items-center gap-1.5"><svg width="10" height="10" aria-hidden="true"><circle cx="5" cy="5" r="5" fill="#9B1C2E" /></svg>Larger share of incidents than of risk entries</li>
        <li class="inline-flex items-center gap-1.5"><svg width="10" height="10" aria-hidden="true"><circle cx="5" cy="5" r="5" fill="#006AAC" /></svg>Larger share of risk entries than of incidents</li>
        @endif
        <li>Bubble size: incidents recorded. Dashed line: equal shares. Square-root scale. Unlabelled bubbles are named when pointed at.</li>
    </ul>
    <x-site.chart-tools />
    <div class="sr-only"><table><caption>{{ $title }}</caption><thead><tr><th scope="col">Subdomain</th><th scope="col">Share of incidents (%)</th><th scope="col">Incidents</th><th scope="col">Share of risk entries (%)</th><th scope="col">Risk entries</th></tr></thead><tbody>@foreach($sorted as $p)<tr><td>{{ $p['id'] }} {{ $p['name'] }}</td><td>{{ $p['incident_share'] }}</td><td>{{ $p['incidents'] }}</td><td>{{ $p['risk_share'] }}</td><td>{{ $p['risks'] }}</td></tr>@endforeach</tbody></table></div>
</figure>
