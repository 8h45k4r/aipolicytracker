# AIPolicyTracker — Product, SEO and AEO Audit

Audit date: 2026-09-10
Scope: the `8h45k4r/aipolicytracker` repository as it stood before the policy-intelligence rebuild (commit `48d7bdc`).

This audit was written before any implementation change. It records what exists, what is wrong, and the plan that the rest of the branch implements.

---

## 1. Current architecture and routes

### Stack

| Layer | Finding |
|-------|---------|
| Framework | PHP 8.2+, Laravel 11.x, Inertia.js 1.x (React adapter), Laravel Breeze auth, Ziggy for named routes in JS |
| Frontend | React 18 + Vite 5 + Tailwind 3 + Flowbite + MUI + amCharts 4/5 (world map) + CKEditor 5 (admin). No TypeScript. |
| Rendering | **Client-side only.** Every page is an Inertia component. `resources/views/app.blade.php` ships a static `<title>` and meta, then hydrates React. Inertia SSR is not enabled, so no route renders its data as HTML. |
| Database | PostgreSQL (Supabase recommended), MySQL, or SQLite. UUID primary keys. |
| Data source | Admin CMS (`/backend/*`) writing to `ai_policy_trackers`, `countries`, `statuses`, `news`, `contributing_orgs`, `nav_bars`. Seeder `AiPolicyTrackerSeeder` loads **fictional placeholder policies** (fake URLs such as `http://www.ftc.gov/ai-regulation`, dates in 1990/1992/1995). |
| Deployment | PHP application behind a CDN edge Worker (`cloudflare/worker.js`); hosting specifics are kept out of the repository. |
| CI | GitHub Actions: PHP tests + Pint (advisory), ESLint + Vite build, CodeQL, Dependabot. `npm run typecheck` and `npm test` are echo placeholders. |
| Analytics | Optional GA4 via `GOOGLE_ANALYTICS_ID` (gtag injected in `app.blade.php`, no consent handling, no event tracking). |

### Public routes (before)

| Route | Component | Notes |
|-------|-----------|-------|
| `/` | `Frontend/Dashboard/Dashboard` | World map (amCharts) + filterable table + news + contributing-org logos. All data client-rendered. |
| `/dashboard/filtered`, `/dashboard/updateStatus` | JSON | Internal filter endpoints, crawlable, no cache headers. |
| `/about-ai-policy` | `Frontend/AboutUs/AboutAiPolicy` | `<Head title="Denied permission" />` — wrong title. |
| `/news`, `/news/{id}` | News list/detail | Client-rendered CMS news. `/news/filtered`, `/news/show-advanced-info` are POST. |
| `/timeline` | Timeline by year | Client-rendered. |
| `/aipolicytracker/single-view/{id}` | Single policy | **Requires login + verified e-mail.** Public visitors get a "denied" page — the only per-policy page on the site is not indexable. |
| `/gov-ai-index/assesment` | Self-assessment questionnaire | Typo in URL; client-only. |
| `/bookmarks*` | Bookmarks | Auth-only. |
| `/denied` | Denied page | Should be noindex. |
| `/backend/*` | Admin | Auth + `isAdmin`. |
| `/login`, `/register`, `/profile`… | Breeze auth | — |
| `/up` | Health check | — |

There is no `/policies`, `/jurisdictions`, `/obligations`, `/compare`, `/changes`, `/open-data`, `/methodology`, `/contribute`, no public API, no sitemap, no RSS.

---

## 2. Branding and content mismatch

- The **repository** is an AI policy tracker; it does not contain Certifyi SOC 2 / ISO 42001 landing-page code. The only Certifyi references are the maintainer link in `README.md` and `.github/FUNDING.yml`.
- The **live domain** could not be fetched from this environment (egress is restricted), so the reported Certifyi-first landing page is most likely served by the Azure origin or a different Cloudflare route/Worker, not by this codebase. That must be checked in the Cloudflare dashboard (Worker routes for `aipolicytracker.org/*`) and on the App Service before deploying this branch. This is listed as a manual task.
- Inside the codebase the messaging is still misaligned with the product brief:
  - Static `<title>` "Global Artificial Intelligence (AI) Policy Status" on every page (duplicate titles site-wide).
  - Marketing-style meta description (~90 words, "join our community for discussions, workshops, and conferences"), a `keywords` meta tag full of stuffed phrases, `og:` tags using `name=` instead of `property=`.
  - Homepage is a map-first dashboard, not a search-first policy explorer; there is no product headline, no trust block, no open-data or methodology story.
  - Status vocabulary (research / whitepaper / pilot / development / launched / cancelled) describes national *strategies*, not legal instruments; there is no notion of "in force", "adopted", "under consultation", "repealed", etc.
  - The seeded records are fictional, which directly contradicts the "source-backed" mission if deployed.

---

## 3. Existing data sources and how live data can be integrated

