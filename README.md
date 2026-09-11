# AIPolicyTracker

**AIPolicyTracker turns AI policy into practical compliance action.** Explore source-backed AI laws, regulations, standards, public-sector guidance, obligations, deadlines, and implementation actions across jurisdictions.

Every important policy claim should link to a primary official source, show its current status, and display a last-verified date.

> **Disclaimer.** Content is informational only and is not legal advice. Check the linked official source and, where needed, qualified counsel before acting on anything shown here.

## What the product does

- **Policy explorer** (`/policies`): search and filter AI laws, regulations, strategies, frameworks, guidance and consultations by jurisdiction, status, type, sector, use case, risk category, actor and date.
- **Coverage**: 117 jurisdictions (countries, US states, Canadian provinces, the EU, ASEAN, the Council of Europe and international bodies) and 182 instruments, each linked to an official source with an explicit review status and confidence level.
- **Jurisdiction pages** (`/jurisdictions/{slug}`): "AI regulation in X" with status, binding rules vs guidance, key instruments, deadlines, changes, regulators and official sources.
- **Policy pages** (`/policies/{slug}`): plain-language overview, scope, phased dates, obligations with evidence examples and original ISO/IEC 42001 and NIST AI RMF mappings, official sources with article references, change history, FAQ and a JSON record.
- **Obligation explorer** (`/obligations`): practical requirements across instruments, with legal requirements separated from voluntary guidance.
- **Compare** (`/compare`): 2–4 jurisdictions side by side, plus curated indexable comparisons.
- **Change log** (`/changes`): dated, source-backed changes with practical impact, yearly archives and RSS.
- **Applicability check** (`/tools/applicability-check`): educational screening, explicitly not legal advice.
- **Open data and API** (`/open-data`, `/api/v1`, `/openapi.json`, `/llms.txt`): CC BY 4.0 dataset with JSON Schema.
- **Contribute** (`/contribute`): corrections, sources and records enter a `pending_review` queue; publishing is admin-only.

The related product **Certifyi** (certifyi.ai) is a separate compliance execution platform; AIPolicyTracker is the open, public intelligence layer and links to it only through small, restrained calls to action.

## Data architecture

```
data/                     canonical, reviewable source of truth (YAML + JSON Schema)
  schema/                 JSON Schema per record type
  taxonomies/terms.yaml   actors, AI system types, sectors, risk categories, use cases, obligation categories
  jurisdictions/*.yaml    one file per jurisdiction
  policies/<j>/*.yaml     one file per policy instrument (sections, obligations, deadlines, sources, FAQ)
  changes/<year>.yaml     dated change events
php artisan policy:validate   →   php artisan policy:import   →   relational read model (PostgreSQL / SQLite)
                                                                   ↓
                                    Blade pages · read-only API · sitemaps · RSS · llms.txt · open-data export
```

Every record carries `official_source_url`, `source_title`, `source_publisher`, `source_document_date`, `source_reference`, `source_tier` (1 official primary … 4 community), `last_checked_at`, `last_verified_at`, `review_status`, `confidence_level`, `content_version`, `change_summary` and `reviewed_by`. See `data/README.md`, `DATA_UPDATE_OPERATIONS.md` and `/methodology`.

Policy statuses: proposed, under_consultation, adopted, in_force, partially_applicable, guidance, voluntary_standard, enforcement_action, superseded, repealed, archived.

## Tech stack

| Layer      | Technology                                                         |
|------------|--------------------------------------------------------------------|
| Public site | Laravel 11 Blade templates (server-rendered, no JS required), Tailwind CSS 3, a small progressive-enhancement script |
| Admin / auth | Inertia.js + React 18 (legacy map, news, bookmarks, admin CMS, review queue) |
| Backend    | PHP 8.2+, Laravel 11, Breeze (auth), Ziggy                          |
| Database   | PostgreSQL (Supabase recommended), MySQL, or SQLite for local work |
| Tooling    | Vite 5, ESLint 9, Laravel Pint, PHPUnit 11, GitHub Actions        |

## Local setup

Prerequisites: PHP 8.2–8.3 with `pdo_sqlite`/`pdo_pgsql` (PHP 8.4 needs `composer install --ignore-platform-req=php` until the lock file is refreshed), Composer 2, Node 20+, npm.

