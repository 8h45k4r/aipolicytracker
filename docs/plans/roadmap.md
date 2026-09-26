# Search and growth roadmap, P1–P10

Written 2026-09-26 from a read of the repository at `5d64f43`. Every statement
about the code below was checked against the code, not assumed from the brief.
Where the brief and the repository disagree, the repository is described and the
phase is adjusted; each adjustment says why.

## 1. What the site is built on

| Area | What is there |
|---|---|
| Framework | Laravel 12 on PHP 8.2+ (8.4 in the image), Composer |
| Public rendering | Blade, server-rendered. Every public page is complete in the raw HTML. |
| Account area | Inertia + React (`/profile`, auth screens) only |
| CSS / JS | Tailwind 3, Vite, a little vanilla JS for filters. No SPA on public pages. |
| Database | PostgreSQL 16 in production; SQLite in tests. CI runs the suite on both. |
| Data layer | YAML under `data/` is the source of truth, validated by JSON Schema (`policy:validate`) and imported to a relational read model (`policy:import`). External datasets (AI Incident Database, MIT AI Risk Repository) are JSON snapshots in `data/external/`, imported by `external:import`. |
| Entities | `Jurisdiction`, `PolicyInstrument`, `Obligation`, `ChangeEvent`, `Control` (+ evidence, framework references), taxonomy terms, `ExternalIncident`, `ExternalRisk`, `RecordVerification` (a reviewer's decision, which survives re-import) |
| Trust fields | `official_source_url`, `source_tier`, `review_status`, `confidence_level`, `last_checked_at`, `last_verified_at`, `reviewed_by`, all in `data/schema/common.schema.json`. `policy:freshness` and `policy:coverage` enforce ratchets in CI. |
| Routing | `routes/public.php` (site), `routes/api.php` (`/api/v1`), `routes/auth.php`, `routes/backend/web.php` (admin). Legacy URLs already 301 (`/news/*`, `/aipolicytracker/*`, `/map`, `/timeline`). |
| SEO plumbing | `App\Support\Seo`: title, description, canonical, breadcrumbs, JSON-LD graph (Organization, WebSite, Dataset, Legislation, FAQPage, ItemList, HowTo), `dateModified`, X-Robots-Tag. Sitemap index with sections. `llms.txt`, `llms-full.txt`, per-record `.md`, `/openapi.json`, CSV/NDJSON exports, RSS at `/changes/feed`, `.ics` calendar feeds. |
| Agent surface | `agent/server.mjs`, a Node stdio MCP server with 7 tools that call the public API (`search_policies`, `get_policy`, `get_jurisdiction`, `list_obligations`, `recent_changes`, `open_gaps`, `corpus_health`). |
| Auth | Breeze email + password, Sanctum, TOTP for admins. Auth already exists, so P8 builds on it. No magic-link system gets added alongside it. |
| Email | Resend (`resend/resend-php`); double opt-in subscribe; `digest:send` (weekly) and `alerts:send` (daily, Pro) |
| Billing | Dodo Payments; entitlements gate Pro features such as daily alerts |
| Scheduler | `routes/console.php` drives jobs through `JobRun`, with an admin log at `/backend/admin/jobs` |
| Tests | PHPUnit 11: 338 tests (unit + feature). `node --test` for the MCP server. |
| Lint | Pint (PHP), ESLint (JS). There is no TypeScript; `npm run typecheck` is a no-op message by design. |
| Deploy | Docker Compose on a VPS behind host nginx and Cloudflare (`deploy/README-docker.md`). No deploy workflow: a person runs it. |
| Analytics | **None on the public site.** GA4 (`gtag`) is loaded only in the Inertia shell (`resources/views/app.blade.php`). Search Console is the only measurement of public pages. |

## 2. Findings that change the plan

1. **The identifier leak is two templates, 3,280 pages.** 1,617 indexable risk pages
   are titled `<risk> (<quick_ref>)`, where `quick_ref` is the MIT repository's
   citation key: `InfoComm2023`, `Hendrycks2023`, `IBM2025`. 73 of the 74 keys
   match `\b[a-z]+\d{4,}\b`. That is the "infocomm2023"-shaped query in Search
   Console. All 1,663 incident pages are titled `AI incident #1382: …`. Incident URLs are the bare
   number (`/ai-risk/incidents/1382`), and risk URLs are a dotted code
   (`/ai-risk/risks/05.17.00`). The exact strings "ai202240" and "tencent679780"
   are not in the current data. They most likely come from the pre-2025 site,
   whose URLs were `/news/{id}` and `/aipolicytracker/single-view/{id}` and
   already 301. P1 fixes what the current site emits. The CTR audit (P1) will
   show whether those queries fade.
2. **The brand suffix does not fit the requested patterns.** `fullTitle()`
   appends ` | AIPolicyTracker`, which is 18 characters and leaves 42. The policy pattern's fixed
   tail `: Status, Duties & Dates` takes 24 of those. The generator will append the brand only
   when the result stays ≤60, and fall back through shorter tails otherwise.
   Google shows the site name separately, taking it from the `WebSite` node already emitted.
3. **Templates exist already.** `config/resources.php` defines free tools,
   including an inventory, a risk register and readiness checklists. They are
   served as static XLSX/CSV/MD files behind an email form at
   `/guides/tools/{slug}/download`. P5 replaces the static files with generated
   ones, removes the gate as the brief requires, and moves the pages to
   `/templates/*` with 301s. It does not stand up a second system. Removing the gate removes
   today's email capture from those downloads. Capture moves to an optional
   "email me when this template changes" box.
4. **Watchlist alerts are half built.** `Follow` covers policy, jurisdiction and
   obligation, and `alerts:send` emails daily (Pro-gated, deduplicated). P8
   extends this with more watch types and more channels rather than adding a
   parallel model. The Pro entitlement stays on the paid channels unless you
   decide otherwise.
5. **The change log is thin and stale.** It holds 2–3 events a month in 2026, and
   **nothing since 14 July 2026**. P2 builds the updates hub correctly (daily
   pages only when there are items, thin guard on everything else). Still, the
   Google News sitemap will be empty most days until changes are logged weekly.
   Google News publisher eligibility also depends on original news content
   published regularly. **This is editorial work, not code.**
6. **The regulatory facts in the brief are not in the data, and cannot be
   verified from this environment.** EUR-Lex, the Colorado legislature,
   congress.gov, nysenate.gov and law.go.kr are all blocked by the egress proxy
   here. Current state:
   - EU AI Act: the record still carries `applies_from: 2026-08-02`, with a note
     in the data itself that the Omnibus may have moved the high-risk dates and
     that the amending act has not been read against the Official Journal.
     Correct behaviour; the dates stay until a reviewer confirms the OJ text.
   - Colorado: the data has SB24-205 only. SB 26-189 is absent.
   - Korea AI Basic Act, 22 Jan 2026: **present**.
   - AI Dividend, the AI Sovereign Wealth Fund Act, the House excise bill, and NY WARN AI disclosure:
     **absent**.
   Under the trust rules, anything I add for these is a `draft` with unknown
   fields `null` and listed on `/gaps`. No date, sponsor, amount or bill number
   goes in unless it comes from a source I have actually read. P7 (deadlines)
   and P9 (economic transition) will be correct but sparse until you, or a reviewer,
   supply the official texts.
7. **The public site has no analytics.** P2's success metric (clicks on "ai policy
   updates") is readable in Search Console, so nothing is blocked. Adding page
   analytics is a product and privacy decision for you, not part of this plan.

## 3. Conventions every phase follows

- One branch per phase (`seo/p1-titles`, `seo/p2-updates`, …), small commits,
  authored as Bhaskar Bhatt `<bhaskar@aipolicytracker.org>`, with no tooling
  attribution (CONTRIBUTING.md).
- New data types go through the existing pipeline: YAML + JSON Schema +
  importer + read model. They then appear in `/api/v1` (with the OpenAPI
  document), CSV/NDJSON export, `llms.txt`, the sitemap, the change feed and the MCP
  server, in the same phase.
- Every page touched meets the page rules. `php artisan seo:audit` (added in P1)
  crawls the sitemap in process and fails CI on a violation, so the rules stay
  enforced rather than remembered.
- Tests per phase: unit tests for pure logic, feature tests for each page and
  API route (the HTTP happy path), and API contract tests against
  `/openapi.json`. There is no browser test framework in the repo. Browser checks
  (Lighthouse, a JS-off pass) run once, in FINAL QA, using the Chromium already
  on the machine rather than a new npm dependency.
- Dependencies: none is planned before P5. P5 needs `phpoffice/phpspreadsheet`
  (dropdowns, formulas, conditional formatting and frozen panes are not
  practical to hand-write in OOXML) and `phpoffice/phpword` (styled DOCX).
  The PDF export in P7/P8 is the one open question. I'll first try a print
  stylesheet that exports cleanly and add a PDF library only if that falls short.

## 4. Phases

Each phase lists what is built, what the brief asked for that I am changing, and
what needs you.

### P1 — Titles and the identifier leak (branch `seo/p1-titles`)
- `App\Support\PageTitle`: one generator with per-entity patterns. The brand
  suffix is added only when it fits; tails fall back in order; the result is never
  truncated mid-word.
- Incidents: neutral descriptive titles, no `#id`. URL `/ai-risk/incidents/<slug>`,
  with 301s from the numeric URL.
- Risks: title from category + subcategory + domain, no citation key. URL
  `/ai-risk/risks/<slug>`, with 301s from the dotted code.
- Policies, jurisdictions and updates use the requested patterns (updates in P2).
- `seo:audit`: an in-process sitemap crawl (title length, identifiers, one H1,
  description length, canonical, noindex-in-sitemap, duplicate titles).
- `scripts/seo/ctr-audit`: reads a Search Console "Pages" or "Queries" CSV
  export, lists position ≤10, impressions ≥100 and CTR <2%, and prints the
  current title next to the generator's suggestion.
- Tests: no title matches the identifier pattern; every title is ≤60. This runs over
  every record in the corpus through the generator, plus HTTP tests per page type.

### P2 — AI policy updates hub (`seo/p2-updates`) — done
`/updates`, `/updates/<yyyy-mm>`, `/updates/<yyyy-mm-dd>` (only with items),
`/updates/<jurisdiction>`. The answer box is computed from counts, and the page
shows a "Last updated" time and filters. A significance score (a pure function,
unit-tested) drives "Top stories". CollectionPage + ItemList, and NewsArticle for
items with their own URL. A Google News sitemap covers the last 48h, plus RSS per
jurisdiction and a "Latest updates" module on the home, policy and jurisdiction
pages. `/changes/*` stays as it is, as the record-level log, and the two link to
each other rather than duplicating. A month or jurisdiction page below the
content threshold is noindexed.
**Needs you:** changes logged weekly (see finding 5), and Google Publisher Center.

### P3 — Answer-first record pages (`seo/p3-answers`) — done
The answer box (40–60 words, composed only from structured fields) goes on
policy, obligation and jurisdiction pages, with a key-facts table and a FAQ from
a question bank. A question renders only when its answer is non-null. A "Cite
this record" box is added. JSON-LD uses Legislation, Article, Dataset, and Person
(for verified records only). The answer box also leads in the `.md` files and
`llms.txt`.

### P4 — Incident brand safety (`seo/p4-incidents`) — done
New fields: `policy_angle`, `related_policy_slugs`, `harm_domain`, and
`sensitivity`. Sensitivity is auto-classified from a keyword list, with a manual
override kept in `data/` so it survives the weekly re-import. Sensitive pages get
neutral titles and `noindex,follow`. They are removed from the sitemap and the
trending modules, and link to the relevant laws. A script lists sensitive URLs
still indexed (from a Search Console export, since the index can't be queried
from here).
**Brought forward:** P1 already gives every incident a neutral title, so no
sexualised wording survives into a title while P4 waits.

### P5 — Templates library (`seo/p5-templates`) — done
18 generated XLSX/DOCX templates (the 16 asked for plus two the free-tools
catalogue already promised: the technical-documentation kit and the global
applicability matrix), built from the read model by `templates:build` (daily,
05:30 UTC, and on every deploy). Each file has a README sheet or page with
version, dataset hash, date, disclaimer and CC BY notice; every duty row cites
its source reference and links to its record. XLSX: dropdowns from a hidden
Lists sheet, formulas copied down, conditional formatting, frozen headers,
autofilter. DOCX: real heading styles, `[PLACEHOLDER: …]` marks in colour,
citations as hyperlinks. A definition's content (sheets and blocks, as data) is
hashed: an unchanged template is skipped, a changed one gets the next version,
a changelog computed from the difference, a routine change event
(`template-<slug>-v<n>`, jurisdiction `international`, first-party source) and
a line in the weekly digest for subscribers of the new `templates` topic.
Downloads need no account. `/templates` hub (filters, CollectionPage, FAQ),
`/templates/{slug}` (preview from the stored version, "What's inside", legal
basis, covered duties, version history, FAQ, DigitalDocument), `/templates/
{slug}/download?format=`, `/templates/feed`. Obligation pages list the
templates that cover them. API `/templates`, `/templates/{slug}`,
`/templates/{slug}/download`; MCP `list_templates`; llms.txt; sitemap section.
**Adjusted:** built on the existing free-tools system (finding 3): the ten
static tools are replaced and `/guides/tools/{old}` (and `/download`) 301 to the
template; the tools no longer list on `/guides` or in the sitemap. The first
build of a template is publication, not a change, so it emits no event.
**Dependencies added:** `phpoffice/phpspreadsheet` ^5.9 (every 4.x and ≤5.8
release carries open advisories; the site never reads a spreadsheet, only
writes them, but the clean version costs nothing) and `phpoffice/phpword` ^1.3.
**Honest limit:** template 15 (Colorado notices) and the dates in template 3
depend on facts not yet in the data (finding 6). Both ship from the record as
it stands and say so on the page and in the file: the Colorado kit carries a
visible note that SB 26-189 is not yet recorded; the classifier's Tiers sheet is
read from the dated milestones on the EU AI Act record and the page shows the
record's own date caveat.

### P6 — Country hubs and compare pages (`seo/p6-hubs`) — done
`/ai-regulation-<country>` for the 20 countries is the jurisdiction page at
its canonical address (`Jurisdiction::url()` resolves to it, every listing
follows, `/jurisdictions/<slug>` 301s). Every jurisdiction page gains an
instruments table with the native-language name where the record carries one
(new optional `title_native` field in the policy schema; unknown stays null
and the page says so), a dated timeline (publication, adoption, entry into
force, application, deadlines), a link to its updates page and RSS feed, and a
breadcrumb to its regional hub. Regional hubs `/ai-regulation-<africa|asia|
europe|americas|oceania>` are computed from `jurisdictions.region`: answer
box from counts, country table, binding instruments, latest changes,
deadlines, FAQ, CollectionPage; indexed with ≥3 indexable countries.
Compare: `/compare/<a>-vs-<b>` for any two published jurisdictions, canonical
order alphabetical by slug (the other order 301s; a pair a curated comparison
covers 301s to it), side-by-side table, obligation overlap by category, and
"if you comply with A, what is left for B" (B's binding duties in categories
A does not bind, then shared ones to check). Only the pairs in
`config/hubs.php` are indexed and listed; the curated four keep their URLs
and gain the same sections.
**Thin guard, as specified:** 14 of the 20 hubs launch indexable; Ghana, Sri
Lanka, Pakistan, South Korea, Nigeria and Kenya have one sourced instrument
each and are served noindex until a second sourced instrument or a verified
strategy is recorded. Those six were indexable at `/jurisdictions/<slug>`
before, so this is a deliberate loss of six thin pages, not an accident.
**Not done:** native-language names for existing records: the field exists
and renders, but no reviewer has entered any yet.

### P7 — Deadline engine (`seo/p7-deadlines`) — done
`/deadlines/which-date-applies`: five plain-form steps (markets, role, kind
of system and risk tier, sector and use case, timeline) that work without
JavaScript, the answers travelling in the query string. The timeline is
computed only from the deadlines on record: a deadline is kept when its
instrument, or the duty it belongs to, names the answer or names no narrower
scope, and the obligation's applicability rules agree; the reason each date
is shown is printed beside it. "Originally X, now Y" comes from a new
`deadline_revisions` table the importer writes when a re-import finds a
deadline's date or status changed (deadlines are replaced wholesale on
import, so revisions are keyed by instrument and title). Exports: `.ics` of
the same rows through the existing calendar renderer, and a PDF (dompdf,
pure PHP, no remote assets). API `POST /api/v1/deadlines/applicable` and
`GET /api/v1/deadlines/applicable.ics`; MCP `get_applicable_deadlines`.
The empty form is the indexable page; answered states are noindex.
**Honest limit:** the record holds 29 deadlines on 14 instruments, 6 of them
future and scheduled, so most answers produce a short timeline; the engine
grows with the data, and revision history starts from this release (there
is none for earlier moves).
**Dependency added:** `dompdf/dompdf` ^3.1 for the PDF export (also used by
P8's register export).

### P8 — Watches and the obligations register (`seo/p8-watches`)
Extends `Follow` into watches. The new watch types are sector, use case,
framework, change type and saved search. Channels: the email digest, a private
RSS token, Slack, and a signed webhook with retries and a delivery log. Auth stays
the existing email + password (see §1). Privacy covers a consent log,
unsubscribe without login, and export/delete. `/api/v1/watches` gets CRUD. The
applicability check exports XLSX, CSV, JSON and PDF, with state kept in the URL
and nothing personal stored. The MCP server gets `build_obligations_register`.

### P9 — AI economic transition tracker (`seo/p9-transition`)
Adds TransitionMeasure, TransitionIndicator, and DisplacementPolicyIndex (a
versioned pure function). The hub, detail pages, 4 landing pages, a methodology
page, the API and MCP tools are added.
**Seed data is drafts only** (finding 6): each proposal gets a record whose sponsor,
bill number, date and amounts are `null` until read from the official source. The
landing pages stay noindexed until the thin guard passes. The index publishes
nothing until it has verified inputs, because a score computed from unverified
drafts would be the first number on this site that isn't sourced.

### P10 — Authority and trust (`seo/p10-authority`)
- `/state-of-ai-regulation`: a quarterly frozen snapshot with methodology, CSV
  and Dataset JSON-LD. The map comes with an accessible list alternative.
- Embeddable widgets (<30KB), an `/embed` configurator, and `frame-ancestors`
  opened only on `/embed/*`.
- Reviewer pages (Person); "Reviewed by" on verified records.
- `/newsletter/<date>`: the digest archive. This overlaps P2, so it's built there
  and linked here.
- Localisation (id, es, pt-BR): summaries only, with hreflang and x-default, and
  indexed only once reviewed.
**Needs you:** reviewers, and translators or reviewers for the three languages.

### Final QA
Full crawl (`seo:audit` plus link, redirect-chain and orphan checks), Lighthouse
on 10 URLs, OpenAPI validation, contract tests, and a check that MCP, llms.txt and
the exports all carry every new entity. The results and your manual steps go in
`docs/launch/<date>.md`.

## 5. What needs you, collected

1. **Weekly change logging** from now on. P2's traffic depends on it more than
   on any code.
2. **Official texts** for the EU Omnibus (the OJ reference), Colorado SB 26-189, and
   the four economic proposals. Without them P5 template 15, P7 and P9 ship with
   visible gaps.
3. **Reviewers** assigned to verify records. That is what moves records from
   draft to verified, and it is what the "Reviewed by" markup reports.
4. A decision on **Pro gating** for the new alert channels (P8).
5. **Search Console**: sitemap resubmission after P1 and P2, and a CSV export for the CTR
   audit. **Google Publisher Center** for News (after P2).
6. Deploying each phase. There is no deploy workflow.