- `ai_policy_trackers` holds one row per national policy with free-text fields (`governing_body`, `technology_partners`, `governance_structure`, `main_motivation`, `description`, `whitepaper_document_link`). There are no source-quality fields (no `last_verified_at`, `source_publisher`, `confidence_level`, `review_status`), no versioning, no obligations, no deadlines, no sections.
- `a_i_policy_activity_logs` records admin edits (activity name + description) — usable as an audit trail but not as a public change log.
- `news` is an admin CMS feed, not a dated regulatory change log.
- Integration path chosen (Part 1): keep Laravel + the existing relational database, add a normalized policy-intelligence schema, and make a versioned `data/` directory of YAML records the canonical, reviewable source of truth (GitHub PR workflow). An artisan importer loads YAML into the database; a validator enforces the JSON Schema in CI. The public API and site read from the database with cache headers. Cloudflare stays in front as CDN. D1/KV/R2 are **not** introduced: the app is PHP and cannot run on Workers, and the existing Postgres + Laravel cache cover the same needs. This is the "adapt cleanly" path the brief allows.
- Legacy tables and the admin CMS are kept (documented in §7) so existing maintainers' data is not destroyed; the legacy map dashboard moves from `/` to `/map`.

---

## 4. SEO / AEO — strengths and gaps

### Strengths
- Clean Laravel routing, HTTPS via Cloudflare, security headers middleware, `robots.txt` exists and already disallows auth/admin paths.
- Tailwind + Vite pipeline is fast to build on; Blade is available for server-rendered pages without adding an SSR process.
- Open-source governance docs (CONTRIBUTING, SOURCE_ATTRIBUTION, issue templates for data corrections) already exist and can back a contribution workflow.

### Gaps
| Area | Gap |
|------|-----|
| Rendering | All content is client-rendered; crawlers that do not execute JS see only the shell. AI crawlers largely do not execute JS. |
| Titles / descriptions | One static title and description for the whole site; Inertia `<Head>` titles are set client-side and are wrong on some pages ("Denied permission" on About). |
| Canonicals | None. `og:url` absent. `www` vs apex not canonicalized in-app. |
| Structured data | None. |
| Sitemaps | None. `robots.txt` has no `Sitemap:` line. |
| Indexable detail pages | The single policy page is login-gated; there are no jurisdiction or obligation pages at all. |
| Internal linking | Map/table → login wall. No breadcrumbs. |
| Feeds / machine-readable | No RSS, no API, no OpenAPI, no `llms.txt`. |
| Search Console | No verification placeholder. |
| Content | No editorial landing pages, no FAQs, no glossary, no methodology. |
| Duplicate/thin URLs | `/dashboard/filtered?...` JSON endpoints are crawlable GET URLs with no `noindex`/`X-Robots-Tag`. |

---

## 5. Technical issues found

1. **Composer lock is incompatible with PHP 8.4** (`nette/schema 1.3.0` requires PHP 8.1–8.3). CI pins PHP 8.2/8.3 so it passes there, but local setups on 8.4 fail without `--ignore-platform-req=php`. Not changed in this branch (would need a lock update); documented.
2. `resources/js/Pages/Frontend/Dashboard/Dashboard.jsx` contains a bare `console;` statement and a debounce that never clears its timer.
3. `SinglePolicyTackerControlle` (typo) gates public policy detail behind login + verified e-mail; `aiPolicyBookMark` is a no-op returning success.
4. `Country` rows carry `status`, but the map builds `$URL_MAP[null]` for inactive countries.
5. `app.blade.php` loads Font Awesome and Flowbite from CDNs on every page, plus MUI/amCharts bundles (heavy for a content site; amCharts alone is >1 MB).
6. `<html class="white">`, `<body class="Poppins" style="max-width:1920px">` — no font named Poppins is loaded; Figtree is loaded from bunny.net and unused in Tailwind config.
7. Migration `add_column_to_ai_policy_trackers` has an empty `down()`.
8. `GovAiIndexHelper` and the "government AI readiness" questionnaire are unrelated to the policy tracker product and are client-only.
9. `npm run typecheck` / `npm test` are placeholders (no failing signal).
10. The About page renders text from `DescriptionData.js` — static marketing copy about "AI Policy Tracker" mixed with contributor logos.
11. No 404 template beyond Laravel's default; no redirects for legacy URLs.
12. Indexation risk: JSON filter endpoints and the `/denied` page are crawlable.
13. Performance risk: homepage loads the amCharts world map, react-select, MUI and slick carousel on first paint; no skeletons; layout shifts while news/logos load.

---

## 6. Prioritized implementation plan

**Phase 0 — Audit (this document).**

