AI policy digest - {{ $periodLabel }}

@forelse($changes as $c)
{{ $c->occurred_on->format('Y-m-d') }} | {{ $c->jurisdiction?->name }} | {{ ucfirst($c->impact_level) }}
{{ $c->title }}
{{ $c->what_changed }}
@if($c->official_source_url)Source: {{ $c->official_source_url }}@endif

@empty
No changes were recorded for your topics this week.
@endforelse
@if($deadlines->isNotEmpty())
Upcoming application dates
@foreach($deadlines as $d)
- {{ $d->displayDate() }}: {{ $d->title }} ({{ $d->policyInstrument->jurisdiction->name }}) {{ $d->policyInstrument->url() }}
@endforeach
@endif

Full change log: {{ route('changes.index') }}
Unsubscribe: {{ $unsubscribeUrl }}
