# Repository guidelines for agents

Orientation for AI coding agents, and for anyone else, working on this repository. Read
[`CONTRIBUTING.md`](CONTRIBUTING.md) and the [developer guide](docs/developer-guide.md) as well; where
they say more, they win.

## What this is

AIPolicyTracker ([aipolicytracker.org](https://aipolicytracker.org)) is an open tracker of AI laws,
policies and the duties they create. It is a Laravel 12 application on PHP 8.3. The public site is
server-rendered Blade; only the account screens are React with Inertia. Production runs PostgreSQL in
Docker; local development and the gating CI job use SQLite. Code is Apache-2.0, data is CC BY 4.0.

## Golden rules

1. **`data/` is the source of truth. Never invent facts.** A law, date, status, obligation, count or
   name goes into the repository only when an official or openly licensed source says it. If a fact
   cannot be established, leave the field empty and lower `confidence_level`; do not guess.
   [`SOURCE_ATTRIBUTION.md`](SOURCE_ATTRIBUTION.md) says which sources count.
2. **Every record needs its official source** (`official_source_url`, `source_title`,
   `source_publisher`). Write summaries in your own words; never paste from paid databases, law-firm
   commentary or standards text.
3. **Verification is a human act and is never backdated.** Only a person who opened the source sets
   `review_status: verified`, `last_verified_at` (the day they checked) and `reviewed_by` (a reviewer
   published in `data/reviewers/`). An agent does not mark records verified.
4. **Permanent ids are permanent.** A new policy, obligation, change or control gets the next id from
   `php artisan policy:next-id POL` (or `OBL`, `CHG`, `CTL`). Never change or reuse an `aipt_id`.
5. **Do not edit `public/build` by hand.** Rebuild it with `npm run build`, and commit it only when the
   change touches `resources/js`, `resources/css` or `vite.config.js`.
6. **Commits are authored by the maintainer or contributor responsible for them.** No trailers or
   footers crediting tools or AI, in commits, comments or docs (CONTRIBUTING, "Branches, commits and
   pull requests").
7. **Never commit secrets** (`.env`, keys, tokens), database dumps, personal data or hosting details
   such as server addresses, hostnames and account IDs. Use a placeholder such as `<origin-ip>`.
8. **Code, data, API and docs move together.** A change that updates one and leaves the others behind
   is not finished. A new module needs a page in `docs/modules/`.
9. **Keep changes small and fix the cause.** Look at how something works before changing it
   ([`engineering-standard.md`](docs/reference/engineering-standard.md)).

## Map

| Path | What is there |
|------|---------------|
| `data/` | YAML records (jurisdictions, policies, controls, changes, implementation, reviewers, taxonomies, transition, translations), JSON Schemas in `data/schema/`, external datasets in `data/external/`. See [`data/README.md`](data/README.md). |
| `app/` | Laravel code. `Console/Commands` (artisan commands), `Http/Controllers/{Site,Backend,Api,Auth}`, `Http/Middleware` (`SecurityHeaders`, `CheckAdmin`, …), `Models`, `Services/*` (one folder per area), `Support`. |
| `routes/` | `public.php` (public site), `api.php` (API v1), `auth.php`, `backend/web.php` (admin), `console.php` (schedule). |
| `resources/views/` | Blade: `site/` (public), `backend/` (admin), `components/`, `emails/`. |
| `resources/js`, `resources/css` | Front-end sources; account screens in `resources/js/Pages`. Built into `public/build`. |
| `config/` | Configuration. Must stay cacheable: no closures, no `config()` calls. |
| `database/` | Migrations and seeders. |
| `tests/` | PHPUnit `Feature` and `Unit` tests; `tests/js` for node tests. |
| `agent/` | MCP server (`server.mjs`) and its tests. |
| `docker/`, `Dockerfile`, `deploy/` | Production image, entrypoint, compose file and runbooks. |
| `cloudflare/` | Edge Worker and its notes. |
| `scripts/` | Release notes, deploy check, npm audit, SEO helpers. |
| `docs/` | Developer guide, architecture, modules, operations, reference, archive. Index: [`docs/README.md`](docs/README.md). |
| `.github/workflows/` | CI, Security, CodeQL, gates, data validation, deploy, release. |

## Commands

```bash
composer install && cp .env.example .env && php artisan key:generate
touch database/database.sqlite && php artisan migrate --seed
php artisan policy:import && php artisan external:import
php artisan serve
```

Before opening a pull request:

```bash
php artisan policy:validate     # data
composer lint                   # Pint (composer lint:fix to apply)
composer test                   # PHP suite, 20 to 30 minutes
npm run lint                    # ESLint
npm test                        # agent/ and tests/js/
npm run build                   # only when the front end changed
```

The full PHP suite is slow. While working, run the files you touched:
`php artisan test tests/Feature/SomeTest.php` or `php artisan test --filter=...`.

## Tests

- Any behaviour change needs a test in `tests/Feature` (or `tests/Unit`).
- CI runs the suite on SQLite (gates deploys) and on PostgreSQL. Avoid SQL that only one of them
  accepts, such as double-quoted string literals.
- Data changes are tested by `policy:validate`, `policy:freshness` and `policy:coverage`.

## Pull requests

- Use [`.github/PULL_REQUEST_TEMPLATE.md`](.github/PULL_REQUEST_TEMPLATE.md). The *Gate check* workflow
  fails a description that lacks the five gate headings or *Accepted debt*
  ([`change-gates.md`](docs/reference/change-gates.md)).
- For data, fill in the sources table: each changed field, its source URL, the date accessed.
- Debt you knowingly accept goes in [`technical-debt.md`](docs/reference/technical-debt.md).
- Add a line to `CHANGELOG.md` under *Unreleased* for anything a reader of the site would notice.

## Where to read more

- [`docs/developer-guide.md`](docs/developer-guide.md): setup, tests, CI, releases, troubleshooting.
- [`docs/architecture.md`](docs/architecture.md): how the parts fit, and the key design decisions.
- [`docs/modules/`](docs/modules/README.md): one page per module.
- [`docs/operations/data-updates.md`](docs/operations/data-updates.md): how records are verified and kept current.
- [`SECURITY.md`](SECURITY.md): security practices and reporting.
