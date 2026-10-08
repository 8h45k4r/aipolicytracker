@extends('backend.layouts.app', ['title' => 'Tool library'])
@section('content')
<x-backend.page-header title="Tool library">
    <x-slot:description>Templates, checklists, registers and plans shown on <a href="{{ route('guides.index') }}">/guides</a>. Only published tools with at least one active file are listed publicly.</x-slot:description>
    <x-slot:actions><a href="{{ route('backend.admin.tools.create') }}" class="btn-primary" data-command="Create a tool">New tool</a>@can('audience.view')<a href="{{ route('backend.admin.downloads', ['view' => 'downloads']) }}" class="btn-secondary">Download activity</a>@endcan</x-slot:actions>
</x-backend.page-header>
<nav class="adm-tabs" aria-label="Status">
    <a href="{{ request()->fullUrlWithQuery(['status' => null]) }}" @if(! $status) aria-current="page" @endif>All<span class="adm-count">{{ $counts->sum() }}</span></a>
    @foreach(\App\Models\Tool::STATUSES as $k => $label)<a href="{{ request()->fullUrlWithQuery(['status' => $k]) }}" @if($status === $k) aria-current="page" @endif>{{ $label }}<span class="adm-count">{{ $counts[$k] ?? 0 }}</span></a>@endforeach
</nav>
<x-backend.filters :action="route('backend.admin.tools.index')" :filters="$filters" placeholder="Title, slug or description" :dates="false" :total="$tools->count()" noun="tool">
    @if($status)<input type="hidden" name="status" value="{{ $status }}">@endif
</x-backend.filters>
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
@if($tools->isEmpty())<div class="mt-6">@if($status || $filters->active())<x-site.empty title="No tools match" :reset="route('backend.admin.tools.index')">Change the status or the search to see more tools.</x-site.empty>@else<x-site.empty title="No tools yet">Create a tool, then upload its files.</x-site.empty>@endif</div>@else
<form method="post" action="{{ route('backend.admin.tools.status.many') }}" id="bulk-tools" class="mt-6 flex flex-wrap items-center gap-2 rounded-sm border border-brand-line bg-white p-3 text-sm">@csrf
    <span class="font-medium text-brand-navy">Set the status of the selection</span>
    <span class="badge-neutral" data-bulk-count="bulk-tools">0 selected</span>
    <label class="ml-auto flex items-center gap-2 text-xs"><span class="meta">Status</span><select name="status" class="input !min-h-0 !py-1 !w-auto" aria-label="Status for the selection">@foreach(\App\Models\Tool::STATUSES as $k => $label)<option value="{{ $k }}">{{ $label }}</option>@endforeach</select></label>
    <button type="submit" class="btn-primary btn-sm" data-bulk-needs="bulk-tools" data-confirm="Change the status of {n} tools? Publishing skips any tool without an active file.">Apply to selected</button>
</form>
<div class="table-wrap mt-3"><table><caption class="sr-only">Tools in the library</caption><thead><tr><th scope="col" class="w-8"><input type="checkbox" data-bulk-all="bulk-tools" aria-label="Select every tool shown"></th><x-backend.sort-th key="order" label="Order" :filters="$filters" /><x-backend.sort-th key="title" label="Title" :filters="$filters" /><th scope="col">Type</th><th scope="col">Status</th><th scope="col">Version</th><th scope="col">Files</th><x-backend.sort-th key="downloads" label="Downloads" :filters="$filters" /><x-backend.sort-th key="updated" label="Updated" :filters="$filters" /><th scope="col" class="sticky right-0"><span class="sr-only">Actions</span></th></tr></thead><tbody>
@foreach($tools as $t)
<tr>
    <td><input type="checkbox" name="ids[]" value="{{ $t->id }}" form="bulk-tools" data-bulk-item aria-label="Select {{ $t->title }}"></td>
    <td class="font-mono">{{ $t->sort_order }}</td>
    <td class="min-w-[12rem] max-w-[16rem]"><a href="{{ route('backend.admin.tools.edit', $t) }}" class="font-medium text-brand-navy">{{ $t->title }}</a><div class="meta font-mono truncate" title="{{ $t->slug }}">{{ $t->slug }}</div></td>
    <td>{{ \App\Models\Tool::TYPES[$t->type] ?? $t->type }}</td>
    <td><x-backend.badge :status="$t->status">{{ \App\Models\Tool::STATUSES[$t->status] ?? $t->status }}</x-backend.badge>@if($t->featured) <span class="badge-neutral">Featured</span>@endif @if($t->status !== 'published' && ! $t->files_count)<div class="meta text-state-warn">no file yet</div>@endif</td>
    <td class="font-mono">{{ $t->version }}</td>
    <td class="font-mono">{{ $t->files_count ?: '—' }}</td>
    <td class="font-mono">@if($t->downloads_count && auth()->user()->can('audience.view'))<a href="{{ route('backend.admin.downloads', ['view' => 'downloads', 'resource' => $t->slug]) }}" title="Who downloaded it">{{ $t->downloads_count }}</a>@else{{ $t->downloads_count ?: '—' }}@endif</td>
    <td class="whitespace-nowrap">{{ $t->updated_on?->format('Y-m-d') ?? '—' }}</td>
    <td class="sticky right-0 whitespace-nowrap bg-white text-right"><a href="{{ route('backend.admin.tools.edit', $t) }}" class="btn-secondary btn-sm">Edit</a> @if($t->status === 'published')<a href="{{ $t->url() }}" class="btn-secondary btn-sm" target="_blank" rel="noopener">View</a>@endif</td>
</tr>
@endforeach
</tbody></table></div>
@endif
@endsection
