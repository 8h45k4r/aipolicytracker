@extends('backend.layouts.app', ['title' => 'Submissions and feedback'])
@section('content')
<h1 class="font-display text-2xl font-semibold text-brand-navy">Submissions and feedback</h1>
<p class="mt-1 meta">Everything sent through the public contribution form: corrections, proposed sources, new policy records and reviewer applications.</p>
<div class="mt-4 flex flex-wrap gap-2 text-sm">
    <a class="chip {{ !$type ? 'chip-active' : '' }}" href="{{ route('backend.admin.submissions', ['status' => $status]) }}">All types</a>
    @foreach(\App\Models\ContributorSubmission::TYPES as $k => $label)<a class="chip {{ $type === $k ? 'chip-active' : '' }}" href="{{ route('backend.admin.submissions', ['type' => $k, 'status' => $status]) }}">{{ $label }} ({{ $byType[$k] ?? 0 }})</a>@endforeach
</div>
<div class="mt-2 flex flex-wrap gap-2 text-sm">
    <a class="chip {{ !$status ? 'chip-active' : '' }}" href="{{ route('backend.admin.submissions', ['type' => $type]) }}">Any status</a>
    @foreach(\App\Enums\SubmissionStatus::cases() as $s)<a class="chip {{ $status === $s->value ? 'chip-active' : '' }}" href="{{ route('backend.admin.submissions', ['type' => $type, 'status' => $s->value]) }}">{{ $s->label() }} ({{ $byStatus[$s->value] ?? 0 }})</a>@endforeach
</div>
@if($submissions->isEmpty())<div class="mt-6"><x-site.empty title="No submissions match" /></div>@else
<div class="mt-6 divide-y divide-brand-line border-y border-brand-line bg-white">
    @foreach($submissions as $s)
    <article class="p-4">
        <div class="flex flex-wrap gap-2 meta"><span class="font-mono">#{{ $s->id }}</span><span>{{ \App\Models\ContributorSubmission::TYPES[$s->type] ?? $s->type }}</span><span>{{ $s->created_at->format('j M Y H:i') }}</span><span class="badge-neutral">{{ $s->status }}</span>@if($s->subject_slug)<span>{{ $s->subject_type }}: {{ $s->subject_slug }}</span>@endif</div>
        <p class="mt-2 font-medium text-brand-navy">{{ $s->summary }}</p>
        @if($s->details)<p class="mt-1 text-sm text-brand-body whitespace-pre-line">{{ $s->details }}</p>@endif
        <dl class="mt-2 grid gap-x-6 gap-y-1 sm:grid-cols-2 text-xs text-brand-muted">
            <div><dt class="inline">Source proposed:</dt> <dd class="inline">@if($s->proposed_source_url)<a href="{{ $s->proposed_source_url }}" rel="noopener nofollow">{{ \Illuminate\Support\Str::limit($s->proposed_source_url, 70) }}</a>@else —@endif</dd></div>
            <div><dt class="inline">From:</dt> <dd class="inline">{{ $s->submitter_name ?: '—' }}@if($s->submitter_email) · <a href="mailto:{{ $s->submitter_email }}">{{ $s->submitter_email }}</a>@endif @if($s->submitter_affiliation)· {{ $s->submitter_affiliation }}@endif</dd></div>
            <div><dt class="inline">Page:</dt> <dd class="inline">{{ $s->source_page ? \Illuminate\Support\Str::limit($s->source_page, 70) : '—' }}</dd></div>
        </dl>
        @foreach($s->decisions as $d)<p class="mt-1 text-xs text-brand-muted">Decision: {{ $d->decision }} by {{ $d->reviewer?->name ?? 'unknown' }} on {{ $d->decided_at->format('j M Y') }}@if($d->notes) — {{ $d->notes }}@endif</p>@endforeach
        <form method="post" action="{{ route('backend.review.decide', $s) }}" class="mt-3 flex flex-wrap gap-2 items-end">@csrf
            <div><label for="d-{{ $s->id }}" class="label">Decision</label><select id="d-{{ $s->id }}" name="decision" class="input !min-h-0"><option value="approved">Approve</option><option value="needs_information">Needs information</option><option value="rejected">Reject</option></select></div>
            <div class="flex-1 min-w-[240px]"><label for="n-{{ $s->id }}" class="label">Notes</label><input id="n-{{ $s->id }}" name="notes" class="input !min-h-0" maxlength="2000"></div>
            <button type="submit" class="btn-primary !min-h-0 !py-2">Record</button>
        </form>
    </article>
    @endforeach
</div>
<nav class="mt-4" aria-label="Pagination">{{ $submissions->links() }}</nav>
@endif
@endsection
