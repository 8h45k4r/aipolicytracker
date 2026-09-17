Daily alert · {{ $periodLabel }}

{{ $changes->count() }} {{ \Illuminate\Support\Str::plural('change', $changes->count()) }} in the records you follow
@foreach($changes as $c)

{{ $c->occurred_on->format('j M Y') }} · {{ $c->jurisdiction?->name }}@if($c->policyInstrument) · {{ $c->policyInstrument->short_title ?: $c->policyInstrument->title }}@endif
@if(!empty($reasons[$c->id]))May affect: {{ implode(', ', $reasons[$c->id]) }}@endif

{{ $c->title }}
{{ $c->what_changed }}
@if($c->practical_impact)Practical impact: {{ $c->practical_impact }}@endif

@if($c->official_source_url)Official source: {{ $c->official_source_url }}@endif

@endforeach
@if($deadlines->isNotEmpty())

Application dates in the next {{ \App\Services\Alerts\AlertBuilder::DEADLINE_HORIZON_DAYS }} days
@foreach($deadlines as $d)
- {{ $d->displayDate() }}: {{ $d->title }} ({{ $d->policyInstrument->jurisdiction->name }}) {{ $d->policyInstrument->url() }}
@endforeach
@endif

You receive this because your Pro account follows these records. Manage what you follow: {{ $manageUrl }}
Informational only, not legal advice.
