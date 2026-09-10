@props(['record'])
@php($verified = $record->isVerified())
<span {{ $attributes->merge(['class' => 'inline-flex items-center gap-1.5 text-xs '.($verified ? 'text-emerald-800' : 'text-amber-800')]) }}>
    <span aria-hidden="true" class="inline-block h-2 w-2 rounded-full {{ $verified ? 'bg-emerald-500' : 'bg-amber-500' }}"></span>
    {{ $record->verificationLabel() }}
    @if(isset($record->confidence_level))<span class="text-slate-500">· confidence: {{ $record->confidence_level }}</span>@endif
</span>
