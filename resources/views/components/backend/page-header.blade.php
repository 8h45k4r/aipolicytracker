@props(['title', 'description' => null])
{{-- The top of every admin page: what it is, one line on what it is for, and its actions. --}}
<div class="flex flex-wrap items-end justify-between gap-4">
    <div class="min-w-0">
        <h1 class="text-2xl font-semibold tracking-tight text-brand-navy">{{ $title }}</h1>
        @if($description)<p class="mt-1 max-w-3xl text-sm text-brand-muted">{{ $description }}</p>@endif
    </div>
    @isset($actions)<div class="flex flex-wrap items-center gap-2 text-sm">{{ $actions }}</div>@endisset
</div>
