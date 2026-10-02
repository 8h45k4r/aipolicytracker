@props(['type', 'slug', 'title', 'url', 'meta' => null, 'label' => 'Save', 'primary' => false])
{{-- Adds the record to a per-browser reading list (localStorage). Needs JavaScript; renders inert without it. --}}
<button type="button" {{ $attributes->merge(['class' => $primary ? 'btn-primary' : 'btn-secondary']) }} data-save="{{ $type }}:{{ $slug }}" data-save-type="{{ $type }}" data-save-title="{{ $title }}" data-save-meta="{{ $meta }}" data-save-label="{{ $label }}" aria-pressed="false" title="Save to your reading list (stored in this browser)">{{ $label }}</button>
