@extends('backend.layouts.app', ['title' => 'Subscribers'])
@section('content')
<div class="flex flex-wrap items-end justify-between gap-3">
    <div><h1 class="font-display text-2xl font-semibold text-brand-navy">Subscribers</h1><p class="mt-1 meta">Double opt-in digest subscribers. Only the address, topics and timestamps are stored.</p></div>
    <a href="{{ route('backend.admin.subscribers.export') }}" class="btn-secondary">Export CSV</a>
</div>
<div class="mt-4 flex flex-wrap gap-2 text-sm">@foreach(['active' => 'Active', 'unconfirmed' => 'Unconfirmed', 'unsubscribed' => 'Unsubscribed', 'all' => 'All'] as $k => $label)<a class="chip {{ $state === $k ? 'chip-active' : '' }}" href="{{ route('backend.admin.subscribers', ['state' => $k]) }}">{{ $label }} ({{ $counts[$k] }})</a>@endforeach</div>
@if($subscribers->isEmpty())<div class="mt-6"><x-site.empty title="No subscribers in this view">Subscribers appear here after they confirm the email sent by the public form.</x-site.empty></div>@else
<div class="table-wrap mt-6 bg-white"><table><thead><tr><th>Email</th><th>Topics</th><th>Confirmed</th><th>Last sent</th><th>Source</th><th>Actions</th></tr></thead>
<tbody>@foreach($subscribers as $s)<tr>
<td class="font-mono text-xs">{{ $s->email }}</td>
<td>{{ implode(', ', $s->topics ?? ['all']) }}</td>
<td>{{ $s->unsubscribed_at ? 'unsubscribed '.$s->unsubscribed_at->format('j M Y') : ($s->confirmed_at?->format('j M Y') ?? 'pending') }}</td>
<td>{{ $s->last_sent_at?->format('j M Y') ?? '—' }}</td>
<td>{{ $s->source ?? '—' }}</td>
<td class="whitespace-nowrap">
    @unless($s->confirmed_at)<form method="post" action="{{ route('backend.admin.subscribers.resend', $s) }}" class="inline">@csrf<button class="btn-secondary !min-h-0 !py-1 !px-2 text-xs">Re-send confirmation</button></form>@endunless
    <form method="post" action="{{ route('backend.admin.subscribers.delete', $s) }}" class="inline" onsubmit="return confirm('Delete this subscriber permanently?')">@csrf @method('DELETE')<button class="btn-secondary !min-h-0 !py-1 !px-2 text-xs text-state-bad">Delete</button></form>
</td></tr>@endforeach</tbody></table></div>
<nav class="mt-4" aria-label="Pagination">{{ $subscribers->links() }}</nav>
@endif
@endsection
