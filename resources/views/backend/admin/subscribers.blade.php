@extends('backend.layouts.app', ['title' => 'Subscribers'])
@section('content')
<x-backend.page-header title="Subscribers" description="Double opt-in digest subscribers. Only the address, topics, timestamps and the page they signed up from are stored. An account badge means a verified account uses the same address." />

<div class="mt-5 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
    <x-backend.stat label="Active" :value="number_format($counts['active'])" :href="route('backend.admin.subscribers', ['state' => 'active'])" />
    <x-backend.stat label="New, last 30 days" :value="number_format(array_sum($trend))" :trend="$trend" :href="route('backend.admin.subscribers', ['state' => 'all', 'from' => now()->subDays(29)->toDateString()])" />
    <x-backend.stat label="Awaiting confirmation" :value="number_format($counts['unconfirmed'])" :href="route('backend.admin.subscribers', ['state' => 'unconfirmed'])" :tone="$counts['unconfirmed'] ? 'text-state-warn' : null" />
    <x-backend.stat label="Confirmation rate" :value="$confirmRate === null ? '—' : $confirmRate.'%'" hint="Confirmed, of everyone who did not unsubscribe" />
</div>

<nav class="adm-tabs" aria-label="Subscription state">
    @foreach(['active' => 'Active', 'unconfirmed' => 'Unconfirmed', 'unsubscribed' => 'Unsubscribed', 'all' => 'All'] as $k => $label)
    <a href="{{ request()->fullUrlWithQuery(['state' => $k, 'page' => null]) }}" @if($state === $k) aria-current="page" @endif>{{ $label }}<span class="adm-count">{{ $counts[$k] }}</span></a>
    @endforeach
</nav>

<x-backend.filters :action="route('backend.admin.subscribers')" :filters="$filters" :export="route('backend.admin.subscribers.export')" placeholder="Email address" :total="$subscribers->total()" noun="subscriber">
    <input type="hidden" name="state" value="{{ $state }}">
    <div><label for="f-topic" class="adm-label">Topic</label><input id="f-topic" name="topic" value="{{ request('topic') }}" class="input !min-h-[38px] !py-1.5 !w-40" placeholder="e.g. eu, all" pattern="[a-z0-9-]+"></div>
    <div><label for="f-source" class="adm-label">Source</label><select id="f-source" name="source" class="input !min-h-[38px] !py-1.5 !w-auto"><option value="">Any</option>@foreach($sources as $src)<option value="{{ $src }}" @selected(request('source') === $src)>{{ $src }}</option>@endforeach<option value="(none)" @selected(request('source') === '(none)')>not recorded</option></select></div>
    <div><label for="f-account" class="adm-label">Account</label><select id="f-account" name="account" class="input !min-h-[38px] !py-1.5 !w-auto"><option value="">Any</option><option value="yes" @selected(request('account') === 'yes')>has a verified account</option><option value="no" @selected(request('account') === 'no')>no account</option></select></div>
</x-backend.filters>

@if($subscribers->isEmpty())<div class="mt-6"><x-site.empty title="No subscribers match" :reset="route('backend.admin.subscribers', ['state' => $state])">Subscribers appear here after they confirm the email sent by the public form. Clear the search, topic or dates to see more.</x-site.empty></div>@else
@can('subscribers.manage')
{{-- The bulk bar posts to a URL carrying this list's query string, so "all matching" means
     exactly the subscribers the tabs, filters and search show. --}}
@php($matching = $subscribers->total())
@php($wholeFilter = old('scope') === 'filtered')
@php($picked = array_map('strval', (array) old('ids', [])))
<form method="post" action="{{ route('backend.admin.subscribers.resend.many', request()->except(['page'])) }}" id="bulk-subscribers" class="adm-bulkbar sticky top-0 z-10 mt-4 flex flex-wrap items-center gap-2 rounded-md border border-brand-line bg-white p-3 text-sm shadow-sm">@csrf
    <span class="font-medium text-brand-navy">Act on the selection</span>
    <span class="badge-neutral" data-bulk-count="bulk-subscribers" data-bulk-count-all="all {{ $matching }} matching">{{ $wholeFilter ? 'all '.$matching.' matching' : count($picked).' selected' }}</span>
    @if($matching > $subscribers->count())<label class="flex items-center gap-1 meta"><input type="checkbox" name="scope" value="filtered" data-bulk-scope="bulk-subscribers" data-bulk-scope-count="{{ $matching }}" @checked($wholeFilter)> apply to all {{ $matching }} matching, not only this page{{ $matching > 1000 ? ' (up to 1,000 per action)' : '' }}</label>@endif
    <span class="ml-auto flex flex-wrap gap-1">
        <button type="submit" class="btn-secondary !min-h-0 !py-1" data-bulk-needs="bulk-subscribers" data-confirm="Re-send the confirmation email to the unconfirmed addresses among the {n} selected?" data-confirm-label="Re-send">Re-send confirmation</button>
        <button type="submit" formaction="{{ route('backend.admin.subscribers.delete.many', request()->except(['page'])) }}" class="btn-secondary !min-h-0 !py-1 text-state-bad" data-bulk-needs="bulk-subscribers" data-confirm="Delete {n} subscribers permanently? This cannot be undone." data-confirm-label="Delete" data-confirm-danger>Delete selected</button>
    </span>
