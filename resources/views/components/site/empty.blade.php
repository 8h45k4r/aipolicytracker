@props(['title' => 'No results', 'reset' => null])
<div class="rounded-lg border border-dashed border-slate-300 p-8 text-center">
    <p class="font-medium text-slate-900">{{ $title }}</p>
    <p class="mt-1 text-sm text-slate-600">{{ $slot->isEmpty() ? 'Try fewer filters or a broader keyword. Only source-backed, published records are shown.' : $slot }}</p>
    @if($reset)<a href="{{ $reset }}" class="btn-secondary mt-4">Clear filters</a>@endif
</div>
