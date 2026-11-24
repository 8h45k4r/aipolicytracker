@props(['rows', 'domains', 'colours', 'title', 'note' => null])
{{--
Who is harmed by which kind of risk. One row per harmed party, one column per MIT risk
domain. A cell is shaded by the share of that row's incidents that fall in the column,
in the column's domain colour, so a row reads as that party's risk profile and a column
as the parties a kind of risk reaches.
$rows: [party => ['total' => n, 'cells' => [domainId => n]]], $domains: [id => name].
--}}
@php
    // White text only where it reaches 4.5:1 on the cell as drawn on a white page (the
    // domain colour at this opacity over white); elsewhere the page's own text colour.
    $lightText = function (string $hex, float $alpha): bool {
        $lin = function (float $c): float {
            $c /= 255;

            return $c <= 0.03928 ? $c / 12.92 : (($c + 0.055) / 1.055) ** 2.4;
        };
        [$r, $g, $b] = array_map(fn ($i) => $alpha * hexdec(substr($hex, $i, 2)) + (1 - $alpha) * 255, [1, 3, 5]);
        $l = 0.2126 * $lin($r) + 0.7152 * $lin($g) + 0.0722 * $lin($b);

        return 1.05 / ($l + 0.05) >= 4.5;
    };
@endphp
<figure {{ $attributes->merge(['class' => 'card-flat p-4']) }}>
    <figcaption class="flex flex-wrap items-baseline justify-between gap-x-4 gap-y-1"><span class="text-sm font-medium text-brand-navy">{{ $title }}</span>@if($note)<span class="meta">{{ $note }}</span>@endif</figcaption>
    <div class="table-wrap mt-3 !mx-0 !border-0">
    <table class="!text-xs harm-matrix"><caption class="sr-only">{{ $title }}. Each cell is the number of incidents; the shading is its share of the row.</caption>
        <thead><tr>
            <th scope="col" class="!bg-transparent">Harmed party</th>
            @foreach($domains as $id => $name)
            <th scope="col" class="!bg-transparent text-center !normal-case !tracking-normal" title="{{ $name }}"><span class="inline-flex flex-col items-center gap-1"><span aria-hidden="true" class="block h-1.5 w-6 rounded-sm" style="background: {{ $colours[$id] ?? '#5D6B7E' }}"></span><span>{{ $id }}</span><span class="sr-only">{{ $name }}</span></span></th>
            @endforeach
            <th scope="col" class="!bg-transparent text-right">Incidents</th>
        </tr></thead>
        <tbody>
        @foreach($rows as $party => $row)
            <tr>
                <th scope="row" class="whitespace-nowrap">{{ $party }}</th>
                @foreach($domains as $id => $name)
                @php($v = (int) ($row['cells'][$id] ?? 0))
                @php($share = $row['total'] ? $v / $row['total'] : 0)
                @php($alpha = $v ? 0.08 + 0.8 * $share : 0)
                <td class="text-center tabular-nums {{ $lightText($colours[$id] ?? '#5D6B7E', $alpha) ? 'harm-hi' : '' }}" style="{{ $v ? 'background: '.($colours[$id] ?? '#5D6B7E').sprintf('%02X', (int) round(255 * $alpha)).';' : '' }}" data-tip="{{ $party }} · {{ $id }} {{ $name }}: {{ $v }} of {{ $row['total'] }} ({{ round(100 * $share) }}%)">{{ $v ?: '·' }}</td>
                @endforeach
                <td class="text-right tabular-nums font-medium text-brand-navy">{{ number_format($row['total']) }}</td>
            </tr>
        @endforeach
        </tbody>
    </table>
    </div>
    <ul class="mt-3 flex flex-wrap gap-x-4 gap-y-1 text-xs text-brand-body" aria-label="Risk domains">
        @foreach($domains as $id => $name)<li class="inline-flex items-center gap-1.5"><span aria-hidden="true" class="inline-block h-2.5 w-2.5 rounded-sm" style="background: {{ $colours[$id] ?? '#5D6B7E' }}"></span>{{ $id }} {{ $name }}</li>@endforeach
    </ul>
</figure>
