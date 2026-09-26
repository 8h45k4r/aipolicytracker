@extends('backend.layouts.app', ['title' => 'Subscribers'])
@section('content')
<div class="flex flex-wrap items-end justify-between gap-3">
    <div><h1 class="font-display text-2xl font-semibold text-brand-navy">Subscribers</h1><p class="mt-1 meta">Double opt-in digest subscribers. Only the address, topics and timestamps are stored.</p></div>
    <a href="{{ route('backend.admin.subscribers.export') }}" class="btn-secondary">Export CSV</a>
</div>
<div class="mt-4 flex flex-wrap gap-2 text-sm">@foreach(['active' => 'Active', 'unconfirmed' => 'Unconfirmed', 'unsubscribed' => 'Unsubscribed', 'all' => 'All'] as $k => $label)<a class="chip {{ $state === $k ? 'chip-active' : '' }}" href="{{ route('backend.admin.subscribers', ['state' => $k]) }}">{{ $label }} ({{ $counts[$k] }})</a>@endforeach</div>
@if($subscribers->isEmpty())<div class="mt-6"><x-site.empty title="No subscribers in this view">Subscribers appear here after they confirm the email sent by the public form.</x-site.empty></div>@else
@can('subscribers.manage')
<form method="post" action="{{ route('backend.admin.subscribers.resend.many') }}" id="bulk-subscribers" class="mt-6 flex flex-wrap items-center gap-2 rounded-sm border border-brand-line bg-white p-3 text-sm">@csrf
    <span class="font-medium text-brand-navy">Act on the selection</span>
    <span class="badge-neutral" data-bulk-count="bulk-subscribers">0 selected</span>
    <span class="ml-auto flex flex-wrap gap-1">
        <button type="submit" class="btn-secondary !min-h-0 !py-1" data-bulk-needs="bulk-subscribers" data-confirm="Re-send the confirmation email to the unconfirmed addresses among the {n} selected?">Re-send confirmation</button>
        <button type="submit" formaction="{{ route('backend.admin.subscribers.delete.many') }}" class="btn-secondary !min-h-0 !py-1 text-state-bad" data-bulk-needs="bulk-subscribers" data-confirm="Delete {n} subscribers permanently? This cannot be undone.">Delete selected</button>
    </span>
</form>
@endcan
<div class="table-wrap mt-3 bg-white"><table><caption class="sr-only">Subscribers</caption><thead><tr>
    @can('subscribers.manage')<th scope="col" class="w-8"><input type="checkbox" data-bulk-all="bulk-subscribers" aria-label="Select every subscriber on this page"></th>@endcan
    <th scope="col">Email</th><th scope="col">Topics</th><th scope="col">Confirmed</th><th scope="col">Last sent</th><th scope="col">Source</th>@can('subscribers.manage')<th scope="col">Actions</th>@endcan</tr></thead>
<tbody>@foreach($subscribers as $s)<tr>
@can('subscribers.manage')<td><input type="checkbox" name="ids[]" value="{{ $s->id }}" form="bulk-subscribers" data-bulk-item aria-label="Select {{ $s->email }}"></td>@endcan
<td class="font-mono text-xs">{{ $s->email }}</td>
<td>{{ implode(', ', $s->topics ?? ['all']) }}</td>
<td class="text-xs">@if($s->unsubscribed_at)<x-backend.badge status="unsubscribed" /> {{ $s->unsubscribed_at->format('j M Y') }}@elseif($s->confirmed_at)<x-backend.badge status="active">confirmed</x-backend.badge> {{ $s->confirmed_at->format('j M Y') }}@else<x-backend.badge status="unconfirmed">pending</x-backend.badge>@endif</td>
<td class="text-xs">{{ $s->last_sent_at?->format('j M Y') ?? '—' }}</td>
<td class="text-xs">{{ $s->source ?? '—' }}</td>
@can('subscribers.manage')<td class="whitespace-nowrap">
    @unless($s->confirmed_at || $s->unsubscribed_at)<form method="post" action="{{ route('backend.admin.subscribers.resend', $s) }}" class="inline">@csrf<button class="btn-secondary !min-h-0 !py-1 !px-2 text-xs">Re-send confirmation</button></form>@endunless
    <form method="post" action="{{ route('backend.admin.subscribers.delete', $s) }}" class="inline" data-confirm="Delete {{ $s->email }} permanently?">@csrf @method('DELETE')<button class="btn-secondary !min-h-0 !py-1 !px-2 text-xs text-state-bad">Delete</button></form>
</td>@endcan</tr>@endforeach</tbody></table></div>
<nav class="mt-4" aria-label="Pagination">{{ $subscribers->links() }}</nav>
@endif
@endsection
