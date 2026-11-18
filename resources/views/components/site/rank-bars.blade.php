@props(['series', 'title', 'note' => null, 'unit' => ''])
{{--
Horizontal bars for categories with long names (a party, a sector, a risk domain),
longest first unless the caller has put them in an order of its own. $series: [label => count].
The label is printed in full, which a vertical bar chart cannot do.
--}}
@php($max = max(1, (int) collect($series)->max()))
<figure {{ $attributes->merge(['class' => 'card-flat p-4']) }}>
    <figcaption class="flex flex-wrap items-baseline justify-between gap-x-4 gap-y-1"><span class="text-sm font-medium text-brand-navy">{{ $title }}</span>@if($note)<span class="meta">{{ $note }}</span>@endif</figcaption>
    <dl class="mt-3 space-y-2 text-xs">
        @foreach($series as $label => $value)
        <div class="grid grid-cols-[minmax(0,11rem)_minmax(0,1fr)] items-center gap-3" data-tip="{{ $label }}: {{ number_format($value) }}{{ $unit ? ' '.$unit : '' }}">
            <dt class="truncate text-brand-body" title="{{ $label }}">{{ $label }}</dt>
            <dd class="flex items-center gap-2"><span class="block h-3 rounded-sm bg-brand-navy" style="width: {{ $value ? max(1, round(88 * $value / $max, 1)) : 0 }}%"></span><span class="tabular-nums text-brand-navy">{{ number_format($value) }}</span></dd>
        </div>
        @endforeach
    </dl>
</figure>
