@props(['standing'])
{{--
    Is this record still good? One line, in the strongest state that applies
    (RecordStanding): no longer in force, a date moved, changed recently, record
    updated, or no change recorded since the last source check or verification.
    Shape and words carry the state, not colour alone.
--}}
@php($tone = ['bad' => 'border-state-bad/40 bg-state-badbg text-state-bad', 'warn' => 'border-state-warn/40 bg-state-warnbg text-state-warn', 'info' => 'border-state-info/30 bg-state-infobg text-state-info', 'good' => 'border-state-good/30 bg-state-goodbg text-state-good'][$standing['tone']] ?? 'border-brand-line bg-brand-paper text-brand-body')
@php($mark = ['bad' => '✕', 'warn' => '!', 'info' => '↻', 'good' => '✓'][$standing['tone']] ?? '·')
<div {{ $attributes->merge(['class' => 'flex flex-wrap items-baseline gap-x-2 gap-y-1 rounded-md border px-3 py-2 text-sm '.$tone]) }} data-standing="{{ $standing['tone'] }}" role="status">
    <span aria-hidden="true" class="font-mono font-semibold">{{ $mark }}</span>
    <span class="font-medium">{{ $standing['label'] }}</span>
    @if($standing['detail'])<span class="text-brand-body">· @if($standing['url'])<a href="{{ $standing['url'] }}">{{ $standing['detail'] }}</a>@else{{ $standing['detail'] }}@endif</span>@endif
    @if($standing['checked_at'])<span class="text-xs text-brand-muted">· official source last checked <time datetime="{{ $standing['checked_at']->toDateString() }}">{{ $standing['checked_at']->format('j M Y') }}</time>@if($standing['check_outcome']) ({{ mb_strtolower($standing['check_outcome']) }})@endif</span>@endif
</div>
