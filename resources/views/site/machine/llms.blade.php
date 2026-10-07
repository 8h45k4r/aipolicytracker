# AIPolicyTracker

> {{ config('aipolicytracker.positioning') }} {{ config('aipolicytracker.supporting') }}

Generated {{ now()->toDateString() }}. AIPolicyTracker is an open, source-backed AI policy and regulatory intelligence platform. Every policy record links to a primary official source, shows its current status and a verification state, and is published as open data ({{ config('aipolicytracker.data_license') }}). Content is informational only and is not legal advice.

## Content taxonomy

- Jurisdictions: countries, supranational bodies and sub-national states, each with regulatory status, binding rules vs guidance, regulators and official sources.
- Policy instruments: acts, regulations, executive orders, rules, strategies, frameworks, guidance, standards, codes of practice, consultations. Statuses: proposed, under_consultation, adopted, in_force, partially_applicable, guidance, voluntary_standard, enforcement_action, superseded, repealed, archived.
- Obligations: practical requirements (risk management, data governance, transparency, human oversight, technical documentation, post-market monitoring, incident handling, vendor governance, impact assessment and more) with source article, actors, evidence examples and original framework mappings.
- Deadlines and change events: dated milestones and developments with practical impact and official sources.

## Canonical pages

- Home: {{ route('home') }}
- Policy explorer: {{ route('policies.index') }}
- Jurisdictions: {{ route('jurisdictions.index') }}
- Country hubs (/ai-regulation-<country>: answer box, instrument table with native-language names, timeline, duties, regulators, deadlines, updates feed) and regional hubs: @foreach(\App\Services\Hubs\HubCatalog::regions() as $slug => $name){{ \App\Services\Hubs\HubCatalog::regionUrl($slug) }}@if(!$loop->last), @endif @endforeach
- Compare any two jurisdictions (/compare/<a>-vs-<b>: side-by-side table, obligation overlap by category, what is left for one side if you comply with the other): {{ route('compare.index') }}
- Obligations: {{ route('obligations.index') }}
- Controls (one control, the duties it satisfies, the evidence it produces): {{ route('controls.index') }}
- Compare: {{ route('compare.index') }}
- AI policy updates (latest changes with a computed summary, top stories by a published rule, month and day archives, one page and RSS feed per jurisdiction): {{ route('updates.index') }}
- Change log: {{ route('changes.index') }} (RSS: {{ route('changes.feed') }}; Google News sitemap: {{ route('sitemap.news') }})
- Weekly digest archive: {{ route('newsletter.index') }}
- AI risk domains (MIT AI Risk Repository taxonomy with incident counts): {{ route('risk.index') }}
- AI incidents summary (AI Incident Database, weekly): {{ route('risk.incidents') }}
- Browse and export incidents: {{ route('risk.incidents.browse') }} (CSV: {{ route('risk.incidents.export', 'csv') }})
- Browse and export MIT AI Risk Repository entries: {{ route('risk.risks') }} (CSV: {{ route('risk.risks.export', 'csv') }})
- Frameworks behind the risk database: {{ route('risk.frameworks') }}
- Law-to-standard crosswalks (which legal duties map to ISO/IEC 42001 and the NIST AI RMF): {{ route('frameworks.index') }}
- Frameworks compared, with the reuse matrix by duty category: {{ route('frameworks.compare') }}
- Applicability check (educational), with an obligations register export as XLSX, CSV, JSON or PDF (state in the URL; API: {{ route('api.v1.applicability.register') }}): {{ route('tools.applicability') }}
- Which date applies to you (five questions, a personal timeline from recorded deadlines with the reason each applies, .ics and PDF; API POST /api/v1/deadlines/applicable): {{ route('deadlines.engine') }}
- AI economic transition tracker (dividends, basic income, AI taxes, funds, layoff disclosure, retraining, worker voice; drafts are labelled and never scored; displacement policy index with published method): {{ route('transition.index') }} (index method: {{ route('transition.methodology') }}; API: {{ route('api.v1.transition.measures') }})
- Enforcement tracker (fines, orders, warnings, settlements, court decisions and annulments under recorded instruments, each with regulator, respondent, legal basis, amount as published, outcome and appeal status; recorded only from the regulator's or court's own publication): {{ route('enforcement.index') }} (API: {{ route('api.v1.enforcement') }}; CSV: {{ route('open-data.csv', 'enforcement') }})
- EU AI Act implementation tracker (guidelines, codes of practice, delegated and implementing acts, templates, with due and actual dates; "overdue" is derived from today's date; drafts are labelled): {{ route('policies.implementation', 'eu-ai-act') }} (API: {{ route('api.v1.implementation', ['instrument' => 'eu-ai-act']) }})
- AI standards tracker (CEN-CENELEC JTC 21 harmonised standards and ISO/IEC JTC 1/SC 42 standards; metadata only, never standard text): {{ route('standards.index') }} (API: {{ route('api.v1.implementation', ['standards' => 1]) }})
- State of AI regulation (quarterly report computed from the records: jurisdictions by level of AI law, instruments, changes, deadlines, verification; past quarters frozen; CSV): {{ route('state-of.show') }}
- Embeddable widgets (jurisdiction card, deadlines, map; framable only under /embed): {{ route('embed.index') }}
- Reviewers, each with a profile page, declared interests and the records they verified: {{ route('reviewers') }}
- Localised hubs (es, id, pt-BR) for selected countries: our summaries translated, the legal text and facts as recorded; unreviewed translations are noindex.
- Methodology: {{ route('methodology') }}
- Glossary (plain-language definitions with a stable anchor each, e.g. {{ route('glossary') }}#deployer, and the source they come from): {{ route('glossary') }}
- Glossary term pages (one per defined term: the definition, its source, and the laws and duties on record that use it), e.g. {{ route('glossary.show', 'high-risk-ai-system') }}, {{ route('glossary.show', 'ai-literacy') }}, {{ route('glossary.show', 'fundamental-rights-impact-assessment') }}
- Open data and API: {{ route('open-data') }} (OpenAPI: {{ route('openapi') }})
- Templates library (XLSX and DOCX generated from the recorded duties, controls, deadlines and crosswalks; versioned; free, no account; CC BY 4.0): {{ route('templates.index') }} (RSS of versions: {{ route('templates.feed') }}; API: {{ route('api.v1.templates') }})
- AI policy examples (national AI policies and strategies on record, by region, newest first): {{ route('ai-policy-examples') }}
- Free AI self-assessments ({{ \App\Services\Assessments\AssessmentCatalog::all()->count() }} questionnaires on Certifyi, a related product: EU AI Act readiness, risk classification, FRIA, ISO/IEC 42001, NIST AI RMF, US state AI laws, LLM security, AI governance; score by domain, no account): {{ route('assessments.index') }}
- Guides: {{ route('guides.index') }}
@foreach(config('content.guides') as $gslug => $g)
  - [{{ $g['h1'] }}]({{ route('guides.show', $gslug) }})
@endforeach
- Templates by framework and type: @foreach(\App\Services\Templates\TemplateCatalog::facets() as $f){{ $f['label'] }} {{ $f['url'] }}@if(!$loop->last), @endif @endforeach
- About, maintainers and references: {{ route('about') }}
- People: maintainer, research contributors and advisors, with the independence note: {{ route('team') }}
- Contact (corrections, press, security): {{ config('aipolicytracker.contact_email') }}

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
- `/jurisdictions/{slug}.md`, `/obligations/{slug}.md`, `/controls/{slug}.md`, `/changes/{slug}.md` (each change also has a page at `/changes/{slug}`)

Whole-corpus exports, one self-contained row at a time: `/open-data/{dataset}.csv` and `/open-data/{dataset}.ndjson` for jurisdictions, policies, obligations, controls, changes and deadlines. JSON Schemas resolve at `/schema/{name}.schema.json`. An assistant can call these through the Model Context Protocol server in the repository's `agent/` directory.

## Jurisdictions

@foreach($jurisdictions as $j)
- [AI regulation in {{ $j->name }}]({{ $j->url() }}): {{ \Illuminate\Support\Str::limit(trim(preg_replace('/\s+/', ' ', $j->regulatory_status_summary)), 200) }} Context file: {{ route('jurisdictions.context', $j->slug) }}
@endforeach

## Key policy instruments

@foreach($policies as $p)
- [{{ $p->short_title ?: $p->title }}]({{ $p->url() }}) ({{ $p->jurisdiction->name }}, {{ $p->statusEnum()->label() }}): {{ \Illuminate\Support\Str::limit(trim(preg_replace('/\s+/', ' ', $p->summary_plain)), 200) }} Official source: {{ $p->official_source_url }} Context file: {{ route('policies.context', $p->slug) }}
@endforeach

## Law-to-standard crosswalks

Organisations are audited against standards but regulated by statutes. Each mapping below records, for one legal duty, the clause or function of a standard it corresponds to. A mapping means the two ask for overlapping work, so evidence may be reusable; it never means certification discharges the duty. Mappings cite clause numbers only and reproduce no standard text.

@foreach($crosswalks as $c)
- [{{ $c['name'] }}]({{ $c['url'] }}): {{ $c['obligations'] }} duties across {{ $c['jurisdictions'] }} jurisdictions.
@foreach($c['pairs'] as $pair)
  - [{{ $pair['name'] }}]({{ $pair['url'] }}): {{ $pair['mapped'] }} of {{ $pair['recorded'] }} recorded duties mapped.
@endforeach
@endforeach

## By role, sector and use case

Generated cuts of the corpus: every recorded duty naming the audience, the controls that meet them and the evidence to keep.

@foreach(config('content.audiences') as $slug => $page)
- [{{ $page['h1'] }}]({{ route('audiences.show', $slug) }})
@endforeach

## Templates

Each template is generated from the records above: its rows cite the duties, controls and deadlines they come from, and a new version is published when those records change. The files are free; the page asks for a work email and sends the download link there.

@foreach(\App\Services\Templates\TemplateCatalog::all() as $slug => $t)
- [{{ $t['title'] }}]({{ \App\Services\Templates\TemplateCatalog::url($slug) }}) ({{ strtoupper(implode(', ', $t['formats'] ?? ['xlsx'])) }}): {{ $t['short'] ?? '' }}
@endforeach

## Curated comparisons

@foreach(config('content.comparisons', []) as $slug => $page)
- [{{ $page['h1'] ?? $page['title'] ?? $slug }}]({{ route('compare.show', $slug) }})
@endforeach

## Editorial landing pages

@foreach(config('content.landings') as $slug => $page)
- [{{ $page['h1'] }}]({{ route('landing', $slug) }})
@endforeach

## Verification and freshness policy

Records carry official_source_url, source_document_date, last_checked_at, last_verified_at, review_status (draft, pending_review, verified, needs_update) and confidence_level (high, medium, low, unavailable). Only a human reviewer who opened the official source may set review_status to verified. Records not verified in the last {{ config('aipolicytracker.stale_after_days') }} days are flagged as stale. Facts that cannot be established from the source are left empty rather than inferred.

## Citation guidance

Cite the record URL and the date accessed, and cite the linked official source alongside it. Format: {{ config('aipolicytracker.citation') }} Data licence: {{ config('aipolicytracker.data_license') }} ({{ config('aipolicytracker.data_license_url') }}). Full inventory: {{ route('llms.full') }}
