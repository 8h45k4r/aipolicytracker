@props(['mode', 'facets' => [], 'options' => [], 'filters' => []])
{{--
The listing at a glance: how the records that match the current filters split, drawn from the
same facet counts as the filter panel. Each segment is a link that adds that value to the
filters, so the figure is also a way in. Policies split by status and by whether they bind;
obligations by category.
--}}
@php
    $link = fn (string $key, string $value) => request()->fullUrlWithQuery([$key => $value, 'page' => null]);
    if ($mode === 'policies') {
        $tone = ['in_force' => 'good', 'partially_applicable' => 'good', 'adopted' => 'blue', 'proposed' => 'warn', 'under_consultation' => 'warn',
            'guidance' => 'cyan', 'voluntary_standard' => 'cyan', 'enforcement_action' => 'bad', 'superseded' => 'neutral', 'repealed' => 'neutral', 'archived' => 'neutral'];
        $rows = collect($options['statuses'] ?? [])->map(fn ($s) => ['key' => 'status', 'value' => $s->value, 'label' => $s->label(), 'n' => (int) ($facets['status'][$s->value] ?? 0), 'tone' => $tone[$s->value] ?? 'neutral'])
            ->filter(fn ($r) => $r['n'] > 0)->values();
        $title = 'By status';
    } else {
        $rows = collect($options['categories'] ?? [])->map(fn ($c) => ['key' => 'category', 'value' => $c->slug, 'label' => $c->name, 'n' => (int) ($facets['category'][$c->slug] ?? 0), 'tone' => 'blue'])
            ->filter(fn ($r) => $r['n'] > 0)->sortByDesc('n')->values();
        $title = 'By category';
    }
    $sum = max(1, $rows->sum('n'));
    $binding = ['yes' => (int) ($facets['binding']['yes'] ?? 0), 'no' => (int) ($facets['binding']['no'] ?? 0)];
    $bindSum = max(1, array_sum($binding));
    $fill = ['good' => 'bg-[rgb(var(--c-good))]', 'blue' => 'bg-[rgb(var(--c-blue))]', 'warn' => 'bg-[rgb(var(--c-warn))]', 'cyan' => 'bg-[rgb(var(--c-cyan))]', 'bad' => 'bg-[rgb(var(--c-bad))]', 'neutral' => 'bg-[rgb(var(--c-neutral))]'];
@endphp
@if($rows->isNotEmpty())
<section class="mt-4 rounded-xl border border-brand-line bg-brand-paper p-4" aria-labelledby="glance-h" data-facet-breakdown>
    <h2 id="glance-h" class="text-sm font-semibold text-brand-navy">At a glance <span class="font-normal text-brand-muted">· {{ $title }}, for the records that match</span></h2>
    @if($mode === 'policies')
        {{-- One bar of the whole set, a segment per status, each a link to that status. --}}
        <ul class="mt-3 flex h-4 w-full overflow-hidden rounded-full bg-white ring-1 ring-brand-line" aria-label="Records by status">
            @foreach($rows as $r)
            <li class="h-full {{ $fill[$r['tone']] }} {{ in_array($r['value'], ['partially_applicable', 'voluntary_standard', 'under_consultation'], true) ? 'opacity-60' : '' }}" style="width: {{ round(100 * $r['n'] / $sum, 2) }}%"><a href="{{ $link($r['key'], $r['value']) }}" class="block h-full" title="{{ $r['label'] }}: {{ $r['n'] }}" rel="nofollow" data-track="glance_segment"><span class="sr-only">{{ $r['label'] }}: {{ $r['n'] }}</span></a></li>
            @endforeach
        </ul>
        <ul class="mt-3 flex flex-wrap gap-x-4 gap-y-1.5 text-sm" aria-hidden="true">
            @foreach($rows as $r)
            <li><a href="{{ $link($r['key'], $r['value']) }}" class="inline-flex items-center gap-1.5 text-brand-body no-underline hover:text-brand-navy" rel="nofollow" tabindex="-1"><span class="h-2.5 w-2.5 rounded-sm {{ $fill[$r['tone']] }} {{ in_array($r['value'], ['partially_applicable', 'voluntary_standard', 'under_consultation'], true) ? 'opacity-60' : '' }}"></span>{{ $r['label'] }} <span class="font-mono text-xs text-brand-muted">{{ $r['n'] }}</span></a></li>
            @endforeach
        </ul>
    @else
        {{-- A bar per category, longest first; the top eight, the rest are in the filter. --}}
        <ul class="mt-3 grid gap-1.5 sm:grid-cols-2 sm:gap-x-6" aria-label="Obligations by category">
            @foreach($rows->take(8) as $r)
            <li><a href="{{ $link($r['key'], $r['value']) }}" class="group grid grid-cols-[1fr_auto] items-center gap-x-2 text-sm no-underline" rel="nofollow" data-track="glance_segment">
                <span class="truncate text-brand-body group-hover:text-brand-navy">{{ $r['label'] }}</span><span class="font-mono text-xs text-brand-muted">{{ $r['n'] }}</span>
                <span class="col-span-2 mt-0.5 h-1.5 rounded-full bg-white ring-1 ring-brand-line" aria-hidden="true"><span class="block h-full rounded-full {{ $fill['blue'] }}" style="width: {{ round(100 * $r['n'] / $rows->max('n'), 2) }}%"></span></span>
            </a></li>
            @endforeach
        </ul>
    @endif
    @if(array_sum($binding) > 0)
    <p class="mt-3 flex flex-wrap items-center gap-x-3 gap-y-1 text-sm">
        <span class="text-brand-muted">Binding</span>
        <span class="flex h-2 w-40 overflow-hidden rounded-full bg-white ring-1 ring-brand-line" aria-hidden="true"><span class="h-full bg-brand-navy" style="width: {{ round(100 * $binding['yes'] / $bindSum, 2) }}%"></span></span>
        <a href="{{ $link('binding', 'yes') }}" rel="nofollow">{{ $binding['yes'] }} legally binding</a>
        <span aria-hidden="true" class="text-brand-muted">·</span>
        <a href="{{ $link('binding', 'no') }}" rel="nofollow">{{ $binding['no'] }} voluntary or guidance</a>
    </p>
    @endif
</section>
@endif