</form>
@endcan
<div class="table-wrap mt-3" id="subscribers-list"><table><caption class="sr-only">Subscribers</caption><thead><tr>
    @can('subscribers.manage')<th scope="col" class="w-8"><input type="checkbox" data-bulk-all="bulk-subscribers" aria-label="Select every subscriber on this page"></th>@endcan
    <x-backend.sort-th key="email" label="Email" :filters="$filters" />
    <th scope="col">Topics</th>
    <x-backend.sort-th key="confirmed" label="Status" :filters="$filters" />
    <x-backend.sort-th key="sent" label="Last sent" :filters="$filters" />
    <th scope="col">Source</th>
    <x-backend.sort-th key="joined" label="Joined" :filters="$filters" />
    @can('subscribers.manage')<th scope="col">Actions</th>@endcan
</tr></thead>
<tbody>@foreach($subscribers as $s)<tr>
@can('subscribers.manage')<td><input type="checkbox" name="ids[]" value="{{ $s->id }}" form="bulk-subscribers" data-bulk-item @checked(in_array((string) $s->id, $picked ?? [], true)) aria-label="Select {{ $s->email }}"></td>@endcan
<td class="font-mono text-xs">{{ $s->email }}@if($s->account_id) @can('users.manage')<a href="{{ route('backend.admin.users.show', $s->account_id) }}" class="badge-neutral font-sans no-underline" title="A verified account uses this address" data-account-badge>account</a>@else<span class="badge-neutral font-sans" title="A verified account uses this address" data-account-badge>account</span>@endcan @endif</td>
<td class="text-xs">@foreach($s->topics ?? ['all'] as $t)<a href="{{ request()->fullUrlWithQuery(['topic' => $t, 'page' => null]) }}" class="chip !min-h-0 !py-0.5 !px-1.5 mr-1" title="Only subscribers to {{ $t }}">{{ $t }}</a>@endforeach</td>
<td class="text-xs whitespace-nowrap">@if($s->unsubscribed_at)<x-backend.badge status="unsubscribed" /> {{ $s->unsubscribed_at->format('j M Y') }}@elseif($s->confirmed_at)<x-backend.badge status="active">confirmed</x-backend.badge> {{ $s->confirmed_at->format('j M Y') }}@else<x-backend.badge status="unconfirmed">pending</x-backend.badge>@endif</td>
<td class="text-xs whitespace-nowrap">{{ $s->last_sent_at?->format('j M Y') ?? '—' }}</td>
<td class="text-xs">@if($s->source)<a href="{{ request()->fullUrlWithQuery(['source' => $s->source, 'page' => null]) }}" class="no-underline hover:underline" title="Only subscribers from {{ $s->source }}">{{ $s->source }}</a>@else<span class="text-brand-muted" title="Not recorded">—</span>@endif</td>
<td class="text-xs whitespace-nowrap">{{ $s->created_at?->format('j M Y') }}</td>
@can('subscribers.manage')<td class="whitespace-nowrap">
    @unless($s->confirmed_at || $s->unsubscribed_at)<form method="post" action="{{ route('backend.admin.subscribers.resend', $s) }}" class="inline">@csrf<button class="btn-secondary !min-h-0 !py-1 !px-2 text-xs">Re-send confirmation</button></form>@endunless
    <form method="post" action="{{ route('backend.admin.subscribers.delete', $s) }}" class="inline" data-confirm="Delete {{ $s->email }} permanently?" data-confirm-label="Delete" data-confirm-danger>@csrf @method('DELETE')<button class="btn-secondary !min-h-0 !py-1 !px-2 text-xs text-state-bad">Delete</button></form>
</td>@endcan</tr>@endforeach</tbody></table></div>
<nav class="mt-4" aria-label="Pagination">{{ $subscribers->links() }}</nav>
@endif
@endsection
