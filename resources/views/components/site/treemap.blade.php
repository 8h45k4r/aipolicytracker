@props(['rows', 'title', 'note' => null])
{{-- rows: [['label' => ..., 'url' => ..., 'total' => n, 'cells' => [['label','url','value']]]] — one horizontal band per row, cell width proportional to value. --}}
@php($max = max(1, (int) collect($rows)->max('total')))
<figure {{ $attributes->merge(['class' => 'card-flat p-4']) }}>
    <figcaption class="text-sm font-semibold text-brand-navy">{{ $title }}</figcaption>
    <div class="mt-3 space-y-2">
        @foreach($rows as $row)
        <div class="grid grid-cols-12 gap-2 items-center text-xs">
            <a href="{{ $row['url'] }}" class="col-span-4 sm:col-span-3 truncate text-brand-navy font-medium" title="{{ $row['label'] }}">{{ $row['label'] }}</a>
            <div class="col-span-8 sm:col-span-9 flex h-8 gap-px" style="width: {{ max(8, round(100 * $row['total'] / $max)) }}%">
                @foreach($row['cells'] as $c)
                @if($c['value'] > 0)
                <a href="{{ $c['url'] }}" class="flex items-center justify-center overflow-hidden rounded-sm bg-brand-navy text-white no-underline hover:bg-brand-blue" style="flex: {{ $c['value'] }} 1 0; opacity: {{ 0.55 + 0.45 * ($loop->index % 2) }}" title="{{ $c['label'] }}: {{ number_format($c['value']) }}" data-tip="{{ $c['label'] }}: {{ number_format($c['value']) }}"><span class="truncate px-1">{{ $c['short'] ?? '' }}</span></a>
                @endif
                @endforeach
            </div>
        </div>
        @endforeach
    </div>
    @if($note)<p class="mt-2 meta">{{ $note }}</p>@endif
</figure>
