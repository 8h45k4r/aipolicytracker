@props(['policy'])
{{-- A law's milestones on one line: adopted, in force, applies from, and its recorded
     deadlines, in date order, past ones filled and future ones outlined. Only dates the
     record holds; a milestone without a date is left out rather than placed. --}}
@php($points = collect([
    ['date' => $policy->adopted_on, 'label' => 'Adopted'],
    ['date' => $policy->in_force_on, 'label' => 'In force'],
    ['date' => $policy->applies_from, 'label' => 'Applies from'],
])->concat($policy->deadlines->filter(fn ($d) => $d->due_on && $d->date_precision === 'exact')->map(fn ($d) => ['date' => $d->due_on, 'label' => \Illuminate\Support\Str::limit($d->title, 48)]))
    ->filter(fn ($p) => $p['date'])->unique(fn ($p) => $p['date']->toDateString().$p['label'])->sortBy(fn ($p) => $p['date']->timestamp)->values()->take(8))
@if($points->count() >= 2)
@php($first = $points->first()['date']) @php($last = $points->last()['date'])
@php($span = max(1, $first->diffInDays($last)))
@php($today = now()->startOfDay())
<figure class="mt-6" aria-labelledby="pt-title-{{ $policy->id }}">
    <svg viewBox="0 0 720 {{ 60 + 34 * $points->count() }}" class="w-full h-auto" role="img" aria-labelledby="pt-title-{{ $policy->id }} pt-desc-{{ $policy->id }}">
        <title id="pt-title-{{ $policy->id }}">Timeline</title>
        <desc id="pt-desc-{{ $policy->id }}">{{ $points->map(fn ($p) => $p['label'].': '.$p['date']->format('j F Y'))->implode('; ') }}.</desc>
        <g aria-hidden="true">
            <line x1="40" y1="30" x2="680" y2="30" class="stroke-brand-line" stroke-width="3" stroke-linecap="round"/>
            @if($today->between($first, $last))
            @php($tx = 40 + 640 * ($first->diffInDays($today) / $span))
            <line x1="{{ round($tx, 1) }}" y1="16" x2="{{ round($tx, 1) }}" y2="44" class="stroke-brand-blue" stroke-width="2" stroke-dasharray="3 3"/>
            <text x="{{ round($tx, 1) }}" y="12" text-anchor="middle" font-size="11" class="fill-brand-blue">today</text>
            @endif
            @foreach($points as $i => $p)
            @php($x = 40 + 640 * ($first->diffInDays($p['date']) / $span))
            @php($past = $p['date']->lte($today))
            <circle cx="{{ round($x, 1) }}" cy="30" r="7" class="{{ $past ? 'fill-brand-navy' : 'fill-white' }} stroke-brand-navy" stroke-width="2.5"/>
            <line x1="{{ round($x, 1) }}" y1="37" x2="{{ round($x, 1) }}" y2="{{ 56 + 34 * $i }}" class="stroke-brand-line" stroke-width="1"/>
            {{-- Labels on the right half sit to the left of their marker, so none runs off the edge. --}}
            @php($right = $x > 360)
            <text x="{{ round($right ? $x - 8 : $x + 8, 1) }}" y="{{ 62 + 34 * $i }}" font-size="13" class="fill-brand-navy" text-anchor="{{ $right ? 'end' : 'start' }}"><tspan font-weight="600">{{ $p['date']->format('j M Y') }}</tspan> · {{ \Illuminate\Support\Str::limit($p['label'], 40) }}</text>
            @endforeach
        </g>
    </svg>
</figure>
@endif
