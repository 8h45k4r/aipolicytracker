@extends('backend.layouts.app', ['title' => 'Review queue'])
@section('content')
<h1 class="font-display text-2xl font-semibold text-brand-navy">Review queue and publishing</h1>
<p class="mt-1 meta">Submissions are triaged here. Approved content must still be applied to the data/ directory through a pull request; the publish switch controls the visibility of imported records only.</p>

<section class="mt-6" aria-labelledby="sub-heading">
    <div class="flex flex-wrap items-baseline justify-between gap-3"><h2 id="sub-heading" class="section-title !text-lg">Contributor submissions</h2><a href="{{ route('backend.admin.submissions') }}" class="text-sm">All submissions and feedback</a></div>
    <div class="mt-2 flex flex-wrap gap-2 text-sm">@foreach(\App\Enums\SubmissionStatus::cases() as $s)<a class="chip {{ $status === $s->value ? 'chip-active' : '' }}" href="{{ route('backend.review.index', ['status' => $s->value]) }}">{{ $s->label() }} ({{ $counts[$s->value] ?? 0 }})</a>@endforeach</div>
    <div class="mt-3 space-y-3">
        @forelse($submissions as $s)
        <article class="card-flat p-4 text-sm">
            <div class="flex flex-wrap gap-2 meta"><span class="font-mono">#{{ $s->id }}</span><span>{{ \App\Models\ContributorSubmission::TYPES[$s->type] ?? $s->type }}</span><span>{{ $s->created_at->format('j M Y H:i') }}</span>@if($s->subject_slug)<span>{{ $s->subject_type }}: {{ $s->subject_slug }}</span>@endif</div>
            <p class="mt-1 font-medium text-brand-navy">{{ $s->summary }}</p>
            @if($s->details)<p class="mt-1 whitespace-pre-line text-brand-body">{{ $s->details }}</p>@endif
            @if($s->proposed_source_url)<p class="mt-1"><a href="{{ $s->proposed_source_url }}" rel="noopener noreferrer" class="break-all">{{ $s->proposed_source_url }}</a></p>@endif
            <p class="mt-1 meta">From: {{ $s->submitter_name ?: 'anonymous' }} {{ $s->submitter_email ? '<'.$s->submitter_email.'>' : '' }} {{ $s->submitter_affiliation }}@if($s->source_page) · via {{ $s->source_page }}@endif</p>
            @foreach($s->decisions as $d)<p class="mt-1 meta">Decision: {{ $d->decision }} by {{ $d->reviewer?->name ?? 'unknown' }} on {{ $d->decided_at->format('j M Y') }}@if($d->notes) — {{ $d->notes }} @endif @if($d->public_note) · published: {{ $d->public_note }} @endif</p>@endforeach
            <form method="post" action="{{ route('backend.review.decide', $s) }}" class="mt-3 flex flex-wrap gap-2 items-end">@csrf
                <div><label for="d-{{ $s->id }}" class="label">Decision</label><select id="d-{{ $s->id }}" name="decision" class="input !min-h-0"><option value="approved">Approve</option><option value="needs_information">Needs information</option><option value="rejected">Reject</option></select></div>
                <div class="flex-1 min-w-[12rem]"><label for="n-{{ $s->id }}" class="label">Notes (internal)</label><input id="n-{{ $s->id }}" name="notes" class="input !min-h-0" maxlength="2000"></div>
                <div class="flex-1 min-w-[12rem]"><label for="pn-{{ $s->id }}" class="label">Public note</label><input id="pn-{{ $s->id }}" name="public_note" class="input !min-h-0" maxlength="500" placeholder="Shown on /corrections. Leave empty to publish the facts only."></div>
                <button type="submit" class="btn-primary !min-h-0">Record decision</button>
            </form>
        </article>
        @empty<x-site.empty title="Nothing in this queue" />@endforelse
    </div>
    <nav class="mt-4" aria-label="Pagination">{{ $submissions->links() }}</nav>
</section>

