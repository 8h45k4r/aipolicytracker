# Changelog

All notable changes to this project are documented here. The format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/) and the project uses [Semantic Versioning](https://semver.org/).

## [Unreleased]

### Changed
- Positioning: "AIPolicyTracker is the regulatory intelligence layer for AI governance. Monitor source-backed AI policy changes, map obligations to real AI systems, and turn regulatory requirements into practical governance actions." applied to the home page eyebrow and meta description, footer, about page, email header, `llms.txt`, Organization structured data and README.

### Added
- Alerts (first Pro capability): "Follow for daily alerts" on every policy, jurisdiction and obligation page for Pro accounts (free and guest visitors see "Follow with Pro"), a `/following` page to manage follows, and `alerts:send`, run daily at 06:30 UTC through `POST /cron/alerts`, which emails each Pro follower once a day when a followed record has a new published change or an application date is 30, 7 or 1 days away. Idempotent per user and day, quiet when nothing changed. Documented in `docs/modules/alerts.md`.
- Billing: Admin → Billing lists recent checkout attempts with the provider's response when a session could not be created, so a refused checkout is diagnosable without server logs.
- Billing: one-click provider setup from Admin → Billing ("Provision webhook and products" registers the webhook endpoint for every subscription and payment event, creates the Pro monthly and annual products, and stores the signing secret and product ids encrypted; idempotent) and a Checkout on/off switch in Admin → Settings that overrides `BILLING_ENABLED`, so test-mode selling can start without a redeploy.

### Changed
- Positioning: "AIPolicyTracker is the regulatory intelligence layer for AI governance. Monitor source-backed AI policy changes, map obligations to real AI systems, and turn regulatory requirements into practical governance actions." applied to the home page eyebrow and meta description, footer, about page, email header, `llms.txt`, Organization structured data and README.

### Added
- Billing foundation with Dodo Payments as merchant of record: `/pricing` (Free and Pro monthly/annual), hosted checkout hand-off, return page that waits for confirmation, customer portal hand-off, a signature-verified webhook endpoint that mirrors subscriptions locally (idempotent by webhook id, stale-event guard), entitlements (`User::entitled()`, `subscribed` middleware), a plan card on the account page, a payment-failed email with a grace period, Dodo keys in Admin → Settings (encrypted) and an Admin → Billing page with a provider price check. Ships disabled (`BILLING_ENABLED=false`); see `docs/modules/billing.md`.

### Changed
- Governance docs: a binding engineering standard (`docs/reference/engineering-standard.md`: inspect before coding, Analyze → Design → Implement → Test → Validate → Document, definition of done, verification language) referenced from `CLAUDE.md`, `CONTRIBUTING.md`, the change gates, the compliance map and the pull request template; stale "four gates" wording corrected to five; `IMPLEMENTATION_REPORT.md` marked as a point-in-time record of #16.
- Resilience of external data: each live sync also merges its rows into a local snapshot on the persistent private disk, and `external:import` reads that snapshot after the repository files, so a rebuilt database recovers every live-synced record without the AI Incident Database API; pages never call AIID at request time, and a failed sync leaves the stored data untouched.

### Fixed
- Tool downloads returned 404 after a clean deploy because the private storage folder was replaced while the file rows survived. The `local` disk root is now configurable (`FILESYSTEM_LOCAL_ROOT`, set to persistent storage in production), the seeder restores missing seeded files at startup, and the download route falls back to the bundled copy.

### Added
- Live sync of AI incidents from the AI Incident Database API (`external:sync-aiid-api`): new and modified records with their alleged deployers, developers and harmed parties (with entity identifiers), implicated systems, editor notes, related incidents, MIT and CSET classifications and report metadata are pulled every six hours through the cron trigger, on demand from Admin → External data, and merged into the reviewed JSON by the weekly refresh. `/ai-risk/incidents` lists the latest recorded incidents from the read model with the sync time, each linking to its profile; profiles show editor notes, entity links, implicated systems, related incidents and sync dates. Re-imports of the weekly snapshot never delete or downgrade live-synced rows.

### Changed
- Open-source hygiene: the edge Worker reads its origin host from an `ORIGIN_HOST` variable instead of the repository; stale hosting examples removed; CODEOWNERS covers data, compliance docs, workflows and middleware; new required **Gate check** workflow fails pull requests that do not document the five gates and accepted debt; the deploy workflow purges the Cloudflare cache when the zone secrets are configured.

### Added
- Five global tool templates: Global AI Regulatory Applicability Matrix, AI Vendor Due Diligence Questionnaire, AI System Technical Documentation Template (Annex IV structure), AI Impact Assessment Template (FRIA and HUDERIA aligned) and AI Policy Statement Template, in XLSX, CSV, Markdown and DOCX; the library now holds ten tools.
- Charts: hover tooltips on every bar, milestone and treemap block; a relative colour scale (below half of peak, half to three-quarters, top quarter) on the incident timeline and the harmed-party and deployer charts with a legend; Download SVG, PNG and CSV on every chart (with source and date stamped).
- Admin Tool library: step-by-step "How to add a tool and upload its files" panel; matching section in the module documentation.

### Added
- AI risk hub rebuilt as an evidenced narrative: headline totals with 12-month growth, an incident timeline annotated with policy milestones (each bar opens that year's incidents), the shift in domain shares since 2019, most-named harmed parties and deployers, assessed harm levels, a "where harm is recorded versus where rules exist" coverage table (incidents by country against tracked and binding instruments), instruments per domain, and next steps by persona (CISO, researcher, policymaker or diplomat, civil society and journalists).
- Home page "Start from your job" entry points; policy pages show the AI risk domains an instrument addresses with recorded incident counts; the weekly digest includes the week's recorded AI incidents.
- `docs/reference/personas-and-jobs.md`: personas, pain points and the product's single USP (harm → rule → action, sourced at every step).

### Added
- Complete country coverage: 95 further jurisdictions so every UN member state (plus Kosovo, Palestine, Taiwan and Hong Kong) has a record. Where no AI-specific instrument exists the record says so and points to the government portal and a labelled secondary source; four instruments added (El Salvador's AI promotion law, the Holy See's Rome Call for AI Ethics, Azerbaijan's AI Strategy 2025–2028, Tajikistan's AI strategy to 2040). Totals: 212 jurisdictions, 186 instruments.

### Added
- MIT AI Risk drilldown: subdomain profile pages (`/ai-risk/{domain}/{subdomain}`) with the repository's definition, causal entity, intent, timing and level breakdowns, incidents per year, the frameworks that cite the subdomain, paginated risk entries and recent incidents; domain→subdomain treemaps on the AI risk hub sized by risk entries and by recorded incidents; subdomain pages in the sitemap.

### Added
- Incident profiles list the news reports the AI Incident Database catalogues for that incident (6,434 reports across 1,647 incidents): date, title linked to the publisher, source, authors and the AIID report number. Metadata only; synced weekly from the AIID backup by `external:sync-aiid-reports`.

### Changed
- Guides framework and topic filters are compact dropdown buttons with a checkbox panel (showing the selected count) instead of native multi-select lists; selections apply when the panel closes.

### Added
- Durable human verification: the admin review queue lists unverified and low-confidence instruments first with a link to the official source; saving "verified" requires confirming the source was opened and records reviewer, date and confidence. Decisions are re-applied after every import and `policy:export-verifications` writes them back into the YAML records.

### Changed
- Removed the 24 estimated adoption dates added in the coverage expansion; those records now show no date until a reviewer sources one.
- Download-links email after every free-tool download: 24-hour personal links to each format, the related guide and the next-step tool.
- DOCX versions of the EU AI Act Readiness Checklist, AI Incident Response Checklist and 30-Day Starter Plan.
- Anonymous daily page-view counts for the guides library and tool pages (no cookies, IPs or user ids) feeding a 30-day funnel on the admin Guides and downloads page: library views, tool views, download clicks, sign-ups from a gate, downloads, second-tool users.
- "Report a correction" on AI incident and MIT risk profile pages, prefilled with the record's fields and its source link.
- Account page (`/profile`) in the site theme: profile and organisation, password change, download history with fresh links, consent status, resend verification, account deletion.

### Changed
- Forgot-password, reset-password, verify-email and confirm-password pages are server-rendered in the site theme; the legacy React versions are no longer served.
- Guide titles and summaries rewritten around the reader's outcome (who it is for, what they get) for the four featured guides.
- Open Graph image regenerated in the navy brand (1200×630).
- Technical-debt register: entries #3, #8, #9, #10 and #12 closed (legacy map, CMS and log mailer no longer exist).

### Changed
- Startup clears all Laravel caches (`optimize:clear`) before rebuilding them, so no configuration, route, view or application cache from the previous release survives a deploy.

### Added
- Admin → Tool library: create, edit, publish/draft/archive free tools; upload, activate, deactivate and remove their files (XLSX, CSV, Markdown, PDF, DOCX, JSON, text). Tools moved from configuration to `tools` and `tool_files` tables, seeded once from the previous configuration; downloads now record the served file and files count downloads.

### Changed
- Guides filters are a compact bar: search, content-type and access dropdowns, multi-select framework and topic lists, with active filters shown as removable chips.
### Fixed
- Scheduled uptime self-heal: every 15 minutes the health endpoint is probed and, if it fails twice, the app is restarted once through the Kudu API (skipped while a deploy is running). Covers the container stalls seen after deploys on the free tier.

### Security
- Nonce-based Content-Security-Policy and Cross-Origin-Opener-Policy on every response; PHP version header removed and nginx server tokens hidden. Fifth change gate (Security / VAPT) added to the process with the first assessment recorded in `docs/reference/vapt-2026-09-11.md`.

### Security
- Removed 15 unused JavaScript packages left from the legacy map site (amCharts, CKEditor, MUI, Emotion, react-select, react-slick, react-toastify, react-dropzone, DOMPurify) and applied `npm audit fix`; production dependencies now audit clean.

### Added
- Guides page now has search and filter chips (content type, framework, topic, access) and a "Free tools and templates" section: AI System Inventory Template, AI Risk Register Template, EU AI Act Readiness Checklist, AI Incident Response Checklist and AI Governance 30-Day Starter Plan (XLSX, CSV, Markdown). Each tool page previews every field, explains purpose and use, maps to recorded policies and guides, and gates the download behind a free account with explicit licence acceptance; files are served through signed 30-minute links to the requesting account.
- Free-account registration is a server-rendered page (name, email, optional organisation, password, terms; marketing updates opt-in and unticked). Admin "Guides and downloads" page: user and download metrics, most downloaded tools, sign-up sources, download activity, registered users and CSV export; dashboard tiles for users and downloads.

### Fixed
- AI incidents page no longer scrolls horizontally; grid children and charts are constrained to the viewport.
### Changed
- README rewritten around what readers can do, with architecture, data-flow and free-tools diagrams (`docs/diagrams/`); hosting and rate-limit specifics moved out of the public README. Dependabot configuration removed; dependency updates are handled through the release process.

### Fixed
- Legacy record URLs (`/news/{id}`, `/aipolicytracker/single-view/{id}`) that Google still crawls now redirect permanently to the change log and policy explorer instead of returning 404 or 5xx.

### Fixed
- Deploys now clean the target folder before unpacking the release, so files deleted or moved in the repository no longer linger on the server (stale duplicates had blocked `policy:import`). When the import fails at startup, the validation errors are printed to the container log.

### Added
- Single-incident profiles (`/ai-risk/incidents/{id}`) with every stored field, alleged deployer/developer/harmed parties, MIT taxonomy classification, similar incidents, other incidents involving the same deployer, the MIT risk entries describing the same failure mode, a link to the incident's news reports on the AI Incident Database, Save button, JSON export and Article structured data.
- Single-risk profiles (`/ai-risk/risks/{ev_id}`) for each MIT AI Risk Repository entry with the subdomain definition, matching real-world incidents, how other frameworks describe the same risk and the paper's other entries. Browse pages, the home page and domain pages now link to the profiles.
- Maintainer links (website, X, LinkedIn, GitHub) on the About page and in the footer.

### Changed
- Admin sign-in is a server-rendered page in the admin theme with a show/hide password control; the legacy React login page with outdated feature copy and a register link is no longer served. Admin layout uses a sticky sidebar and full-height content area.
- Correction form prefilled from the record: opening "Report a correction" on a policy, jurisdiction, obligation or change shows the record, its official source, a field picker with the value currently displayed, and a "correct value" box. The captured context (field, shown value, proposed value, record URL and content version) is stored with the submission and shown in the admin queue and alert email.
- `/subscribe` page with a jurisdiction picker grouped by region, subscriber count and recent changes; "Subscribe" and "Saved" links in the header, mobile menu and footer.
- "Follow" box on every policy page: subscribers can receive changes for one instrument only (policy slugs are now valid digest topics).
- Saved records: a per-browser reading list (`/saved`) with Save buttons on policy, jurisdiction and obligation pages, count badge in the header, and copy as Markdown or JSON.

### Fixed
- `/dashboard` redirected to `/map`, which redirected again; it now goes straight to the home page.
- Branded email layout for every message (confirmation, weekly digest, submission alerts and the admin test mail): centred logo, tagline, organisation, "Follow us on social" icons and copyright footer. Official LinkedIn, X, Facebook and Instagram profiles are configured once in `config/aipolicytracker.php` and shown in the website footer, the emails and the Organization structured data.
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
