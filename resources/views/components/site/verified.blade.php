@props(['record'])
@php($verified = $record->isVerified())
<span {{ $attributes->merge(['class' => 'inline-flex items-center gap-1.5 text-xs '.($verified ? 'text-state-good' : 'text-state-warn')]) }}>
    <span aria-hidden="true" class="inline-block h-2 w-2 rounded-sm {{ $verified ? 'bg-state-good' : 'bg-state-warn' }}"></span>
    {{ $record->verificationLabel() }}
    @if(isset($record->confidence_level))<span class="text-brand-muted">· confidence: {{ $record->confidence_level }}</span>@endif
</span>
