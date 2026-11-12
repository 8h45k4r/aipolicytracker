@props(['policies', 'jurisdiction'])
{{--
What a jurisdiction has on record, drawn: one bar split into binding law and guidance, and a
count per status. Counts come from the instruments listed on the page; each part links to the
policy listing filtered to it. Shown from two instruments up, where a split says something.
--}}
@php
    $total = $policies->count();
    $binding = $policies->where('is_binding', true)->count();
    $statuses = $policies->groupBy(fn ($p) => $p->status instanceof \BackedEnum ? $p->status->value : (string) $p->status)->map->count()->sortDesc();
    $to = fn (array $q) => route('policies.index', ['jurisdiction' => $jurisdiction->slug] + $q);
@endphp
@if($total >= 2)
<section {{ $attributes->merge(['class' => 'rounded-xl border border-brand-line bg-brand-paper p-4']) }} aria-labelledby="mix-h" data-jurisdiction-mix>
    <h2 id="mix-h" class="text-sm font-semibold text-brand-navy">On record for {{ $jurisdiction->short_name ?: $jurisdiction->name }} <span class="font-normal text-brand-muted">· {{ $total }} instruments</span></h2>
    <div class="mt-3 flex h-3 w-full overflow-hidden rounded-full bg-white ring-1 ring-brand-line" aria-hidden="true">
        <span class="h-full bg-brand-navy" style="width: {{ round(100 * $binding / $total, 2) }}%"></span>
        <span class="h-full bg-[rgb(var(--c-cyan))] opacity-50" style="width: {{ round(100 * ($total - $binding) / $total, 2) }}%"></span>
    </div>
    <p class="mt-2 flex flex-wrap gap-x-4 gap-y-1 text-sm">
        @if($binding > 0)
        <a href="{{ $to(['binding' => 'yes']) }}" class="inline-flex items-center gap-1.5 no-underline hover:underline" rel="nofollow"><span class="h-2.5 w-2.5 rounded-sm bg-brand-navy" aria-hidden="true"></span>{{ $binding }} legally binding</a>
        @else
        <span class="text-brand-muted">No binding AI law on record</span>
        @endif
        @if($total - $binding > 0)
        <a href="{{ $to(['binding' => 'no']) }}" class="inline-flex items-center gap-1.5 no-underline hover:underline" rel="nofollow"><span class="h-2.5 w-2.5 rounded-sm bg-[rgb(var(--c-cyan))] opacity-50" aria-hidden="true"></span>{{ $total - $binding }} strategy, guidance or voluntary</a>
        @else
        <span class="text-brand-muted">No strategy or guidance on record</span>
        @endif
    </p>
    <ul class="mt-3 flex flex-wrap gap-2 text-sm" aria-label="Instruments by status">
        @foreach($statuses as $value => $n)
        @php($label = \App\Enums\PolicyStatus::tryFrom($value)?->label() ?? $value)
        <li><a href="{{ $to(['status' => $value]) }}" class="chip gap-1.5" rel="nofollow">{{ $label }} <span class="font-mono text-xs text-brand-muted">{{ $n }}</span></a></li>
        @endforeach
    </ul>
</section>
@endif
