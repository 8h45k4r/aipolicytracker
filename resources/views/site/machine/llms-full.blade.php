# AIPolicyTracker: full machine-readable inventory

Generated {{ now()->toDateString() }}. Licence: {{ config('aipolicytracker.data_license') }}. Not legal advice. Source of truth: {{ config('aipolicytracker.github_url') }} (data/ directory). API: {{ url('/api/v1') }}. OpenAPI: {{ route('openapi') }}.

## Jurisdictions ({{ $jurisdictions->count() }})

@foreach($jurisdictions as $j)
### {{ $j->name }}
- URL: {{ $j->url() }}
- Type: {{ $j->jurisdiction_type }}; region: {{ $j->region }}
- Status: {{ trim(preg_replace('/\s+/', ' ', $j->regulatory_status_summary)) }}
- Verification: {{ $j->verificationLabel() }}; confidence: {{ $j->confidence_level }}
- Official sources: {{ collect($j->official_sources ?? [])->pluck('url')->implode('; ') }}

@endforeach
## Policy instruments ({{ $policies->count() }})

@foreach($policies as $p)
### {{ $p->title }}
- In brief: {{ \App\Services\Records\AnswerBox::policy($p) }}
- URL: {{ $p->url() }} (JSON: {{ route('policies.json', $p->slug) }})
- Jurisdiction: {{ $p->jurisdiction->name }}; type: {{ $p->instrument_type }}; status: {{ $p->status }}; binding: {{ $p->is_binding ? 'yes' : 'no' }}
- Dates: adopted {{ $p->adopted_on?->toDateString() ?? 'n/a' }}; in force {{ $p->in_force_on?->toDateString() ?? 'n/a' }}; applies from {{ $p->applies_from?->toDateString() ?? 'n/a' }}
- Summary: {{ trim(preg_replace('/\s+/', ' ', $p->summary_plain)) }}
- Official source: {{ $p->official_source_url }} ({{ $p->source_title }}, {{ $p->source_publisher }})
- Verification: {{ $p->verificationLabel() }}; confidence: {{ $p->confidence_level }}; record version {{ $p->content_version }}
@if($p->deadlines->isNotEmpty())
- Deadlines: {{ $p->deadlines->map(fn($d) => $d->displayDate().' '.$d->title)->implode('; ') }}
@endif

@endforeach
## Obligations ({{ $obligations->count() }})

@foreach($obligations as $o)
- [{{ $o->title }}]({{ $o->url() }}) — {{ $o->category }}; {{ $o->is_binding ? 'legal requirement' : 'voluntary' }}; {{ $o->policyInstrument->short_title ?: $o->policyInstrument->title }}@if($o->source_reference), {{ $o->source_reference }}@endif
@endforeach

## Controls ({{ $controls->count() }})

One control serves many duties. Each line names the control, its kind, the number of recorded duties it satisfies or supports, and the evidence it produces.

@foreach($controls as $c)
- [{{ $c->title }}]({{ $c->url() }}) — {{ $c->kindLabel() }}; serves {{ $c->obligations->count() }} {{ \Illuminate\Support\Str::plural('duty', $c->obligations->count()) }}; evidence: {{ $c->evidence->pluck('title')->join(', ') }}
@endforeach

## Recent change events ({{ $changes->count() }})

@foreach($changes as $c)
- {{ $c->occurred_on->toDateString() }} [{{ $c->jurisdiction->name }}] {{ $c->title }} ({{ $c->impact_level }}) — {{ trim(preg_replace('/\s+/', ' ', $c->what_changed)) }} Source: {{ $c->official_source_url }}
@endforeach
