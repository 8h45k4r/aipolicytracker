@props(['type', 'slug', 'title', 'url', 'meta' => null, 'label' => 'Save'])
{{-- Adds the record to a per-browser reading list (localStorage). Needs JavaScript; renders inert without it. --}}
<button type="button" {{ $attributes->merge(['class' => 'btn-secondary']) }} data-save="{{ $type }}:{{ $slug }}" data-save-type="{{ $type }}" data-save-title="{{ $title }}" data-save-url="{{ $url }}" data-save-meta="{{ $meta }}" data-save-label="{{ $label }}" aria-pressed="false" title="Save to your reading list (stored in this browser)">{{ $label }}</button>
