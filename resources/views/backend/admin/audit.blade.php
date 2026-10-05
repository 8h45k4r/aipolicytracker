@extends('backend.layouts.app', ['title' => 'Audit log'])
@section('content')
<x-backend.page-header title="Audit log" description="Every request that changed something in the admin: who, which action, which record and whether it succeeded. Reads are not recorded and request bodies never are, so the log holds no passwords, codes or keys." />

<div class="mt-5 grid gap-4 sm:grid-cols-3">
    <x-backend.stat label="Actions, last 7 days" :value="number_format($summary['week'])" :trend="$summary['trend']" :href="route('backend.admin.audit', ['from' => now()->subDays(6)->toDateString()])" />
    <x-backend.stat label="Failed, last 7 days" :value="number_format($summary['failed'])" :tone="$summary['failed'] ? 'text-state-bad' : null" :href="route('backend.admin.audit', ['result' => 'failed', 'from' => now()->subDays(6)->toDateString()])" hint="Refused or errored requests" />
    <x-backend.stat label="People active, last 7 days" :value="number_format($summary['people'])" />
</div>

<x-backend.filters :action="route('backend.admin.audit')" :filters="$filters" :export="route('backend.admin.audit.export')" placeholder="Path, action or email" :total="$entries->total()" noun="action">
    <div><label for="f-user" class="adm-label">Person</label><select id="f-user" name="user" class="input !min-h-[38px] !py-1.5 !w-auto"><option value="">Anyone</option>@foreach($people as $p)<option value="{{ $p }}" @selected(request('user') === $p)>{{ $p }}</option>@endforeach</select></div>
    <div><label for="f-action" class="adm-label">Action</label><select id="f-action" name="action" class="input !min-h-[38px] !py-1.5 !w-auto max-w-[16rem]"><option value="">Any</option>@foreach($actions as $a)<option value="{{ $a }}" @selected(request('action') === $a)>{{ \Illuminate\Support\Str::after($a, 'backend.') }}</option>@endforeach</select></div>
    <div><label for="f-result" class="adm-label">Result</label><select id="f-result" name="result" class="input !min-h-[38px] !py-1.5 !w-auto"><option value="">Any</option><option value="ok" @selected(request('result') === 'ok')>Succeeded</option><option value="failed" @selected(request('result') === 'failed')>Failed</option></select></div>
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
    <tr>
        <td class="whitespace-nowrap"><time datetime="{{ $e->created_at->toIso8601String() }}" title="{{ $e->created_at->toDayDateTimeString() }}">{{ $e->created_at->format('j M Y H:i') }}</time></td>
        <td>@if($e->user_email)<a href="{{ request()->fullUrlWithQuery(['user' => $e->user_email, 'page' => null]) }}" class="no-underline hover:underline" title="Only this person's actions">{{ $e->user?->name ?? $e->user_email }}</a>@else — @endif</td>
        <td><span class="font-mono text-xs text-brand-muted">{{ $e->method }}</span> @if($e->route_name)<a href="{{ request()->fullUrlWithQuery(['action' => $e->route_name, 'page' => null]) }}" class="no-underline hover:underline" title="Only this action">{{ \Illuminate\Support\Str::after($e->route_name, 'backend.') }}</a>@else{{ $e->path }}@endif</td>
        <td class="font-mono text-xs">
            @if(($e->route_params['user'] ?? null) && \Illuminate\Support\Facades\Route::has('backend.admin.users.show') && auth()->user()->can('users.manage'))<a href="{{ route('backend.admin.users.show', $e->route_params['user']) }}">user {{ $e->route_params['user'] }}</a>
            @else{{ $e->route_params ? collect($e->route_params)->map(fn ($v, $k) => $k.'='.$v)->implode(' ') : '—' }}@endif
        </td>
        <td>@if($e->status >= 400)<x-backend.badge status="failed">{{ $e->status }}</x-backend.badge>@else<x-backend.badge status="ok">{{ $e->status }}</x-backend.badge>@endif</td>
    </tr>
    @empty
    <tr><td colspan="5"><x-site.empty title="{{ $filters->active() || request()->hasAny(['user', 'action', 'result']) ? 'No actions match these filters' : 'No admin actions recorded yet' }}">{{ $filters->active() ? 'Widen the dates or clear a filter.' : 'The first publish, decision or setting change will appear here.' }}</x-site.empty></td></tr>
    @endforelse
    </tbody></table></div>
<nav class="mt-4" aria-label="Pagination">{{ $entries->links() }}</nav>
@endsection
