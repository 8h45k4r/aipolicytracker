{{-- One table of implementation measures; $measures, $caption and $standards (bool) in scope. --}}
<div class="table-wrap mt-3"><table><caption class="sr-only">{{ $caption }}</caption>
    <thead><tr>
        <th scope="col">Measure</th><th scope="col">Kind</th>
        @if($standards)<th scope="col">Body</th><th scope="col">Stage</th><th scope="col">Published</th><th scope="col">OJ citation</th>
        @else<th scope="col">Legal basis</th><th scope="col">Due</th><th scope="col">Adopted</th><th scope="col">Published</th>@endif
        <th scope="col">Status</th><th scope="col">Record</th>
    </tr></thead>
    <tbody>@forelse($measures as $m)@php($status = $m->effectiveStatus())<tr id="{{ $m->slug }}">
        <th scope="row" class="font-medium">@if($m->official_source_url)<a href="{{ $m->official_source_url }}" rel="noopener" class="text-brand-navy">{{ $m->title }}</a>@else{{ $m->title }}@endif
            @if($m->reference)<br><span class="text-xs font-normal text-brand-muted">{{ $m->reference }}</span>@endif
            @if($m->relatedPolicy)<br><a href="{{ $m->relatedPolicy->url() }}" class="text-xs font-normal">Record: {{ $m->relatedPolicy->short_title ?: $m->relatedPolicy->title }}</a>@endif
            @if($m->summary)<br><span class="text-xs font-normal text-brand-body">{{ $m->summary }}</span>@endif</th>
        <td class="whitespace-nowrap">{{ $m->kindLabel() }}</td>
        @if($standards)
        <td>{{ $m->bodyLabel() ?? '—' }}</td>
        <td>{{ $m->stage ?? '—' }}</td>
        <td class="whitespace-nowrap">{{ $m->publishedLabel() ?? '—' }}</td>
        <td class="whitespace-nowrap">@if($m->kind !== 'harmonised_standard')<span class="text-brand-muted" title="Only a harmonised standard is cited in the Official Journal">n/a</span>@elseif($m->oj_citation_on){{ $m->oj_citation_on->format('j M Y') }}@elseif($m->oj_citation_expected_on)expected {{ $m->oj_citation_expected_on->format('j M Y') }}@else — @endif</td>
        @else
        <td>{{ $m->legal_basis ?? '—' }}</td>
        <td class="whitespace-nowrap">{{ $m->due_on?->format('j M Y') ?? '—' }}</td>
        <td class="whitespace-nowrap">{{ $m->adopted_on?->format('j M Y') ?? '—' }}</td>
        <td class="whitespace-nowrap">{{ $m->publishedLabel() ?? '—' }}</td>
        @endif
        <td class="whitespace-nowrap">@if($status === 'overdue')<span class="badge bg-state-badbg text-state-bad ring-state-bad/30">Overdue</span>@else{{ \App\Services\Implementation\ImplementationStatus::label($status) }}@endif</td>
        <td>@if($m->isDraft())<span class="badge bg-brand-paper text-brand-body ring-brand-line" title="Not yet read from an official source">Draft</span>@elseif($m->isVerified())<span class="badge bg-state-goodbg text-state-good ring-state-good/30">Verified</span>@else<span class="badge bg-brand-paper text-brand-body ring-brand-line" title="Recorded from a cited source; not yet confirmed by a named reviewer">Pending review</span>@endif</td>
    </tr>@empty<tr><td colspan="8" class="text-brand-muted">Nothing recorded yet.</td></tr>@endforelse</tbody></table></div>
