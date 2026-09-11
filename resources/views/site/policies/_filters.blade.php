{{-- Shared filter form for policies and obligations. $mode = 'policies' | 'obligations' --}}
@php($action = $mode === 'policies' ? route('policies.index') : route('obligations.index'))
<form action="{{ $action }}" method="get" data-autosubmit class="space-y-4" aria-label="Filters">
    <div>
        <label for="f-q" class="label">Keyword</label>
        <input id="f-q" type="search" name="q" value="{{ $filters['q'] ?? '' }}" class="input" placeholder="e.g. transparency, GPAI, biometric">
    </div>
    <div>
        <label for="f-jurisdiction" class="label">Jurisdiction</label>
        <select id="f-jurisdiction" name="jurisdiction" class="input">
            <option value="">All</option>
            @foreach($options['jurisdictions'] as $j)<option value="{{ $j->slug }}" @selected(($filters['jurisdiction'] ?? '') === $j->slug)>{{ $j->name }}</option>@endforeach
        </select>
    </div>
    @if($mode === 'policies')
    <div>
        <label for="f-region" class="label">Region</label>
        <select id="f-region" name="region" class="input"><option value="">All</option>@foreach($options['regions'] as $r)<option value="{{ $r }}" @selected(($filters['region'] ?? '') === $r)>{{ $r }}</option>@endforeach</select>
    </div>
    <div>
        <label for="f-status" class="label">Status</label>
        <select id="f-status" name="status" class="input"><option value="">All</option>@foreach($options['statuses'] as $s)<option value="{{ $s->value }}" @selected(($filters['status'] ?? '') === $s->value)>{{ $s->label() }}</option>@endforeach</select>
    </div>
    <div>
        <label for="f-type" class="label">Instrument type</label>
        <select id="f-type" name="type" class="input"><option value="">All</option>@foreach($options['types'] as $t)<option value="{{ $t->value }}" @selected(($filters['type'] ?? '') === $t->value)>{{ $t->label() }}</option>@endforeach</select>
    </div>
    <div>
        <label for="f-risk" class="label">Risk category</label>
        <select id="f-risk" name="risk" class="input"><option value="">All</option>@foreach($options['risks'] as $t)<option value="{{ $t->slug }}" @selected(($filters['risk'] ?? '') === $t->slug)>{{ $t->name }}</option>@endforeach</select>
    </div>
    @else
    <div>
        <label for="f-category" class="label">Obligation category</label>
        <select id="f-category" name="category" class="input"><option value="">All</option>@foreach($options['categories'] as $t)<option value="{{ $t->slug }}" @selected(($filters['category'] ?? '') === $t->slug)>{{ $t->name }}</option>@endforeach</select>
    </div>
    @endif
    <div>
        <label for="f-sector" class="label">Sector</label>
        <select id="f-sector" name="sector" class="input"><option value="">All</option>@foreach($options['sectors'] as $t)<option value="{{ $t->slug }}" @selected(($filters['sector'] ?? '') === $t->slug)>{{ $t->name }}</option>@endforeach</select>
    </div>
    <div>
        <label for="f-use-case" class="label">AI use case</label>
        <select id="f-use-case" name="use_case" class="input"><option value="">All</option>@foreach($options['use_cases'] as $t)<option value="{{ $t->slug }}" @selected(($filters['use_case'] ?? '') === $t->slug)>{{ $t->name }}</option>@endforeach</select>
    </div>
    <div>
        <label for="f-actor" class="label">Actor</label>
        <select id="f-actor" name="actor" class="input"><option value="">All</option>@foreach($options['actors'] as $t)<option value="{{ $t->slug }}" @selected(($filters['actor'] ?? '') === $t->slug)>{{ $t->name }}</option>@endforeach</select>
    </div>
    <div>
        <label for="f-binding" class="label">Binding</label>
        <select id="f-binding" name="binding" class="input"><option value="">Legal and voluntary</option><option value="yes" @selected(($filters['binding'] ?? '') === 'yes')>Legal requirements only</option><option value="no" @selected(($filters['binding'] ?? '') === 'no')>Voluntary guidance only</option></select>
    </div>
    @if($mode === 'policies')
    <div class="grid grid-cols-2 gap-2">
        <div><label for="f-from" class="label">Effective from</label><input id="f-from" type="date" name="from" value="{{ $filters['from'] ?? '' }}" class="input"></div>
        <div><label for="f-to" class="label">Effective to</label><input id="f-to" type="date" name="to" value="{{ $filters['to'] ?? '' }}" class="input"></div>
    </div>
    <div>
        <label for="f-sort" class="label">Sort</label>
        <select id="f-sort" name="sort" class="input">@foreach(\App\Services\PolicyData\PolicyCatalog::SORTS as $k => $v)<option value="{{ $k }}" @selected(($filters['sort'] ?? 'updated') === $k)>{{ $v }}</option>@endforeach</select>
    </div>
    @endif
    <div class="flex gap-2">
        <button type="submit" class="btn-primary flex-1" data-track="filter_apply">Apply filters</button>
        <a href="{{ $action }}" class="btn-secondary">Reset</a>
    </div>
</form>
