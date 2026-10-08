@extends('backend.layouts.app', ['title' => 'Review queue'])
@section('content')
<x-backend.page-header title="Review queue" description="Imported records are confirmed against their official source and switched on or off for the public site, API and sitemaps. Tick rows, then act on the selection: one attestation covers the whole selection and every record still gets its own dated, named verification. Submissions are decided on the Submissions page.">
    <x-slot:actions>
        <a href="{{ route('backend.admin.submissions', ['status' => 'pending_review']) }}" class="btn-secondary !min-h-0 !py-1.5" data-command="Decide waiting submissions">Submissions</a>
        @can('records.verify')<a href="{{ route('backend.checks.index') }}" class="btn-secondary !min-h-0 !py-1.5">Independent checks</a>@endcan
    </x-slot:actions>
</x-backend.page-header>

{{-- Submissions are decided in one place, the Submissions page. The queue only says what is
     waiting and links there, so there is never a second form for the same decision. --}}
<section class="mt-6" aria-labelledby="sub-heading" data-submissions-summary>
    <div class="flex flex-wrap items-baseline justify-between gap-3"><h2 id="sub-heading" class="section-title !text-lg">Contributor submissions</h2><a href="{{ route('backend.admin.submissions', ['status' => 'pending_review']) }}" class="text-sm">Decide them on the Submissions page →</a></div>
    <div class="mt-2 flex flex-wrap gap-2 text-sm">@foreach(\App\Enums\SubmissionStatus::cases() as $s)<a class="chip" href="{{ route('backend.admin.submissions', ['status' => $s->value]) }}">{{ $s->label() }} ({{ $counts[$s->value] ?? 0 }})</a>@endforeach</div>
    @if($waiting->isEmpty())
    <p class="mt-3 meta">No submission is waiting for review.</p>
    @else
    <ul class="mt-3 divide-y divide-brand-line border-y border-brand-line bg-white text-sm">
        @foreach($waiting as $s)
        <li class="flex flex-wrap items-baseline gap-x-3 gap-y-0.5 px-3 py-2"><span class="font-mono text-xs text-brand-muted">#{{ $s->id }}</span><a href="{{ route('backend.admin.submissions', ['status' => 'pending_review']) }}#submission-{{ $s->id }}" class="min-w-0 flex-1">{{ \Illuminate\Support\Str::limit($s->summary, 120) }}</a><span class="meta">{{ \App\Models\ContributorSubmission::TYPES[$s->type] ?? $s->type }} · {{ $s->created_at->format('j M Y') }}</span></li>
        @endforeach
    </ul>
    @if(($counts['pending_review'] ?? 0) > $waiting->count())<p class="mt-2 meta">The latest {{ $waiting->count() }} of {{ $counts['pending_review'] }} waiting.</p>@endif
    @endif
</section>

