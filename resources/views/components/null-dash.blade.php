@props(['value' => null, 'format' => null])
@if(!is_null($value) && $value !== '')@if($format && $value instanceof \DateTimeInterface){{ $value->format($format) }}@else{{ $value }}@endif@else<span class="text-brand-muted">—</span>@endif
