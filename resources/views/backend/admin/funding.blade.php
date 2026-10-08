@extends('backend.layouts.app', ['title' => 'Funding and funders'])
@section('content')
<x-backend.page-header title="Funding and funders" description="Funders shown on the public funding page. Only published entries are listed there.">
    <x-slot:actions><a href="{{ route('backend.admin.funding.index', ['new' => 1]) }}#funder-form" class="btn-primary btn-sm" data-drawer-open="funder-add" data-command="Add a funder">Add a funder</a><a href="{{ route('funding') }}" class="btn-secondary btn-sm" target="_blank" rel="noopener">View /funding</a><a href="{{ route('backend.admin.settings') }}#citation-funding" class="btn-secondary btn-sm">Threshold and sponsor link</a></x-slot:actions>
</x-backend.page-header>

<div class="mt-4 rounded-sm border border-brand-line bg-white px-4 py-3 text-sm text-brand-body" data-funding-threshold>
    <p><strong class="text-brand-navy">Disclosure threshold: {{ '$'.number_format($threshold) }} a year.</strong> Every funder giving more than this in a year must be listed and published here, with the amount and what it pays for. Add the entry when the money is agreed, not when it arrives.</p>
    <p class="mt-1 meta">Write the amount as agreed. Do not estimate or round it. The threshold and the sponsor link are set on the settings page.@if($sponsorUrl) Sponsor link: <a href="{{ $sponsorUrl }}" rel="noopener">{{ $sponsorUrl }}</a>.@endif</p>
    @if($usingConfig)<p class="mt-1 text-state-warn">This table is empty, so /funding still shows the list in config/funding.php. Once you add an entry here, only this table is used.</p>@endif
</div>

@if($funders->isEmpty())
<div class="mt-6"><x-site.empty title="No funders yet">/funding says there are none. Use "Add a funder" once the money is agreed.</x-site.empty></div>
@else
<div class="table-wrap mt-6"><table><caption class="sr-only">Funders</caption><thead><tr>
    <th scope="col">Order</th><th scope="col">Funder</th><th scope="col">Kind</th><th scope="col">Amount</th><th scope="col">Dates</th><th scope="col">Status</th><th scope="col"><span class="sr-only">Actions</span></th>
</tr></thead><tbody>
@foreach($funders as $f)
<tr>
    <td class="whitespace-nowrap">
        <form method="post" action="{{ route('backend.admin.funding.move', $f) }}" class="inline">@csrf<input type="hidden" name="action" value="up"><button class="btn-secondary btn-sm text-xs" @disabled($loop->first) aria-label="Move {{ $f->name }} up">↑</button></form>
        <form method="post" action="{{ route('backend.admin.funding.move', $f) }}" class="inline">@csrf<input type="hidden" name="action" value="down"><button class="btn-secondary btn-sm text-xs" @disabled($loop->last) aria-label="Move {{ $f->name }} down">↓</button></form>
    </td>
    <td class="min-w-[12rem] max-w-[20rem]"><span class="font-medium text-brand-navy">{{ $f->name }}</span>@if($f->url)<div class="meta truncate"><a href="{{ $f->url }}" rel="noopener" target="_blank">{{ $f->url }}</a></div>@endif<div class="meta">{{ \Illuminate\Support\Str::limit($f->purpose, 120) }}</div></td>
    <td>{{ $f->kindLabel() }}</td>
    <td class="whitespace-nowrap">{{ $f->amount_display }}@if($f->period)<div class="meta">{{ $f->period }}</div>@endif</td>
    <td class="whitespace-nowrap text-xs">{{ $f->starts_on?->format('Y-m-d') ?? '—' }} to {{ $f->ends_on?->format('Y-m-d') ?? '—' }}</td>
    <td>@if($f->published)<x-backend.badge status="published" />@else<x-backend.badge status="draft">hidden</x-backend.badge>@endif</td>
    <td class="whitespace-nowrap text-right">
        <form method="post" action="{{ route('backend.admin.funding.publish', $f) }}" class="inline">@csrf<input type="hidden" name="state" value="{{ $f->published ? 'off' : 'on' }}"><button class="btn-secondary btn-sm text-xs">{{ $f->published ? 'Hide' : 'Publish' }}</button></form>
        <a href="{{ route('backend.admin.funding.index', ['edit' => $f->id]) }}#funder-form" class="btn-secondary btn-sm text-xs" data-drawer-open="funder-edit-{{ $f->id }}" aria-label="Edit {{ $f->name }}">Edit</a>
        <form method="post" action="{{ route('backend.admin.funding.destroy', $f) }}" class="inline" data-confirm="Delete {{ $f->name }}? It disappears from /funding at once and cannot be restored. Hide it instead to keep the record." data-confirm-danger data-confirm-label="Delete {{ $f->name }}">@csrf @method('DELETE')<button class="btn-secondary btn-sm text-xs text-state-bad">Delete</button></form>
    </td>
</tr>
@endforeach
</tbody></table></div>
@endif

{{-- Without JavaScript, "Add a funder" and "Edit" lead here (?new=1, ?edit=id) and the
     form is on the page. With it, the same links open the side panels below. --}}
@if($adding || $editing)
<section id="funder-form" class="mt-6 card-flat scroll-mt-6 p-5" aria-labelledby="funder-form-title">
    <h2 id="funder-form-title" class="section-title !text-lg">{{ $editing ? 'Edit '.$editing->name : 'Add a funder' }}</h2>
    <div class="mt-4">@include('backend.admin.funding.form', ['form' => $editing ? 'funder-edit-'.$editing->id : 'funder-add', 'funder' => $editing, 'inline' => true])</div>
</section>
@endif

@unless($adding)
<x-backend.drawer id="funder-add" title="Add a funder" description="Add it when the money is agreed, not when it arrives." :open="old('_drawer') === 'funder-add'">
    @include('backend.admin.funding.form', ['form' => 'funder-add', 'funder' => null])
    <x-slot:footer><button type="button" class="btn-secondary" data-drawer-close>Cancel</button><button type="submit" form="funder-add-form" class="btn-primary">Add funder</button></x-slot:footer>
</x-backend.drawer>
@endunless
@foreach($funders as $f)
@continue($editing && $editing->id === $f->id)
<x-backend.drawer id="funder-edit-{{ $f->id }}" title="Edit {{ $f->name }}" :open="old('_drawer') === 'funder-edit-'.$f->id">
    @include('backend.admin.funding.form', ['form' => 'funder-edit-'.$f->id, 'funder' => $f])
    <x-slot:footer><button type="button" class="btn-secondary" data-drawer-close>Cancel</button><button type="submit" form="funder-edit-{{ $f->id }}-form" class="btn-primary">Save funder</button></x-slot:footer>
</x-backend.drawer>
@endforeach
@endsection
