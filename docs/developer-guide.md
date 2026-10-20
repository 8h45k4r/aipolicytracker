# Developer guide

How to run AIPolicyTracker on your own machine, test it, and get a change through CI.
The contribution rules themselves are in [`CONTRIBUTING.md`](../CONTRIBUTING.md); this page is
the practical part.

- [Prerequisites](#prerequisites)
- [Quickstart](#quickstart)
- [Front end](#front-end)
- [Tests](#tests)
- [Linting](#linting)
- [Data checks](#data-checks)
- [The MCP server](#the-mcp-server)
- [Docker](#docker)
- [Artisan commands](#artisan-commands)
- [CI](#ci)
- [Releases](#releases)
- [Troubleshooting](#troubleshooting)

## Prerequisites

- **PHP 8.3 or newer** (`composer.json` requires `^8.3`) with the `pdo_sqlite`, `mbstring`, `intl`,
  `gd`, `zip`, `xml`, `fileinfo`, `iconv` and `bcmath` extensions. `composer check-platform-reqs`
  lists anything missing.
- **Composer 2.**
- **Node 26**, the version in [`.nvmrc`](../.nvmrc) (`package.json` asks for `>=26`). Only needed to
  change the front end or run the JavaScript tests.

Nothing needs a network connection or an API key to run locally. The external datasets are
committed under `data/external/`, and the built front end is committed in `public/build`.

## Quickstart

```bash
composer install
cp .env.example .env && php artisan key:generate
touch database/database.sqlite
php artisan migrate --seed       # schema, plus the admin account and the tools library
php artisan policy:import        # build the database from data/
php artisan external:import      # AI incidents and the MIT risk repository
php artisan serve                # then open http://127.0.0.1:8000
```

`.env.example` uses SQLite (`DB_CONNECTION=sqlite`). Production uses PostgreSQL; the commented
`pgsql` block in `.env.example` shows the keys.

`migrate --seed` runs `AdminSeeder`, which creates an account from `ADMIN_NAME`, `ADMIN_EMAIL` and
`ADMIN_PASSWORD`. To reach `/backend`, that address must also be in `ADMIN_EMAILS`. Outside the
`local` and `testing` environments the seeder refuses the placeholder password from `.env.example`.

`policy:import` validates `data/` first and refuses to import invalid records. It is an idempotent
upsert, so run it again after every data edit.

## Front end

The public site is server-rendered Blade with Tailwind. Only the account screens are React with
Inertia. Sources are in `resources/js` and `resources/css`; Vite builds them (`vite.config.js`).

```bash
npm ci           # once
npm run dev      # Vite dev server with hot reload, in a second terminal
npm run build    # production build into public/build
```

Without `npm run dev` the site uses the committed build in `public/build`, so a PHP-only or
data-only change needs no Node at all.

`public/build` is committed. Rebuild and commit it only when your change touches `resources/js`,
`resources/css` or `vite.config.js`, in the same commit. Never edit files in `public/build` by
hand. CI checks that the committed manifest covers every Vite entry point.

## Tests

```bash
composer test                                          # the whole PHP suite (php artisan test)
php artisan test tests/Feature/ConfigIsCacheableTest.php   # one file
php artisan test --filter=test_no_configuration_value_is_a_closure   # one test
npm test                                               # JavaScript tests (agent/ and tests/js/)
```

The PHP suite lives in `tests/Feature` and `tests/Unit`. `phpunit.xml` runs it on SQLite. The full
suite takes 20 to 30 minutes, so while you work, run only the files you touched. Add or update a
test in `tests/Feature` for any behaviour change.

CI runs the suite twice:

- **SQLite** (`PHP lint & tests` in `ci.yml`). This job gates deploys.
- **PostgreSQL 16** (`PHP tests (PostgreSQL)`). Production runs PostgreSQL, and the two databases
  disagree in ways that reach production silently. This job is often cancelled at its 90-minute
  cap, so it does not gate deploys yet (debt #49 in
  [`technical-debt.md`](reference/technical-debt.md)).

## Linting

```bash
composer lint        # Laravel Pint, check only (pint --test); blocking in CI
composer lint:fix    # Pint, rewriting files
npm run lint         # ESLint (eslint.config.js)
npm run lint:fix
```

Pint's rules are in `pint.json`. `npm run typecheck` exists but is a no-op: the front end is plain
JavaScript (debt #5).

## Data checks

`data/` is the source of truth. The format is in [`data/README.md`](../data/README.md) and the
schemas are in `data/schema/`.

```bash
php artisan policy:validate              # JSON Schema and cross-reference checks over data/
php artisan policy:freshness --list=40   # records past their re-check date (verification policy)
php artisan policy:coverage --list=40    # records missing required fields (completeness policy)
php artisan policy:next-id POL           # next free permanent id (also OBL, CHG, CTL)
```

`policy:freshness` and `policy:coverage` read the database, so run `policy:import` first. The
rules behind them are in [`verification-policy.md`](reference/verification-policy.md) and
[`completeness-policy.md`](reference/completeness-policy.md). The *Validate policy data* workflow
runs all three on any pull request that touches `data/`.

## The MCP server

`agent/` holds a dependency-free MCP server (`agent/server.mjs`) that answers over stdio by calling
the site's public URLs. See [`agent/README.md`](../agent/README.md).

```bash
node agent/server.mjs                                              # against aipolicytracker.org
AIPOLICYTRACKER_BASE_URL=http://127.0.0.1:8000 node agent/server.mjs   # against your local copy
node --test agent/server.test.mjs                                  # its tests (also part of npm test)
```

The tests run the server as a child process against a local stub of the site, so they need no
network.

## Docker

The production image is built from the root [`Dockerfile`](../Dockerfile): a `node:26-alpine` stage
builds the front end, then a `php:8.3-cli-alpine` stage runs the app. Its entrypoint,
[`docker/entrypoint.sh`](../docker/entrypoint.sh), migrates, builds the config, route and view
caches, starts `policy:import` and `external:import` in the background, starts the scheduler
(unless `SCHEDULER=off`) and a queue worker, then serves on port 8080.

[`deploy/compose.yml`](../deploy/compose.yml) runs that image with PostgreSQL 16:

```bash
cd deploy
# write deploy/.env first: see "First deploy" in deploy/README-docker.md
docker compose up -d --build
docker compose logs -f app
curl -s -o /dev/null -w '%{http_code}\n' http://127.0.0.1:8080/up
```

The compose file reads `DB_DATABASE`, `DB_USERNAME` and `DB_PASSWORD` from `deploy/.env` and
refuses to start without them. The full runbook, including nginx and automatic deploys, is
[`deploy/README-docker.md`](../deploy/README-docker.md).

## Artisan commands

The project's own commands, from `app/Console/Commands`. Run `php artisan help <command>` for the
options.

| Command | What it does |
|---------|--------------|
| `policy:validate {--path=}` | Validate the data/ records against their JSON Schemas and cross-reference rules |
| `policy:import {--path=} {--skip-validation}` | Validate and import the data/ records into the database (idempotent upsert) |
| `policy:next-id {kind}` | Print the next unused permanent record id (`AIPT-POL-0241` and so on) |
| `policy:freshness {--json} {--list=20}` | Check published records against the verification policy and list what is overdue |
| `policy:coverage {--json} {--list=0} {--kind=} {--check=}` | Check published records against the completeness policy and list what is missing |
| `policy:export {--out=storage/app/exports}` | Export published policy data as a versioned JSON bundle |
| `policy:export-verifications {--all}` | Write admin verification decisions back into the data/ YAML files |
| `verification:sample {--percent=} {--seed=} {--quarter=} {--include-checked}` | Print a reproducible random sample of verified records for independent second checks |
| `external:import` | Import the AI Incident Database and MIT AI Risk Repository rows from `data/external/` |
| `external:sync-aiid {--file=}` | Rebuild the AI Incident Database summary from the latest weekly export |
| `external:sync-aiid-reports {--dir=}` | Refresh `data/external/aiid_reports.json` from the latest AIID backup |
| `external:sync-aiid-api {--since=} {--full} {--max=600} {--write-json} {--no-db}` | Sync the latest AIID records from its API |
| `external:sync-mit-risk {--file=}` | Rebuild the MIT AI Risk Repository domain taxonomy file |
| `incidents:enrich {--refresh}` | Fill or refresh harm domain, related laws, policy angle and sensitivity on incidents |
| `templates:build {--only=} {--force}` | Generate the templates library (XLSX/DOCX) from the records |
| `report:freeze {--quarter=}` | Freeze a quarter of the state-of-AI-regulation report |
| `alerts:send {--dry-run}` | Send daily change and deadline alerts to Pro accounts |
| `alerts:deliver` | Retry pending Slack and webhook alert deliveries |
| `digest:send {--days=7} {--dry-run}` | Send the weekly digest to confirmed subscribers |
| `social:post {--dry-run}` | Queue posts to X for new verified changes, then send the ones that are due |
| `social:doctor {--out=}` | Report whether social preview cards can be drawn on this host |
| `social:clear` | Delete cached social preview cards |
| `seo:audit {--section=*} {--limit=0} {--json=} {--fail-on-violation}` | Crawl the sitemap in process and report page-rule violations |
| `seo:ctr-audit {csv*} …` | List pages that rank on page one but are not clicked (Search Console export) |
| `seo:indexnow {--since=24 hours} {--url=*} {--dry-run}` | Tell IndexNow search engines which pages changed |
| `seo:sensitive-indexed {csv?} {--out=}` | List sensitive incident pages a Search Console export shows as indexed |
| `email:check {address*}` | Show what the address policy decides about an address |
| `email:domains-refresh {--source=} {--dry-run}` | Refresh the operator list of throwaway e-mail domains |
| `admin:invite {email} {name} {--role=reviewer} {--by=} {--note=}` | Create an account with an admin role and email its invitation |
| `admin:two-factor-reset {email}` | Remove an admin's authenticator so they enrol again |
| `aip:health` | Exit non-zero if the database is unreachable or the scheduler has stopped |

The schedule that runs these in production is in `routes/console.php`; the jobs are described in
[`docs/modules/jobs.md`](modules/jobs.md).

## CI

Each workflow in `.github/workflows/`:

| Workflow | What it does |
|----------|--------------|
| `ci.yml` (CI) | PHP lint and tests on SQLite, the production cache build, `seo:audit` over the sitemap; tests on PostgreSQL; JS lint, test, build and the committed-manifest check. |
| `security.yml` (Security) | Gitleaks, Semgrep, `composer audit`, `scripts/npm-audit.mjs`, Trivy; IaC and image scans when deploy files change; ZAP weekly. See [`security-scanning.md`](reference/security-scanning.md). |
| `codeql.yml` (CodeQL) | CodeQL on the JavaScript, on pull requests to `main`, pushes to `main` and weekly. |
| `gates.yml` (Gate check) | Fails a pull request whose description lacks the five gate headings or *Accepted debt*. |
| `validate-data.yml` (Validate policy data) | `policy:validate`, then `policy:freshness` and `policy:coverage`, when `data/` or the policy code changes. |
| `deploy.yml` (Deploy) | Deploys a commit of `main` after CI finishes. |
| `deploy-check.yml` (Deployment check) | Manual: probes the live site for one marker per shipped feature (`scripts/deploy-check.sh`). |
| `release.yml` (Release) | Publishes a GitHub release from the matching `CHANGELOG.md` section. |
| `refresh-external-data.yml` | Weekly: syncs the AIID and MIT datasets and opens a pull request. |
| `uptime-self-heal.yml` (Uptime check) | Every 15 minutes: probes `/up` and fails if the site stays down. It reports only; it restarts nothing. |
| `composer-lock.yml` | Manual: regenerates `composer.lock` for named packages and uploads it as an artifact. |

**What gates a deploy.** `deploy.yml` deploys only when the `PHP lint & tests` (SQLite) and
`JS lint & build` jobs of CI passed and the Security workflow for the same commit succeeded. The
PostgreSQL job is not waited for (debt #49). `main` is protected: changes arrive by pull request
with a code owner's approval and green required checks.

## Releases

Releases are cut from `CHANGELOG.md`, which is the only place release notes are written. The steps
are in [CONTRIBUTING.md → Releases](../CONTRIBUTING.md#releases):

1. In `CHANGELOG.md`, rename *Unreleased* to `[X.Y.Z] - YYYY-MM-DD` and start a new *Unreleased*.
2. Check the notes: `scripts/release/notes.sh X.Y.Z`. It fails if the section is missing or empty.
3. Push to `main`, then push the tag `vX.Y.Z`, or run **Release** from the Actions tab (it releases
   the newest version in the changelog and creates the tag).
4. After the deploy, run **Deployment check** from the Actions tab.

Dataset releases and DOIs are covered in [`releases.md`](reference/releases.md).

## Troubleshooting

**A config value is `null` in production but fine locally.** Production runs `config:cache`
(`docker/entrypoint.sh`). A config file that calls `config()` to read another config file resolves
to `null` once cached, and a closure in `config/` stops the cache from building at all.
`ConfigIsCacheableTest` catches closures, not the `config()` case (debt #44). To check, run
`php artisan config:cache`, read the value back, then `php artisan config:clear`.

**Tests pass on SQLite and fail on PostgreSQL.** The two differ: an unmatched double-quoted
identifier is a string literal in SQLite and an error in PostgreSQL, and a constraint violation
inside a transaction poisons the whole transaction in PostgreSQL but not in SQLite. Both reached
production before the PostgreSQL job existed (comments in `ci.yml`). Point `DB_CONNECTION` at a
local PostgreSQL to reproduce.

**CI fails on the `public/build` manifest.** The JS job checks that the committed manifest covers
every Vite entry. Run `npm run build` and commit `public/build`. The bundle is not byte-for-byte
reproducible across machines (debt #30), so do not commit a rebuild when your change does not touch
the front end.

**Every job runs twice, or subscribers get two digests.** Only one scheduler may run per deployment.
The container starts `schedule:work` unless `SCHEDULER=off`; a host that drives the jobs from its own
crontab (`deploy/cron-install.sh`) must set `SCHEDULER=off`.

**`/backend` sends you to the homepage.** Admin access is the list in `ADMIN_EMAILS`, not a column on
the users table. Add your address there.
