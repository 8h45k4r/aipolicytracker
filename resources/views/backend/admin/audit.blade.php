@extends('backend.layouts.app', ['title' => 'Audit log'])
@section('content')
@php($viewer = auth()->user())
<x-backend.page-header title="Audit log" description="Every request that changed something in the admin, and every export: who, what, which record and whether it went through. Reads are not recorded and request bodies never are, so the log holds no passwords, codes or keys." />

<div class="mt-5 grid gap-4 sm:grid-cols-3">
    <x-backend.stat label="Actions, last 7 days" :value="number_format($summary['week'])" :trend="$summary['trend']" :href="route('backend.admin.audit', ['from' => now()->subDays(6)->toDateString()])" />
    <x-backend.stat label="Refused or failed, last 7 days" :value="number_format($summary['failed'])" :tone="$summary['failed'] ? 'text-state-bad' : null" :href="route('backend.admin.audit', ['result' => 'failed', 'from' => now()->subDays(6)->toDateString()])" hint="Turned away by a check, or broke" />
    <x-backend.stat label="People active, last 7 days" :value="number_format($summary['people'])" />
</div>

<x-backend.filters :action="route('backend.admin.audit')" :filters="$filters" placeholder="Address, action or email" :total="$entries->total()" noun="action">
    <div><label for="f-user" class="adm-label">Person</label><select id="f-user" name="user" class="input !min-h-[38px] !py-1.5 !w-auto"><option value="">Anyone</option>@foreach($people as $p)<option value="{{ $p }}" @selected(request('user') === $p)>{{ $p }}</option>@endforeach</select></div>
    <div><label for="f-action" class="adm-label">Action</label><select id="f-action" name="action" class="input !min-h-[38px] !py-1.5 !w-auto max-w-[18rem]"><option value="">Any</option>@foreach($actions as $route => $label)<option value="{{ $route }}" @selected(request('action') === $route)>{{ $label }}</option>@endforeach</select></div>
    <div><label for="f-result" class="adm-label">Result</label><select id="f-result" name="result" class="input !min-h-[38px] !py-1.5 !w-auto"><option value="">Any</option><option value="ok" @selected(request('result') === 'ok')>Success</option><option value="failed" @selected(request('result') === 'failed')>Refused or failed</option></select></div>
</x-backend.filters>

<div class="table-wrap mt-4"><table><caption class="sr-only">Admin actions</caption>
    <thead><tr>
        <x-backend.sort-th key="when" label="When" :filters="$filters" />
        <x-backend.sort-th key="who" label="Who" :filters="$filters" />
        <x-backend.sort-th key="action" label="Action" :filters="$filters" />
        <th scope="col">Record</th>
        <x-backend.sort-th key="status" label="Result" :filters="$filters" />
    </tr></thead>
    <tbody>
    @forelse($entries as $e)
    @php($target = \App\Support\Admin\AuditActions::target($e, $viewer, $lookups))
    @php($rest = \App\Support\Admin\AuditActions::rest($e))
    <tr>
        <td class="whitespace-nowrap"><time datetime="{{ $e->created_at->toIso8601String() }}" title="{{ $e->created_at->toDayDateTimeString() }}">{{ $e->created_at->format('j M Y H:i') }}</time></td>
        <td>@if($e->user_email)<a href="{{ request()->fullUrlWithQuery(['user' => $e->user_email, 'page' => null]) }}" class="no-underline hover:underline" title="Only {{ $e->user_email }}’s actions">{{ $e->user?->name ?? $e->user_email }}</a>@else — @endif</td>
        <td>
            @if($e->route_name)<a href="{{ request()->fullUrlWithQuery(['action' => $e->route_name, 'page' => null]) }}" class="font-medium text-brand-navy no-underline hover:underline" title="Only this action">{{ \App\Support\Admin\AuditActions::label($e->route_name, $e->method, $e->path, $e->route_params ?? []) }}</a>@else<span class="font-medium text-brand-navy">{{ $e->method }} {{ $e->path }}</span>@endif
            <div class="meta font-mono text-[11px]" title="Method and route as recorded">{{ $e->method }} {{ $e->route_name ?? $e->path }}</div>
        </td>
        <td class="text-sm">
            @if($target)@if($target['url'])<a href="{{ $target['url'] }}" @if(isset($e->route_params['ids'])) title="{{ $e->route_params['ids'] }}" @endif>{{ $target['text'] }}</a>@else<span @if(isset($e->route_params['ids'])) title="{{ $e->route_params['ids'] }}" @endif>{{ $target['text'] }}</span>@endif
            @elseif($rest === '')<span class="text-brand-muted">—</span>@endif
            @if($rest !== '')<div class="meta font-mono text-[11px] break-all">{{ \Illuminate\Support\Str::limit($rest, 140) }}</div>@endif
        </td>
        <td><x-backend.badge :status="\App\Support\Admin\AuditActions::tone((int) $e->status)" title="HTTP {{ $e->status }}">{{ \App\Support\Admin\AuditActions::outcome((int) $e->status) }}</x-backend.badge><span class="sr-only"> (HTTP {{ $e->status }})</span></td>
    </tr>
    @empty
    <tr><td colspan="5"><x-site.empty title="{{ $filters->active() || request()->hasAny(['user', 'action', 'result']) ? 'No actions match these filters' : 'No admin actions recorded yet' }}">{{ $filters->active() ? 'Widen the dates or clear a filter.' : 'The first publish, decision or setting change will appear here.' }}</x-site.empty></td></tr>
    @endforelse
    </tbody></table></div>
<nav class="mt-4" aria-label="Pagination">{{ $entries->links() }}</nav>
@endsection
