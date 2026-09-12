# Implementation Report: AIPolicyTracker rebuild

Branch: `claude/relaxed-ritchie-eb25kl` · Written 2026-09-10 · Merged to `main` as #16 on 2026-09-11 (squash commit `8359752`); the Deploy workflow ran on that commit.

This report is a point-in-time record of the rebuild. Later pull requests changed some of what it describes (for example the legacy map was removed under debt #17, public colour tokens were added under debt #14, global coverage was expanded under debt #18, and a fifth Security gate was added). For current state use `CHANGELOG.md`, `docs/modules/README.md`, `docs/reference/technical-debt.md` and `docs/reference/compliance-map.md`. Read `PRODUCT_SEO_AEO_AUDIT.md` for the pre-change audit.

## 1. Routes created or changed

All public routes are server-rendered Blade pages unless marked JSON/XML/text.

| Method | URI | Purpose |
|--------|-----|---------|
| GET | `/` | Homepage: positioning, universal search, quick filters, latest changes, upcoming dates, featured jurisdictions, key instruments, tools, trust block, GitHub and restrained Certifyi CTA |
| GET | `/policies` | Policy explorer with server-side filters (jurisdiction, region, status, type, sector, use case, risk, actor, binding, dates), sort, pagination |
| GET | `/policies/{slug}` | Policy instrument page (answer-first sections, dates table, obligation accordions, sources, history, FAQ, JSON-LD) |
| GET | `/policies/{slug}.json` | Machine-readable record |
| GET | `/jurisdictions` | Jurisdiction directory grouped by region |
| GET | `/jurisdictions/{slug}` | "AI regulation in X" page |
| GET | `/obligations` | Obligation explorer (category, binding, jurisdiction, sector, use case, actor filters) |
| GET | `/obligations/{slug}` | Obligation page with evidence examples and framework mappings |
| GET | `/compare` | Compare 2–4 jurisdictions (ad-hoc results are noindex) |
| GET | `/compare/{slug}` | Curated, indexable comparisons (EU vs India, EU vs UK, EU vs US, Singapore vs Australia) |
| GET | `/changes` | Change log with filters, urgent/high sidebar |
| GET | `/changes/{year}` | Yearly archive |
| GET | `/changes/feed` | RSS 2.0 feed |
| GET | `/tools/applicability-check` | Educational screening questionnaire and results |
| GET | `/open-data` | Dataset, schema, licence, API docs, contribution workflow, releases, citation |
| GET | `/open-data/aipolicytracker-latest.json` | Full dataset download |
| GET | `/methodology` | Principles, hierarchy, data model, review levels, status definitions, limitations, AI disclosure |
| GET | `/about` | Mission, maintainers, reviewer invitation, contact, Certifyi relationship |
| GET, POST | `/contribute` | Contribution guidance and submission form (stored as pending_review) |
| GET | `/guides`, `/guides/{slug}` | Guides: EU AI Act startup readiness, AI governance for startups, ISO/IEC 42001 vs EU AI Act, NIST AI RMF vs EU AI Act |
| GET | `/eu-ai-act`, `/ai-regulation-india`, `/ai-policy-nepal`, `/ai-governance-singapore`, `/ai-regulation-australia`, `/ai-regulation-uk`, `/ai-regulation-usa`, `/ai-governance-uae`, `/ai-regulation-south-asia` | Editorial landing pages enriched with live records |
| GET | `/sitemap.xml`, `/sitemap-{static,jurisdictions,policies,obligations,changes,resources}.xml` | Sitemap index and child sitemaps |
| GET | `/llms.txt`, `/llms-full.txt`, `/openapi.json` | AI-readable assets and API description |
| GET | `/api/v1`, `/api/v1/jurisdictions[/{slug}]`, `/api/v1/policies[/{slug}]`, `/api/v1/obligations[/{slug}]`, `/api/v1/changes`, `/api/v1/taxonomies` | Read-only public API (throttled, cached, CORS) |
| GET, POST | `/backend/review`, `/backend/review/submissions/{id}/decide`, `/backend/review/publish/{type}/{slug}` | Admin-only review queue and publish switch |

Changed legacy routes: `/` (Inertia map dashboard) moved to `/map` (noindex); `/about-ai-policy` now 301 → `/about` (legacy page kept at `/legacy/about-ai-policy`); `/dashboard` 301 → `/map`. News, timeline, bookmarks, the Gov AI questionnaire and the admin CMS are unchanged but their Inertia shell is `noindex`.

## 2. Files created or changed

- `.env.example` (modified)
- `CHANGELOG.md` (modified)
- `CONTENT_OPERATIONS.md` (added)
- `CONTRIBUTING.md` (modified)
- `DATA_UPDATE_OPERATIONS.md` (added)
- `PRODUCT_SEO_AEO_AUDIT.md` (added)
- `README.md` (modified)
- `SEO_OPERATIONS.md` (added)
- `app/Console/Commands/PolicyExportCommand.php` (added)
- `app/Console/Commands/PolicyImportCommand.php` (added)
- `app/Console/Commands/PolicyValidateCommand.php` (added)
- `app/Enums/ConfidenceLevel.php` (added)
- `app/Enums/ImpactLevel.php` (added)
- `app/Enums/InstrumentType.php` (added)
- `app/Enums/PolicyStatus.php` (added)
- `app/Enums/ReviewStatus.php` (added)
- `app/Enums/SubmissionStatus.php` (added)
- `app/Http/Controllers/Api/V1/PublicApiController.php` (added)
- `app/Http/Controllers/Auth/AuthenticatedSessionController.php` (modified)
- `app/Http/Controllers/Auth/EmailVerificationPromptController.php` (modified)
- `app/Http/Controllers/Backend/Review/ReviewController.php` (added)
- `app/Http/Controllers/Frontend/WatchList/WatchListController.php` (modified)
- `app/Http/Controllers/Site/ApplicabilityController.php` (added)
- `app/Http/Controllers/Site/ChangeController.php` (added)
- `app/Http/Controllers/Site/CompareController.php` (added)
- `app/Http/Controllers/Site/ContributeController.php` (added)
- `app/Http/Controllers/Site/HomeController.php` (added)
- `app/Http/Controllers/Site/JurisdictionController.php` (added)
- `app/Http/Controllers/Site/LandingController.php` (added)
- `app/Http/Controllers/Site/MachineReadableController.php` (added)
- `app/Http/Controllers/Site/ObligationController.php` (added)
- `app/Http/Controllers/Site/PageController.php` (added)
- `app/Http/Controllers/Site/PolicyController.php` (added)
- `app/Http/Controllers/Site/SitemapController.php` (added)
- `app/Http/Middleware/CheckAdmin.php` (modified)
- `app/Models/ApplicabilityRule.php` (added)
- `app/Models/ChangeEvent.php` (added)
- `app/Models/Concerns/HasSourceQuality.php` (added)
- `app/Models/Concerns/HasTaxonomyTerms.php` (added)
- `app/Models/ContributorSubmission.php` (added)
- `app/Models/Deadline.php` (added)
- `app/Models/EnforcementEvent.php` (added)
- `app/Models/EvidenceArtifact.php` (added)
- `app/Models/FrameworkMapping.php` (added)
- `app/Models/Jurisdiction.php` (added)
- `app/Models/Obligation.php` (added)
- `app/Models/PolicyInstrument.php` (added)
- `app/Models/PolicySection.php` (added)
- `app/Models/PolicyVersion.php` (added)
- `app/Models/ProcurementRule.php` (added)
- `app/Models/ReviewerDecision.php` (added)
- `app/Models/SourceDocument.php` (added)
- `app/Models/TaxonomyTerm.php` (added)
- `app/Services/PolicyData/ComparisonBuilder.php` (added)
- `app/Services/PolicyData/OpenDataExporter.php` (added)
- `app/Services/PolicyData/PolicyCatalog.php` (added)
- `app/Services/PolicyData/PolicyDataRepository.php` (added)
- `app/Services/PolicyData/PolicyDataValidator.php` (added)
- `app/Services/PolicyData/PolicyImporter.php` (added)
- `app/Services/PolicyData/PolicySerializer.php` (added)
- `app/Services/PolicyData/SchemaValidator.php` (added)
- `app/Support/Seo.php` (added)
- `azure/startup.sh` (modified)
- `bootstrap/app.php` (modified)
- `config/aipolicytracker.php` (modified)
- `config/content.php` (added)
- `data/README.md` (added)
- `data/changes/2024.yaml` (added)
- `data/changes/2025.yaml` (added)
- `data/changes/2026.yaml` (added)
- `data/jurisdictions/australia.yaml` (added)
- `data/jurisdictions/eu.yaml` (added)
- `data/jurisdictions/india.yaml` (added)
- `data/jurisdictions/nepal.yaml` (added)
- `data/jurisdictions/singapore.yaml` (added)
- `data/jurisdictions/uae.yaml` (added)
- `data/jurisdictions/uk.yaml` (added)
- `data/jurisdictions/us-california.yaml` (added)
- `data/jurisdictions/us-colorado.yaml` (added)
- `data/jurisdictions/us.yaml` (added)
- `data/policies/australia/australia-mandatory-guardrails-proposal.yaml` (added)
- `data/policies/australia/australia-voluntary-ai-safety-standard.yaml` (added)
- `data/policies/eu/eu-ai-act.yaml` (added)
- `data/policies/india/india-ai-governance-guidelines.yaml` (added)
- `data/policies/india/india-dpdp-act.yaml` (added)
- `data/policies/nepal/nepal-individual-privacy-act.yaml` (added)
- `data/policies/nepal/nepal-national-ai-policy.yaml` (added)
- `data/policies/singapore/singapore-model-ai-governance-framework.yaml` (added)
- `data/policies/singapore/singapore-pdpc-ai-advisory-guidelines.yaml` (added)
- `data/policies/uae/uae-national-ai-strategy-2031.yaml` (added)
- `data/policies/uae/uae-personal-data-protection-law.yaml` (added)
- `data/policies/uk/uk-ai-regulation-white-paper.yaml` (added)
- `data/policies/uk/uk-ico-ai-data-protection-guidance.yaml` (added)
- `data/policies/us/us-california-sb-53.yaml` (added)
- `data/policies/us/us-colorado-ai-act.yaml` (added)
- `data/policies/us/us-executive-order-14179.yaml` (added)
- `data/policies/us/us-nist-ai-rmf.yaml` (added)
- `data/policies/us/us-omb-m-25-21.yaml` (added)
- `data/schema/change.schema.json` (added)
- `data/schema/common.schema.json` (added)
- `data/schema/jurisdiction.schema.json` (added)
- `data/schema/policy.schema.json` (added)
- `data/schema/taxonomy.schema.json` (added)
- `data/taxonomies/terms.yaml` (added)
- `database/migrations/2026_09_10_000000_create_policy_intelligence_tables.php` (added)
- `database/seeders/DatabaseSeeder.php` (modified)
- `docker/entrypoint.sh` (modified)
- `eslint.config.js` (modified)
- `public/og-default.png` (added)
- `public/robots.txt` (modified)
- `resources/css/public.css` (added)
- `resources/js/Layouts/Header/Header.jsx` (modified)
- `resources/js/Pages/Frontend/Dashboard/Dashboard.jsx` (modified)
- `resources/js/public.js` (added)
- `resources/openapi/openapi.php` (added)
- `resources/views/app.blade.php` (modified)
- `resources/views/backend/review/index.blade.php` (added)
- `resources/views/components/site/breadcrumbs.blade.php` (added)
- `resources/views/components/site/certifyi-cta.blade.php` (added)
- `resources/views/components/site/change-item.blade.php` (added)
- `resources/views/components/site/correction-cta.blade.php` (added)
- `resources/views/components/site/disclaimer.blade.php` (added)
- `resources/views/components/site/empty.blade.php` (added)
- `resources/views/components/site/faq.blade.php` (added)
- `resources/views/components/site/listing-shell.blade.php` (added)
- `resources/views/components/site/policy-row.blade.php` (added)
- `resources/views/components/site/source-list.blade.php` (added)
- `resources/views/components/site/status-badge.blade.php` (added)
- `resources/views/components/site/verified.blade.php` (added)
- `resources/views/errors/404.blade.php` (added)
- `resources/views/site/changes/feed.blade.php` (added)
- `resources/views/site/changes/index.blade.php` (added)
- `resources/views/site/changes/year.blade.php` (added)
- `resources/views/site/compare/_table.blade.php` (added)
- `resources/views/site/compare/index.blade.php` (added)
- `resources/views/site/compare/show.blade.php` (added)
- `resources/views/site/guides/index.blade.php` (added)
- `resources/views/site/guides/show.blade.php` (added)
- `resources/views/site/home.blade.php` (added)
- `resources/views/site/jurisdictions/index.blade.php` (added)
- `resources/views/site/jurisdictions/show.blade.php` (added)
- `resources/views/site/landing.blade.php` (added)
- `resources/views/site/layouts/app.blade.php` (added)
- `resources/views/site/machine/llms-full.blade.php` (added)
- `resources/views/site/machine/llms.blade.php` (added)
- `resources/views/site/obligations/index.blade.php` (added)
- `resources/views/site/obligations/show.blade.php` (added)
- `resources/views/site/pages/about.blade.php` (added)
- `resources/views/site/pages/contribute.blade.php` (added)
- `resources/views/site/pages/methodology.blade.php` (added)
- `resources/views/site/pages/open-data.blade.php` (added)
- `resources/views/site/policies/_filters.blade.php` (added)
- `resources/views/site/policies/_listing-shell.blade.php` (added)
- `resources/views/site/policies/index.blade.php` (added)
- `resources/views/site/policies/show.blade.php` (added)
- `resources/views/site/sitemap/index.blade.php` (added)
- `resources/views/site/sitemap/urlset.blade.php` (added)
- `resources/views/site/tools/applicability.blade.php` (added)
- `routes/api.php` (added)
- `routes/backend/web.php` (modified)
- `routes/frontend/web.php` (modified)
- `routes/public.php` (added)
- `routes/web.php` (modified)
- `tailwind.config.js` (modified)
- `tests/Feature/AdminAccessTest.php` (modified)
- `tests/Feature/Auth/AuthenticationTest.php` (modified)
- `tests/Feature/Site/PublicSiteTest.php` (added)
- `vite.config.js` (modified)

## 3. Data architecture

- **Canonical source:** `data/` (YAML) validated by JSON Schema (`data/schema/*.json`) and cross-reference rules (`App\Services\PolicyData\PolicyDataValidator`). GitHub pull requests are the review workflow; the PR template already requires a source table.
- **Read model:** migration `2026_09_10_000000_create_policy_intelligence_tables` creates jurisdictions, policy_instruments, policy_versions, policy_sections, obligations, applicability_rules, taxonomy_terms, taxonomy_assignments, deadlines, enforcement_events, procurement_rules, framework_mappings, evidence_artifacts, change_events, source_documents, contributor_submissions and reviewer_decisions. Source-quality columns are shared via `addSourceQualityColumns()` and the `HasSourceQuality` trait.
- **Import:** `PolicyImporter` upserts by slug and replaces child rows per policy; `published: false` keeps a record out of the site, API and sitemaps. `Cache::flush()` after import.
- **Serving:** `PolicyCatalog` (filters, indexation rules), `PolicySerializer` (one JSON shape for API, per-page JSON and export), `ComparisonBuilder` (derived comparison matrix), `OpenDataExporter`.
- **Public vs private:** only `published` records are exposed; contributor submissions, reviewer decisions, submitter contact details and unpublished records never leave the admin area (`/backend/review`, auth + `isAdmin`).
- **Infrastructure decision:** the app is PHP/Laravel, so Cloudflare D1/KV/R2 were not introduced. Cloudflare stays as CDN/edge; Laravel cache and Postgres cover cached reads and structured data. Exports go to `storage/app/exports` (mountable to R2/Blob later).

## 4. Commands

```bash
# setup
composer install            # add --ignore-platform-req=php on PHP 8.4
npm ci && cp .env.example .env && php artisan key:generate
touch database/database.sqlite && php artisan migrate --seed

# data
php artisan policy:validate
php artisan policy:import
php artisan policy:export

# quality gates
composer test               # 40 tests, 196 assertions
composer lint               # Pint (legacy files still have style drift; CI keeps it advisory)
npm run lint
npm run build

# local preview
php artisan serve           # http://localhost:8000
```

Deployment preview: build the container (`docker build -t aipolicytracker .`) or push to a non-production Azure slot; `docker/entrypoint.sh` and `azure/startup.sh` now run `policy:import` after migrations.

## 5. SEO checklist (implemented)

- [x] Server-rendered HTML for every public page; no JS needed for content
- [x] Unique title, description, canonical, robots, Open Graph, Twitter card per page (`App\Support\Seo`), enforced by a test
- [x] Canonical/noindex rules for filter, search, sort, pagination and ad-hoc comparison URLs
- [x] Indexation thresholds for thin records (`isIndexable()`), 404 for unpublished
- [x] `/sitemap.xml` index + static, jurisdictions, policies, obligations, changes, resources sitemaps with real `lastmod`
- [x] `robots.txt` allowing public content and assets for all crawlers, disallowing admin/auth/JSON/API, declaring the sitemap
- [x] Breadcrumb navigation + `BreadcrumbList`
- [x] 301 redirects for restructured URLs, branded 404
- [x] Clean, stable slugs; internal linking jurisdiction → policies → obligations → guides/crosswalks → tools
- [x] Search Console and Bing verification placeholders; setup steps in `SEO_OPERATIONS.md`
- [x] Performance: system fonts, no third-party assets on public pages, one CSS + one small JS bundle, cache headers on API/feeds/sitemaps, no async layout shift
- [x] Accessibility: skip link, landmarks, one H1 per page, labelled forms and errors, 44px touch targets, visible focus rings, reduced-motion support, sticky mobile action bar and bottom-sheet filters

## 6. AEO / GEO checklist (implemented)

- [x] Answer-first opening paragraph on jurisdiction, policy, obligation, landing and guide pages
- [x] Question headings ("What is …?", "Who does it apply to?", "When do the requirements apply?", "What must organisations do?")
- [x] Dates in `<time>` elements and HTML tables; comparison matrix as semantic table
- [x] Verification line and official source next to factual content; confidence shown on non-high items
- [x] Explicit uncertainty labels (status notes, "confidence", "pending review", proposal caveats)
- [x] FAQ sections and `FAQPage` only where visible FAQs exist
- [x] `/llms.txt`, `/llms-full.txt`, `/openapi.json`, per-policy `.json`, dataset download, RSS
- [x] Citation guidance on `/open-data` and in `llms.txt`; no claim that llms.txt guarantees citation
- [x] JSON-LD: Organization, WebSite+SearchAction, WebPage, CollectionPage, ItemList, BreadcrumbList, Dataset, FAQPage, Article (editorial only), WebApplication (tool)

## 7. Remaining manual tasks

1. **Verify every seeded record.** No official source could be fetched from the build environment, so all records are `review_status: pending_review` with `last_verified_at: null`. A reviewer must open each `official_source_url`, confirm fields, and set `verified`, `last_verified_at`, `reviewed_by`. Highest priority: EU AI Act dates after the Digital Omnibus proposal; Colorado effective date after the 2026 session; India DPDP Rules commencement; Nepal National AI Policy title, date and document URL; UAE PDPL executive regulations; California SB 53 penalties; Australia National AI Plan outcome.
2. **Locate and replace the live Certifyi landing page.** It is not in this repository; check the Cloudflare Worker routes for `aipolicytracker.org/*` and the Azure App Service slots, then deploy this branch.
3. Connect Google Search Console and Bing Webmaster Tools (`GOOGLE_SITE_VERIFICATION`, `BING_SITE_VERIFICATION`), submit `/sitemap.xml`.
4. Configure analytics keys (`GOOGLE_ANALYTICS_ID` and/or `CLOUDFLARE_ANALYTICS_TOKEN`); decide `ANALYTICS_REQUIRE_CONSENT`.
5. Add verified social profiles to `SITE_SOCIAL_PROFILES` and contact addresses to `CONTACT_EMAILS`.
6. Replace `public/og-default.png` with a designed image or add per-page OG generation if desired.
7. Set `SITE_NEWSLETTER_URL` when an email-alert provider is chosen (the "Email alerts" button is a placeholder until then).
8. Recruit reviewers (the About and Contribute pages invite them); decide who may set `verified`.
9. Refresh `composer.lock` for PHP 8.4 compatibility (`nette/schema`), or keep PHP 8.3 in production.
10. Optionally run `composer lint:fix` on legacy files and make Pint blocking in CI.
11. Purge Cloudflare cache after deploy; add rate-limit rules for `/contribute` and `/api/*`.

## 8. Blockers, uncertainties and content risks to review before deployment

- **Unverified regulatory facts.** Dates, penalties and article references were written from knowledge of the official texts but not checked against them in this environment. The UI labels this honestly, but publishing without human verification carries reputational risk. Do not remove the "pending" banners by editing the UI; clear them by verifying records.
- **Post-2025 developments** (EU Digital Omnibus, US state amendments, Australia's National AI Plan, India's guidelines and rules) are recorded with medium/low confidence and explicit reviewer notes.
- **Nepal and UAE document links** point to ministry or portal home pages because stable document URLs could not be confirmed; `source_reference` says so.
- **Framework mappings** to ISO/IEC 42001 and NIST AI RMF are original editorial judgements with confidence levels; they must not be presented as official crosswalks.
- **Legal-advice boundary.** Every page carries the informational disclaimer; the applicability tool never states that a law applies. Keep this wording if the copy is edited.
- **Legacy content** (news, timeline, Gov AI questionnaire, map) remains reachable but noindex; decide whether to retire it.
