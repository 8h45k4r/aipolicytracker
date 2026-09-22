@props(['label', 'value', 'href' => null, 'note' => null])
{{-- One figure with its label. Used wherever a page states a count, so every count on the site is set the same way. --}}
<div {{ $attributes->merge(['class' => 'stat']) }}>
    <dt class="stat-label">{{ $label }}</dt>
    <dd class="stat-value">@if($href)<a href="{{ $href }}" class="no-underline text-brand-navy hover:text-brand-blue">{{ $value }}</a>@else{{ $value }}@endif</dd>
    @if($note)<dd class="stat-note">{{ $note }}</dd>@endif
</div>
