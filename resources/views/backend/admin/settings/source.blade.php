{{-- Where the value in use comes from. "unknown": the configuration is cached, so the
     environment cannot be read and an unsaved value is the environment's or the default. --}}
@php
    [$sourceLabel, $sourceTone] = match ($v['source']) {
        'saved' => ['Saved here', 'bg-state-goodbg text-state-good ring-state-good/30'],
        'environment' => ['From environment', 'bg-brand-paper text-brand-navy ring-brand-line'],
        'default' => ['Default', 'bg-brand-paper text-brand-muted ring-brand-line'],
        default => ['Environment or default', 'bg-brand-paper text-brand-muted ring-brand-line'],
    };
@endphp
<span class="badge mt-1 {{ $sourceTone }}" data-source="{{ $v['source'] }}">{{ $sourceLabel }}</span>
