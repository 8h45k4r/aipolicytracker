@props(['record'])
@php($verified = $record->isVerified())
<span {{ $attributes->merge(['class' => 'inline-flex items-center gap-1.5 text-xs '.($verified ? 'text-state-good' : 'text-brand-muted')]) }}>
    <span aria-hidden="true" class="inline-block h-2 w-2 rounded-sm {{ $verified ? 'bg-state-good' : 'bg-brand-line' }}"></span>
    {{ $record->verificationLabel() }}@if($verified && filled($record->reviewed_by ?? null)) · reviewed by <a href="{{ route('reviewers') }}" class="underline decoration-dotted" title="The reviewer roster and each reviewer's declared interests">{{ $record->reviewed_by }}</a>@endif
</span>