<section class="mt-10" aria-labelledby="pub-heading">
    <h2 id="pub-heading" class="section-title !text-lg">Policy instruments</h2>
    <p class="mt-1 meta">{{ $policies->count() }} imported records; unverified and low-confidence first. Open the official source, then save the review status and confidence. Decisions survive re-imports @if($pendingExport); <strong>{{ $pendingExport }}</strong> not yet written back to data/ (run <code>php artisan policy:export-verifications</code> and open a pull request)@endif. Unpublishing hides a record from the public site, API and sitemaps.</p>
    <form method="post" action="{{ route('backend.review.verify.many', ['type' => 'policy']) }}" id="bulk-policies">@csrf
        <div class="mt-3 flex flex-wrap items-end gap-2 rounded-sm border border-brand-line bg-white p-3 text-xs">
            <p class="w-full text-sm font-medium text-brand-navy">Verify the selected records</p>
            <p class="w-full meta">One attestation for the selection. Tick only what you have actually opened and confirmed: each record is dated and published under your name, and the site tells readers a person checked it.</p>
            <label class="flex items-center gap-1"><input type="checkbox" data-bulk-all> select all shown</label>
            <select name="review_status" class="input !min-h-0 !py-1 !w-auto" aria-label="Review status for the selection"><option value="verified">verified</option><option value="needs_update">needs update</option><option value="pending_review">pending</option></select>
            <select name="confidence_level" class="input !min-h-0 !py-1 !w-auto" aria-label="Confidence for the selection">@foreach(['high','medium','low','unavailable'] as $c)<option value="{{ $c }}" @selected($c === 'high')>{{ $c }}</option>@endforeach</select>
            <label class="flex items-center gap-1"><input type="checkbox" name="source_opened" value="1"> I opened the official source of every selected record</label>
            <button type="submit" class="btn-primary !min-h-0 !py-1">Save selected</button>
        </div>
    <div class="table-wrap mt-3 bg-white"><table><thead><tr><th scope="col"><span class="sr-only">Select</span></th><th scope="col">Policy</th><th scope="col">Jurisdiction</th><th scope="col">Review status</th><th scope="col">Last verified</th><th scope="col">Published</th><th scope="col">Action</th></tr></thead><tbody>
        @foreach($policies as $p)<tr>
            <td><input type="checkbox" name="slugs[]" value="{{ $p->slug }}" form="bulk-policies" aria-label="Select {{ $p->short_title ?: $p->title }}" data-bulk-item></td>
            <td><a href="{{ $p->url() }}">{{ $p->short_title ?: $p->title }}</a><div class="meta">@if($p->official_source_url)<a href="{{ $p->official_source_url }}" target="_blank" rel="noopener">Open official source ↗</a>@else no source URL @endif · confidence {{ $p->confidence_level }}@if($p->date_notes && str_contains($p->date_notes, 'not established')) · <span class="text-state-warn">date missing</span>@endif</div></td>
            <td>{{ $p->jurisdiction->name }}</td>
            <td><span class="badge {{ $p->review_status === 'verified' ? 'bg-state-goodbg text-state-good ring-state-good/30' : 'badge-neutral' }}">{{ $p->review_status }}</span></td>
            <td>{{ $p->last_verified_at?->format('j M Y') ?? '—' }}@if($p->reviewed_by)<div class="meta">{{ $p->reviewed_by }}</div>@endif</td>
            <td>{{ $p->published_at ? 'yes' : 'no' }}</td>
            <td class="min-w-[320px]">
                <form method="post" action="{{ route('backend.review.verify', ['type' => 'policy', 'slug' => $p->slug]) }}" class="flex flex-wrap items-end gap-1.5 text-xs">@csrf
                    <select name="review_status" class="input !min-h-0 !py-1 !w-auto" aria-label="Review status"><option value="verified">verified</option><option value="needs_update">needs update</option><option value="pending_review" @selected($p->review_status !== 'verified')>pending</option></select>
                    <select name="confidence_level" class="input !min-h-0 !py-1 !w-auto" aria-label="Confidence">@foreach(['high','medium','low','unavailable'] as $c)<option value="{{ $c }}" @selected($p->confidence_level === $c)>{{ $c }}</option>@endforeach</select>
                    <label class="flex items-center gap-1"><input type="checkbox" name="source_opened" value="1">source opened</label>
                    <button type="submit" class="btn-secondary !min-h-0 !py-1">Save</button>
                </form>
                <form method="post" action="{{ route('backend.review.publish', ['type' => 'policy', 'slug' => $p->slug]) }}" class="mt-1">@csrf<input type="hidden" name="publish" value="{{ $p->published_at ? 0 : 1 }}"><button type="submit" class="btn-secondary !min-h-0 !py-1 text-xs">{{ $p->published_at ? 'Unpublish' : 'Publish' }}</button></form>
            </td></tr>@endforeach
    </tbody></table></div>
    </form>
