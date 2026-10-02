@props(['record', 'compact' => false])
@php($verified = $record->isVerified())
@php($reviewer = $verified && filled($record->reviewed_by ?? null) ? $record->reviewed_by : null)
@if($compact)
{{-- In lists: a check (or a dot), one word and the date. The full line and the reviewer are in the tooltip and on the record page. --}}
@php($date = $verified ? $record->last_verified_at : ($record->last_checked_at ?? null))
<span {{ $attributes->merge(['class' => 'inline-flex items-center gap-1 text-xs whitespace-nowrap '.($verified ? 'text-state-good' : 'text-brand-muted')]) }} title="{{ $record->verificationLabel() }}{{ $reviewer ? ' · reviewed by '.$reviewer : '' }}. A factual check against the official source, not a legal review." data-verified-mark>
    @if($verified)<svg class="h-3.5 w-3.5" viewBox="0 0 16 16" fill="none" aria-hidden="true"><path d="m3.5 8.5 3 3 6-7" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>@else<span aria-hidden="true" class="inline-block h-2 w-2 rounded-sm bg-brand-line"></span>@endif
    {{ $verified ? 'Verified' : ($date ? 'Checked' : 'Source-linked') }}@if($date) <time datetime="{{ $date->toDateString() }}">{{ $date->format('j M Y') }}</time>@endif
</span>
@else
<span {{ $attributes->merge(['class' => 'inline-flex items-center gap-1.5 text-xs '.($verified ? 'text-state-good' : 'text-brand-muted')]) }} title="A factual check against the official source. Not a legal review and not legal advice.">
    <span aria-hidden="true" class="inline-block h-2 w-2 rounded-sm {{ $verified ? 'bg-state-good' : 'bg-brand-line' }}"></span>
    {{ $record->verificationLabel() }}@if($reviewer) · reviewed by <a href="{{ route('reviewers') }}" class="underline decoration-dotted" title="The reviewer roster and each reviewer's declared interests">{{ $reviewer }}</a>@endif<span class="sr-only"> (a factual check against the official source, not a legal review or legal advice)</span>
</span>
@endif
