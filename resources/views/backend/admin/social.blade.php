@extends('backend.layouts.app', ['title' => 'Posts to X'])
@section('content')
<x-backend.page-header title="Posts to X" description="Each new verified change becomes a post with its link, hashtags and mentions. Read, edit, approve or skip it here.">
    <x-slot:actions>
        <form method="post" action="{{ route('backend.admin.social.queue') }}" class="inline">@csrf<button class="btn-primary btn-sm" data-command="Check for new changes to post">Check for new changes</button></form>
        @if(($counts['draft'] ?? 0) > 0)
        <form method="post" action="{{ route('backend.admin.social.approve.all') }}" class="inline" data-confirm="Queue all {{ $counts['draft'] }} drafts? Each goes out on X within the monthly cap." data-confirm-label="Queue {{ $counts['draft'] }} drafts">@csrf<button class="btn-secondary btn-sm">Approve all drafts ({{ $counts['draft'] }})</button></form>
        @endif
        @can('settings.manage')<a href="{{ route('backend.admin.settings') }}#group-social" class="btn-secondary btn-sm">Keys and rules</a>@endcan
    </x-slot:actions>
</x-backend.page-header>

{{-- What the bot will do on its next run, in one line each. --}}
<div class="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-4" data-social-status>
    <x-backend.stat label="Posting" :value="$enabled ? 'On' : 'Off'" :tone="$enabled ? 'text-state-good' : 'text-brand-muted'" :hint="$enabled ? 'Runs every 30 minutes' : 'Turn on under Settings → Live switches'" />
    <x-backend.stat label="Before posting" :value="$mode === 'review' ? 'Approval' : 'Automatic'" />
    <x-backend.stat label="X keys" :value="$configured ? 'Set' : 'Missing'" :tone="$configured ? 'text-state-good' : 'text-state-bad'" />
    <x-backend.stat label="Posted this month" :value="$thisMonth.' / '.$cap" :tone="$thisMonth >= $cap ? 'text-state-bad' : null" />
</div>
<p class="meta mt-2">X bills each post that has a link, about $0.20 each since April 2026: this month's posts come to roughly ${{ number_format($thisMonth * 0.2, 2) }}. Check the current rate in the X developer console.</p>

@if($pending->isNotEmpty())
<div class="mt-4 rounded-sm border border-brand-line bg-white px-4 py-3 text-sm" data-social-pending>
    <p class="font-medium text-brand-navy">{{ $pending->count() }} new verified {{ \Illuminate\Support\Str::plural('change', $pending->count()) }} not yet turned into a post</p>
    <ul class="mt-1 list-disc pl-5 text-brand-body">@foreach($pending->take(5) as $c)<li>{{ $c->title }} <span class="meta">({{ $c->occurred_on->format('j M Y') }})</span></li>@endforeach</ul>
    <p class="meta mt-1">The next run adds them, or use "Check for new changes".</p>
</div>
@endif

<nav class="mt-6 flex flex-wrap gap-2" aria-label="Filter by status">
    <a href="{{ route('backend.admin.social.index') }}" class="btn-sm {{ $status === null ? 'btn-primary' : 'btn-secondary' }}" @if($status === null) aria-current="page" @endif>All ({{ $counts->sum() }})</a>
    @foreach(\App\Models\SocialPost::STATUSES as $key => $label)
    <a href="{{ route('backend.admin.social.index', ['status' => $key]) }}" class="btn-sm {{ $status === $key ? 'btn-primary' : 'btn-secondary' }}" @if($status === $key) aria-current="page" @endif>{{ $label }} ({{ $counts[$key] ?? 0 }})</a>
    @endforeach
</nav>

