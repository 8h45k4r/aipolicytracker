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
<details class="mt-4 card-flat p-4 text-sm" @if($tools->isEmpty()) open @endif>
    <summary class="cursor-pointer font-semibold text-brand-navy">How to add a tool and upload its files</summary>
    <ol class="mt-3 list-decimal space-y-1.5 pl-5 text-brand-body">
        <li><strong>New tool</strong>: give it a title, a slug (becomes <code>/guides/tools/&lt;slug&gt;</code>), a content type, a short description for the card and the longer "what it is for" text. Leave status as <em>Draft</em>.</li>
        <li><strong>Preview fields</strong>: one line per field as <code>Field name | what to record</code>. These render as the public preview table, so readers can judge the tool before signing up.</li>
        <li><strong>Steps, frameworks, topics</strong>: add the how-to steps, tick the frameworks and topics (they drive the filters on /guides), link related guides and policy slugs, and pick a "next step" tool.</li>
        <li><strong>Create</strong>, then in the Files panel upload each format (XLSX, CSV, Markdown, PDF, DOCX, JSON or text, up to 10 MB). Put the version, date and the informational-only note inside every file. The label is derived from the extension; set a version if it differs from the tool's.</li>
        <li>Use <strong>Download</strong> to check a file, <strong>Deactivate</strong> to hide a format without deleting it, <strong>Remove</strong> to delete it.</li>
        <li>Set status to <strong>Published</strong> and save. Publishing needs at least one active file; the tool then appears on /guides, in the sitemap and in the download funnel. <strong>Archive</strong> hides it again while keeping download history.</li>
    </ol>
    <p class="mt-2 meta">The initial library is seeded from <code>config/resources.php</code> once; edits made here are never overwritten by deploys.</p>
</details>
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
