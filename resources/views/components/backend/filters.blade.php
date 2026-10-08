@props(['action', 'filters', 'export' => null, 'placeholder' => 'Search…', 'dates' => true, 'total' => null, 'noun' => 'row', 'keepSort' => true])
{{-- One filter bar for every admin list: search, the page's own selects (the slot), an
     optional date range, Apply and Clear, and an export of exactly what is filtered. It is
     a GET form, so a filtered view is a URL that can be bookmarked or shared. --}}
<form method="get" action="{{ $action }}" role="search" class="adm-toolbar" data-admin-filters>
    <div class="min-w-[14rem] flex-1">
        <label for="f-q" class="adm-label">Search</label>
        <input id="f-q" type="search" name="q" value="{{ $filters->q }}" class="input !min-h-[38px] !py-1.5" placeholder="{{ $placeholder }}" autocomplete="off">
    </div>
    {{ $slot }}
    @if($dates)
    <div><label for="f-from" class="adm-label">From</label><input id="f-from" type="date" name="from" value="{{ $filters->from?->toDateString() }}" class="input !min-h-[38px] !py-1.5 !w-auto"></div>
    <div><label for="f-to" class="adm-label">To</label><input id="f-to" type="date" name="to" value="{{ $filters->to?->toDateString() }}" class="input !min-h-[38px] !py-1.5 !w-auto"></div>
    @endif
    @if($keepSort && request('sort'))<input type="hidden" name="sort" value="{{ request('sort') }}"><input type="hidden" name="dir" value="{{ request('dir') }}">@endif
    <div class="flex items-end gap-2">
        <button type="submit" class="btn-primary">Apply</button>
        @if(collect(request()->except(['page', 'sort', 'dir']))->filter(fn ($v) => filled($v))->isNotEmpty())<a href="{{ $action }}" class="btn-secondary">Clear</a>@endif
    </div>
    <div class="ml-auto flex items-end gap-3">
        @if($total !== null)<p class="pb-2 text-sm text-brand-muted" role="status"><span class="font-semibold text-brand-navy">{{ number_format($total) }}</span> {{ \Illuminate\Support\Str::plural($noun, $total) }}</p>@endif
        @if($export)<a href="{{ $export.(str_contains($export, '?') ? '&' : '?').http_build_query(request()->except(['page'])) }}" class="btn-secondary" data-track="admin_export">Export CSV</a>@endif
    </div>
</form>
