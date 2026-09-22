@props(['stats'])
{{--
    The model the site is built on, as a row of linked counts: a law creates a duty, a
    duty is met by a control, a control produces evidence. Every figure is live and
    every tile is the page that lists what it counts, so the strip is navigation as
    much as it is a claim.
--}}
<ol {{ $attributes->merge(['class' => 'chain']) }} aria-label="From law to evidence">
    @foreach([
        ['Jurisdictions', $stats['jurisdictions'] ?? 0, route('jurisdictions.index'), 'countries, blocs and states'],
        ['Instruments', $stats['policies'] ?? 0, route('policies.index'), 'laws, strategies, guidance'],
        ['Obligations', $stats['obligations'] ?? 0, route('obligations.index'), 'what the rules require'],
        ['Controls', $stats['controls'] ?? 0, route('controls.index'), 'what you operate to meet them'],
        ['Evidence types', $stats['evidence_types'] ?? 0, route('controls.index').'#evidence', 'what proves it'],
    ] as [$label, $n, $href, $note])
    <li class="chain-step">
        <a href="{{ $href }}" class="chain-link">
            <span class="stat-label">{{ $label }}</span>
            <span class="stat-value">{{ $n ?: '—' }}</span>
            <span class="stat-note">{{ $note }}</span>
        </a>
    </li>
    @endforeach
</ol>
