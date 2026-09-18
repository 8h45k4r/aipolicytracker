@extends('backend.layouts.app', ['title' => 'Audit log'])
@section('content')
<h1 class="font-display text-2xl font-semibold text-brand-navy">Audit log</h1>
<p class="mt-1 text-sm text-brand-body max-w-3xl">Every request that changed something in the admin: who, which action, which record, and whether it succeeded. Reads are not recorded and request bodies are never stored, so the log holds no passwords, codes or keys.</p>
<div class="table-wrap mt-6"><table><caption class="sr-only">Admin actions</caption>
    <thead><tr><th scope="col">When</th><th scope="col">Who</th><th scope="col">Action</th><th scope="col">Record</th><th scope="col">Result</th></tr></thead>
    <tbody>
    @forelse($entries as $e)
    <tr>
        <td class="whitespace-nowrap"><time datetime="{{ $e->created_at->toIso8601String() }}">{{ $e->created_at->format('j M Y H:i') }}</time></td>
        <td>{{ $e->user?->name ?? $e->user_email ?? '—' }}</td>
        <td><span class="font-mono text-xs">{{ $e->method }}</span> {{ $e->route_name ?? $e->path }}</td>
        <td class="font-mono text-xs">{{ $e->route_params ? collect($e->route_params)->map(fn ($v, $k) => $k.'='.$v)->implode(' ') : '—' }}</td>
        <td class="{{ $e->status >= 400 ? 'text-state-bad' : '' }}">{{ $e->status }}</td>
    </tr>
    @empty
    <tr><td colspan="5"><x-site.empty title="No admin actions recorded yet">The first publish, decision or setting change will appear here.</x-site.empty></td></tr>
    @endforelse
    </tbody></table></div>
<nav class="mt-4" aria-label="Pagination">{{ $entries->links() }}</nav>
@endsection
