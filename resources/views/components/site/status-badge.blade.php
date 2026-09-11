@props(['status'])
@php($s = $status instanceof \App\Enums\PolicyStatus ? $status : (\App\Enums\PolicyStatus::tryFrom((string) $status) ?? \App\Enums\PolicyStatus::Archived))
<span {{ $attributes->merge(['class' => 'badge '.$s->badgeClass()]) }} title="{{ $s->definition() }}">{{ $s->label() }}</span>
