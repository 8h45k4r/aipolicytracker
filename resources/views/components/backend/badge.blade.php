@props(['status'])
@php($tone = match ($status) {
    'verified', 'published', 'approved', 'ok', 'active', 'yes' => 'bg-state-goodbg text-state-good ring-state-good/30',
    'needs_update', 'needs_information', 'unconfirmed', 'running' => 'bg-state-warnbg text-state-warn ring-state-warn/30',
    'rejected', 'failed', 'suspended', 'unpublished' => 'bg-state-badbg text-state-bad ring-state-bad/30',
    'draft', 'archived', 'no' => 'bg-brand-paper text-brand-muted ring-brand-line',
    default => 'bg-brand-paper text-brand-body ring-brand-line',
})
<span {{ $attributes->merge(['class' => 'badge '.$tone]) }}>{{ $slot->isEmpty() ? str_replace('_', ' ', $status) : $slot }}</span>
