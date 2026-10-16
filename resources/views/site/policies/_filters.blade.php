{{-- Shared filter form for policies and obligations. $mode = 'policies' | 'obligations' --}}
@php($inSheet = ($idp ?? 'f-') === 'm-f-')
@php($action = $mode === 'policies' ? route('policies.index') : route('obligations.index'))
{{-- Each option carries how many results it would give with the other filters as they are. --}}
@php($n = fn (string $facet, $value) => isset($facets[$facet]) ? ' ('.number_format($facets[$facet][(string) $value] ?? 0).')' : '')
<form action="{{ $action }}" method="get" data-autosubmit class="space-y-4" aria-label="Filters">
    <div>
        <label for="{{ $idp ?? 'f-' }}q" class="label">Keyword</label>
        <input id="{{ $idp ?? 'f-' }}q" type="search" name="q" value="{{ $filters['q'] ?? '' }}" class="input" placeholder="e.g. transparency, GPAI, biometric">
    </div>
    <div>
        <label for="{{ $idp ?? 'f-' }}jurisdiction" class="label">Jurisdiction</label>
        <select id="{{ $idp ?? 'f-' }}jurisdiction" name="jurisdiction" class="input">
            <option value="">All</option>
            @foreach($options['jurisdictions'] as $j)<option value="{{ $j->slug }}" @selected(($filters['jurisdiction'] ?? '') === $j->slug)>{{ $j->name }}{{ $n('jurisdiction', $j->slug) }}</option>@endforeach
        </select>
    </div>
    @if($mode === 'policies')
    <div>
        <label for="{{ $idp ?? 'f-' }}region" class="label">Region</label>
        <select id="{{ $idp ?? 'f-' }}region" name="region" class="input"><option value="">All</option>@foreach($options['regions'] as $r)<option value="{{ $r }}" @selected(($filters['region'] ?? '') === $r)>{{ $r }}{{ $n('region', $r) }}</option>@endforeach</select>
    </div>
    <div>
        <label for="{{ $idp ?? 'f-' }}status" class="label">Status</label>
        <select id="{{ $idp ?? 'f-' }}status" name="status" class="input"><option value="">All</option>@foreach($options['statuses'] as $s)<option value="{{ $s->value }}" @selected(($filters['status'] ?? '') === $s->value)>{{ $s->label() }}{{ $n('status', $s->value) }}</option>@endforeach</select>
    </div>
    <div>
        <label for="{{ $idp ?? 'f-' }}type" class="label">Instrument type</label>
        <select id="{{ $idp ?? 'f-' }}type" name="type" class="input"><option value="">All</option>@foreach($options['types'] as $t)<option value="{{ $t->value }}" @selected(($filters['type'] ?? '') === $t->value)>{{ $t->label() }}{{ $n('type', $t->value) }}</option>@endforeach</select>
    </div>
    <div>
        <label for="{{ $idp ?? 'f-' }}risk" class="label">Risk category</label>
        <select id="{{ $idp ?? 'f-' }}risk" name="risk" class="input"><option value="">All</option>@foreach($options['risks'] as $t)<option value="{{ $t->slug }}" @selected(($filters['risk'] ?? '') === $t->slug)>{{ $t->name }}{{ $n('risk', $t->slug) }}</option>@endforeach</select>
    </div>
    @else
    <div>
        <label for="{{ $idp ?? 'f-' }}category" class="label">Obligation category</label>
        <select id="{{ $idp ?? 'f-' }}category" name="category" class="input"><option value="">All</option>@foreach($options['categories'] as $t)<option value="{{ $t->slug }}" @selected(($filters['category'] ?? '') === $t->slug)>{{ $t->name }}{{ $n('category', $t->slug) }}</option>@endforeach</select>
    </div>
    @endif
    <div>
        <label for="{{ $idp ?? 'f-' }}sector" class="label">Sector</label>
        <select id="{{ $idp ?? 'f-' }}sector" name="sector" class="input"><option value="">All</option>@foreach($options['sectors'] as $t)<option value="{{ $t->slug }}" @selected(($filters['sector'] ?? '') === $t->slug)>{{ $t->name }}{{ $n('sector', $t->slug) }}</option>@endforeach</select>
    </div>
    <div>
        <label for="{{ $idp ?? 'f-' }}use-case" class="label">AI use case</label>
        <select id="{{ $idp ?? 'f-' }}use-case" name="use_case" class="input"><option value="">All</option>@foreach($options['use_cases'] as $t)<option value="{{ $t->slug }}" @selected(($filters['use_case'] ?? '') === $t->slug)>{{ $t->name }}{{ $n('use_case', $t->slug) }}</option>@endforeach</select>
    </div>
    <div>
        <label for="{{ $idp ?? 'f-' }}actor" class="label">Actor</label>
        <select id="{{ $idp ?? 'f-' }}actor" name="actor" class="input"><option value="">All</option>@foreach($options['actors'] as $t)<option value="{{ $t->slug }}" @selected(($filters['actor'] ?? '') === $t->slug)>{{ $t->name }}{{ $n('actor', $t->slug) }}</option>@endforeach</select>
    </div>
    <div>
        <label for="{{ $idp ?? 'f-' }}binding" class="label">Binding</label>
        <select id="{{ $idp ?? 'f-' }}binding" name="binding" class="input"><option value="">Legal and voluntary</option><option value="yes" @selected(($filters['binding'] ?? '') === 'yes')>Legal requirements only{{ $n('binding', 'yes') }}</option><option value="no" @selected(($filters['binding'] ?? '') === 'no')>Voluntary guidance only{{ $n('binding', 'no') }}</option></select>
    </div>
    @if($mode === 'policies')
    {{-- Stacked in the narrow desktop sidebar, where two date inputs side by side truncate the date. --}}
    <div class="grid gap-2 {{ $inSheet ? 'grid-cols-2' : 'grid-cols-1' }}">
        <div><label for="{{ $idp ?? 'f-' }}from" class="label">Effective from</label><input id="{{ $idp ?? 'f-' }}from" type="date" name="from" value="{{ $filters['from'] ?? '' }}" class="input"></div>
        <div><label for="{{ $idp ?? 'f-' }}to" class="label">Effective to</label><input id="{{ $idp ?? 'f-' }}to" type="date" name="to" value="{{ $filters['to'] ?? '' }}" class="input"></div>
    </div>
    @endif
    {{-- Each listing has its own orders; the explorer's sort is the same control as before. --}}
    @php($sortOptions = $mode === 'policies' ? \App\Services\PolicyData\PolicyCatalog::SORTS : \App\Services\PolicyData\PolicyCatalog::OBLIGATION_SORTS)
    @php($sortDefault = $mode === 'policies' ? \App\Services\PolicyData\PolicyCatalog::SITE_DEFAULT_SORT : 'binding')
    <div>
        <label for="{{ $idp ?? 'f-' }}sort" class="label">Sort</label>
        <select id="{{ $idp ?? 'f-' }}sort" name="sort" class="input">@foreach($sortOptions as $k => $v)<option value="{{ $k }}" @selected(($filters['sort'] ?? $sortDefault) === $k)>{{ $v }}</option>@endforeach</select>
    </div>
    {{-- In the phone sheet the buttons stay pinned to its bottom, so Apply is reachable from any filter. --}}
    <div class="flex gap-2 {{ $inSheet ? 'sticky bottom-0 -mx-4 border-t border-brand-line bg-white px-4 py-3' : '' }}">
        <button type="submit" class="btn-primary flex-1" data-track="filter_apply">Apply filters</button>
        <a href="{{ $action }}" class="btn-secondary">Reset</a>
    </div>
</form>
