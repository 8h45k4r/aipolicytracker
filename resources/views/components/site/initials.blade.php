@props(['name', 'size' => 'h-12 w-12 text-base', 'voice' => null, 'eager' => false, 'alt' => ''])
{{-- A person's openly licensed photo when the profile has one (credited on the profile page),
     otherwise their initials in a circle. The name is printed beside it, so the photo's alt text
     is empty unless the caller names it (the profile page does). --}}
@php($photo = $voice ? \App\Services\Voices\VoiceCatalog::photoUrl($voice) : null)
@if($photo)
<img src="{{ $photo }}" alt="{{ $alt }}" width="160" height="160" {{ $attributes->merge(['class' => "shrink-0 rounded-full object-cover object-top bg-brand-paper ring-1 ring-brand-line {$size}"]) }} @unless($eager) loading="lazy" @endunless decoding="async">
@else
<span {{ $attributes->merge(['class' => "inline-flex shrink-0 items-center justify-center rounded-full bg-brand-navy font-semibold text-white {$size}"]) }} aria-hidden="true">{{ \App\Services\Voices\VoiceCatalog::initials($name) }}</span>
@endif
