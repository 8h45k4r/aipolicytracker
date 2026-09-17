# AIPolicyTracker

> {{ config('aipolicytracker.positioning') }} {{ config('aipolicytracker.supporting') }}

AIPolicyTracker is an open, source-backed AI policy and regulatory intelligence platform. Every policy record links to a primary official source, shows its current status and a verification state, and is published as open data ({{ config('aipolicytracker.data_license') }}). Content is informational only and is not legal advice.

## Content taxonomy

- Jurisdictions: countries, supranational bodies and sub-national states, each with regulatory status, binding rules vs guidance, regulators and official sources.
- Policy instruments: acts, regulations, executive orders, rules, strategies, frameworks, guidance, standards, codes of practice, consultations. Statuses: proposed, under_consultation, adopted, in_force, partially_applicable, guidance, voluntary_standard, enforcement_action, superseded, repealed, archived.
- Obligations: practical requirements (risk management, data governance, transparency, human oversight, technical documentation, post-market monitoring, incident handling, vendor governance, impact assessment and more) with source article, actors, evidence examples and original framework mappings.
- Deadlines and change events: dated milestones and developments with practical impact and official sources.

## Canonical pages

- Home: {{ route('home') }}
- Policy explorer: {{ route('policies.index') }}
- Jurisdictions: {{ route('jurisdictions.index') }}
- Obligations: {{ route('obligations.index') }}
- Compare: {{ route('compare.index') }}
- Change log: {{ route('changes.index') }} (RSS: {{ route('changes.feed') }})
- AI risk domains (MIT AI Risk Repository taxonomy with incident counts): {{ route('risk.index') }}
- AI incidents summary (AI Incident Database, weekly): {{ route('risk.incidents') }}
- Browse and export incidents: {{ route('risk.incidents.browse') }} (CSV: {{ route('risk.incidents.export', 'csv') }})
- Browse and export MIT AI Risk Repository entries: {{ route('risk.risks') }} (CSV: {{ route('risk.risks.export', 'csv') }})
- Frameworks behind the risk database: {{ route('risk.frameworks') }}
- Applicability check (educational): {{ route('tools.applicability') }}
- Methodology: {{ route('methodology') }}
- Open data and API: {{ route('open-data') }} (OpenAPI: {{ route('openapi') }})
- Guides: {{ route('guides.index') }}
- About, maintainers and references: {{ route('about') }}

## How far this data can be trusted

Read {{ route('open-data.health') }} before quoting any record as settled. It reports, as JSON, how many records are past their re-check date, how many are missing a field a checkable record needs, and how many have been verified by a named reviewer. Most records are structured summaries that no reviewer has yet confirmed against the official source; the record itself says so, and so does every context file below.

- Verification policy (how old a fact may be, by record type): {{ route('verification') }}
- Coverage (what a record must carry, and what is missing): {{ route('coverage') }}
- Open queue of gaps: {{ route('gaps') }}
- Corrections log (what readers reported and what was decided): {{ route('corrections') }}
- Reviewers and their declared interests: {{ route('reviewers') }}

## Reading a record without scraping a page

Every published record is also served as one Markdown context file with a provenance block:

- `/policies/{slug}.md` — e.g. {{ route('policies.context', 'eu-ai-act') }}
- `/jurisdictions/{slug}.md`, `/obligations/{slug}.md`, `/changes/{slug}.md`

Whole-corpus exports, one self-contained row at a time: `/open-data/{dataset}.csv` and `/open-data/{dataset}.ndjson` for jurisdictions, policies, obligations, changes and deadlines. JSON Schemas resolve at `/schema/{name}.schema.json`. An assistant can call these through the Model Context Protocol server in the repository's `agent/` directory.

## Jurisdictions

@foreach($jurisdictions as $j)
- [AI regulation in {{ $j->name }}]({{ $j->url() }}): {{ \Illuminate\Support\Str::limit(trim(preg_replace('/\s+/', ' ', $j->regulatory_status_summary)), 200) }}
@endforeach

## Key policy instruments

@foreach($policies as $p)
- [{{ $p->short_title ?: $p->title }}]({{ $p->url() }}) ({{ $p->jurisdiction->name }}, {{ $p->statusEnum()->label() }}): {{ \Illuminate\Support\Str::limit(trim(preg_replace('/\s+/', ' ', $p->summary_plain)), 200) }} Official source: {{ $p->official_source_url }}
@endforeach

## Editorial landing pages

@foreach(config('content.landings') as $slug => $page)
- [{{ $page['h1'] }}]({{ route('landing', $slug) }})
@endforeach

## Verification and freshness policy

Records carry official_source_url, source_document_date, last_checked_at, last_verified_at, review_status (draft, pending_review, verified, needs_update) and confidence_level (high, medium, low, unavailable). Only a human reviewer who opened the official source may set review_status to verified. Records not verified in the last {{ config('aipolicytracker.stale_after_days') }} days are flagged as stale. Facts that cannot be established from the source are left empty rather than inferred.

## Citation guidance

Cite the record URL and the date accessed, and cite the linked official source alongside it. Format: {{ config('aipolicytracker.citation') }} Data licence: {{ config('aipolicytracker.data_license') }} ({{ config('aipolicytracker.data_license_url') }}). Full inventory: {{ route('llms.full') }}
