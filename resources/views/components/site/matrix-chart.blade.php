@props(['rows', 'cols', 'cells', 'title', 'rowLabel' => '', 'colLabel' => ''])
@php($max = max(1, (int) collect($cells)->flatten()->max()))
<figure {{ $attributes->merge(['class' => 'card-flat p-4']) }}>
    <figcaption class="text-sm font-medium text-brand-navy">{{ $title }}</figcaption>
    <div class="table-wrap mt-2 !mx-0 !border-0"><table class="!text-xs"><caption class="sr-only">{{ $title }}</caption>
        <thead><tr><th scope="col" class="!bg-white">{{ $rowLabel }} \ {{ $colLabel }}</th>@foreach($cols as $c)<th scope="col" class="!bg-white text-center">{{ $c }}</th>@endforeach</tr></thead>
        <tbody>
        @foreach($rows as $r)
        <tr><th scope="row" class="!bg-white text-left font-medium text-brand-body">{{ $r }}</th>
            @foreach($cols as $c)
            @php($v = (int) ($cells[$r][$c] ?? 0))
            @php($a = $v ? 0.12 + 0.75 * ($v / $max) : 0)
            <td class="text-center font-mono tabular-nums" style="background: rgba(0,33,71,{{ number_format($a, 2) }}); color: {{ $a > 0.5 ? '#ffffff' : '#1E2A3B' }};">{{ $v ?: '—' }}</td>
            @endforeach
        </tr>
        @endforeach
        </tbody></table></div>
</figure>
