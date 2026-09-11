# Changelog

All notable changes to this project are documented here. The format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/) and the project uses [Semantic Versioning](https://semver.org/).

## [Unreleased]

### Added
- Global coverage: 78 new jurisdictions (117 total) and 104 new policy instruments (182 total) with 26 dated change events, covering every EU member state, non-EU Europe (Norway, Iceland, Switzerland, Serbia, Ukraine, Türkiye, Russia), the Council of Europe Framework Convention, international instruments (OECD AI Principles, UNESCO Recommendation, UN General Assembly resolution, G7 Hiroshima code, Bletchley Declaration, ISO/IEC 42001), ASEAN guides, US states (Texas, Utah, Illinois, New York, Tennessee), Canadian provinces (Quebec, Ontario), Central and South America, the Caribbean, Asia-Pacific (New Zealand, Hong Kong, Taiwan, Sri Lanka, Kazakhstan, Uzbekistan, Mongolia, Cambodia), the Middle East (Israel, Jordan, Kuwait, Oman, Lebanon) and Africa (Morocco, Tunisia, Algeria, Ethiopia, Senegal, Mauritius, Benin, Côte d'Ivoire, Uganda, Zambia, Zimbabwe, Sierra Leone, Tanzania, Namibia). Every record links an official source and enters as `pending_review` (debt #18).

### Changed
- Region labels normalised (`Americas`/`Northern America`, `Asia`/`Western Asia`, `South-East Asia`) so jurisdiction grouping and the subscribe page read consistently; US state policy files moved under their own jurisdiction directories.

### Changed
- Deploy workflow waits for the health endpoint and restarts the app once through the Kudu API if the platform stops the container after a deploy.

### Fixed
- `external:import` failed on PostgreSQL when the MIT database contained duplicate `Ev_ID` values in one upsert batch; duplicates are now suffixed deterministically and the import is idempotent. Startup data steps no longer take the container down on failure.

### Changed
- Space Grotesk (with Space Mono for numerals) is now the typeface across the public site, admin and emails.
- HTML responses are served with no-store cache headers so every page reflects the latest import; API, exports, feeds and sitemaps keep their own caching.
- Admins receive an email for every public submission (correction, source, policy, reviewer application) linking to the review queue.
- Home page shows the latest AI incidents with the snapshot date; research pages carry Dataset structured data with CSV/JSON distributions and FAQ schema for search and answer engines.

### Added
- Researcher tooling on AI risk: the full AI Incident Database (1,663 incidents, metadata only) and MIT AI Risk Repository database (2,500 risk entries from 74 frameworks) imported into read-model tables; browse pages with filters (year, domain, subdomain, entity, intent, timing, country, sector, harm level, keyword), CSV/JSON exports that carry licence and citation, a frameworks page, causal entity × intent matrix and stacked domain-by-year charts, and per-domain risk/incident counts and recent incidents.

### Removed
- Legacy map site and admin CRUD (React/Inertia pages, controllers, models, seeders, the `ai_policies.json` sample dataset and ten database tables). `/map`, `/news`, `/timeline` and `/bookmarks` now redirect permanently to the structured pages; the change log and topic-based digest subscriptions replace news, timeline and bookmarks. Auth, profile and registration are unchanged.

### Changed
- Review queue moved into the shared admin layout with a jurisdictions publish table.
- Legacy map site (`/map`, `/news`, `/timeline`, `/bookmarks`) and its admin CRUD are disabled by default (`LEGACY_MAP_ENABLED=false`); old URLs redirect to the new pages. Audit in `docs/reference/admin-audit.md`.

### Added
- Weekly email digest: double opt-in subscribe form (home, change log, jurisdiction pages), branded confirmation and digest emails with one-click unsubscribe, `digest:send` command and a weekly workflow trigger secured by a token.
- New admin (Blade) at `/backend/dashboard`: live counts from the structured model, submissions and feedback with review decisions, subscribers (export CSV, re-send confirmation, delete), external-data status, and a Settings page where the Resend API key, mail transport and cron token are stored encrypted and applied without redeploying. Legacy React admin remains under "Legacy (map data)".

### Fixed
- Legacy login, register and verify-email pages now use the navy primary and open "Go to home" as a full page load instead of an Inertia visit (which rendered the server-side home page inside a frame).

### Changed
- About page rewritten with an original structure (why it exists, how to use it, how records are made, datasets and research references, team, open-by-default, reviewers, contact) plus FAQ structured data; Organization structured data now carries the founder (bhaskar.com.np) and parent organisation.

### Changed
- Record verification wording: unverified records now read "Source-linked · checked <date>" instead of "Human verification pending"; the amber banner on policy pages was removed (review status stays in the data, API and llms-full output).
- Footer no longer lists llms.txt; it remains linked from the Open data page and served at /llms.txt.

### Added
- AI risk section: the seven MIT AI Risk Repository domains and 24 subdomains (CC BY 4.0) with incident counts, per-domain pages linking to related policies, and an AI incidents summary page (AI Incident Database, CC BY-SA 4.0) with yearly, domain, sector, country and harm-level views and links to each incident record. Data lives in `data/external/` and is refreshed weekly by `external:sync-aiid` / `external:sync-mit-risk` through a pull request.

### Added
- Structured records bridged from the source-backed legacy dataset: 29 new jurisdictions, 60 policy instruments and 96 dated change events (all `pending_review`, official source on every record), bringing the public site to 39 jurisdictions and 78 instruments.

### Changed
- Public site brand refresh: official logo lockups (light and dark), SVG favicon from the mark, navy `#002147` primary with semantic brand and state colour tokens, Source Serif 4 / IBM Plex typography, editorial front page (lead changes, upcoming dates, jurisdiction rows) replacing stat tiles and card grids, navy footer with attribution.

### Fixed
- Change log pages and the changes sitemap failed on PostgreSQL because year grouping used `substr()` on a date column; years are now derived portably.
- Production startup: `symfony/yaml` declared as a runtime dependency and caches rebuilt before data steps.

### Added
- Policy-intelligence data foundation: `data/` YAML records with JSON Schema, taxonomies, and `policy:validate`, `policy:import`, `policy:export` commands; new tables for jurisdictions, policy instruments, versions, sections, obligations, applicability rules, deadlines, enforcement events, procurement rules, framework mappings, evidence artifacts, change events, source documents, contributor submissions and reviewer decisions.
- Source-backed seed records for the EU, UK, US (federal, Colorado, California), India, Nepal, Singapore, Australia and the UAE, all marked `pending_review` until human verification.
- Server-rendered public site: home, `/policies`, `/policies/{slug}` (+ `.json`), `/jurisdictions`, `/jurisdictions/{slug}`, `/obligations`, `/obligations/{slug}`, `/compare` (+ curated pages), `/changes` (+ yearly archives and RSS), `/tools/applicability-check`, `/open-data`, `/methodology`, `/about`, `/contribute`, `/guides/*` and editorial landing pages.
- Read-only public API under `/api/v1` with OpenAPI document at `/openapi.json`.
- Technical SEO: per-page metadata, canonicals and noindex rules, sitemap index with six child sitemaps, updated `robots.txt`, JSON-LD structured data, `llms.txt` / `llms-full.txt`, branded 404, legacy redirects.
- Admin review queue and publish/unpublish controls at `/backend/review`.
- Consent-gated analytics hooks and event tracking; Search Console / Bing verification placeholders.
- `PRODUCT_SEO_AEO_AUDIT.md`, `SEO_OPERATIONS.md`, `CONTENT_OPERATIONS.md`, `DATA_UPDATE_OPERATIONS.md`.

### Changed
- The homepage is now the policy-intelligence site; the legacy map dashboard moved to `/map` (noindex). The client-rendered legacy/admin shell is `noindex`.
- `php artisan migrate --seed` also imports the structured `data/` records after the legacy map dataset.
- Deployment scripts run `policy:import` after migrations.
- Replaced the fictional sample policies with a source-backed dataset (`database/data/ai_policies.json`) covering the EU, UK, US, Canada, Australia, Japan, South Korea, China, South Asia, ASEAN, Africa, the GCC, and Latin America; every entry cites an official source and access date. The seeder is now idempotent and `policies:purge-sample` removes the old sample rows on deployment.
- Binding four-role change gates (`docs/reference/change-gates.md`), technical-debt register, compliance map, module documentation for every existing module, data-integrity test proving all interlinks, and `CLAUDE.md` project rules; PR template restructured around the gates.
- Why comments on every migration.
- Licence changed from MIT to Apache License 2.0 (with `NOTICE`).

### Added
- CodeQL analysis, Dependabot configuration, CODEOWNERS, and private vulnerability reporting.
- Cloudflare Worker (`cloudflare/worker.js`) that fronts the public domain.
- Azure App Service startup/nginx configuration and GitHub Actions deploy workflow.
- Production `Dockerfile`, entrypoint, `.dockerignore`, and optional `fly.toml`; README deployment notes for containers and Cloudflare.

## [1.0.0] - 2026-09-10

First public open-source release.

### Added
- MIT licence, README, contribution guide, code of conduct, security policy, and source attribution policy.
- GitHub issue templates (bug, policy data correction, new jurisdiction/source, feature), pull request template, and CI workflow (PHP lint/tests, JS lint/build).
- `config/aipolicytracker.php` with environment-driven admin list, public links, contact addresses, analytics ID, and page-size limits.
- `SecurityHeaders` middleware and a restrictive `config/cors.php`.
- ESLint 9 configuration and `npm run lint`.
- Admin access feature tests.
- Migration dropping the legacy `user_infos.password` column.

### Changed
- Admin authorisation now uses `ADMIN_EMAILS` instead of hard-coded addresses.
- Header/footer/about links and contact addresses are read from configuration.
- Google Analytics loads only when `GOOGLE_ANALYTICS_ID` is set.
- JSON error responses no longer include exception messages; page sizes are clamped.
- Country status update validates its input.
- Registration no longer stores the plaintext password.
- Admin seeder creates the first admin from `ADMIN_*` environment variables.
- Tests updated to match the application's redirect behaviour; PHPUnit uses in-memory SQLite.

### Removed
- `/clear-cache` and `/storage-link` web routes.
- Hard-coded personal e-mail addresses, contributor lists, and external document links.
- Duplicate/dead files (copied templates, unused Vue components, duplicate profile pages, unused images) and unused npm packages.