<section class="mt-10" aria-labelledby="rec-heading">
    <h2 id="rec-heading" class="section-title !text-lg">Imported records</h2>
    <p class="mt-1 meta max-w-3xl">Unverified and low-confidence first. Decisions survive re-imports{{ '' }}@if($pendingExport); <strong>{{ $pendingExport }}</strong> not yet written back to data/ (run <code>php artisan policy:export-verifications</code> and open a pull request)@endif.</p>

    {{-- One kind at a time: each tab is its own queue with its own counts. --}}
    <nav class="mt-3 flex flex-wrap gap-2 text-sm" aria-label="Record kinds">
        @foreach($tabs as $tab)
        <a class="chip {{ $type === $tab['key'] ? 'chip-active' : '' }}" href="{{ route('backend.review.index', ['type' => $tab['key']]) }}#rec-heading" @if($type === $tab['key']) aria-current="page" @endif>{{ $tab['label'] }} <span class="font-mono {{ $type === $tab['key'] ? 'text-white/80' : 'text-brand-muted' }}">{{ $tab['total'] }}</span>@if($tab['open']) <span class="rounded-sm px-1 {{ $type === $tab['key'] ? 'bg-white/20' : 'bg-state-warnbg text-state-warn' }}" title="not yet verified">{{ $tab['open'] }} open</span>@endif</a>
        @endforeach
    </nav>

    <form method="get" action="{{ route('backend.review.index') }}#rec-heading" class="mt-3 flex flex-wrap items-end gap-2 text-sm" data-autosubmit>
        <input type="hidden" name="type" value="{{ $type }}">
        <label class="text-sm"><span class="block meta">Search</span><input type="search" name="q" value="{{ $filters['q'] }}" placeholder="title or slug" class="input mt-1 !min-h-0 !py-1.5 w-56"></label>
        <label class="text-sm"><span class="block meta">Review status</span><select name="review" class="input mt-1 !min-h-0 !py-1.5 !w-auto"><option value="">Any</option>@foreach($reviewFilters as $r)<option value="{{ $r }}" @selected($filters['review'] === $r)>{{ $r === 'stale' ? 'never verified or stale' : str_replace('_', ' ', $r) }} ({{ $byReview[$r] ?? 0 }})</option>@endforeach</select></label>
        <label class="text-sm"><span class="block meta">Published</span><select name="published" class="input mt-1 !min-h-0 !py-1.5 !w-auto"><option value="">Any</option><option value="yes" @selected($filters['published'] === 'yes')>yes</option><option value="no" @selected($filters['published'] === 'no')>no ({{ $unpublished }})</option></select></label>
        <button class="btn-secondary !min-h-0 !py-1.5">Filter</button>
        @if($filters['q'] !== '' || $filters['review'] || $filters['published'])<a href="{{ route('backend.review.index', ['type' => $type]) }}#rec-heading" class="btn-secondary !min-h-0 !py-1.5">Clear</a>@endif
        <span class="meta ml-auto">{{ $matching }} {{ \Illuminate\Support\Str::plural('record', $matching) }} match{{ $records->hasPages() ? ', '.$records->count().' on this page' : '' }}</span>
        <a href="{{ route('backend.review.export', ['type' => $type] + array_filter($filters)) }}" class="btn-secondary !min-h-0 !py-1.5 text-sm" data-track="admin_export">Export CSV</a>
    </form>

    @if($rows->isEmpty())
    <div class="mt-3"><x-site.empty title="No {{ $meta['plural'] }} match" :reset="route('backend.review.index', ['type' => $type])">Change the filters, or clear them to see every record of this kind.</x-site.empty></div>
    @else
    @php($bulk = 'bulk-'.$type)
    {{-- A form that came back with errors keeps what the reviewer had chosen: the ticked rows,
         the scope, both selects, the notes and the attestation. --}}
    @php($picked = collect(old('slugs', []))->map(fn ($s) => (string) $s))
    @php($wholeFilter = old('scope') === 'filtered')
    @php($pickedCount = $wholeFilter ? 'all '.$matching.' matching' : $picked->count().' selected')
    {{-- The action bar. It is a sibling of the table (a form cannot hold another form), and the
         rows' checkboxes point at it with form="{{ $bulk }}". Sticky, so it stays in reach on a long
         page; on a phone it is a compact bar along the bottom that appears once a row is ticked. --}}
    <form method="post" action="{{ route('backend.review.verify.many', ['type' => $type]) }}" id="{{ $bulk }}" class="adm-bulkbar sticky top-0 z-10 mt-3 space-y-2 rounded-sm border border-brand-line bg-white p-3 text-sm shadow-sm">@csrf
        <input type="hidden" name="q" value="{{ $filters['q'] }}"><input type="hidden" name="review" value="{{ $filters['review'] }}"><input type="hidden" name="published" value="{{ $filters['published'] }}">
        <div class="flex flex-wrap items-center gap-x-4 gap-y-1">
            <span class="font-medium text-brand-navy">Act on the selection</span>
            <span class="badge-neutral" data-bulk-count="{{ $bulk }}" data-bulk-count-all="all {{ $matching }} matching">{{ $pickedCount }}</span>
            @if($matching > $records->count())<label class="flex items-center gap-1 meta"><input type="checkbox" name="scope" value="filtered" data-bulk-scope="{{ $bulk }}" data-bulk-scope-count="{{ $matching }}" @checked($wholeFilter)> apply to all {{ $matching }} {{ $meta['plural'] }} matching the filter, not only this page{{ $matching > $bulkLimit ? ' (up to '.number_format($bulkLimit).' per action)' : '' }}</label>@endif
        </div>
        @can('records.verify')
        @unless($onRoster)
        <p class="rounded-sm border border-brand-line bg-state-warnbg px-3 py-2 text-xs text-brand-body" data-roster-notice>You are signed in as <strong>{{ auth()->user()->name }}</strong>, a name that is not on the published reviewer roster, so you cannot mark records verified. You can still set another review status or the confidence. To verify, add yourself to <code>data/reviewers/</code> with a declaration of interest by pull request and import it. <a href="{{ route('reviewers') }}" target="_blank" rel="noopener">See the reviewer roster ↗</a></p>
        @endunless
        <div class="flex flex-wrap items-end gap-2">
            <label class="text-xs"><span class="block meta">Review status</span><select name="review_status" class="input mt-0.5 !min-h-0 !py-1 !w-auto"><option value="{{ $keep }}">Keep current</option>@foreach($reviewStatuses as $r)<option value="{{ $r }}" @selected(old('review_status') === $r)>{{ str_replace('_', ' ', $r) }}</option>@endforeach</select></label>
            <label class="text-xs"><span class="block meta">Confidence</span><select name="confidence_level" class="input mt-0.5 !min-h-0 !py-1 !w-auto"><option value="{{ $keep }}">Keep current</option>@foreach($confidenceLevels as $c)<option value="{{ $c }}" @selected(old('confidence_level') === $c)>{{ $c }}</option>@endforeach</select></label>
            <label class="text-xs flex-1 min-w-[10rem]"><span class="block meta">Notes (internal, optional)</span><input name="notes" value="{{ old('notes') }}" class="input mt-0.5 !min-h-0 !py-1" maxlength="2000"></label>
            <button type="submit" class="btn-primary !min-h-0 !py-1" data-bulk-needs="{{ $bulk }}" data-confirm="Record this review for {n} {{ $meta['plural'] }} under your name? Fields left on “Keep current” are not changed.">Save review for selected</button>
        </div>
        @if($onRoster)
        {{-- Its own row: the one statement a verification rests on, with the count it covers. --}}
        <label class="flex items-start gap-2 text-xs" data-attestation><input type="checkbox" name="source_opened" value="1" class="mt-0.5" @checked(old('source_opened'))> <span>{{ $meta['attestation'] }} (<span data-bulk-count="{{ $bulk }}" data-bulk-count-all="all {{ $matching }} matching">{{ $pickedCount }}</span>). Needed only to mark them verified.</span></label>
        @endif
        @endcan
        @can('records.publish')
        <div class="flex flex-wrap gap-1">
            <button type="submit" name="publish" value="1" formaction="{{ route('backend.review.publish.many', ['type' => $type]) }}" formnovalidate class="btn-secondary !min-h-0 !py-1" data-bulk-needs="{{ $bulk }}" data-confirm="Publish {n} {{ $meta['plural'] }}? They appear on the public site, API and sitemaps at once." data-confirm-label="Publish">Publish selected</button>
            <button type="submit" name="publish" value="0" formaction="{{ route('backend.review.publish.many', ['type' => $type]) }}" formnovalidate class="btn-secondary !min-h-0 !py-1 text-state-bad" data-bulk-needs="{{ $bulk }}" data-confirm="Unpublish {n} {{ $meta['plural'] }}? They leave the public site, API and sitemaps at once." data-confirm-label="Unpublish" data-confirm-danger>Unpublish selected</button>
        </div>
        @endcan
        <p class="meta adm-bulkbar-hint">Tick only what you have actually opened and confirmed: each record is dated and published under your name, and the site tells readers a person checked it. Shift-click selects a range.</p>
    </form>
    <div class="table-wrap mt-3 bg-white"><table>
        <caption class="sr-only">{{ \Illuminate\Support\Str::ucfirst($meta['plural']) }}: review status and publication</caption>
        <thead><tr>
            <th scope="col" class="w-8"><input type="checkbox" data-bulk-all="{{ $bulk }}" aria-label="Select every {{ $meta['label'] }} on this page"></th>
            <th scope="col">{{ $meta['label'] }}</th><th scope="col">{{ $meta['context'] }}</th><th scope="col">Review</th><th scope="col">Last verified</th><th scope="col">Published</th>
        </tr></thead>
        <tbody>
        @foreach($rows as $r)
        <tr id="rec-{{ $r['slug'] }}">
            <td><input type="checkbox" name="slugs[]" value="{{ $r['slug'] }}" form="{{ $bulk }}" data-bulk-item aria-label="Select {{ $r['title'] }}" @checked($picked->contains($r['slug']))></td>
            <td><a href="{{ $r['url'] }}" target="_blank" rel="noopener">{{ $r['title'] }}</a><div class="meta">@if($r['source'])<a href="{{ $r['source'] }}" target="_blank" rel="noopener">Open official source ↗</a>@else <span class="text-state-warn">no source URL</span> @endif · confidence {{ $r['confidence'] }}@if($r['note']) · <span class="text-state-warn">{{ $r['note'] }}</span>@endif</div></td>
            <td class="text-xs">{{ $r['context'] }}</td>
            <td><x-backend.badge :status="$r['review_status']" /></td>
            <td class="text-xs whitespace-nowrap">{{ $r['last_verified_at']?->format('j M Y') ?? '—' }}@if($r['stale']) <span class="text-state-warn" title="older than the re-check age">stale</span>@endif @if($r['reviewed_by'])<div class="meta">{{ $r['reviewed_by'] }}</div>@endif</td>
            <td class="whitespace-nowrap"><x-backend.badge :status="$r['published'] ? 'yes' : 'no'">{{ $r['published'] ? 'yes' : 'no' }}</x-backend.badge>
                @can('records.publish')<form method="post" action="{{ route('backend.review.publish', ['type' => $type, 'slug' => $r['slug']]) }}" class="inline ml-1" @if($r['published']) data-confirm="Unpublish {{ $r['title'] }}? It leaves the public site, API and sitemaps at once." data-confirm-label="Unpublish" data-confirm-danger @else data-confirm="Publish {{ $r['title'] }}? It appears on the public site, API and sitemaps at once." data-confirm-label="Publish" @endif>@csrf<input type="hidden" name="publish" value="{{ $r['published'] ? 0 : 1 }}"><button type="submit" class="text-xs underline">{{ $r['published'] ? 'unpublish' : 'publish' }}</button></form>@endcan
            </td>
        </tr>
        @endforeach
        </tbody></table></div>
    <nav class="mt-4" aria-label="Records pagination">{{ $records->links() }}</nav>
    @endif
