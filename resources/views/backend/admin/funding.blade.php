@extends('backend.layouts.app', ['title' => 'Funding'])
@section('content')
<x-backend.page-header title="Funding" description="Funders shown on the public funding page. Only published entries are listed there.">
    <x-slot:actions><a href="{{ route('funding') }}" class="btn-secondary !min-h-[38px] !py-1.5" target="_blank" rel="noopener">View /funding</a><a href="{{ route('backend.admin.settings') }}#citation-funding" class="btn-secondary !min-h-[38px] !py-1.5">Threshold and sponsor link</a></x-slot:actions>
</x-backend.page-header>

<div class="mt-4 rounded-sm border border-brand-line bg-white px-4 py-3 text-sm text-brand-body" data-funding-threshold>
    <p><strong class="text-brand-navy">Disclosure threshold: {{ '$'.number_format($threshold) }} a year.</strong> Every funder giving more than this in a year must be listed and published here, with the amount and what it pays for. Add the entry when the money is agreed, not when it arrives.</p>
    <p class="mt-1 meta">Write the amount as agreed. Do not estimate or round it. The threshold and the sponsor link are set on the settings page.@if($sponsorUrl) Sponsor link: <a href="{{ $sponsorUrl }}" rel="noopener">{{ $sponsorUrl }}</a>.@endif</p>
    @if($usingConfig)<p class="mt-1 text-state-warn">This table is empty, so /funding still shows the list in config/funding.php. Once you add an entry here, only this table is used.</p>@endif
</div>

@if($funders->isEmpty())
<div class="mt-6"><x-site.empty title="No funders yet">/funding says there are none. Add a funder below once the money is agreed.</x-site.empty></div>
@else
<div class="table-wrap mt-6"><table><caption class="sr-only">Funders</caption><thead><tr>
    <th scope="col">Order</th><th scope="col">Funder</th><th scope="col">Kind</th><th scope="col">Amount</th><th scope="col">Dates</th><th scope="col">Status</th><th scope="col"><span class="sr-only">Actions</span></th>
</tr></thead><tbody>
@foreach($funders as $f)
<tr>
    <td class="whitespace-nowrap">
        <form method="post" action="{{ route('backend.admin.funding.move', $f) }}" class="inline">@csrf<input type="hidden" name="action" value="up"><button class="btn-secondary !min-h-0 !py-0.5 !px-1.5 text-xs" @disabled($loop->first) aria-label="Move {{ $f->name }} up">↑</button></form>
        <form method="post" action="{{ route('backend.admin.funding.move', $f) }}" class="inline">@csrf<input type="hidden" name="action" value="down"><button class="btn-secondary !min-h-0 !py-0.5 !px-1.5 text-xs" @disabled($loop->last) aria-label="Move {{ $f->name }} down">↓</button></form>
    </td>
    <td class="min-w-[12rem] max-w-[20rem]"><span class="font-medium text-brand-navy">{{ $f->name }}</span>@if($f->url)<div class="meta truncate"><a href="{{ $f->url }}" rel="noopener" target="_blank">{{ $f->url }}</a></div>@endif<div class="meta">{{ \Illuminate\Support\Str::limit($f->purpose, 120) }}</div></td>
    <td>{{ $f->kindLabel() }}</td>
    <td class="whitespace-nowrap">{{ $f->amount_display }}@if($f->period)<div class="meta">{{ $f->period }}</div>@endif</td>
    <td class="whitespace-nowrap text-xs">{{ $f->starts_on?->format('Y-m-d') ?? '—' }} to {{ $f->ends_on?->format('Y-m-d') ?? '—' }}</td>
    <td>@if($f->published)<x-backend.badge status="published" />@else<x-backend.badge status="draft">hidden</x-backend.badge>@endif</td>
    <td class="whitespace-nowrap text-right">
        <form method="post" action="{{ route('backend.admin.funding.publish', $f) }}" class="inline">@csrf<input type="hidden" name="state" value="{{ $f->published ? 'off' : 'on' }}"><button class="btn-secondary !min-h-0 !py-1 !px-2 text-xs">{{ $f->published ? 'Hide' : 'Publish' }}</button></form>
        <a href="{{ route('backend.admin.funding.index', ['edit' => $f->id]) }}#funder-form" class="btn-secondary !min-h-0 !py-1 !px-2 text-xs">Edit</a>
        <form method="post" action="{{ route('backend.admin.funding.destroy', $f) }}" class="inline" data-confirm="Delete {{ $f->name }}? It disappears from /funding at once. Hide it instead to keep the record.">@csrf @method('DELETE')<button class="btn-secondary !min-h-0 !py-1 !px-2 text-xs text-state-bad">Delete</button></form>
    </td>
