@extends('backend.layouts.app', ['title' => 'Independent checks'])
@section('content')
@php($size = $rows->count())
<x-backend.page-header title="Independent checks" :description="'Each quarter a random '.rtrim(rtrim(number_format($sample['percent'], 2, '.', ''), '0'), '.').'% of verified policy records is re-checked by a second reviewer. This page lists the quarter\'s sample, how far the second checks have got, and what the two reviewers disagreed on. The method is in docs/reference/verification-policy.md.'">
    <x-slot:actions>
        <a href="{{ route('methodology') }}#independent-checks" target="_blank" rel="noopener" class="btn-secondary btn-sm">Published figures ↗</a>
        @can('submissions.decide')<a href="{{ route('backend.review.index') }}" class="btn-secondary btn-sm">Review queue</a>@endcan
    </x-slot:actions>
</x-backend.page-header>

<section class="mt-6" aria-labelledby="sample-heading">
    <div class="flex flex-wrap items-baseline justify-between gap-3">
        <h2 id="sample-heading" class="section-title !text-lg">Sample for {{ $sample['quarter'] }}</h2>
        <form method="get" action="{{ route('backend.checks.index') }}" class="flex items-end gap-2 text-sm">
            <label class="text-sm"><span class="block meta">Quarter</span><select name="quarter" class="input mt-1 !min-h-0 !py-1.5 !w-auto">@foreach($quarters as $q)<option value="{{ $q }}" @selected($sample['quarter'] === $q)>{{ $q }}</option>@endforeach @if(! in_array($sample['quarter'], $quarters, true))<option value="{{ $sample['quarter'] }}" selected>{{ $sample['quarter'] }}</option>@endif</select></label>
            <button class="btn-secondary btn-sm">Show</button>
            <a href="{{ route('backend.checks.export', ['quarter' => $sample['quarter']]) }}" class="btn-secondary btn-sm" data-track="admin_export">Export sample CSV</a>
        </form>
    </div>
    <p class="mt-1 meta">Seed <code>{{ $sample['seed'] }}</code> · population {{ number_format($sample['population']) }} verified {{ \Illuminate\Support\Str::plural('record', $sample['population']) }} · sample {{ $size }}. The same list as <code>php artisan verification:sample --quarter={{ $sample['quarter'] }}</code>.</p>

    <p class="mt-3 text-sm text-brand-body" data-checks-progress="{{ $checked }}/{{ $size }}"><strong>{{ $checked }} of {{ $size }}</strong> sampled {{ \Illuminate\Support\Str::plural('record', $size) }} double-checked.</p>
    @if($size > 0)
    <div class="mt-1 h-2 w-full max-w-md rounded-sm bg-brand-line" role="presentation"><div class="h-2 rounded-sm bg-state-good" style="width: {{ (int) round(100 * $checked / $size) }}%"></div></div>
    @endif

    @if($rows->isEmpty())
    <div class="mt-3"><x-site.empty title="Nothing to sample">No published, verified policy record with a named reviewer is waiting for a second check.</x-site.empty></div>
    @else
    <div class="table-wrap mt-3 bg-white"><table>
        <caption class="sr-only">Records sampled for an independent second check in {{ $sample['quarter'] }}</caption>
        <thead><tr><th scope="col">Record</th><th scope="col">First review</th><th scope="col">Second check</th></tr></thead>
        <tbody>
        @foreach($rows as $r)
        <tr data-sample-row="{{ $r['slug'] }}">
            <td><a href="{{ $r['url'] }}" target="_blank" rel="noopener">{{ $r['title'] }}</a><div class="meta">{{ $r['jurisdiction'] ?? '—' }} · <code>{{ $r['slug'] }}</code>@if($r['source']) · <a href="{{ $r['source'] }}" target="_blank" rel="noopener">official source ↗</a>@endif</div></td>
            <td class="text-xs">{{ $r['first_reviewer'] ?? '—' }}<div class="meta">{{ $r['last_verified_at']?->format('j M Y') ?? '—' }}</div></td>
            <td class="text-xs">
                @if($r['review'])
                    <x-backend.badge :status="$r['double_checked'] ? 'verified' : 'needs_update'">{{ $r['double_checked'] ? 'done' : 'recorded, record no longer verified' }}</x-backend.badge>
                    <div class="meta">{{ $r['review']['reviewed_by'] }}@if(! empty($r['review']['reviewed_on'])) · {{ $r['review']['reviewed_on'] }}@endif · {{ $r['disputed'] === [] ? 'agreed' : 'disputed: '.implode(', ', array_map(fn ($f) => $fields[$f]['label'] ?? $f, $r['disputed'])) }}</div>
                @elseif($r['same_reviewer'])
                    <x-backend.badge status="failed">not independent</x-backend.badge>
                    <div class="meta">The second review names the first reviewer and is not counted.</div>
                @else
                    <x-backend.badge status="draft">not yet</x-backend.badge>
                    <div class="meta">Any published reviewer except {{ $r['first_reviewer'] }}</div>
                @endif
            </td>
        </tr>
        @endforeach
        </tbody></table></div>
    @endif
</section>