@if($posts->isEmpty())
<div class="mt-4"><x-site.empty title="No posts {{ $status ? 'with this status' : 'yet' }}">A post appears here when a change is published and verified. Only changes from the last {{ config('social.x.max_age_days') }} days are posted automatically; add an older one below.</x-site.empty></div>
@else
<ul class="mt-4 grid gap-4 lg:grid-cols-2" data-social-posts>
@foreach($posts as $post)
@php($weight = \App\Services\Social\ChangePost::weight($post->text))
<li class="card-flat flex flex-col p-4" data-social-post="{{ $post->id }}">
    <div class="flex flex-wrap items-center justify-between gap-2">
        <x-backend.badge :status="match ($post->status) { 'posted' => 'published', 'queued' => 'running', 'skipped' => 'archived', default => $post->status }">{{ \App\Models\SocialPost::STATUSES[$post->status] }}</x-backend.badge>
        <span class="meta tabular-nums {{ $weight > \App\Services\Social\ChangePost::LIMIT ? 'text-state-bad' : '' }}">{{ $weight }}/{{ \App\Services\Social\ChangePost::LIMIT }}</span>
    </div>
    {{-- The post as it will read on X: line breaks kept, links shown whole. --}}
    <p class="mt-3 flex-1 whitespace-pre-line break-words rounded-sm border border-brand-line bg-brand-paper p-3 text-sm leading-6 text-brand-ink" lang="en">{{ $post->text }}</p>
    <p class="meta mt-2">
        @if($post->changeEvent)Change: <a href="{{ $post->changeEvent->url() }}" target="_blank" rel="noopener">{{ \Illuminate\Support\Str::limit($post->changeEvent->title, 70) }}</a> · {{ $post->changeEvent->occurred_on->format('j M Y') }}@endif
        @if($post->approver) · approved by {{ $post->approver->name }}@endif
        @if($post->posted_at) · posted {{ $post->posted_at->diffForHumans() }}@endif
        @if($post->attempts) · {{ $post->attempts }} {{ \Illuminate\Support\Str::plural('attempt', $post->attempts) }}@endif
    </p>
    @if($post->error && $post->status !== 'posted')<p class="mt-2 rounded-sm border border-state-bad/30 bg-state-badbg px-3 py-2 text-sm text-state-bad" role="status">{{ $post->error }}</p>@endif
    <div class="mt-3 flex flex-wrap gap-2">
        @if($post->status === 'posted' && $post->externalUrl())
            <a href="{{ $post->externalUrl() }}" class="btn-secondary btn-sm" target="_blank" rel="noopener">View on X</a>
        @endif
        @if(in_array($post->status, ['draft', 'failed', 'skipped'], true))
            <form method="post" action="{{ route('backend.admin.social.approve', $post) }}" class="inline">@csrf<button class="btn-primary btn-sm">{{ $post->status === 'draft' ? 'Approve' : 'Queue again' }}</button></form>
        @endif
        @if(in_array($post->status, ['draft', 'queued', 'failed'], true))
            <form method="post" action="{{ route('backend.admin.social.send', $post) }}" class="inline" data-confirm="Post this to X now? It is public at once and X bills it." data-confirm-label="Post now">@csrf<button class="btn-secondary btn-sm">Post now</button></form>
        @endif
        @if($post->editable())
            <a href="#edit-{{ $post->id }}" class="btn-secondary btn-sm" data-drawer-open="social-edit-{{ $post->id }}">Edit text</a>
        @endif
        @if(in_array($post->status, ['draft', 'queued', 'failed'], true))
            <form method="post" action="{{ route('backend.admin.social.skip', $post) }}" class="inline">@csrf<button class="btn-secondary btn-sm">Skip</button></form>
        @endif
    </div>
    @if($post->editable())
    {{-- Without JavaScript the form below is the edit form; with it, a side panel opens. --}}
    <x-backend.drawer id="social-edit-{{ $post->id }}" title="Edit post" description="280 characters, counting each link as 23. Keep the link: it is what the post is for." :open="old('_drawer') === 'social-edit-'.$post->id">
        <form id="social-edit-{{ $post->id }}-form" method="post" action="{{ route('backend.admin.social.update', $post) }}">@csrf @method('PUT')
            <input type="hidden" name="_drawer" value="social-edit-{{ $post->id }}">
            <label for="text-{{ $post->id }}" class="label">Post text</label>
            <textarea id="text-{{ $post->id }}" name="text" rows="10" class="input font-mono text-sm" @error('text') aria-invalid="true" @enderror>{{ old('_drawer') === 'social-edit-'.$post->id ? old('text') : $post->text }}</textarea>
            @if(old('_drawer') === 'social-edit-'.$post->id)@error('text')<p class="mt-1 text-xs font-medium text-state-bad">{{ $message }}</p>@enderror @endif
        </form>
        <x-slot:footer><button type="button" class="btn-secondary" data-drawer-close>Cancel</button><button type="submit" form="social-edit-{{ $post->id }}-form" class="btn-primary">Save text</button></x-slot:footer>
    </x-backend.drawer>
    @endif
</li>
@endforeach
</ul>
<div class="mt-4">{{ $posts->links() }}</div>
@endif

<section class="mt-8 card-flat p-5" aria-labelledby="social-add-title">
    <h2 id="social-add-title" class="section-title !text-lg">Post an older change</h2>
    <p class="meta mt-1">For a verified change the automatic rules passed over. It is added as a draft for you to read and approve.</p>
    <form method="post" action="{{ route('backend.admin.social.store') }}" class="mt-3 flex flex-wrap items-end gap-3">@csrf
        <div class="min-w-[16rem] flex-1">
            <label for="social-change" class="label">Change</label>
            <select id="social-change" name="change" class="input" @error('change') aria-invalid="true" @enderror>
                @foreach($recent as $c)<option value="{{ $c->slug }}" @selected(old('change') === $c->slug)>{{ $c->occurred_on->format('Y-m-d') }} · {{ \Illuminate\Support\Str::limit($c->title, 90) }}</option>@endforeach
            </select>
            @error('change')<p class="mt-1 text-xs font-medium text-state-bad">{{ $message }}</p>@enderror
        </div>
        <button class="btn-secondary">Add as draft</button>
    </form>
</section>
@endsection
