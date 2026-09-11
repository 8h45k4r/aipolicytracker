@extends('backend.layouts.app', ['title' => 'Tool library'])
@section('content')
<div class="flex flex-wrap items-start justify-between gap-3">
    <div><h1 class="font-display text-2xl font-semibold text-brand-navy">Tool library</h1><p class="mt-1 meta">Templates, checklists, registers and plans shown on <a href="{{ route('guides.index') }}">/guides</a>. Only published tools with at least one active file are listed publicly.</p></div>
    <div class="flex gap-2"><a href="{{ route('backend.admin.tools.create') }}" class="btn-primary">New tool</a><a href="{{ route('backend.admin.downloads') }}" class="btn-secondary">Download activity</a></div>
</div>
<div class="mt-4 flex flex-wrap gap-2 text-sm">
    <a class="chip {{ !$status ? 'chip-active' : '' }}" href="{{ route('backend.admin.tools.index') }}">All ({{ $counts->sum() }})</a>
    @foreach(\App\Models\Tool::STATUSES as $k => $label)<a class="chip {{ $status === $k ? 'chip-active' : '' }}" href="{{ route('backend.admin.tools.index', ['status' => $k]) }}">{{ $label }} ({{ $counts[$k] ?? 0 }})</a>@endforeach
</div>
@if($tools->isEmpty())<div class="mt-6"><x-site.empty title="No tools yet">Create a tool, then upload its files.</x-site.empty></div>@else
<div class="table-wrap mt-6"><table><thead><tr><th>Order</th><th>Title</th><th>Type</th><th>Status</th><th>Version</th><th>Files</th><th>Downloads</th><th>Updated</th><th></th></tr></thead><tbody>
@foreach($tools as $t)
<tr>
    <td class="font-mono">{{ $t->sort_order }}</td>
    <td><a href="{{ route('backend.admin.tools.edit', $t) }}" class="font-medium text-brand-navy">{{ $t->title }}</a><div class="meta font-mono">{{ $t->slug }}</div></td>
    <td>{{ \App\Models\Tool::TYPES[$t->type] ?? $t->type }}</td>
    <td><span class="badge {{ $t->status === 'published' ? 'bg-state-goodbg text-state-good ring-state-good/30' : 'badge-neutral' }}">{{ \App\Models\Tool::STATUSES[$t->status] ?? $t->status }}</span>@if($t->featured) <span class="badge-neutral">Featured</span>@endif</td>
    <td class="font-mono">{{ $t->version }}</td>
    <td class="font-mono">{{ $t->files_count ?: '—' }}</td>
    <td class="font-mono">{{ $t->downloads_count ?: '—' }}</td>
    <td class="whitespace-nowrap">{{ $t->updated_on?->format('Y-m-d') ?? '—' }}</td>
    <td class="whitespace-nowrap"><a href="{{ route('backend.admin.tools.edit', $t) }}" class="btn-secondary !min-h-0 !py-1">Edit</a> @if($t->status === 'published')<a href="{{ $t->url() }}" class="btn-secondary !min-h-0 !py-1" target="_blank" rel="noopener">View</a>@endif</td>
</tr>
@endforeach
</tbody></table></div>
@endif
@endsection
