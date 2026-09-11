<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="robots" content="noindex,nofollow">
    <title>Review queue | AIPolicyTracker admin</title>
    @vite(['resources/css/public.css', 'resources/js/public.js'])
</head>
<body class="bg-slate-50">
<div class="mx-auto max-w-7xl px-4 py-8">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <h1 class="text-2xl font-semibold text-slate-900">Review queue and publishing</h1>
        <nav class="text-sm flex gap-3"><a href="{{ route('backend.admin.dashboard') }}">Admin dashboard</a><a href="{{ route('backend.admin.submissions') }}">Submissions</a><a href="{{ route('backend.admin.subscribers') }}">Subscribers</a><a href="{{ route('backend.admin.settings') }}">Settings</a><a href="{{ route('home') }}">Public site</a></nav>
    </div>
    @if(session('success'))<p class="mt-4 rounded-md bg-emerald-50 border border-emerald-200 px-3 py-2 text-sm text-emerald-900" role="status">{{ session('success') }}</p>@endif
    <p class="mt-2 text-sm text-slate-600">Submissions are triaged here. Approved content must still be applied to the data/ directory through a pull request; the publish switch controls visibility of imported records only.</p>

    <section class="mt-6" aria-labelledby="sub-heading">
        <h2 id="sub-heading" class="section-title">Contributor submissions</h2>
        <div class="mt-2 flex flex-wrap gap-2 text-sm">@foreach(\App\Enums\SubmissionStatus::cases() as $s)<a class="chip {{ $status === $s->value ? 'chip-active' : '' }}" href="{{ route('backend.review.index', ['status' => $s->value]) }}">{{ $s->label() }} ({{ $counts[$s->value] ?? 0 }})</a>@endforeach</div>
        <div class="mt-3 space-y-3">
            @forelse($submissions as $s)
            <article class="card-flat p-4 text-sm">
                <div class="flex flex-wrap gap-2 text-xs text-slate-500"><span class="font-mono">#{{ $s->id }}</span><span>{{ \App\Models\ContributorSubmission::TYPES[$s->type] ?? $s->type }}</span><span>{{ $s->created_at->format('j M Y H:i') }}</span>@if($s->subject_slug)<span>{{ $s->subject_type }}: {{ $s->subject_slug }}</span>@endif</div>
                <p class="mt-1 font-medium text-slate-900">{{ $s->summary }}</p>
                @if($s->details)<p class="mt-1 whitespace-pre-line text-slate-700">{{ $s->details }}</p>@endif
                @if($s->proposed_source_url)<p class="mt-1"><a href="{{ $s->proposed_source_url }}" rel="noopener noreferrer" class="text-teal-800 break-all">{{ $s->proposed_source_url }}</a></p>@endif
                <p class="mt-1 text-xs text-slate-500">From: {{ $s->submitter_name ?: 'anonymous' }} {{ $s->submitter_email ? '<'.$s->submitter_email.'>' : '' }} {{ $s->submitter_affiliation }}@if($s->source_page) · via {{ $s->source_page }}@endif</p>
                @foreach($s->decisions as $d)<p class="mt-1 text-xs text-slate-600">Decision: {{ $d->decision }} by {{ $d->reviewer?->name ?? 'unknown' }} on {{ $d->decided_at->format('j M Y') }}@if($d->notes) — {{ $d->notes }}@endif</p>@endforeach
                <form method="post" action="{{ route('backend.review.decide', $s) }}" class="mt-3 flex flex-wrap gap-2 items-end">@csrf
                    <div><label for="d-{{ $s->id }}" class="label">Decision</label><select id="d-{{ $s->id }}" name="decision" class="input !min-h-0"><option value="approved">Approve</option><option value="needs_information">Needs information</option><option value="rejected">Reject</option></select></div>
                    <div class="flex-1 min-w-[12rem]"><label for="n-{{ $s->id }}" class="label">Notes</label><input id="n-{{ $s->id }}" name="notes" class="input !min-h-0" maxlength="2000"></div>
                    <button type="submit" class="btn-primary !min-h-0">Record decision</button>
                </form>
            </article>
            @empty<p class="text-sm text-slate-600">Nothing in this queue.</p>@endforelse
        </div>
        <div class="mt-4">{{ $submissions->links() }}</div>
    </section>

    <section class="mt-10" aria-labelledby="pub-heading">
        <h2 id="pub-heading" class="section-title">Published records</h2>
        <div class="table-wrap mt-3"><table><thead><tr><th scope="col">Policy</th><th scope="col">Jurisdiction</th><th scope="col">Review status</th><th scope="col">Last verified</th><th scope="col">Published</th><th scope="col">Action</th></tr></thead><tbody>
            @foreach($policies as $p)<tr><td><a href="{{ $p->url() }}" class="text-slate-900">{{ $p->short_title ?: $p->title }}</a></td><td>{{ $p->jurisdiction->name }}</td><td>{{ $p->review_status }}</td><td>{{ $p->last_verified_at?->format('j M Y') ?? '—' }}</td><td>{{ $p->published_at ? 'yes' : 'no' }}</td><td><form method="post" action="{{ route('backend.review.publish', ['type' => 'policy', 'slug' => $p->slug]) }}">@csrf<input type="hidden" name="publish" value="{{ $p->published_at ? 0 : 1 }}"><button type="submit" class="btn-secondary !min-h-0 !py-1">{{ $p->published_at ? 'Unpublish' : 'Publish' }}</button></form></td></tr>@endforeach
        </tbody></table></div>
    </section>
</div>
</body>
</html>