</tr>
@endforeach
</tbody></table></div>
@endif

@php($f = $editing ?? new \App\Models\Funder(['kind' => 'grant']))
<form method="post" action="{{ $editing ? route('backend.admin.funding.update', $editing) : route('backend.admin.funding.store') }}" id="funder-form" class="mt-6 card-flat p-5 space-y-4">@csrf @if($editing) @method('PUT') @endif
    <h2 class="section-title !text-lg">{{ $editing ? 'Edit '.$editing->name : 'Add a funder' }}</h2>
    <div class="grid gap-4 sm:grid-cols-2">
        <div><label for="fu-name" class="label">Name</label><input id="fu-name" name="name" class="input" required maxlength="160" value="{{ old('name', $f->name) }}">@error('name')<p class="mt-1 text-xs text-state-bad">{{ $message }}</p>@enderror</div>
        <div><label for="fu-kind" class="label">Kind</label><select id="fu-kind" name="kind" class="input">@foreach(\App\Models\Funder::KINDS as $k => $l)<option value="{{ $k }}" @selected(old('kind', $f->kind) === $k)>{{ $l }}</option>@endforeach</select></div>
        <div><label for="fu-amount" class="label">Amount</label><input id="fu-amount" name="amount_display" class="input" required maxlength="64" value="{{ old('amount_display', $f->amount_display) }}" placeholder="As agreed, with currency"><p class="meta mt-1">Free text, shown as written.</p>@error('amount_display')<p class="mt-1 text-xs text-state-bad">{{ $message }}</p>@enderror</div>
        <div><label for="fu-period" class="label">Period</label><input id="fu-period" name="period" class="input" maxlength="64" value="{{ old('period', $f->period) }}" placeholder="e.g. a year, 2027"></div>
        <div class="sm:col-span-2"><label for="fu-purpose" class="label">What it pays for</label><textarea id="fu-purpose" name="purpose" class="input" rows="3" required maxlength="2000">{{ old('purpose', $f->purpose) }}</textarea>@error('purpose')<p class="mt-1 text-xs text-state-bad">{{ $message }}</p>@enderror</div>
        <div class="sm:col-span-2"><label for="fu-url" class="label">Link (optional)</label><input id="fu-url" name="url" type="url" class="input" maxlength="512" value="{{ old('url', $f->url) }}" placeholder="https://"><p class="meta mt-1">https only.</p>@error('url')<p class="mt-1 text-xs text-state-bad">{{ $message }}</p>@enderror</div>
        <div><label for="fu-starts" class="label">Starts on (optional)</label><input id="fu-starts" name="starts_on" type="date" class="input" value="{{ old('starts_on', $f->starts_on?->format('Y-m-d')) }}"></div>
        <div><label for="fu-ends" class="label">Ends on (optional)</label><input id="fu-ends" name="ends_on" type="date" class="input" value="{{ old('ends_on', $f->ends_on?->format('Y-m-d')) }}">@error('ends_on')<p class="mt-1 text-xs text-state-bad">{{ $message }}</p>@enderror</div>
        <div><label for="fu-sort" class="label">Order (optional)</label><input id="fu-sort" name="sort" type="number" min="0" class="input" value="{{ old('sort', $editing?->sort) }}"><p class="meta mt-1">Empty puts a new entry last.</p></div>
        <div class="flex items-end"><label class="flex items-center gap-2 text-sm"><input type="hidden" name="published" value="0"><input type="checkbox" name="published" value="1" @checked(old('published', $f->published))> Published on /funding</label></div>
    </div>
    <div class="flex gap-2"><button type="submit" class="btn-primary">{{ $editing ? 'Save funder' : 'Add funder' }}</button>@if($editing)<a href="{{ route('backend.admin.funding.index') }}" class="btn-secondary">Cancel</a>@endif</div>
</form>
@endsection
