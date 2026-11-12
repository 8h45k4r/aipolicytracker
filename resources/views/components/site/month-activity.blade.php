@props(['activity'])
{{--
Twelve months of recorded changes as columns: the full height is every change that month, the
dark part the urgent and high-impact ones. A month with changes links to its archive.
--}}
@php($max = max(1, $activity->max('total')))
@php($sum = $activity->sum('total'))
<section class="mt-5 rounded-xl border border-brand-line bg-brand-paper p-4" aria-labelledby="activity-h" data-month-activity>
    <div class="flex flex-wrap items-baseline justify-between gap-2">
        <h2 id="activity-h" class="text-sm font-semibold text-brand-navy">The last twelve months <span class="font-normal text-brand-muted">· {{ number_format($sum) }} recorded {{ \Illuminate\Support\Str::plural('change', $sum) }}, {{ number_format($activity->sum('high')) }} urgent or high impact</span></h2>
        <p class="flex items-center gap-3 text-xs text-brand-muted" aria-hidden="true"><span class="inline-flex items-center gap-1"><span class="h-2.5 w-2.5 rounded-sm bg-brand-navy"></span>Urgent or high</span><span class="inline-flex items-center gap-1"><span class="h-2.5 w-2.5 rounded-sm bg-[rgb(var(--c-cyan))] opacity-50"></span>Routine</span></p>
    </div>
    <ol class="mt-3 grid h-28 grid-cols-12 items-end gap-1.5 sm:gap-2">
        @foreach($activity as $m)
        @php($h = $m['total'] ? max(6, round(100 * $m['total'] / $max)) : 0)
        <li class="flex h-full flex-col items-center justify-end gap-1">
            @if($m['total'])
            <a href="{{ route('updates.month', $m['month']) }}" class="flex w-full flex-col justify-end overflow-hidden rounded-t no-underline" style="height: {{ $h }}%" title="{{ $m['label'] }} {{ $m['year'] }}: {{ $m['total'] }} changes, {{ $m['high'] }} urgent or high" data-track="activity_month">
                <span class="block w-full bg-[rgb(var(--c-cyan))] opacity-50" style="flex-grow: {{ $m['total'] - $m['high'] }}"></span>
                <span class="block w-full bg-brand-navy" style="flex-grow: {{ $m['high'] }}"></span>
                <span class="sr-only">{{ $m['label'] }} {{ $m['year'] }}: {{ $m['total'] }} changes, {{ $m['high'] }} urgent or high impact</span>
            </a>
            @else
            <span class="block h-px w-full bg-brand-line" aria-hidden="true"></span><span class="sr-only">{{ $m['label'] }} {{ $m['year'] }}: no changes</span>
            @endif
        </li>
        @endforeach
    </ol>
    <ol class="mt-1 grid grid-cols-12 gap-1.5 sm:gap-2 text-center font-mono text-[10px] text-brand-muted sm:text-xs" aria-hidden="true">
        @foreach($activity as $m)<li>{{ $m['label'] }}</li>@endforeach
    </ol>
</section>
