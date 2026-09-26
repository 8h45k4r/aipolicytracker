# AIPolicyTracker

**AI governance intelligence, from regulation to evidence.**

[![CI](https://github.com/8h45k4r/aipolicytracker/actions/workflows/ci.yml/badge.svg)](https://github.com/8h45k4r/aipolicytracker/actions/workflows/ci.yml)
[![Gate check](https://github.com/8h45k4r/aipolicytracker/actions/workflows/gates.yml/badge.svg)](https://github.com/8h45k4r/aipolicytracker/actions/workflows/gates.yml)
[![Validate policy data](https://github.com/8h45k4r/aipolicytracker/actions/workflows/validate-data.yml/badge.svg)](https://github.com/8h45k4r/aipolicytracker/actions/workflows/validate-data.yml)
[![Security](https://github.com/8h45k4r/aipolicytracker/actions/workflows/security.yml/badge.svg)](https://github.com/8h45k4r/aipolicytracker/actions/workflows/security.yml)
[![Code: Apache-2.0](https://img.shields.io/badge/code-Apache--2.0-blue.svg)](LICENSE)
[![Data: CC BY 4.0](https://img.shields.io/badge/data-CC%20BY%204.0-blue.svg)](data/LICENSE)

Track regulations. Map obligations. Operationalise controls. Prove compliance. Source-backed AI laws, regulations, strategies, standards and guidance across 212 jurisdictions (every UN member state plus territories, sub-national and international bodies), the obligations and deadlines they create, recorded AI incidents and risks, and free templates that help teams act on them. Every claim is traceable to its official source.

**[aipolicytracker.org](https://aipolicytracker.org)** · [Open data and API](https://aipolicytracker.org/open-data) · [Weekly digest](https://aipolicytracker.org/subscribe) · [Methodology](https://aipolicytracker.org/methodology) · [Contribute](https://aipolicytracker.org/contribute)

> **Disclaimer.** Content is informational only and is not legal advice. Check the linked official source and, where needed, qualified counsel before acting on anything shown here.

![Architecture](docs/diagrams/architecture.svg)

## Quick start

Prerequisites: PHP 8.2 or newer with `pdo_sqlite` (production runs 8.3), Composer 2, Node 22+. To contribute, fork first and clone your fork; `CONTRIBUTING.md` has the steps.

```bash
git clone https://github.com/8h45k4r/aipolicytracker.git
cd aipolicytracker
composer install
npm ci
cp .env.example .env
php artisan key:generate
touch database/database.sqlite
php artisan migrate --seed
php artisan policy:import
php artisan external:import
npm run dev        # in one terminal
php artisan serve  # in another
```

The site is then on <http://127.0.0.1:8000>. `.env.example` documents every variable; nothing deployment-specific is committed.

| Task | Command |
|------|---------|
| Check the data files against their schemas and cross-references | `php artisan policy:validate` |
| Rebuild the database from `data/` | `php artisan policy:import` |
| Load the external datasets (AI incidents, MIT risks) | `php artisan external:import` |
| Refresh one external source | `php artisan external:sync-aiid`, `external:sync-aiid-api`, `external:sync-mit-risk` |
| Preview the weekly digest without sending it | `php artisan digest:send --dry-run` |
| Everything CI runs | `composer lint`, `composer test`, `npm run lint`, `npm run build` |

## What you can do here

| Need | Where |
|------|-------|
| Find every AI instrument in a country or bloc, with status, regulators and official sources | [/jurisdictions](https://aipolicytracker.org/jurisdictions) |
| Read a plain-language record of a law or strategy: scope, dates, obligations, penalties, FAQ, JSON | [/policies](https://aipolicytracker.org/policies) |
| See what organisations must actually do, legal requirements separated from voluntary guidance | [/obligations](https://aipolicytracker.org/obligations) |
| Find the control that meets a duty, every other duty it serves, and the evidence that proves it is operating | [/controls](https://aipolicytracker.org/controls) |
| Compare two to four jurisdictions side by side | [/compare](https://aipolicytracker.org/compare) |
| Follow dated, source-linked changes (RSS and weekly email) | [/changes](https://aipolicytracker.org/changes) |
| Explore recorded AI incidents and the MIT AI Risk Repository, with profiles, charts and exports | [/ai-risk](https://aipolicytracker.org/ai-risk) |
| Screen whether a rule applies to you (educational, not advice) | [/tools/applicability-check](https://aipolicytracker.org/tools/applicability-check) |
| Read practical guides and download free templates, checklists and registers | [/guides](https://aipolicytracker.org/guides) |
| Reuse the dataset (CC BY 4.0) through JSON, CSV, an OpenAPI description and `llms.txt` | [/open-data](https://aipolicytracker.org/open-data) |
| Read the corpus as structured data: every page publishes schema.org JSON-LD, and a binding instrument states whether it is actually in force | any page's `<head>`, [docs](docs/reference/machine-readable-surfaces.md) |
| Report a correction with the record and field prefilled | "Report a correction" on any record |

## How records are produced

![Data flow](docs/diagrams/data-flow.svg)

```
data/
  schema/                 JSON Schema per record type
  taxonomies/terms.yaml   actors, AI system types, sectors, risk categories, use cases, obligation categories
  jurisdictions/*.yaml    one file per jurisdiction
  policies/<j>/*.yaml     one file per policy instrument (sections, obligations, deadlines, sources, FAQ)
  controls/*.yaml         one file per organisational control: evidence, owner, risks, standards clauses
  changes/<year>.yaml     dated change events
```

Every record carries an official source URL, publisher, document date, source tier, `last_checked_at`, `last_verified_at`, review status, confidence level and a content version. `policy:validate` checks schema and cross-references in CI; `policy:import` rebuilds the database from these files on every deploy. External datasets (AI Incident Database, MIT AI Risk Repository) are refreshed weekly through a pull request. See `data/README.md`, `/methodology` and `docs/modules/`.

Records are never inferred. A record that no one has opened and confirmed is labelled source-linked rather than verified, and [/coverage](https://aipolicytracker.org/coverage) states in words what the corpus does not yet know.

## Free tools

![Free tools flow](docs/diagrams/free-tools-flow.svg)

Guides are free to read. Templates, checklists and registers can be previewed field by field; downloading needs a free account and an explicit licence acceptance, and files are delivered through short-lived personal links. See `docs/modules/guides-and-downloads.md`.

## Plans

The dataset is free and open (CC BY 4.0) for everyone. Pro (monthly or annual) pays for the service around it: follow any policy, jurisdiction or obligation and get one daily email when it changes, plus reminders before application dates fall due. Payments run through Dodo Payments as merchant of record; access is granted only by verified webhooks. Billing is shipped disabled until the provider account is verified. See `docs/modules/billing.md`.

## Tech stack

| Layer | Technology |
|-------|------------|
| Public site and admin | Laravel 11, Blade (server-rendered), Tailwind CSS 3, a small progressive-enhancement script |
| Backend | PHP 8.3, Laravel 11 |
| Database | PostgreSQL in production. The suite runs twice in CI: once on in-memory SQLite for speed, once on PostgreSQL 16 because the two disagree in ways that used to reach production |
| Tooling | Vite 5, ESLint 9, Laravel Pint, PHPUnit 11, GitHub Actions |

## Repository map

| Path | What is there |
|------|---------------|
| `app/`, `routes/`, `resources/views/` | The Laravel application: public site, admin, Blade templates and the shared design system in `resources/css/public.css` |
| `data/` | The corpus, as reviewable YAML with a JSON Schema per record type |
| `docs/modules/` | One document per module, with its field table and its interlinks |
| `docs/reference/` | The engineering standard, change gates, compliance map, technical-debt register, verification and completeness policies, VAPT findings |
| `docs/reports/` | Point-in-time audit and implementation reports, kept for the audit trail; `CHANGELOG.md` is the current record |
| `deploy/`, `docker/` | Deployment runbooks and the release script |
| `tests/` | PHPUnit suite, including the interlink proofs CI depends on |

## Deployment

The application needs a PHP runtime and a PostgreSQL database; a CDN in front handles DNS, TLS and caching. **Deploys are run by hand, not by a workflow**: pushing to `main` runs CI and nothing else. The runbook for the current host, which runs nginx in front of Docker containers, is [`deploy/README-docker.md`](deploy/README-docker.md); [`deploy/README.md`](deploy/README.md) covers a host with a native PHP-FPM instead. `deploy/deploy.sh` migrates, imports, caches config, routes and views, and swaps the release only if every step succeeded.

Recurring work (digest, alerts, syncs, imports) runs on the timetable in `routes/console.php`. Exactly one scheduler may run for a deployment: the container starts `schedule:work` itself unless given `SCHEDULER=off`, in which case `deploy/cron-install.sh` puts a single `schedule:run` entry in the host crontab. Every run, from either, is recorded and shown on the admin *Jobs and schedule* page. See [`docs/modules/jobs.md`](docs/modules/jobs.md).

## Contributing

Corrections to the data are as welcome as code, and are the fastest way to help.

Every change follows the engineering standard in [`docs/reference/engineering-standard.md`](docs/reference/engineering-standard.md) (inspect first, Analyze → Design → Implement → Test → Validate → Document, nothing claimed working without evidence) and passes five role gates — engineering, UI/UX, documentation, compliance, security — described in [`docs/reference/change-gates.md`](docs/reference/change-gates.md).

1. Open an issue using one of the templates (bug, policy data correction, new jurisdiction or source, feature).
2. Fork, branch (`feat/...`, `fix/...`, `data/...`), and for data changes include official source links and a verification status.
3. Make sure `composer lint`, `composer test`, `npm run lint` and `npm run build` pass, then open a pull request with the template and record the outcome of all five gates.

See [`CONTRIBUTING.md`](CONTRIBUTING.md), [`SOURCE_ATTRIBUTION.md`](SOURCE_ATTRIBUTION.md), [`GOVERNANCE.md`](GOVERNANCE.md) and [`CODE_OF_CONDUCT.md`](CODE_OF_CONDUCT.md).

## Security

Report vulnerabilities privately through the repository's *Security* tab or to <bhaskar@aipolicytracker.org>. Please do not open a public issue for a security report. See [`SECURITY.md`](SECURITY.md); the address is also published for automated discovery at `/.well-known/security.txt`.

## Contact

Official address for corrections, partnerships, press and security reports: **<bhaskar@aipolicytracker.org>**. Data corrections can also be filed from the "Report a correction" link on any record.

## Licence and sources

Code: Apache License 2.0 ([`LICENSE`](LICENSE), [`NOTICE`](NOTICE)). Data: CC BY 4.0, from official or openly licensed sources only. AI incidents: AI Incident Database (Responsible AI Collaborative), CC BY-SA 4.0, McGregor (2021). AI risk taxonomy: MIT AI Risk Repository, CC BY 4.0, Slattery et al. (2025). Third-party libraries keep their own licences.

The related product **Certifyi** ([certifyi.ai](https://certifyi.ai)) is a separate compliance execution platform; AIPolicyTracker is the open, public intelligence layer.

---

Maintained by [Bhaskar Bhatt](https://bhaskar.com.np/) · [GitHub](https://github.com/8h45k4r) · [LinkedIn](https://www.linkedin.com/in/8h45k4r/) · [X](https://x.com/8h45k4r)

Follow AIPolicyTracker: [LinkedIn](https://www.linkedin.com/company/aipolicytracker/) · [X](https://x.com/aipolicytracker) · [Facebook](https://www.facebook.com/aipolicytracker) · [Instagram](https://www.instagram.com/aipolicytracker/)
