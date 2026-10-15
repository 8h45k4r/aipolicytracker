@props(['change'])
{{-- Urgency fades: after 90 days a red "Urgent" on an old change reads as a live alarm, so it
     keeps the level as history ("Urgent at the time") in neutral styling. --}}
@php($impact = $change->impactEnum())
@php($stale = $change->occurred_on && $change->occurred_on->lt(now()->subDays(90)))
@if($stale && in_array($impact->value, ['urgent', 'high'], true))
<span class="badge bg-state-neutralbg text-brand-body ring-brand-line">{{ $impact->label() }} at the time</span>
@else
<span class="badge {{ $impact->value === 'urgent' ? 'bg-state-badbg text-state-bad ring-state-bad/20' : ($impact->value === 'high' ? 'bg-state-warnbg text-state-warn ring-state-warn/20' : 'bg-state-neutralbg text-brand-body ring-brand-line') }}">{{ $impact->label() }}</span>
@endif
