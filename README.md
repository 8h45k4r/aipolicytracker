# AI Policy Tracker

Open, source-backed AI policy and regulatory intelligence. AI Policy Tracker collects the status of national and regional AI policies, strategies, and regulations, links every entry to its official source, and lets people follow the jurisdictions they care about.

> **Disclaimer.** The content in this project is informational only and is not legal advice. Always consult the official source and, where needed, qualified counsel before acting on anything shown here.

## Features

- **Interactive world map** of AI policy status per country (research, whitepaper, pilot, development, launched, cancelled).
- **Policy directory** with governing body, announcement date, governance structure, technology partners, motivation, and a link to the official document.
- **Timeline** of announcements grouped by year, with an activity log per policy.
- **News feed** linked to policies and jurisdictions.
- **Bookmarks and e-mail alerts** so registered users are notified when a followed policy changes.
- **Government AI readiness self-assessment** questionnaire.
- **Admin area** for maintainers to curate policies, news, countries, logos, and contributing organisations.

## Tech stack

| Layer      | Technology                                                         |
|------------|--------------------------------------------------------------------|
| Backend    | PHP 8.2+, Laravel 11, Inertia.js, Laravel Breeze (auth), Ziggy     |
| Frontend   | React 18, Vite 5, Tailwind CSS 3, Flowbite, MUI, amCharts 4/5      |
| Database   | PostgreSQL (Supabase recommended), MySQL, or SQLite for local work |
| Queue/mail | Laravel queues (database driver) + any Laravel mail transport      |
| Tooling    | ESLint 9, Laravel Pint, PHPUnit 11, GitHub Actions                 |

## Local setup

Prerequisites: PHP 8.2+ with `pdo_sqlite`/`pdo_pgsql`, Composer 2, Node 20+, npm.

```bash
git clone https://github.com/8h45k4r/aipolicytracker.git
cd aipolicytracker
composer install
npm install
cp .env.example .env
php artisan key:generate
```

## Environment setup

All deployment-specific values live in `.env` (never committed). `.env.example` lists every variable with safe placeholders. The important ones:

| Variable | Purpose |
|----------|---------|
| `APP_URL`, `APP_DEBUG`, `APP_ENV` | Standard Laravel settings. `APP_DEBUG=false` in production. |
| `DB_*` | Database connection (see below). |
| `ADMIN_EMAILS` | Comma-separated e-mail addresses allowed into `/backend`. |
| `ADMIN_NAME`, `ADMIN_EMAIL`, `ADMIN_PASSWORD` | Used once by the seeder to create the first admin account. |
| `MAIL_*` | Transport for verification e-mails and bookmark notifications. |
| `SITE_LINK_*`, `CONTACT_EMAILS` | Public links and contact addresses shown in the UI. |
| `GOOGLE_ANALYTICS_ID` | Optional GA4 ID. Leave blank to disable tracking. |
| `CORS_ALLOWED_ORIGINS`, `MAX_PER_PAGE` | Security limits for JSON endpoints. |

## Database setup and migrations

**Quick start (SQLite):**

```bash
touch database/database.sqlite
php artisan migrate --seed
php artisan storage:link
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

The seeder creates countries, statuses, the admin account from `ADMIN_*`, and a small set of **illustrative sample policies**. The sample policies are placeholders, not verified data; replace them with source-backed entries through the admin area.

If a database was provisioned from this repository's SQL translation of the migrations (for example with the Supabase SQL editor), the `migrations` table is already populated and `php artisan migrate` will report nothing to do.

## Development commands

```bash
php artisan serve          # backend on http://localhost:8000
npm run dev                # Vite dev server with HMR
php artisan queue:work     # process e-mail notifications

npm run lint               # ESLint
npm run build              # production assets
composer lint              # Laravel Pint (code style)
composer test              # PHPUnit
```

Useful artisan helpers:

```bash
php artisan db:reset       # wipe + migrate + seed (development only)
```

## Deployment

The app is a standard Laravel application and needs a PHP runtime. It cannot run on edge platforms that only execute JavaScript (for example Cloudflare Workers or Pages Functions); use those for DNS, TLS, and CDN in front of a PHP host instead.

1. Provision PHP 8.2+, Composer, Node, and PostgreSQL (Supabase works well).
2. Set every variable from `.env.example` in the host's environment. Use `APP_ENV=production`, `APP_DEBUG=false`, `SESSION_SECURE_COOKIE=true`, a real `MAIL_MAILER`, and a strong `ADMIN_PASSWORD`.
3. Build and optimise:

   ```bash
   composer install --no-dev --optimize-autoloader
   npm ci && npm run build
   php artisan migrate --force
   php artisan db:seed --class=AdminSeeder --force   # first deploy only
   php artisan storage:link
   php artisan config:cache && php artisan route:cache && php artisan view:cache
   ```

4. Point the web server's document root at `public/` and run a queue worker (`php artisan queue:work`) under a process supervisor.
5. Put a CDN or reverse proxy in front for TLS and rate limiting. Suggested limits: 60 requests/minute per IP on JSON filter endpoints, 10/minute on `/login` and `/register` (login is also throttled in-app).

## Contribution workflow

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

Code is released under the MIT License (see `LICENSE`). Policy metadata contributed to this project should come only from official or openly licensed sources; see `SOURCE_ATTRIBUTION.md` for what may and may not be contributed. Third-party libraries keep their own licences; note in particular that CKEditor 5 is GPL-licensed and amCharts uses a free-with-attribution licence.
