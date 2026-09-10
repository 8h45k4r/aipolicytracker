# Open Source Readiness Audit

Audit date: 2026-09-10. Scope: the full `aipolicytracker` codebase as extracted from `aipolicytracker-main.zip`. Items marked **Done** were fixed in this pass; others remain for the maintainers.

## 1. Current architecture

- **Framework / runtime:** Laravel 11 (PHP 8.2+) serving an Inertia.js + React 18 single-page front end. Auth is Laravel Breeze (registration, e-mail verification, password reset). Ziggy exposes named routes to JS.
- **Build:** Vite 5 with `laravel-vite-plugin`, Tailwind 3, Sass. `npm run build` outputs to `public/build`.
- **Package managers:** Composer (PHP) and npm (JS). Lock files for both are tracked.
- **Database:** Any Laravel-supported RDBMS. Migrations use UUID primary keys for domain tables and bigint IDs for users/auth tables. Supabase (PostgreSQL 17) is the recommended host; the schema has been applied there and the Laravel `migrations` table pre-populated.
- **Queues / mail:** Database queue driver for `SendAiPolicyTrackerNotificationJob`; mail notifications for verification and bookmark updates.
- **Domain:** countries, statuses, `ai_policy_trackers`, news + thumbnails, activity logs, bookmarks, user_infos, nav bar logo, contributing organisations.
- **Routes:** public pages (`/`, `/news`, `/timeline`, `/about-ai-policy`, `/gov-ai-index/assesment`), auth routes, per-user bookmarks, and an admin area under `/backend/*` guarded by `auth` + `isAdmin`.
- **Deployment:** No Dockerfile or platform config was present. Requires a PHP host; not deployable to Cloudflare Workers/Pages.

## 2. Risks that had to be fixed before going public

| # | Risk | Status |
|---|------|--------|
| 1 | **Real admin credentials** (two named accounts with a shared password) in `database/seeders/AdminSeeder.php`. | **Done** – seeder now reads `ADMIN_*` from env. The exposed password must be rotated if those accounts still exist anywhere. |
| 2 | **Plaintext password stored** in `user_infos.password` on registration. | **Done** – removed from controller/model; migration drops the column; Supabase schema created without it. |
| 3 | **Hard-coded personal e-mail addresses** used for admin authorisation in six PHP files. | **Done** – replaced by `ADMIN_EMAILS` + `User::isAdmin()`. |
| 4 | **Personal names, LinkedIn URLs, WhatsApp invite links, private Google Docs** in the front end. | **Done** – removed; links/contacts now come from env. |
| 5 | **Google Analytics ID** hard-coded in the layout. | **Done** – env-driven and optional. |
| 6 | **Maintenance routes** `/clear-cache` and `/storage-link` callable by any logged-in user. | **Done** – removed. |
| 7 | **Exception messages returned to clients** in three JSON endpoints. | **Done** |
| 8 | **Unvalidated input** in country status update; unbounded `per_page`. | **Done** |
| 9 | Notification mark-as-read route had no auth middleware. | **Done** |
| 10 | No security headers, no CORS config. | **Done** – `SecurityHeaders` middleware; restrictive `config/cors.php`. |
| 11 | Debug `console.log` output in production bundles, including user objects. | **Done** |
| 12 | Existing Breeze tests asserted redirects that no longer matched the app. | **Done** – tests updated; PHPUnit now uses in-memory SQLite. |

## 3. Recommended cleanup actions

### Critical
- Rotate the seeded admin password and any mail/DB credentials that were ever used with this code (**manual**).
- Publish from a **fresh Git history** (this folder was delivered as a zip with no `.git`; keep it that way). If an older repository exists, do not push its history.
- Run `composer test` and `composer lint` on a machine with PHP 8.2+ before the first push; PHP was not available in this environment, so the PHP side is unverified (**manual**).

### High
- **CKEditor 5 licensing:** `@ckeditor/ckeditor5-build-classic` is GPL-2.0-or-later (or commercial). Bundling it in an MIT project is legally possible but the combined distribution carries GPL obligations. Decide: keep and document, buy a licence, or swap for an MIT editor (e.g. TipTap). Flagged, not changed.
- **amCharts licensing:** amCharts 4/5 are free only with the on-chart attribution logo, not OSI-approved. Keep the attribution enabled or purchase a licence. Flagged, not changed.
- **GovAI assessment questionnaire** (`PublicPurpose.jsx`) contains 179 lines of survey questions whose origin is unclear. Confirm they are original or openly licensed before release.
- Enable GitHub private vulnerability reporting and branch protection with CI required.

### Medium
- `resources/js/assets/images/T4DNepal.png` is a third-party organisation logo shipped in the bundle; move it to the CMS "contributing organisations" upload or confirm permission.
- `AiPolicyTrackerSeeder` contains fictional sample policies with fake government URLs; it is now labelled as illustrative. Consider replacing with a small, source-verified dataset.
- Rename `SinglePolicyTackerControlle` and `GovAiAssesment*` (typos) in a follow-up refactor.
- Add a Content-Security-Policy once script sources are fixed.
- Add feature tests for policy CRUD, bookmarks, and news filtering.

### Low
- Remove remaining commented-out code blocks in controllers and React pages.
- `AcceptUserTermsConditonCommand` is a one-off backfill; delete once no longer needed.
- Vite warns about >500 kB chunks (CKEditor, pdfmake, amCharts). Lazy-load the editor and chart pages.

## 4. Files that must never be committed

`.env` and any `.env.*` except `.env.example`; `auth.json`; `*.sqlite*`, `*.sql`, `*.dump`; `storage/app/public/*` (user uploads); `storage/logs/*`; `public/build`, `public/hot`, `public/storage`; `node_modules`, `vendor`; `bootstrap/cache/*`; IDE folders; `*.pem`/`*.key`. All are covered by the new `.gitignore`.

## 5. Required environment variables (placeholders only)

See `.env.example` for the full list. Minimum for a working instance:

```dotenv
APP_KEY=                       # php artisan key:generate
APP_URL=https://example.org
APP_DEBUG=false
DB_CONNECTION=pgsql
DB_HOST=aws-0-<region>.pooler.supabase.com
DB_PORT=5432
DB_DATABASE=postgres
DB_USERNAME=postgres.<project-ref>
DB_PASSWORD=<database-password>
DB_SSLMODE=require
MAIL_MAILER=smtp
MAIL_FROM_ADDRESS=no-reply@example.org
ADMIN_EMAILS=admin@example.org
ADMIN_EMAIL=admin@example.org
ADMIN_PASSWORD=<long-random-password>
CONTACT_EMAILS=contact@example.org
GOOGLE_ANALYTICS_ID=
```
