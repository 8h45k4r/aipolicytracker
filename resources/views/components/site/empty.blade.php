@props(['title' => 'No results', 'reset' => null])
{{-- Empty state. One definition, so a filtered list that finds nothing looks the same
     everywhere and always offers the reader a way back. --}}
<div class="empty-state">
    <p class="empty-mark" aria-hidden="true">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" class="h-5 w-5"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5" stroke-linecap="round"/></svg>
    </p>
    <p class="empty-title">{{ $title }}</p>
    <p class="empty-body">{{ $slot->isEmpty() ? 'Try fewer filters or a broader keyword. Only source-backed, published records are shown.' : $slot }}</p>
    @if($reset)<a href="{{ $reset }}" class="btn-secondary mt-4">Clear filters</a>@endif
</div>
