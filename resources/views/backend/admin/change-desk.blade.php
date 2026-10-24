@extends('backend.layouts.app', ['title' => 'Change desk'])
@section('content')
<x-backend.page-header title="Change desk" description="The weekly routine that keeps the record current: open each source that is due, record what you found, and log any change in data/changes.">
    <x-slot:actions>
        <a href="{{ route('freshness') }}" class="btn-secondary btn-sm" target="_blank" rel="noopener" data-command="Public freshness page">Public freshness page<span class="sr-only"> (opens in new tab)</span></a>
        <a href="{{ route('backend.review.index', ['type' => 'change']) }}" class="btn-secondary btn-sm" data-command="Changes in the review queue">Changes in the review queue</a>
    </x-slot:actions>
</x-backend.page-header>

<div class="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-4" data-changedesk-stats>
    <x-backend.stat label="Sources due a look" :value="$dueCount.' / '.$total" :tone="$dueCount > 0 ? 'text-state-warn' : 'text-state-good'" :hint="'Binding: every '.config('changedesk.cadence_days.binding').' days · other: every '.config('changedesk.cadence_days.other').' days'" />
    <x-backend.stat label="Checks logged this week" :value="$checkedThisWeek" />
    <x-backend.stat label="Median days to log a change" :value="$freshness['median'] === null ? '—' : rtrim(rtrim(number_format($freshness['median'], 1), '0'), '.')" :tone="$freshness['median'] !== null && $freshness['median'] > $targetDays ? 'text-state-bad' : null" :hint="'Target: '.$targetDays.' days or fewer · '.$freshness['count'].' changes since '.$since->format('j M Y')" />
    <x-backend.stat label="Logged within target" :value="$freshness['within_target'] === null ? '—' : $freshness['within_target'].'%'" />
</div>

<nav class="mt-6 flex flex-wrap gap-2" aria-label="Which sources">
    <a href="{{ route('backend.admin.changedesk.index') }}" class="btn-sm {{ $filter === 'due' ? 'btn-primary' : 'btn-secondary' }}" @if($filter === 'due') aria-current="page" @endif>Due now ({{ $dueCount }})</a>
    <a href="{{ route('backend.admin.changedesk.index', ['show' => 'all']) }}" class="btn-sm {{ $filter === 'all' ? 'btn-primary' : 'btn-secondary' }}" @if($filter === 'all') aria-current="page" @endif>All current instruments ({{ $total }})</a>
</nav>

@if($rows->isEmpty())
<div class="mt-4"><x-site.empty title="Nothing is due">Every current instrument's official source has been checked within its cadence.</x-site.empty></div>
@else
<form method="post" action="{{ route('backend.admin.changedesk.bulk') }}" id="bulk-check" class="adm-bulkbar sticky top-0 z-10 mt-4 rounded-md border border-brand-line bg-white p-3 text-sm shadow-sm">@csrf
    <div class="flex flex-wrap items-center gap-2">
        <span data-bulk-count="bulk-check" class="font-medium text-brand-navy">0 selected</span>
        <label for="bulk-outcome" class="sr-only">Outcome for selected</label>
        <select id="bulk-outcome" name="outcome" class="input !min-h-[30px] !w-auto !py-1 text-xs">
            @foreach(\App\Models\SourceCheck::OUTCOMES as $value => $label)<option value="{{ $value }}">{{ $label }}</option>@endforeach
        </select>
        <label for="bulk-note" class="sr-only">Note for selected</label>
        <input id="bulk-note" name="note" maxlength="500" class="input !min-h-[30px] !w-40 !py-1 text-xs" placeholder="Note (optional)">
        <button type="submit" class="btn-primary btn-sm" data-bulk-needs="bulk-check">Record for all selected</button>
    </div>
</form>
<div class="table-wrap mt-4" id="desk-table"><table><caption class="sr-only">Official sources, most urgent first</caption><thead><tr>
    <th scope="col" class="w-8"><label class="sr-only" for="desk-bulk-all">Select all on this page</label><input type="checkbox" id="desk-bulk-all" class="rounded border-brand-line" data-bulk-all="bulk-check"></th>
    <th scope="col">Instrument</th><th scope="col" class="hidden sm:table-cell">Watchers</th><th scope="col">Last checked</th><th scope="col">Record what you found</th>
