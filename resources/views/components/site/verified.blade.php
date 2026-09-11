@props(['record'])
@php($verified = $record->isVerified())
<span {{ $attributes->merge(['class' => 'inline-flex items-center gap-1.5 text-xs '.($verified ? 'text-state-good' : 'text-brand-muted')]) }}>
    <span aria-hidden="true" class="inline-block h-2 w-2 rounded-sm {{ $verified ? 'bg-state-good' : 'bg-brand-line' }}"></span>
    {{ $record->verificationLabel() }}
</span>