</section>

{{-- Read-only: an enforcement event is an item inside its instrument's file and is rebuilt on
     every import, so a decision is made in that file by pull request, not here. --}}
<section class="mt-10" aria-labelledby="enf-heading" data-pending-enforcement="{{ $pendingEvents['total'] }}">
    <h2 id="enf-heading" class="section-title !text-lg">Enforcement events pending review <span class="font-mono text-sm text-brand-muted">{{ $pendingEvents['total'] }}</span></h2>
    <p class="mt-1 meta max-w-3xl">Listed, not decided here. An event is recorded inside its instrument's YAML file and rebuilt on every import, so a decision made in this queue would not survive the next import and could not be written back to the right item. Open the official source, then set <code>review_status</code>, <code>reviewed_by</code> and <code>last_verified_at</code> on the event in the policy file by pull request. A reader's report goes through the correction form.</p>
    @if($pendingEvents['events']->isEmpty())
    <div class="mt-3"><x-site.empty title="No enforcement events pending review">Events whose review status is “pending review” appear here.</x-site.empty></div>
    @else
    <div class="table-wrap mt-3 bg-white"><table>
        <caption class="sr-only">Enforcement events with review status pending review</caption>
        <thead><tr><th scope="col">Event</th><th scope="col">Instrument</th><th scope="col">Occurred</th><th scope="col">Data file</th></tr></thead>
        <tbody>
        @foreach($pendingEvents['events'] as $e)
        <tr>
            <td><a href="{{ $e->url() }}" target="_blank" rel="noopener">{{ $e->title }}</a><div class="meta">@if($e->official_source_url)<a href="{{ $e->official_source_url }}" target="_blank" rel="noopener">Open official source ↗</a>@else <span class="text-state-warn">no source URL</span> @endif · confidence {{ $e->confidence_level }}</div></td>
            <td class="text-xs">@if($e->policyInstrument)<a href="{{ $e->policyInstrument->url() }}" target="_blank" rel="noopener">{{ $e->policyInstrument->short_title ?: $e->policyInstrument->title }}</a> · <a href="{{ route('contribute', ['type' => 'correction', 'subject_type' => 'policy', 'subject_slug' => $e->policyInstrument->slug]) }}" target="_blank" rel="noopener">correction form</a>@else — @endif</td>
            <td class="text-xs whitespace-nowrap">{{ $e->occurred_on?->format('j M Y') ?? '—' }}</td>
            <td class="text-xs"><code>{{ $pendingEvents['files'][$e->policyInstrument?->slug] ?? '—' }}</code></td>
        </tr>
        @endforeach
        </tbody></table></div>
    @if($pendingEvents['total'] > $pendingEvents['events']->count())<p class="mt-2 meta">Showing the latest {{ $pendingEvents['events']->count() }} of {{ $pendingEvents['total'] }}.</p>@endif
    @endif
</section>
@endsection