</section>

<section class="mt-10" aria-labelledby="jur-heading">
    <h2 id="jur-heading" class="section-title !text-lg">Jurisdictions</h2>
    <div class="table-wrap mt-3 bg-white"><table><thead><tr><th scope="col">Jurisdiction</th><th scope="col">Region</th><th scope="col">Review status</th><th scope="col">Published</th><th scope="col">Action</th></tr></thead><tbody>
        @foreach($jurisdictions as $j)<tr><td><a href="{{ $j->url() }}">{{ $j->name }}</a></td><td>{{ $j->region ?: '—' }}</td><td><span class="badge-neutral">{{ $j->review_status }}</span></td><td>{{ $j->published_at ? 'yes' : 'no' }}</td><td><form method="post" action="{{ route('backend.review.publish', ['type' => 'jurisdiction', 'slug' => $j->slug]) }}">@csrf<input type="hidden" name="publish" value="{{ $j->published_at ? 0 : 1 }}"><button type="submit" class="btn-secondary !min-h-0 !py-1">{{ $j->published_at ? 'Unpublish' : 'Publish' }}</button></form></td></tr>@endforeach
    </tbody></table></div>
</section>

<section class="mt-10" aria-labelledby="ctl-heading">
    <h2 id="ctl-heading" class="section-title !text-lg">Controls</h2>
    <p class="mt-1 meta">A control is verified when a reviewer has read its purpose, evidence and clause references against the standards it cites and the duties it serves.</p>
    <div class="table-wrap mt-3"><table><caption class="sr-only">Controls</caption><thead><tr><th scope="col">Control</th><th scope="col">Kind</th><th scope="col">Duties</th><th scope="col">Review</th><th scope="col">Published</th><th scope="col">Verify</th><th scope="col">Publish</th></tr></thead><tbody>
    @foreach($controls as $c)<tr><td><a href="{{ $c->url() }}">{{ $c->title }}</a></td><td>{{ $c->kindLabel() }}</td><td class="tabular-nums">{{ $c->obligations_count }}</td><td><span class="badge-neutral">{{ $c->review_status }}</span>@if($c->reviewed_by)<div class="meta">{{ $c->reviewed_by }}</div>@endif</td><td>{{ $c->published_at ? 'yes' : 'no' }}</td>
        <td><form method="post" action="{{ route('backend.review.verify', ['type' => 'control', 'slug' => $c->slug]) }}" class="flex flex-wrap items-end gap-1.5 text-xs">@csrf
            <select name="review_status" class="input !min-h-0 !py-1 !text-xs w-36">@foreach(['verified', 'pending_review', 'needs_update'] as $st)<option value="{{ $st }}" @selected($c->review_status === $st)>{{ $st }}</option>@endforeach</select>
            <select name="confidence_level" class="input !min-h-0 !py-1 !text-xs w-28">@foreach(['high', 'medium', 'low', 'unavailable'] as $cl)<option value="{{ $cl }}" @selected($c->confidence_level === $cl)>{{ $cl }}</option>@endforeach</select>
            <label class="flex items-center gap-1"><input type="checkbox" name="source_opened" value="1">references checked</label>
            <button type="submit" class="btn-secondary !min-h-0 !py-1">Save</button></form></td>
        <td><form method="post" action="{{ route('backend.review.publish', ['type' => 'control', 'slug' => $c->slug]) }}">@csrf<input type="hidden" name="publish" value="{{ $c->published_at ? 0 : 1 }}"><button type="submit" class="btn-secondary !min-h-0 !py-1 text-xs">{{ $c->published_at ? 'Unpublish' : 'Publish' }}</button></form></td></tr>@endforeach
    </tbody></table></div>
</section>
@endsection
