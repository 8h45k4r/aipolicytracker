@extends('backend.layouts.app', ['title' => $tool->exists ? 'Edit tool' : 'New tool'])
@section('content')
@php($fw = config('resources.frameworks'))
@php($topics = config('resources.topics'))
<div class="flex flex-wrap items-start justify-between gap-3">
    <div><h1 class="font-display text-2xl font-semibold text-brand-navy">{{ $tool->exists ? $tool->title : 'New tool' }}</h1><p class="mt-1 meta"><a href="{{ route('backend.admin.tools.index') }}">Tool library</a>@if($tool->exists) · <span class="font-mono">{{ $tool->slug }}</span> · {{ \App\Models\Tool::STATUSES[$tool->status] }}@if($tool->status === 'published') · <a href="{{ $tool->url() }}" target="_blank" rel="noopener">View public page</a>@endif @endif</p></div>
    @if($tool->exists && $tool->status !== 'archived')
    <form method="post" action="{{ route('backend.admin.tools.destroy', $tool) }}" data-confirm="Archive this tool? It will be hidden from /guides and can no longer be downloaded. Download records are kept.">@csrf @method('DELETE')<button type="submit" class="btn-secondary">Archive</button></form>
    @endif
</div>
<div class="mt-6 grid gap-8 lg:grid-cols-3">
<form method="post" action="{{ $tool->exists ? route('backend.admin.tools.update', $tool) : route('backend.admin.tools.store') }}" class="lg:col-span-2 card-flat p-5 space-y-4">
    @csrf @if($tool->exists) @method('PUT') @endif
    <div class="grid gap-4 sm:grid-cols-2">
        <div class="sm:col-span-2"><label for="t-title" class="label">Title</label><input id="t-title" name="title" class="input" required maxlength="160" value="{{ old('title', $tool->title) }}"></div>
        <div><label for="t-slug" class="label">Slug (URL)</label><input id="t-slug" name="slug" class="input font-mono" required pattern="[a-z0-9]+(-[a-z0-9]+)*" value="{{ old('slug', $tool->slug) }}" placeholder="ai-system-inventory-template"></div>
        <div><label for="t-type" class="label">Content type</label><select id="t-type" name="type" class="input">@foreach(\App\Models\Tool::TYPES as $k => $l)<option value="{{ $k }}" @selected(old('type', $tool->type) === $k)>{{ $l }}</option>@endforeach</select></div>
        <div><label for="t-status" class="label">Status</label><select id="t-status" name="status" class="input">@foreach(\App\Models\Tool::STATUSES as $k => $l)<option value="{{ $k }}" @selected(old('status', $tool->status) === $k)>{{ $l }}</option>@endforeach</select>@error('status')<p class="mt-1 text-xs text-state-bad">{{ $message }}</p>@enderror</div>
        <div><label for="t-version" class="label">Version</label><input id="t-version" name="version" class="input font-mono" required maxlength="16" value="{{ old('version', $tool->version) }}"></div>
        <div><label for="t-updated" class="label">Last updated</label><input id="t-updated" type="date" name="updated_on" class="input" value="{{ old('updated_on', $tool->updated_on?->toDateString()) }}"></div>
        <div><label for="t-order" class="label">Sort order</label><input id="t-order" type="number" name="sort_order" class="input" min="0" value="{{ old('sort_order', $tool->sort_order ?? 0) }}"></div>
        <div class="sm:col-span-2"><label for="t-short" class="label">Short description (card and meta)</label><textarea id="t-short" name="short" class="input" rows="2" required maxlength="300">{{ old('short', $tool->short) }}</textarea></div>
        <div class="sm:col-span-2"><label for="t-purpose" class="label">What it is for</label><textarea id="t-purpose" name="purpose" class="input" rows="4" maxlength="4000">{{ old('purpose', $tool->purpose) }}</textarea></div>
        <div class="sm:col-span-2"><label for="t-fields" class="label">Fields (one per line: <span class="font-mono">Field name | what to record</span>)</label><textarea id="t-fields" name="fields_text" class="input font-mono text-xs" rows="8">{{ old('fields_text', collect($tool->fields ?? [])->map(fn ($f) => $f[0].' | '.($f[1] ?? ''))->implode("\n")) }}</textarea></div>
        <div class="sm:col-span-2"><label for="t-instr" class="label">How to use it (one step per line)</label><textarea id="t-instr" name="instructions_text" class="input" rows="4">{{ old('instructions_text', implode("\n", $tool->instructions ?? [])) }}</textarea></div>
        <div><span class="label">Frameworks</span>@foreach($fw as $k => $l)<label class="flex items-center gap-2 text-sm"><input type="checkbox" name="frameworks[]" value="{{ $k }}" @checked(in_array($k, old('frameworks', $tool->frameworks ?? []), true))>{{ $l }}</label>@endforeach</div>
        <div><span class="label">Topics</span>@foreach($topics as $k => $l)<label class="flex items-center gap-2 text-sm"><input type="checkbox" name="topics[]" value="{{ $k }}" @checked(in_array($k, old('topics', $tool->topics ?? []), true))>{{ $l }}</label>@endforeach</div>
        <div><label for="t-guides" class="label">Related guides</label><select id="t-guides" name="related_guides[]" class="input" multiple size="5">@foreach($guides as $k => $l)<option value="{{ $k }}" @selected(in_array($k, old('related_guides', $tool->related_guides ?? []), true))>{{ $l }}</option>@endforeach</select></div>
        <div><label for="t-policies" class="label">Related policy slugs (comma-separated)</label><input id="t-policies" name="related_policies_text" class="input font-mono text-xs" value="{{ old('related_policies_text', implode(', ', $tool->related_policies ?? [])) }}" placeholder="eu-ai-act, us-nist-ai-rmf"><label for="t-next" class="label mt-3">Next step tool</label><select id="t-next" name="next_slug" class="input"><option value="">None</option>@foreach($tools as $o)<option value="{{ $o->slug }}" @selected(old('next_slug', $tool->next_slug) === $o->slug)>{{ $o->title }}</option>@endforeach</select></div>
        <div><label for="t-seo-title" class="label">SEO title (optional)</label><input id="t-seo-title" name="seo_title" class="input" maxlength="160" value="{{ old('seo_title', $tool->seo_title) }}"></div>
        <div><label for="t-seo-desc" class="label">SEO description (optional)</label><input id="t-seo-desc" name="seo_description" class="input" maxlength="300" value="{{ old('seo_description', $tool->seo_description) }}"></div>
        <div class="sm:col-span-2"><label class="flex items-center gap-2 text-sm"><input type="checkbox" name="featured" value="1" @checked(old('featured', $tool->featured))>Featured (shown first)</label></div>
    </div>
    <button type="submit" class="btn-primary">{{ $tool->exists ? 'Save changes' : 'Create tool' }}</button>