<section class="mt-10" aria-labelledby="agree-heading">
    <h2 id="agree-heading" class="section-title !text-lg">Agreement</h2>
    <p class="mt-1 meta max-w-3xl">Over every published, verified record that carries an independent second check, not only this quarter's. The same figures and the same threshold as the public page: nothing per field is published until {{ $summary['min_sample'] }} records have been double-checked.</p>
    @if($summary['sufficient'])
    <p class="mt-3 text-sm text-brand-body" data-admin-agreement="{{ $summary['n'] }}">{{ number_format($summary['n']) }} of {{ number_format($summary['verified']) }} verified policy records double-checked; the two reviewers agreed on every field for {{ number_format($summary['records_fully_agreed']) }}.</p>
    <div class="table-wrap mt-3 bg-white"><table data-admin-agreement-table>
        <caption class="sr-only">Agreement between first and second reviewers, by field</caption>
        <thead><tr><th scope="col">Field</th><th scope="col">Records</th><th scope="col">Agreed</th><th scope="col">Agreement</th><th scope="col">Cohen's kappa</th></tr></thead>
        <tbody>
        @foreach($summary['fields'] as $row)
        <tr><th scope="row" class="font-medium">{{ $row['label'] }}</th><td>{{ number_format($row['n']) }}</td><td>{{ number_format($row['agreed']) }}</td><td>{{ number_format($row['percent'], 1) }}%</td><td>@if($row['kappa'] !== null){{ number_format($row['kappa'], 2) }}@elseif($row['categorical'])<span class="text-brand-muted" title="Both reviewers used a single value for every record, so chance agreement is total and kappa is undefined">undefined</span>@else<span class="text-brand-muted">—</span>@endif</td></tr>
        @endforeach
        </tbody></table></div>
    @else
    <p class="mt-3 text-sm text-brand-body" data-admin-agreement="{{ $summary['n'] }}">{{ number_format($summary['n']) }} of {{ $summary['min_sample'] }} double-checked records needed before agreement figures are published. {{ number_format($summary['records_fully_agreed']) }} {{ $summary['records_fully_agreed'] === 1 ? 'has' : 'have' }} no disputed field so far. The public page shows only the count until then.</p>
    @endif
</section>

<section class="mt-10" aria-labelledby="disputes-heading">
    <h2 id="disputes-heading" class="section-title !text-lg">Disputed fields <span class="font-mono text-sm text-brand-muted">{{ $disputes->count() }}</span></h2>
    <p class="mt-1 meta max-w-3xl">Every field a second reviewer disputed, unresolved first. A resolution is written on the dispute in the policy file; if the record was wrong it is corrected in the same pull request and appears in the <a href="{{ route('corrections') }}" target="_blank" rel="noopener">corrections log ↗</a>.</p>
    @if($disputes->isEmpty())
    <div class="mt-3"><x-site.empty title="No disputed fields">Disagreements recorded under <code>second_review.fields_disputed</code> appear here.</x-site.empty></div>
    @else
    <div class="table-wrap mt-3 bg-white"><table>
        <caption class="sr-only">Fields disputed by second reviewers</caption>
        <thead><tr><th scope="col">Record</th><th scope="col">Field</th><th scope="col">First / second</th><th scope="col">Reviewers</th><th scope="col">Resolution</th></tr></thead>
        <tbody>
        @foreach($disputes as $d)
        <tr data-dispute="{{ $d['record']->slug }}:{{ $d['field'] }}">
            <td><a href="{{ $d['record']->url() }}" target="_blank" rel="noopener">{{ $d['record']->short_title ?: $d['record']->title }}</a></td>
            <td class="text-xs">{{ $d['label'] }}@if($d['note'])<div class="meta">{{ $d['note'] }}</div>@endif</td>
            <td class="text-xs font-mono">@if($fields[$d['field']]['categorical'] ?? false){{ is_bool($d['first']) ? ($d['first'] ? 'true' : 'false') : ($d['first'] ?? '—') }} / {{ is_bool($d['second']) ? ($d['second'] ? 'true' : 'false') : ($d['second'] ?? '—') }}@else—@endif</td>
            <td class="text-xs">{{ $d['record']->reviewed_by }} / {{ $d['review']['reviewed_by'] ?? '—' }}@if(! empty($d['review']['reviewed_on']))<div class="meta">{{ $d['review']['reviewed_on'] }}</div>@endif</td>
            <td class="text-xs">@if(filled($d['resolution'])){{ $d['resolution'] }}@else<span class="text-state-warn">unresolved</span>@endif</td>
        </tr>
        @endforeach
        </tbody></table></div>
    @endif
</section>

<section class="mt-10" aria-labelledby="how-heading">
    <h2 id="how-heading" class="section-title !text-lg">Recording a second check</h2>
    <div class="mt-2 max-w-3xl space-y-2 text-sm text-brand-body">
        <p>Second checks are entered by pull request to <code>data/</code>, not on this page. The admin does not write YAML from a form: the review queue stores decisions and <code>policy:export-verifications</code> writes them back, and a second check needs the full block below, which only a person editing the file can fill honestly.</p>
        <ol class="list-decimal pl-5 space-y-1">
            <li>Assign each sampled record to a published reviewer who is not its first reviewer. Published reviewers: {{ $reviewers->isEmpty() ? 'none yet' : $reviewers->implode(', ') }}.</li>
            <li>The second reviewer opens the official source without looking at the record's values, then adds <code>second_review</code> to the policy file: <code>reviewed_by</code>, <code>reviewed_on</code>, <code>sample: {{ $sample['quarter'] }}</code>, <code>coded</code> (status, is_binding, review_status), <code>agreed</code> and <code>fields_disputed</code>.</li>
            <li>Run <code>php artisan policy:validate</code>. It refuses a reviewer who is not on the roster, the first reviewer under another spelling, an <code>agreed</code> that contradicts the disputes, and a disputed status, binding or review status without the first reviewer's value.</li>
            <li>Resolve any dispute between the two reviewers and write the resolution on it. Correct the record in the same pull request if it was wrong.</li>
        </ol>
    </div>
</section>
@endsection
