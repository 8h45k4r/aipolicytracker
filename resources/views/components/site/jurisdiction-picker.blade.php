@props([
    'name' => 'jurisdictions',
    'jurisdictions',
    'selected' => [],
    'max' => null,
    'legend' => 'Markets or jurisdictions',
    'hint' => null,
    'required' => false,
    // Compact: quick picks for the most-recorded jurisdictions, every region
    // collapsed, a shorter list, and the hint kept to one line.
    'compact' => false,
])
{{--
    Picking jurisdictions out of 212 checkboxes in one flat list is the worst
    interaction on the site. This groups them by region behind collapsed
    headings, marks which ones actually hold records, and — with JavaScript —
    adds a type-ahead and a row of removable chips for what is already chosen.

    It degrades honestly. Without JavaScript every checkbox is still present and
    submits normally; the search box and the chips are rendered hidden and are
    revealed by the script, so nothing on screen is inert.
--}}
@php
    $selected = array_values(array_filter((array) $selected));
    $groups = $jurisdictions->groupBy(fn ($j) => $j->region ?: 'Other')->sortKeys();
    $chosen = $jurisdictions->whereIn('slug', $selected)->sortBy('name')->values();
    $withRecords = $jurisdictions->filter(fn ($j) => ($j->policy_instruments_count ?? 0) > 0)->count();
    $quick = $compact ? $jurisdictions->sortByDesc(fn ($j) => [(int) ($j->policy_instruments_count ?? 0), $j->featured ?? false])->take(10)->sortBy('name')->values() : collect();
@endphp
<fieldset {{ $attributes->merge(['class' => 'min-w-0']) }} data-jurisdiction-picker @if($max) data-max="{{ $max }}" @endif>
    <legend class="label">{{ $legend }} @if($required)<span class="text-state-bad" aria-hidden="true">*</span>@endif</legend>
    <p class="mt-0.5 text-xs text-brand-muted">{{ $hint ?? $jurisdictions->count().' recorded, '.$withRecords.' with at least one instrument.' }}</p>

    {{-- Live summary of the current selection. Revealed by script; the counter itself
         is announced politely so a screen reader hears the count change. --}}
    <div class="mt-2" data-picker-summary hidden>
        <p class="text-sm text-brand-body"><span data-picker-count aria-live="polite">{{ count($selected) }}</span> selected @if($max)<span class="text-brand-muted">(up to {{ $max }})</span>@endif</p>
        <ul class="mt-1.5 flex flex-wrap gap-1.5" data-picker-chips>
            @foreach($chosen as $j)
            <li><button type="button" class="chip !min-h-0 !py-1 inline-flex items-center gap-1" data-picker-remove="{{ $j->slug }}">{{ $j->short_name ?: $j->name }}<span aria-hidden="true">&times;</span><span class="sr-only">Remove {{ $j->name }}</span></button></li>
            @endforeach
        </ul>
        <p class="mt-1 text-xs text-state-bad" data-picker-limit hidden>Only the first {{ $max }} are compared.</p>
    </div>

    @if($quick->isNotEmpty())
    <div class="mt-2 flex flex-wrap gap-1.5" aria-label="Most recorded jurisdictions" data-picker-quick>
        @foreach($quick as $j)
        <button type="button" class="chip !min-h-0 !py-1 {{ in_array($j->slug, $selected, true) ? 'chip-active' : '' }}" data-picker-quick-pick="{{ $j->slug }}" aria-pressed="{{ in_array($j->slug, $selected, true) ? 'true' : 'false' }}">{{ $j->short_name ?: $j->name }}</button>
        @endforeach
    </div>
    @endif

    <div class="mt-3" data-picker-search-wrap hidden>
        <label for="{{ $name }}-search" class="sr-only">Filter the list of jurisdictions</label>
        <input id="{{ $name }}-search" type="search" class="input !min-h-[40px]" placeholder="Type to filter, e.g. Brazil or Europe" autocomplete="off" data-picker-search>
        <p class="mt-1 text-xs text-brand-muted" data-picker-empty hidden>No jurisdiction matches that.</p>
    </div>

    <div class="mt-2 {{ $compact ? 'max-h-[13rem]' : 'max-h-[22rem]' }} overflow-y-auto rounded-sm border border-brand-line divide-y divide-brand-line">
        @foreach($groups as $region => $list)
        @php($inRegion = $list->whereIn('slug', $selected)->count())
        <details data-picker-group @if($inRegion || (! $compact && $loop->first)) open @endif>
            <summary class="flex cursor-pointer list-none items-center justify-between gap-2 px-3 py-2 text-sm font-medium text-brand-navy hover:bg-brand-paper">
                <span>{{ $region }} <span class="font-normal text-brand-muted">({{ $list->count() }})</span></span>
                <span class="text-xs text-brand-muted" data-picker-group-count>{{ $inRegion ? $inRegion.' selected' : '' }}</span>
            </summary>
            <div class="px-3 pb-2">
                @foreach($list->sortBy('name') as $j)
                <label class="flex min-h-[36px] items-center gap-2 rounded-sm px-1 text-sm text-brand-body hover:bg-brand-paper" data-picker-option data-picker-label="{{ Str::lower($j->name.' '.$j->short_name.' '.$region) }}">
                    <input type="checkbox" name="{{ $name }}[]" value="{{ $j->slug }}" class="rounded border-brand-line text-brand-blue focus:ring-brand-cyan" @checked(in_array($j->slug, $selected, true))>
                    <span class="min-w-0 flex-1 truncate">{{ $j->name }}</span>
                    @if(($j->policy_instruments_count ?? 0) > 0)
                        <span class="shrink-0 text-xs text-brand-muted" title="{{ $j->policy_instruments_count }} recorded {{ Str::plural('instrument', $j->policy_instruments_count) }}">{{ $j->policy_instruments_count }}</span>
                    @else
                        <span class="shrink-0 text-xs text-brand-muted" title="No AI-specific instrument recorded yet">—</span>
                    @endif
                </label>
                @endforeach
            </div>
        </details>
        @endforeach
    </div>
</fieldset>
