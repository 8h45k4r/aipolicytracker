@props(['title' => 'No results', 'reset' => null])
<div class="rounded-sm border border-dashed border-brand-line p-8 text-center">
    <p class="font-medium text-brand-navy">{{ $title }}</p>
    <p class="mt-1 text-sm text-brand-muted">{{ $slot->isEmpty() ? 'Try fewer filters or a broader keyword. Only source-backed, published records are shown.' : $slot }}</p>
    @if($reset)<a href="{{ $reset }}" class="btn-secondary mt-4">Clear filters</a>@endif
</div>
