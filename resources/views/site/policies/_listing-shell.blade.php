{{-- Responsive listing shell: desktop sidebar filters, mobile bottom-sheet filters, sticky bottom bar. --}}
<div class="container-site py-8">
    <x-site.breadcrumbs :items="$seo->breadcrumbs" />
    <h1 class="mt-2 text-2xl sm:text-3xl font-semibold tracking-tight text-brand-navy">{{ $heading }}</h1>
    <p class="mt-2 max-w-3xl text-sm sm:text-base text-brand-body">{{ $intro }}</p>

    @if(!empty(array_filter($filters)))
    <div class="mt-4 flex flex-wrap gap-2" aria-label="Active filters">
        @foreach($filters as $k => $v)
            @if($k !== 'sort')
            <a class="chip chip-active" href="{{ request()->fullUrlWithoutQuery([$k, 'page']) }}" aria-label="Remove filter {{ $k }}">{{ str_replace('_', ' ', $k) }}: {{ $v }} ×</a>
            @endif
        @endforeach
    </div>
    @endif

    <div class="mt-6 grid gap-8 lg:grid-cols-4">
        <aside class="hidden lg:block" aria-label="Filter sidebar">
            <div class="sticky top-4 card-flat p-4">@include('site.policies._filters')</div>
        </aside>
        <div class="lg:col-span-3">
            <p class="text-sm text-brand-muted" role="status">{{ $paginator->total() }} {{ \Illuminate\Support\Str::plural('result', $paginator->total()) }}@if($paginator->lastPage() > 1) · page {{ $paginator->currentPage() }} of {{ $paginator->lastPage() }}@endif</p>
            <div class="mt-2 divide-y divide-brand-line border-y border-brand-line">
                {{ $slot }}
            </div>
            <nav class="mt-6" aria-label="Pagination">{{ $paginator->links() }}</nav>
        </div>
    </div>
</div>

{{-- Mobile bottom sheet --}}
<div id="filter-sheet" data-open="false" class="lg:hidden fixed inset-0 z-50 data-[open=false]:hidden" role="dialog" aria-modal="true" aria-label="Filters">
    <div class="absolute inset-0 bg-brand-navy/40" data-close-filters></div>
    <div class="absolute inset-x-0 bottom-0 max-h-[85vh] overflow-y-auto rounded-t-2xl bg-white p-4 shadow-xl">
        <div class="flex items-center justify-between"><p class="font-semibold text-brand-navy">Filters</p><button type="button" class="btn-secondary !min-h-[40px]" data-close-filters>Close</button></div>
        <div class="mt-3">@include('site.policies._filters')</div>
    </div>
</div>
<div class="lg:hidden sticky bottom-0 z-40 border-t border-brand-line bg-white/95 backdrop-blur px-4 py-2 flex gap-2">
    <a href="#f-q" class="btn-secondary flex-1" data-open-filters>Search</a>
    <button type="button" class="btn-primary flex-1" data-open-filters>Filters</button>
    <button type="button" class="btn-secondary" data-copy-link>Share</button>
</div>
