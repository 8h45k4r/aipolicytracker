{{-- Responsive listing shell: desktop sidebar filters, mobile bottom-sheet filters, sticky bottom bar. --}}
<div class="container-site py-8">
    <x-site.breadcrumbs :items="$seo->breadcrumbs" />
    <h1 class="mt-2 text-2xl sm:text-3xl font-semibold tracking-tight text-brand-navy">{{ $heading }}</h1>
    <p class="mt-2 max-w-3xl text-sm sm:text-base text-brand-body">{{ $intro }}</p>

    <div class="mt-6 grid gap-8 lg:grid-cols-4">
        <aside class="hidden lg:block" aria-label="Filter sidebar">
            <div class="sticky top-4 card-flat p-4">@include('site.policies._filters')</div>
        </aside>
        <div class="lg:col-span-3">
            {{-- Each result card is an h3; this keeps the outline h1 → h2 → h3 (axe heading-order). --}}
            <h2 class="sr-only">Results</h2>
            @php($filtered = $chips !== [] && $total !== null)
            @php($noun = \Illuminate\Support\Str::plural($mode === 'policies' ? 'instrument' : 'obligation', $filtered ? $total : $paginator->total()))
            <p class="text-sm text-brand-muted" role="status">
                <span class="font-medium text-brand-navy">{{ number_format($paginator->total()) }}</span>
                {{ $filtered ? 'of '.number_format($total).' '.$noun.' match' : $noun }}
                @if($paginator->lastPage() > 1) · page {{ $paginator->currentPage() }} of {{ $paginator->lastPage() }} @endif
            </p>
            @if($chips !== [])
            <ul class="mt-2 flex flex-wrap items-center gap-2" aria-label="Active filters" data-active-filters>
                @foreach($chips as $chip)
                <li><a class="chip chip-active gap-1.5" href="{{ $chip['remove'] }}" aria-label="Remove filter {{ $chip['label'] }}: {{ strip_tags($chip['value']) }}" data-track="filter_chip_remove" data-track-label="{{ $chip['key'] }}"><span class="font-normal opacity-80">{{ $chip['label'] }}:</span> {{ $chip['value'] }} <span aria-hidden="true">×</span></a></li>
                @endforeach
                @if(count($chips) > 1)<li><a href="{{ $mode === 'policies' ? route('policies.index') : route('obligations.index') }}" class="text-sm">Clear all</a></li>@endif
            </ul>
            @endif
            <div class="mt-2 divide-y divide-brand-line border-y border-brand-line">
                {{ $slot }}
            </div>
            <div class="mt-6">{{ $paginator->links() }}</div>
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
    <button type="button" class="btn-primary flex-1" data-open-filters>Filters @if($chips !== []) <span class="rounded-sm bg-white/20 px-1.5 text-xs">{{ count($chips) }}</span>@endif</button>
    <button type="button" class="btn-secondary" data-copy-link>Share</button>
</div>