</form>
<aside class="space-y-6">
    <section class="card-flat p-5" aria-labelledby="files-h">
        <h2 id="files-h" class="section-title !text-lg">Files</h2>
        @if(!$tool->exists)<p class="mt-2 text-sm text-brand-muted">Create the tool first, then upload files here.</p>@else
        <ul class="mt-3 divide-y divide-brand-line text-sm">
            @forelse($tool->files as $f)
            <li class="py-2">
                <div class="flex flex-wrap items-center justify-between gap-2"><span class="font-mono">{{ $f->file_name }}</span><span class="badge-neutral">{{ $f->label }}</span></div>
                <p class="meta mt-0.5">v{{ $f->version }} · {{ number_format($f->size / 1024, 1) }} KB · {{ $f->download_count ?: '—' }} downloads · {{ $f->is_active ? 'active' : 'inactive' }}@if(!$f->exists()) · <span class="text-state-bad">file missing on disk</span>@endif</p>
                <div class="mt-1.5 flex flex-wrap gap-1.5">
                    <a href="{{ route('backend.admin.tools.files.download', [$tool, $f]) }}" class="btn-secondary !min-h-0 !py-1">Download</a>
                    <form method="post" action="{{ route('backend.admin.tools.files.toggle', [$tool, $f]) }}">@csrf<button type="submit" class="btn-secondary !min-h-0 !py-1">{{ $f->is_active ? 'Deactivate' : 'Activate' }}</button></form>
                    <form method="post" action="{{ route('backend.admin.tools.files.destroy', [$tool, $f]) }}" data-confirm="Remove this file permanently?">@csrf @method('DELETE')<button type="submit" class="btn-secondary !min-h-0 !py-1">Remove</button></form>
                </div>
            </li>
            @empty<li class="py-2 text-brand-muted">No files yet.</li>@endforelse
        </ul>
        <form method="post" action="{{ route('backend.admin.tools.files.store', $tool) }}" enctype="multipart/form-data" class="mt-4 space-y-2 text-sm">@csrf
            <div><label for="f-file" class="label">Upload file</label><input id="f-file" type="file" name="file" required accept=".xlsx,.csv,.md,.pdf,.docx,.json,.txt" class="block w-full text-sm"><p class="meta mt-1">XLSX, CSV, Markdown, PDF, DOCX, JSON or text; up to 10 MB. Same name replaces the existing file. Include version, date and the informational-only note inside the file.</p>@error('file')<p class="text-xs text-state-bad">{{ $message }}</p>@enderror</div>
            <div class="grid grid-cols-2 gap-2"><div><label for="f-label" class="label">Label</label><input id="f-label" name="label" class="input" maxlength="40" placeholder="auto"></div><div><label for="f-version" class="label">Version</label><input id="f-version" name="version" class="input font-mono" maxlength="16" placeholder="{{ $tool->version }}"></div></div>
            <button type="submit" class="btn-primary">Upload</button>
        </form>
        @endif
    </section>
    <section class="card-flat p-5 text-sm"><h2 class="section-title !text-lg">Checklist before publishing</h2><ul class="mt-2 list-disc pl-5 space-y-1 text-brand-body"><li>At least one active file with version and date inside it.</li><li>Fields and instructions filled so the preview is useful without downloading.</li><li>Frameworks and topics set so filters find it.</li><li>Related guides and policies linked (no dead ends).</li></ul></section>
</aside>
</div>
@endsection
