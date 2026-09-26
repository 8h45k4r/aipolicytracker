@extends('backend.layouts.app', ['title' => 'Review queue'])
@section('content')
<h1 class="font-display text-2xl font-semibold text-brand-navy">Review queue and publishing</h1>
<p class="mt-1 meta max-w-3xl">Submissions are triaged here; approved content is applied to <code>data/</code> through a pull request. Imported records are confirmed against their official source and switched on or off for the public site, API and sitemaps. Tick rows, then act on the selection: one attestation covers the whole selection and every record still gets its own dated, named verification.</p>

<section class="mt-6" aria-labelledby="sub-heading">
    <div class="flex flex-wrap items-baseline justify-between gap-3"><h2 id="sub-heading" class="section-title !text-lg">Contributor submissions</h2><a href="{{ route('backend.admin.submissions') }}" class="text-sm">All submissions and feedback</a></div>
    <div class="mt-2 flex flex-wrap gap-2 text-sm">@foreach(\App\Enums\SubmissionStatus::cases() as $s)<a class="chip {{ $status === $s->value ? 'chip-active' : '' }}" href="{{ route('backend.review.index', ['status' => $s->value, 'type' => $type] + $filters) }}#sub-heading">{{ $s->label() }} ({{ $counts[$s->value] ?? 0 }})</a>@endforeach</div>
    @if($submissions->isEmpty())
    <div class="mt-3"><x-site.empty title="Nothing in this queue">Submissions with the status “{{ \App\Enums\SubmissionStatus::from($status)->label() }}” appear here.</x-site.empty></div>
    @else
    <x-backend.decision-bar id="bulk-submissions" />
    <div class="mt-3 divide-y divide-brand-line border-y border-brand-line bg-white">
        @foreach($submissions as $s)<x-backend.submission :submission="$s" bulk="bulk-submissions" />@endforeach
    </div>
    <nav class="mt-4" aria-label="Submissions pagination">{{ $submissions->links() }}</nav>
    @endif
</section>

