@props(['name', 'size' => 'h-12 w-12 text-base'])
{{-- Initials in a circle in place of a photo: profiles carry no images of people. --}}
@php($parts = preg_split('/\s+/', trim($name)))
@php($initials = mb_strtoupper(mb_substr($parts[0] ?? '', 0, 1).(count($parts) > 1 ? mb_substr(end($parts), 0, 1) : '')))
<span {{ $attributes->merge(['class' => "inline-flex shrink-0 items-center justify-center rounded-full bg-brand-navy font-semibold text-white {$size}"]) }} aria-hidden="true">{{ $initials }}</span>