**Phase 1 — Data foundation (Part 1).**
- Migration creating: `jurisdictions`, `policy_instruments`, `policy_versions`, `policy_sections`, `obligations`, `applicability_rules`, `taxonomy_terms` (AI system types, actors, sectors, risk categories, use cases, obligation categories), `taxonomy_assignments`, `deadlines`, `enforcement_events`, `procurement_rules`, `framework_mappings`, `evidence_artifacts`, `change_events`, `source_documents`, `contributor_submissions`, `reviewer_decisions`.
- Status enum: proposed, under_consultation, adopted, in_force, partially_applicable, guidance, voluntary_standard, enforcement_action, superseded, repealed, archived.
- Source-quality fields on every record type that makes a factual claim.
- `data/` directory: `data/schema/*.schema.json`, `data/taxonomies/*.yaml`, `data/jurisdictions/*.yaml`, `data/policies/<jurisdiction>/<slug>.yaml`, `data/changes/*.yaml`.
- Artisan commands: `policy:validate` (schema + referential checks), `policy:import` (idempotent upsert), `policy:export` (JSON bundle for open data).
- Seed the eight priority jurisdictions with official-source-linked records; every record marked `review_status: pending_review` with `last_verified_at: null` because this environment cannot reach official sites (see §8).
- Read-only public API `/api/v1/*` with OpenAPI document; contributor submissions POST → `pending_review`; admin review/publish screens under `/backend/review/*`.

**Phase 2 — Public UI (Parts 2–3).** Server-rendered Blade + Tailwind public site (no JS needed for content; small progressive-enhancement script for filter sheet, copy-link, accordion polish). Routes: `/`, `/policies`, `/policies/{slug}`, `/jurisdictions`, `/jurisdictions/{slug}`, `/obligations`, `/obligations/{slug}`, `/compare`, `/compare/{slug}`, `/changes`, `/changes/feed`, `/tools/applicability-check`, `/open-data`, `/methodology`, `/about`, `/contribute`, editorial landing pages and guides. Legacy Inertia dashboard → `/map` (noindex), legacy single-view → 301 to new policy page where mapped, otherwise noindex.

**Phase 3 — Technical SEO (Part 4).** Per-page metadata component, canonical rules, `noindex,follow` for filtered/paginated-filter URLs, sitemap index + child sitemaps with real `lastmod`, dynamic `robots.txt`, 404 page, breadcrumbs, `llms.txt` / `llms-full.txt`, Search Console verification placeholder.

**Phase 4 — AEO + structured data (Parts 5–6).** Answer-first page templates with question headings, definition blocks, timeline tables, FAQ only where genuine, JSON-LD (`Organization`, `WebSite`+`SearchAction`, `WebPage`, `CollectionPage`, `BreadcrumbList`, `Dataset`, `FAQPage`, `ItemList`), OpenAPI, RSS, downloadable JSON per policy.

**Phase 5 — Content system (Part 7).** Editorial page templates driven by verified data with indexation thresholds (`noindex` when a jurisdiction has fewer than one published instrument or a policy lacks a summary + official source), related-content blocks, initial landing pages and guides.

**Phase 6 — Analytics & operations (Part 8).** Config-driven GA4 / Cloudflare Web Analytics / Search Console / Bing placeholders, consent gate, `data-track` event hooks, `SEO_OPERATIONS.md`, `CONTENT_OPERATIONS.md`, `DATA_UPDATE_OPERATIONS.md`.

**Phase 7 — Verification.** Feature tests for every public route, sitemap, API and submission workflow; `composer lint`, `composer test`, `npm run lint`, `npm run build`.

---

## 7. Functionality intentionally kept, moved, or retired

| Item | Decision | Reason |
|------|----------|--------|
| Legacy map dashboard (`Frontend/Dashboard`) | Moved to `/map`, `noindex` | Map-only UX is explicitly not the core interaction; keeps existing maintainers' data visible. |
| Legacy `ai_policy_trackers` table + admin CMS | Kept | Non-destructive; maintainers may migrate entries into `data/` over time. |
| News, timeline, bookmarks, notifications | Kept, `noindex` on the Inertia shell | Client-rendered and thin; not part of the new information architecture yet. |
| Gov AI readiness questionnaire | Kept at its existing URL, `noindex` | Out of product scope; retiring it is a product decision for the maintainer. |
| Login-gated single policy view | Kept for legacy records only | New `/policies/{slug}` pages are public. |
| Fictional seed data | Not loaded by default | `DatabaseSeeder` now seeds the verified `data/` records; the legacy sample seeder remains callable explicitly and is labelled illustrative. |

---

## 8. Blockers and risks identified before implementation

1. **No outbound access to official sources from this environment.** Every seeded record therefore carries `last_verified_at: null` and `review_status: pending_review`. A human reviewer must open each `official_source_url`, confirm the fields, and set `last_verified_at` before public deployment.
2. **Live-site mismatch is outside the repo.** The Certifyi landing page must be located (Cloudflare Worker routes, App Service slots) and replaced by deploying this branch.
3. **PHP 8.4 lock incompatibility** (see §5.1).
4. Legal/content risk: all summaries are original, plain-language descriptions of official documents and are labelled "informational, not legal advice"; obligation text is paraphrased with article references, never reproduced at length. ISO/IEC 42001 and NIST AI RMF mappings are original editorial crosswalks pointing to the standards' official pages, not standard text.