```bash
git clone https://github.com/8h45k4r/aipolicytracker.git
cd aipolicytracker
composer install
npm install
cp .env.example .env
php artisan key:generate
touch database/database.sqlite
php artisan migrate --seed      # reference data, admin user, and the data/ records
php artisan storage:link
php artisan serve               # http://localhost:8000
npm run dev                     # Vite with HMR (or npm run build)
```

**Supabase / PostgreSQL (recommended):**

1. Create a Supabase project and open *Project Settings → Database*.
2. Copy the **Session pooler** connection details (IPv4-friendly) into `.env`:

   ```dotenv
   DB_CONNECTION=pgsql
   DB_HOST=aws-0-<region>.pooler.supabase.com
   DB_PORT=5432
   DB_DATABASE=postgres
   DB_USERNAME=postgres.<project-ref>
   DB_PASSWORD=<database password>
   DB_SSLMODE=require
   ```

3. Run `php artisan migrate --seed` and `php artisan storage:link`.

The seeder creates the admin account from `ADMIN_*`; structured policy records are imported from `data/` with `php artisan policy:import` (see `docs/modules/policy-intelligence.md`). Re-running is idempotent.

If a database was provisioned from this repository's SQL translation of the migrations (for example with the Supabase SQL editor), the `migrations` table is already populated and `php artisan migrate` will report nothing to do.

## Commands

```bash
php artisan policy:validate     # schema + cross-reference checks on data/
php artisan policy:import       # idempotent import into the database (runs on deploy)
php artisan policy:export       # dated JSON bundle in storage/app/exports
composer lint                   # Laravel Pint
composer test                   # PHPUnit (includes public-site, SEO and API tests)
npm run lint                    # ESLint
npm run build                   # production assets
```

## Environment

All deployment-specific values live in `.env` (never committed); `.env.example` lists every variable. Beyond the standard Laravel settings and `DB_*`, the public site reads: `SITE_NAME`, `SITE_GITHUB_URL`, `SITE_CERTIFYI_URL`, `SITE_NEWSLETTER_URL`, `SITE_SOCIAL_PROFILES`, `SITE_OG_IMAGE`, `GOOGLE_SITE_VERIFICATION`, `BING_SITE_VERIFICATION`, `GOOGLE_ANALYTICS_ID`, `CLOUDFLARE_ANALYTICS_TOKEN`, `ANALYTICS_REQUIRE_CONSENT`, `ADMIN_EMAILS`, `CONTACT_EMAILS`.

## Operations manuals

- `PRODUCT_SEO_AEO_AUDIT.md` – the audit that preceded the rebuild.
- `SEO_OPERATIONS.md` – Search Console setup, weekly and monthly checks, metadata rules.
- `CONTENT_OPERATIONS.md` – page types, quality bar, editorial templates, change-log workflow.
- `DATA_UPDATE_OPERATIONS.md` – verification cadence, adding records, handling submissions.

## Deployment

The app is a standard Laravel application and needs a PHP runtime. It cannot run on edge platforms that only execute JavaScript (for example Cloudflare Workers or Pages Functions); use those for DNS, TLS, and CDN in front of a PHP host instead.

1. Provision PHP 8.2+, Composer, Node, and PostgreSQL (Supabase works well).
2. Set every variable from `.env.example` in the host's environment. Use `APP_ENV=production`, `APP_DEBUG=false`, `SESSION_SECURE_COOKIE=true`, a real `MAIL_MAILER`, and a strong `ADMIN_PASSWORD`.
3. Build and optimise:

   ```bash
   composer install --no-dev --optimize-autoloader
   npm ci && npm run build
   php artisan migrate --force
   php artisan policy:import                          # load data/ records (every deploy)
   php artisan db:seed --class=AdminSeeder --force   # first deploy only
   php artisan storage:link
   php artisan config:cache && php artisan route:cache && php artisan view:cache
   ```

4. Point the web server's document root at `public/` and run a queue worker (`php artisan queue:work`) under a process supervisor.
5. Put a CDN or reverse proxy in front for TLS and rate limiting.

**Container deployment.** A production `Dockerfile` and `fly.toml` are included. Build with `docker build -t aipolicytracker .` and run with the environment variables from `.env.example`; the entrypoint runs migrations, caches config, starts a queue worker, and serves on port 8080. On Fly.io: `fly launch --no-deploy`, `fly secrets set ...`, `fly deploy`.

