@props(['stats'])
{{--
    The model the site is built on, as a row of linked counts: a law creates a duty, a
    duty is met by a control, a control produces evidence. Every figure is live and
    every tile is the page that lists what it counts, so the strip is navigation as
    much as it is a claim.
--}}
@php($new = $stats['new_this_month'] ?? [])
<ol {{ $attributes->merge(['class' => 'chain']) }} aria-label="From law to evidence">
    @foreach([
        ['Jurisdictions', $stats['jurisdictions'] ?? 0, route('jurisdictions.index'), 'Track the rule', 'jurisdictions'],
        ['Instruments', $stats['policies'] ?? 0, route('policies.index'), 'Track the requirement', 'policies'],
        ['Obligations', $stats['obligations'] ?? 0, route('obligations.index'), 'Understand the duty', 'obligations'],
        ['Controls', $stats['controls'] ?? 0, route('controls.index'), 'Operationalise it', 'controls'],
        ['Evidence types', $stats['evidence_types'] ?? 0, route('controls.index').'#evidence', 'Prove it', 'evidence_types'],
    ] as [$label, $n, $href, $note, $key])
    <li class="chain-step">
        <a href="{{ $href }}" class="chain-link no-underline">
            <span class="stat-label">{{ $label }}</span>
            <span class="stat-value">{{ $n ?: '—' }}</span>
            <span class="stat-note">{{ $note }}</span>
            {{-- Counted from when each record was first published; nothing shown for a quiet month. --}}
            @if(($new[$key] ?? 0) > 0)<span class="stat-note text-state-good" data-new-this-month>+{{ $new[$key] }} this month</span>@endif
        </a>
    </li>
    @endforeach
</ol>
{{-- One sentence of plain text with the headline figures, for a reader or an answer engine to quote. --}}
<p class="mt-4 max-w-[70ch] text-sm text-brand-body" data-quotable-stats>AIPolicyTracker tracks {{ number_format($stats['policies'] ?? 0) }} AI policy instruments across {{ number_format($stats['jurisdictions'] ?? 0) }} jurisdictions, mapped to {{ number_format($stats['obligations'] ?? 0) }} obligations.</p>
