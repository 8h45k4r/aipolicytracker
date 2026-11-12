@props(['sources' => 0])
{{-- Three source kinds feeding one dated list. Decorative detail is aria-hidden; the
     figure's title and description carry the meaning for a screen reader. --}}
<figure class="mt-6" aria-labelledby="ns-title">
    <svg viewBox="0 0 720 170" class="w-full h-auto" role="img" aria-labelledby="ns-title ns-desc">
        <title id="ns-title">Where the news comes from</title>
        <desc id="ns-desc">Three kinds of source feed one list, newest first: official sources' own feeds ({{ $sources }} feeds), the laws and changes this tracker records, and recorded AI incidents.</desc>
        @php($boxes = [['y' => 10, 'label' => 'Official sources', 'sub' => $sources.' regulator and government feeds', 'cls' => 'fill-brand-blue'], ['y' => 62, 'label' => 'Recorded changes', 'sub' => 'from this tracker\'s change log', 'cls' => 'fill-brand-navy'], ['y' => 114, 'label' => 'AI incidents', 'sub' => 'AI Incident Database records', 'cls' => 'fill-state-warn']])
        @foreach($boxes as $b)
        <g aria-hidden="true">
            <rect x="4" y="{{ $b['y'] }}" width="250" height="46" rx="8" class="fill-white stroke-brand-line" stroke-width="1.5"/>
            <rect x="4" y="{{ $b['y'] }}" width="8" height="46" rx="4" class="{{ $b['cls'] }}"/>
            <text x="24" y="{{ $b['y'] + 20 }}" class="fill-brand-navy" font-size="15" font-weight="600">{{ $b['label'] }}</text>
            <text x="24" y="{{ $b['y'] + 38 }}" class="fill-brand-muted" font-size="12">{{ $b['sub'] }}</text>
            <path d="M258 {{ $b['y'] + 23 }} C 330 {{ $b['y'] + 23 }}, 360 85, 440 85" fill="none" class="stroke-brand-line" stroke-width="2"/>
        </g>
        @endforeach
        <g aria-hidden="true">
            <polygon points="440,79 452,85 440,91" class="fill-brand-line"/>
            <rect x="456" y="40" width="258" height="90" rx="10" class="fill-brand-navy"/>
            <text x="476" y="72" class="fill-white" font-size="16" font-weight="600">One dated list</text>
            <text x="476" y="94" class="fill-white" font-size="12" opacity=".85">newest first, filter by topic,</text>
            <text x="476" y="112" class="fill-white" font-size="12" opacity=".85">every item linked to its source</text>
        </g>
    </svg>
</figure>