**Azure App Service.** The `azure/` folder holds an nginx config and startup script for the built-in PHP 8.x Linux image, and `.github/workflows/deploy.yml` builds and deploys on every push to `main`. Create a Linux web app (PHP 8.3), set its Startup Command to `bash /home/site/wwwroot/azure/startup.sh`, add the `.env.example` variables as application settings, and store the publish profile in the `AZURE_WEBAPP_PUBLISH_PROFILE` repository secret with the app name in the `AZURE_WEBAPP_NAME` variable. Azure Database for PostgreSQL (Flexible Server, Burstable B1ms) is the smallest managed option.

**Cloudflare.** Cloudflare provides DNS, TLS, caching, and a small Worker (`cloudflare/worker.js`) that forwards the public domain to the origin; see `cloudflare/README.md`. Add rate-limiting rules for `/login`, `/register`, `/contribute`, `/api/*` and `/*/filtered`. Purge `/sitemap*.xml`, `/robots.txt` and `/llms*.txt` after each deploy. The application itself cannot run on Workers or Pages. Suggested limits: 60 requests/minute per IP on JSON filter endpoints, 10/minute on `/login` and `/register` (login is also throttled in-app).

## Contribution workflow

Every change to `main` passes the four role gates in `docs/reference/change-gates.md`; module docs live in `docs/modules/`, accepted debt in `docs/reference/technical-debt.md`, and the compliance map in `docs/reference/compliance-map.md`.

1. Open an issue using one of the templates (bug, policy data correction, new jurisdiction/source, feature).
2. Fork, create a branch (`feat/...`, `fix/...`, `data/...`).
3. For data changes, include official source links and a verification status in the pull request.
4. Make sure `composer lint`, `composer test`, `npm run lint`, and `npm run build` pass.
5. Open a pull request using the template. See `CONTRIBUTING.md` and `SOURCE_ATTRIBUTION.md`.

## Security and responsible use

- Report vulnerabilities privately as described in `SECURITY.md`; do not open public issues.
- The admin area is restricted to addresses in `ADMIN_EMAILS`. Keep that list short and use strong, unique passwords.
- Do not upload personal data, unpublished government documents, or content you do not have the right to share.
- Automated collection from official sites must respect their terms of use and robots rules.

## License

Code is released under the Apache License 2.0 (see `LICENSE` and `NOTICE`). Policy metadata contributed to this project should come only from official or openly licensed sources; see `SOURCE_ATTRIBUTION.md` for what may and may not be contributed. Third-party libraries keep their own licences; note in particular that CKEditor 5 is GPL-licensed and amCharts uses a free-with-attribution licence.

## Admin

Sign in at `/login` with an address listed in `ADMIN_EMAILS`. The admin at `/backend/dashboard` covers the review queue, submissions and feedback, digest subscribers, external-data status and Settings (mail transport and Resend API key stored encrypted). The weekly digest is triggered by `.github/workflows/weekly-digest.yml` using the `CRON_TOKEN` secret.

## Data sources and references

- Policy records: official government, legislature and regulator sources (see `SOURCE_ATTRIBUTION.md`).
- AI risk taxonomy: MIT AI Risk Repository (MIT AI Risk Initiative), CC BY 4.0. Slattery et al. (2025), arXiv:2408.12622.
- AI incidents: AI Incident Database (Responsible AI Collaborative), CC BY-SA 4.0. McGregor (2021), IAAI-21.
- Refresh: `.github/workflows/refresh-external-data.yml` runs weekly and opens a pull request; see `docs/modules/external-data.md`.

## Governance and contact

- **Bug reports, data corrections, feature requests:** open an issue using the templates.
- **Code changes:** fork, branch, open a pull request; at least one maintainer review and green CI are required to merge into `main` (see `CONTRIBUTING.md`).
- **Security:** private reporting via the repository's *Security* tab (see `SECURITY.md`).

---

Built by **Dignep Group Pvt. Ltd.** · Powering AI Governance for Regulated Industries
[certifyi.ai](https://certifyi.ai) · [LinkedIn](https://www.linkedin.com/in/8h45k4r/) · [Twitter/X](https://x.com/8h45k4r) · [bhaskar.com.np](https://bhaskar.com.np/)
