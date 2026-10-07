@props(['record', 'compact' => false, 'legend' => false])
{{--
One verification badge for every record, in three states (HasSourceQuality::verificationState):
  Verified        a named reviewer confirmed the record against the official source, with the date;
  Pending review  linked to the official source and waiting in the review queue;
  Source-linked   linked to the official source, not yet confirmed by a reviewer.
`compact` is the list form (state, date, the full line in the tooltip); `legend` adds a
no-JavaScript explanation of the three states with a link to the verification policy.
--}}
@php($state = $record->verificationState())
@php($verified = $state === 'verified')
@php($reviewer = $verified && filled($record->reviewed_by ?? null) ? $record->reviewed_by : null)
@php($name = ['verified' => 'Verified', 'pending_review' => 'Pending review', 'source_linked' => 'Source-linked'][$state])
@php($tone = ['verified' => 'text-state-good', 'pending_review' => 'text-state-warn', 'source_linked' => 'text-brand-muted'][$state])
@if($compact)
{{-- In lists: a mark, the state and the date. The full line and the reviewer are in the tooltip and on the record page. --}}
@php($date = $verified ? $record->last_verified_at : ($record->last_checked_at ?? null))
<span {{ $attributes->merge(['class' => 'inline-flex items-center gap-1 text-xs whitespace-nowrap '.$tone]) }} data-verification-state="{{ $state }}" title="{{ $record->verificationLabel() }}{{ $reviewer ? ' · reviewed by '.$reviewer : '' }}. A factual check against the official source, not a legal review." data-verified-mark>
    @if($verified)<svg class="h-3.5 w-3.5" viewBox="0 0 16 16" fill="none" aria-hidden="true"><path d="m3.5 8.5 3 3 6-7" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>@else<span aria-hidden="true" class="inline-block h-2 w-2 rounded-sm {{ $state === 'pending_review' ? 'bg-state-warn' : 'bg-brand-line' }}"></span>@endif
    {{ $name }}@if($date) <time datetime="{{ $date->toDateString() }}">{{ $date->format('j M Y') }}</time>@endif
</span>
@else
<span {{ $attributes->merge(['class' => 'inline-flex flex-wrap items-center gap-x-1.5 gap-y-1 text-xs '.$tone]) }} data-verification-badge data-verification-state="{{ $state }}">
    @if($verified)<svg class="h-3.5 w-3.5" viewBox="0 0 16 16" fill="none" aria-hidden="true"><path d="m3.5 8.5 3 3 6-7" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>@else<span aria-hidden="true" class="inline-block h-2 w-2 rounded-sm {{ $state === 'pending_review' ? 'bg-state-warn' : 'bg-brand-line' }}"></span>@endif
    @if($verified)
    <span>{{ $record->verificationLabel() }}@if($reviewer) · reviewed by <a href="{{ route('reviewers') }}" class="underline decoration-dotted" title="The reviewer roster and each reviewer's declared interests">{{ $reviewer }}</a>@endif</span>
    @else
    <span><span class="font-medium">{{ $name }}</span><span class="text-brand-muted"> · {{ $state === 'pending_review' ? 'linked to the official source, waiting for a reviewer to confirm it' : 'linked to the official source, not yet confirmed by a reviewer' }}@if(! empty($record->last_checked_at)) · source checked <time datetime="{{ $record->last_checked_at->toDateString() }}">{{ $record->last_checked_at->format('j M Y') }}</time>@endif</span></span>
    @endif
    <span class="sr-only"> (a factual check against the official source, not a legal review or legal advice)</span>
</span>
@if($legend)
<details class="relative text-xs text-brand-muted" data-verification-legend>
    <summary class="cursor-pointer list-none underline decoration-dotted hover:text-brand-navy">What this label means</summary>
    <dl class="mt-2 max-w-md space-y-1.5 rounded-sm border border-brand-line bg-brand-paper p-3 text-brand-body">
        <div><dt class="inline font-medium text-state-good">Verified</dt> <dd class="inline">— a named reviewer opened the official source and confirmed the record; the date and the reviewer are shown.</dd></div>
        <div><dt class="inline font-medium text-state-warn">Pending review</dt> <dd class="inline">— linked to the official source and in the queue for a reviewer to confirm.</dd></div>
        <div><dt class="inline font-medium">Source-linked</dt> <dd class="inline">— linked to the official source, not yet confirmed by a reviewer.</dd></div>
        <div class="pt-1"><a href="{{ route('verification') }}" class="text-brand-blue hover:underline">Verification policy and current standing</a> · a factual check, not legal advice.</div>
    </dl>
</details>
@endif
@endif