<section class="mt-10" aria-labelledby="rec-heading">
    <h2 id="rec-heading" class="section-title !text-lg">Imported records</h2>
    <p class="mt-1 meta max-w-3xl">Unverified and low-confidence first. Decisions survive re-imports{{ '' }}@if($pendingExport); <strong>{{ $pendingExport }}</strong> not yet written back to data/ (run <code>php artisan policy:export-verifications</code> and open a pull request)@endif.</p>

    {{-- One kind at a time: each tab is its own queue with its own counts. --}}
    <nav class="mt-3 flex flex-wrap gap-2 text-sm" aria-label="Record kinds">
        @foreach($tabs as $tab)
        <a class="chip {{ $type === $tab['key'] ? 'chip-active' : '' }}" href="{{ route('backend.review.index', ['type' => $tab['key'], 'status' => $status]) }}#rec-heading" @if($type === $tab['key']) aria-current="page" @endif>{{ $tab['label'] }} <span class="font-mono {{ $type === $tab['key'] ? 'text-white/80' : 'text-brand-muted' }}">{{ $tab['total'] }}</span>@if($tab['open']) <span class="rounded-sm px-1 {{ $type === $tab['key'] ? 'bg-white/20' : 'bg-state-warnbg text-state-warn' }}" title="not yet verified">{{ $tab['open'] }} open</span>@endif</a>
        @endforeach
    </nav>

    <form method="get" action="{{ route('backend.review.index') }}" class="mt-3 flex flex-wrap items-end gap-2 text-sm" data-autosubmit>
        <input type="hidden" name="type" value="{{ $type }}"><input type="hidden" name="status" value="{{ $status }}">
        <label class="text-sm"><span class="block meta">Search</span><input type="search" name="q" value="{{ $filters['q'] }}" placeholder="title or slug" class="input mt-1 !min-h-0 !py-1.5 w-56"></label>
        <label class="text-sm"><span class="block meta">Review status</span><select name="review" class="input mt-1 !min-h-0 !py-1.5 !w-auto"><option value="">Any</option>@foreach($reviewFilters as $r)<option value="{{ $r }}" @selected($filters['review'] === $r)>{{ str_replace('_', ' ', $r) }} ({{ $byReview[$r] ?? 0 }})</option>@endforeach</select></label>
        <label class="text-sm"><span class="block meta">Published</span><select name="published" class="input mt-1 !min-h-0 !py-1.5 !w-auto"><option value="">Any</option><option value="yes" @selected($filters['published'] === 'yes')>yes</option><option value="no" @selected($filters['published'] === 'no')>no ({{ $unpublished }})</option></select></label>
        <button class="btn-secondary !min-h-0 !py-1.5">Filter</button>
        @if($filters['q'] !== '' || $filters['review'] || $filters['published'])<a href="{{ route('backend.review.index', ['type' => $type, 'status' => $status]) }}#rec-heading" class="btn-secondary !min-h-0 !py-1.5">Clear</a>@endif
        <span class="meta ml-auto">{{ $matching }} {{ \Illuminate\Support\Str::plural('record', $matching) }} match{{ $records->hasPages() ? ', '.$records->count().' on this page' : '' }}</span>
    </form>

    @if($rows->isEmpty())
    <div class="mt-3"><x-site.empty title="No {{ $meta['plural'] }} match" :reset="route('backend.review.index', ['type' => $type, 'status' => $status])">Change the filters, or clear them to see every record of this kind.</x-site.empty></div>
    @else
    @php($bulk = 'bulk-'.$type)
    {{-- The action bar. It is a sibling of the table (a form cannot hold another form), and the
         rows' checkboxes point at it with form="{{ $bulk }}". Sticky, so it stays in reach on a long page. --}}
    <form method="post" action="{{ route('backend.review.verify.many', ['type' => $type]) }}" id="{{ $bulk }}" class="sticky top-0 z-10 mt-3 space-y-2 rounded-sm border border-brand-line bg-white p-3 text-sm shadow-sm">@csrf
        <input type="hidden" name="q" value="{{ $filters['q'] }}"><input type="hidden" name="review" value="{{ $filters['review'] }}"><input type="hidden" name="published" value="{{ $filters['published'] }}">
        <div class="flex flex-wrap items-center gap-x-4 gap-y-1">
            <span class="font-medium text-brand-navy">Act on the selection</span>
            <span class="badge-neutral" data-bulk-count="{{ $bulk }}" data-bulk-count-all="all {{ $matching }} matching">0 selected</span>
            @if($matching > $records->count())<label class="flex items-center gap-1 meta"><input type="checkbox" name="scope" value="filtered" data-bulk-scope="{{ $bulk }}" data-bulk-scope-count="{{ $matching }}"> apply to all {{ $matching }} {{ $meta['plural'] }} matching the filter, not only this page</label>@endif
        </div>
        <div class="flex flex-wrap items-end gap-2">
            @can('records.verify')
            <label class="text-xs"><span class="block meta">Review status</span><select name="review_status" class="input mt-0.5 !min-h-0 !py-1 !w-auto">@foreach($reviewStatuses as $r)<option value="{{ $r }}">{{ str_replace('_', ' ', $r) }}</option>@endforeach</select></label>
            <label class="text-xs"><span class="block meta">Confidence</span><select name="confidence_level" class="input mt-0.5 !min-h-0 !py-1 !w-auto">@foreach($confidenceLevels as $c)<option value="{{ $c }}">{{ $c }}</option>@endforeach</select></label>
            <label class="text-xs flex-1 min-w-[10rem]"><span class="block meta">Notes (internal, optional)</span><input name="notes" class="input mt-0.5 !min-h-0 !py-1" maxlength="2000"></label>
            <label class="flex items-center gap-1 text-xs"><input type="checkbox" name="source_opened" value="1"> {{ $meta['attestation'] }}</label>
            <button type="submit" class="btn-primary !min-h-0 !py-1" data-bulk-needs="{{ $bulk }}" data-confirm="Record this review for {n} {{ $meta['plural'] }} under your name?">Save review for selected</button>
            @endcan
            @can('records.publish')
            <span class="ml-auto flex gap-1">
                <button type="submit" name="publish" value="1" formaction="{{ route('backend.review.publish.many', ['type' => $type]) }}" formnovalidate class="btn-secondary !min-h-0 !py-1" data-bulk-needs="{{ $bulk }}">Publish selected</button>
                <button type="submit" name="publish" value="0" formaction="{{ route('backend.review.publish.many', ['type' => $type]) }}" formnovalidate class="btn-secondary !min-h-0 !py-1 text-state-bad" data-bulk-needs="{{ $bulk }}" data-confirm="Unpublish {n} {{ $meta['plural'] }}? They leave the public site, API and sitemaps at once.">Unpublish selected</button>
            </span>
            @endcan
        </div>
        <p class="meta">Tick only what you have actually opened and confirmed: each record is dated and published under your name, and the site tells readers a person checked it. Shift-click selects a range.</p>
    </form>
    <div class="table-wrap mt-3 bg-white"><table>
        <caption class="sr-only">{{ \Illuminate\Support\Str::ucfirst($meta['plural']) }}: review status and publication</caption>
        <thead><tr>
            <th scope="col" class="w-8"><input type="checkbox" data-bulk-all="{{ $bulk }}" aria-label="Select every {{ $meta['label'] }} on this page"></th>
            <th scope="col">{{ $meta['label'] }}</th><th scope="col">{{ $meta['context'] }}</th><th scope="col">Review</th><th scope="col">Last verified</th><th scope="col">Published</th>
        </tr></thead>
        <tbody>
        @foreach($rows as $r)
        <tr>
            <td><input type="checkbox" name="slugs[]" value="{{ $r['slug'] }}" form="{{ $bulk }}" data-bulk-item aria-label="Select {{ $r['title'] }}"></td>
            <td><a href="{{ $r['url'] }}" target="_blank" rel="noopener">{{ $r['title'] }}</a><div class="meta">@if($r['source'])<a href="{{ $r['source'] }}" target="_blank" rel="noopener">Open official source ↗</a>@else <span class="text-state-warn">no source URL</span> @endif · confidence {{ $r['confidence'] }}@if($r['note']) · <span class="text-state-warn">{{ $r['note'] }}</span>@endif</div></td>
            <td class="text-xs">{{ $r['context'] }}</td>
            <td><x-backend.badge :status="$r['review_status']" /></td>
            <td class="text-xs whitespace-nowrap">{{ $r['last_verified_at']?->format('j M Y') ?? '—' }}@if($r['stale']) <span class="text-state-warn" title="older than the re-check age">stale</span>@endif @if($r['reviewed_by'])<div class="meta">{{ $r['reviewed_by'] }}</div>@endif</td>
            <td class="whitespace-nowrap"><x-backend.badge :status="$r['published'] ? 'yes' : 'no'">{{ $r['published'] ? 'yes' : 'no' }}</x-backend.badge>
                @can('records.publish')<form method="post" action="{{ route('backend.review.publish', ['type' => $type, 'slug' => $r['slug']]) }}" class="inline ml-1" @if($r['published']) data-confirm="Unpublish {{ $r['title'] }}?" @endif>@csrf<input type="hidden" name="publish" value="{{ $r['published'] ? 0 : 1 }}"><button type="submit" class="text-xs underline">{{ $r['published'] ? 'unpublish' : 'publish' }}</button></form>@endcan
            </td>
        </tr>
        @endforeach
        </tbody></table></div>
    <nav class="mt-4" aria-label="Records pagination">{{ $records->links() }}</nav>
    @endif
</section>
@endsection
