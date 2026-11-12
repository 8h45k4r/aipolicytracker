@props(['events'])
{{-- The next twelve months as a line, each recorded deadline a marker on its day. The list
     below the picture carries the same dates as text; this is the overview. --}}
@php($start = now()->startOfDay())
@php($end = $start->copy()->addYear())
@php($span = max(1, $start->diffInDays($end)))
@php($shown = $events->filter(fn ($d) => $d->due_on->betweenIncluded($start, $end))->values())
@if($shown->isNotEmpty())
<figure class="mt-6" aria-labelledby="tl-title">
    <svg viewBox="0 0 720 96" class="w-full h-auto" role="img" aria-labelledby="tl-title tl-desc">
        <title id="tl-title">The next twelve months</title>
        <desc id="tl-desc">{{ $shown->count() }} recorded {{ \Illuminate\Support\Str::plural('deadline', $shown->count()) }} between {{ $start->format('j F Y') }} and {{ $end->format('j F Y') }}: {{ $shown->map(fn ($d) => $d->due_on->format('j F Y').', '.$d->title)->implode('; ') }}.</desc>
        <g aria-hidden="true">
            <line x1="20" y1="48" x2="700" y2="48" class="stroke-brand-line" stroke-width="3" stroke-linecap="round"/>
            @for($m = 0; $m <= 12; $m++)
            @php($x = 20 + 680 * ($start->diffInDays($start->copy()->addMonths($m)) / $span))
            <line x1="{{ round($x, 1) }}" y1="42" x2="{{ round($x, 1) }}" y2="54" class="stroke-brand-line" stroke-width="1.5"/>
            @if($m % 3 === 0)<text x="{{ round($x, 1) }}" y="76" text-anchor="middle" font-size="12" class="fill-brand-muted">{{ $start->copy()->addMonths($m)->format('M Y') }}</text>@endif
            @endfor
            <text x="20" y="24" font-size="12" class="fill-brand-navy" font-weight="600">Today</text>
            @foreach($shown as $i => $d)
            @php($x = 20 + 680 * ($start->diffInDays($d->due_on) / $span))
            <circle cx="{{ round($x, 1) }}" cy="48" r="{{ $i === 0 ? 8 : 6 }}" class="{{ $i === 0 ? 'fill-brand-blue' : 'fill-brand-navy' }} stroke-white" stroke-width="2"/>
            @endforeach
        </g>
    </svg>
    <figcaption class="meta">Each marker is a deadline recorded to an exact day; the larger one is the next. The list below names them.</figcaption>
</figure>
@endif
