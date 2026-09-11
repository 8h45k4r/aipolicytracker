# Who comes to AIPolicyTracker, and what they need

Written 2026-09-12 to drive product decisions. Each persona is described by the job they are trying to do, the pain the site must remove, the proof they need before they trust a page, and the feature that serves them. Features marked **live** exist; others are backlog.

| Persona | Why they arrive | Pain today | What convinces them | Feature |
|---|---|---|---|---|
| **Responsible-AI researcher** (university, lab) | Needs a citable, structured view of AI harms and the taxonomies that describe them | Incident and risk data is scattered across a database, a spreadsheet and papers; no crosswalk to law | Source, licence and snapshot date on every number; exports with citation; stable URLs per record | Incident and risk profiles, subdomain drilldown, CSV/JSON exports with attribution (**live**); policy-to-risk crosswalk (**this release**) |
| **AI CISO / head of compliance** (regulated company) | Must answer "which rules apply to us, by when, and what evidence do we need" | Legal texts are long; vendors sell fear; deadlines are buried | Obligations with dates and article references; evidence examples; templates they can adopt today | Obligations explorer, applicability check, EU AI Act readiness checklist, inventory and risk register templates (**live**); "risks this instrument addresses" on policy pages (**this release**) |
| **Policymaker / regulator staff** | Benchmarks their draft against peers; wants to know which harms are growing and unaddressed | Comparisons are anecdotal; incident evidence is not linked to instruments | Country comparisons, dated change log, incidents mapped to domains and to the instruments that respond | Compare, change log (**live**); coverage gap "where harm occurs vs where rules exist" and annotated policy timeline (**this release**) |
| **Diplomat / international organisation** | Tracks convergence: OECD, UNESCO, Council of Europe, G7, UN | Hard to see which states signed or adopted what | International instruments as first-class records with adoption status by state | International jurisdiction page, Council of Europe record (**live**); signatory tracking (backlog) |
| **INGO / civil-society advocate** | Evidence of harm to specific groups; who deploys the systems | Harm data lacks "who was harmed" and "who deployed" summaries | Harmed parties, deployers, sectors, harm levels with links to source reports | Incident profiles with reports (**live**); "who is harmed, who deploys" section (**this release**) |
| **Donor / foundation** | Decides where funding closes the biggest gaps | No view of which regions have harm without governance capacity | Coverage gap by country; jurisdictions with no instrument | Every UN member state recorded honestly (**live**); coverage gap table (**this release**) |
| **Journalist** | Fast, sourced context for a story | Needs the timeline and the numbers in one place | Latest incidents, dated changes, quotable totals with sources | Home feed, digest (**live**); incidents in the weekly digest and persona entry points (**this release**) |
| **Student / newcomer** | Wants an orientation | Jargon (Annex III, GPAI, HUDERIA) | Plain-language guides and glossary-style FAQs | Guides, FAQ schema on records (**live**) |

## The single USP

Every page connects three things that are elsewhere kept apart: **the harm** (recorded incidents and the risk taxonomy), **the rule** (obligations with dates and sources) and **the action** (templates, checklists, evidence). A visitor should be able to move from an incident to the subdomain it belongs to, to the instruments that respond, to the obligation and deadline, to the template that produces the evidence, in four clicks, with a source on every step.

## Design rules that follow

1. Numbers carry their source, licence and date inline; a number without a source is a bug.
2. Never claim harm is absent where data is sparse: say how many incidents carry a country code, how many are classified.
3. Every visualisation is a doorway: bars and blocks link to the filtered list behind them.
4. Persona entry points are short and honest; they route to existing pages, they do not create new silos.
