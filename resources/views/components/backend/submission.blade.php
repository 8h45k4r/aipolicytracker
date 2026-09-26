{{-- One contributor submission with its history and, when the page has a bulk form, a
     checkbox that joins it to that form. The per-card form still records one decision. --}}
@props(['submission', 'bulk' => null])
@php($s = $submission)
<article class="p-4 text-sm {{ $bulk ? 'flex gap-3' : '' }}" id="submission-{{ $s->id }}">
    @if($bulk)<div class="pt-0.5"><input type="checkbox" name="ids[]" value="{{ $s->id }}" form="{{ $bulk }}" data-bulk-item aria-label="Select submission {{ $s->id }}: {{ \Illuminate\Support\Str::limit($s->summary, 60) }}"></div>@endif
    <div class="min-w-0 flex-1">
        <div class="flex flex-wrap items-center gap-2 meta"><span class="font-mono">#{{ $s->id }}</span><span>{{ \App\Models\ContributorSubmission::TYPES[$s->type] ?? $s->type }}</span><span>{{ $s->created_at->format('j M Y H:i') }}</span><x-backend.badge :status="$s->status" />@if($s->subject_slug)<span>{{ $s->subject_type }}: {{ $s->subject_slug }}</span>@endif</div>
        <p class="mt-1 font-medium text-brand-navy">{{ $s->summary }}</p>
        @if($s->details)<p class="mt-1 whitespace-pre-line text-brand-body">{{ $s->details }}</p>@endif
        @if(!empty($s->payload['field']) || !empty($s->payload['record_title']))
        <div class="mt-2 rounded-sm border border-brand-line bg-brand-paper p-3">
            @if(!empty($s->payload['record_title']))<p class="font-medium text-brand-navy">{{ $s->payload['record_title'] }}@if(!empty($s->payload['record_url'])) <a href="{{ $s->payload['record_url'] }}" class="text-xs font-normal">open record</a>@endif @if(!empty($s->payload['record_content_version'])) <span class="text-xs font-normal text-brand-muted">· content v{{ $s->payload['record_content_version'] }} at submission</span>@endif</p>@endif
            @if(!empty($s->payload['field']))<dl class="mt-1 grid gap-1 sm:grid-cols-3 text-xs"><div><dt class="text-brand-muted">Field</dt><dd class="font-mono">{{ $s->payload['field'] }}</dd></div><div><dt class="text-brand-muted">Shown at submission</dt><dd class="whitespace-pre-line">{{ $s->payload['current_value'] ?? '—' }}</dd></div><div><dt class="text-brand-muted">Proposed</dt><dd class="whitespace-pre-line text-brand-navy">{{ $s->payload['proposed_value'] ?? '—' }}</dd></div></dl>@endif
            @if(!empty($s->payload['record_official_source_url']))<p class="mt-1 text-xs text-brand-muted">Source on file: <a href="{{ $s->payload['record_official_source_url'] }}" rel="noopener nofollow">{{ \Illuminate\Support\Str::limit($s->payload['record_official_source_url'], 70) }}</a></p>@endif
        </div>
        @endif
        <dl class="mt-2 grid gap-x-6 gap-y-1 sm:grid-cols-2 text-xs text-brand-muted">
            <div><dt class="inline">Source proposed:</dt> <dd class="inline">@if($s->proposed_source_url)<a href="{{ $s->proposed_source_url }}" rel="noopener nofollow" class="break-all">{{ \Illuminate\Support\Str::limit($s->proposed_source_url, 70) }}</a>@else —@endif</dd></div>
            <div><dt class="inline">From:</dt> <dd class="inline">{{ $s->submitter_name ?: 'anonymous' }}@if($s->submitter_email) · <a href="mailto:{{ $s->submitter_email }}">{{ $s->submitter_email }}</a>@endif @if($s->submitter_affiliation)· {{ $s->submitter_affiliation }}@endif</dd></div>
            <div><dt class="inline">Page:</dt> <dd class="inline">{{ $s->source_page ? \Illuminate\Support\Str::limit($s->source_page, 70) : '—' }}</dd></div>
        </dl>
        @foreach($s->decisions as $d)<p class="mt-1 text-xs text-brand-muted">Decision: <x-backend.badge :status="$d->decision" /> by {{ $d->reviewer?->name ?? 'unknown' }} on {{ $d->decided_at->format('j M Y') }}@if($d->notes) — {{ $d->notes }}@endif @if($d->public_note) · published: {{ $d->public_note }}@endif</p>@endforeach
        <form method="post" action="{{ route('backend.review.decide', $s) }}" class="mt-3 flex flex-wrap items-end gap-2">@csrf
            <div><label for="d-{{ $s->id }}" class="label !mb-0.5 !text-xs">Decision</label><select id="d-{{ $s->id }}" name="decision" class="input !min-h-0 !py-1.5"><option value="approved">Approve</option><option value="needs_information">Needs information</option><option value="rejected">Reject</option></select></div>
            <div class="flex-1 min-w-[12rem]"><label for="n-{{ $s->id }}" class="label !mb-0.5 !text-xs">Notes (internal)</label><input id="n-{{ $s->id }}" name="notes" class="input !min-h-0 !py-1.5" maxlength="2000"></div>
            <div class="flex-1 min-w-[12rem]"><label for="pn-{{ $s->id }}" class="label !mb-0.5 !text-xs">Public note</label><input id="pn-{{ $s->id }}" name="public_note" class="input !min-h-0 !py-1.5" maxlength="500" placeholder="Shown on /corrections. Leave empty to publish the facts only."></div>
            <button type="submit" class="btn-secondary !min-h-0 !py-1.5">Record decision</button>
        </form>
    </div>
</article>