</tr></thead><tbody>
@foreach($rows as $r)
@php($p = $r['policy'])
<tr data-desk-row="{{ $p->slug }}">
    <td class="w-8"><input type="checkbox" class="rounded border-brand-line" value="{{ $p->slug }}" data-bulk-item form="bulk-check" name="slugs[]"></td>
    <td class="min-w-[16rem] max-w-[26rem]">
        <a href="{{ $p->url() }}" class="font-medium text-brand-navy" target="_blank" rel="noopener">{{ $p->short_title ?: $p->title }}<span class="sr-only"> (opens in new tab)</span></a>
        <div class="meta">{{ $p->jurisdiction?->short_name ?: $p->jurisdiction?->name }} · {{ $p->is_binding ? 'binding' : 'non-binding' }} · {{ $p->obligations_count }} {{ \Illuminate\Support\Str::plural('duty', $p->obligations_count) }}@if($p->aipt_id) · <span class="font-mono">{{ $p->aipt_id }}</span>@endif</div>
        @if($p->official_source_url)<a href="{{ $p->official_source_url }}" class="text-xs" target="_blank" rel="noopener">Open the official source <span aria-hidden="true">↗</span><span class="sr-only"> (opens in new tab)</span></a>@endif
    </td>
    {{-- Hidden on phones: the least needed column, and dropping it lets the row fit without a sideways scroll. --}}
    <td class="hidden tabular-nums sm:table-cell">{{ $r['watchers'] }}</td>
    <td class="whitespace-nowrap text-xs">
        @if($r['last_at'])
            {{ $r['last_at']->format('j M Y') }}<div class="meta">{{ $r['last_outcome'] ? (\App\Models\SourceCheck::OUTCOMES[$r['last_outcome']] ?? $r['last_outcome']) : 'Checked in the data files' }}</div>
            @if($r['due'])<x-backend.badge status="needs_update">due</x-backend.badge>@endif
        @else
            <x-backend.badge status="needs_update">never checked</x-backend.badge>
        @endif
    </td>
    <td class="min-w-[18rem]">
        <form method="post" action="{{ route('backend.admin.changedesk.store') }}" class="flex flex-wrap items-center gap-2">@csrf
            <input type="hidden" name="policy" value="{{ $p->slug }}">
            <label class="sr-only" for="outcome-{{ $p->id }}">Outcome for {{ $p->short_title ?: $p->title }}</label>
            <select id="outcome-{{ $p->id }}" name="outcome" class="input !min-h-[30px] !w-auto !py-1 text-xs">
                @foreach(\App\Models\SourceCheck::OUTCOMES as $value => $label)<option value="{{ $value }}">{{ $label }}</option>@endforeach
            </select>
            <label class="sr-only" for="note-{{ $p->id }}">Note for {{ $p->short_title ?: $p->title }}</label>
            <input id="note-{{ $p->id }}" name="note" maxlength="500" class="input !min-h-[30px] !w-40 !py-1 text-xs" placeholder="Note (optional)">
            <button class="btn-secondary btn-sm" aria-label="Record check for {{ $p->short_title ?: $p->title }}">Record</button>
        </form>
    </td>
</tr>
@endforeach
</tbody></table></div>
@if($rows->hasPages())<div class="mt-4">{{ $rows->links() }}</div>@endif
@endif

<div class="mt-8 grid gap-6 lg:grid-cols-2">
    <section class="card-flat p-5" aria-labelledby="deepen-title">
        <h2 id="deepen-title" class="section-title !text-lg">Deepen next</h2>
        <p class="meta mt-1">Watched instruments with fewer than five recorded duties. Depth goes where readers already are.</p>
        @if($deepen->isEmpty())
        <p class="mt-3 text-sm text-brand-muted">No watched instrument is short of duties yet. This fills in as accounts follow records.</p>
        @else
        <ul class="mt-3 divide-y divide-brand-line text-sm">@foreach($deepen as $d)<li class="flex items-baseline justify-between gap-4 py-2"><a href="{{ $d['policy']->url() }}">{{ $d['policy']->short_title ?: $d['policy']->title }}</a><span class="meta whitespace-nowrap">{{ $d['watchers'] }} watching · {{ $d['obligations'] }} duties</span></li>@endforeach</ul>
        @endif
    </section>
    <section class="card-flat p-5" aria-labelledby="recent-title">
        <h2 id="recent-title" class="section-title !text-lg">Latest checks</h2>
        @if($recent->isEmpty())
        <p class="mt-3 text-sm text-brand-muted">No checks recorded yet. Start with the binding instruments at the top of the list.</p>
        @else
        <ul class="mt-3 divide-y divide-brand-line text-sm">@foreach($recent as $c)<li class="py-2"><span class="font-medium">{{ $titles[$c->record_slug] ?? $c->record_slug }}</span> · {{ mb_strtolower(\App\Models\SourceCheck::OUTCOMES[$c->outcome] ?? $c->outcome) }}<div class="meta">{{ $c->checked_at->format('j M Y H:i') }}@if($c->user) · {{ $c->user->name }}@endif @if($c->note)· {{ $c->note }}@endif</div></li>@endforeach</ul>
        @endif
    </section>
</div>
@endsection
