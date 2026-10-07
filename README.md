# AIPolicyTracker

[![CI](https://github.com/8h45k4r/aipolicytracker/actions/workflows/ci.yml/badge.svg)](https://github.com/8h45k4r/aipolicytracker/actions/workflows/ci.yml)
[![Code: Apache-2.0](https://img.shields.io/badge/code-Apache--2.0-blue.svg)](LICENSE)
[![Data: CC BY 4.0](https://img.shields.io/badge/data-CC%20BY%204.0-blue.svg)](data/LICENSE)

An open tracker of AI laws, policies and the duties they create, at **[aipolicytracker.org](https://aipolicytracker.org)**.

It covers 212 jurisdictions, including every UN member state, with extra attention to the regions most trackers
skip: South Asia, South-East Asia, Africa and Latin America. Each record links to its official source and says
whether a person has checked it. Laws are broken down into obligations, the obligations are linked to controls
and evidence, and the whole dataset can be downloaded, queried through an API or read by AI agents.

Nothing here is legal advice. Check the official source before you rely on a record.

## Using the data

- Browse: [laws and policies](https://aipolicytracker.org/policies), [obligations](https://aipolicytracker.org/obligations), [what changed](https://aipolicytracker.org/updates)
- Download or query: [open data, API and OpenAPI](https://aipolicytracker.org/open-data), plus `llms.txt` and an MCP server in [`agent/`](agent)
- Follow: [weekly digest](https://aipolicytracker.org/subscribe), RSS and calendar feeds
- Fix something: every record has a "Report a correction" link, and [`/corrections`](https://aipolicytracker.org/corrections) shows what happened to each report

The data is CC BY 4.0. Credit "AIPolicyTracker" and link to the record you used.

## Running it locally

You need PHP 8.2+ with `pdo_sqlite`, Composer 2 and Node (the version in `.nvmrc`).

```bash
composer install && npm ci
cp .env.example .env && php artisan key:generate
touch database/database.sqlite
php artisan migrate --seed
php artisan policy:import      # build the database from data/
php artisan external:import    # AI incidents and the MIT risk repository
npm run dev                    # in one terminal
php artisan serve              # in another, then open http://127.0.0.1:8000
```

Before you open a pull request: `composer lint`, `composer test`, `npm run lint`, `npm run build`.
`php artisan policy:validate` checks the data files on their own.

## How it is put together

- `data/` is the source of truth: one YAML file per jurisdiction, policy and control, each with a JSON Schema in
  `data/schema/`. Every deploy rebuilds the database from these files, so a correction is a reviewable diff.
- The site is Laravel with server-rendered Blade pages. Only the account screens use React.
- PostgreSQL runs in production; the tests run on both SQLite and PostgreSQL.
- `docs/modules/` has one page per part of the system, `docs/reference/` holds the standards and audits, and
  `deploy/` has the runbooks.

## Contributing

Data corrections are the most useful contribution: each needs an official source.
[`CONTRIBUTING.md`](CONTRIBUTING.md) explains the process, and [`SOURCE_ATTRIBUTION.md`](SOURCE_ATTRIBUTION.md)
lists which sources count. Report security issues privately ([`SECURITY.md`](SECURITY.md)).

## Who runs it

It is built and maintained by [Bhaskar Bhatt](https://bhaskar.com.np/). Contact:
[bhaskar@aipolicytracker.org](mailto:bhaskar@aipolicytracker.org).

[How the project is funded](https://aipolicytracker.org/funding) is public, including its rules on independence.
The maintainer is also associated with Certifyi, a commercial compliance product. That product has no say over
what the records contain.

## Licences

Code: [Apache 2.0](LICENSE). Data: [CC BY 4.0](data/LICENSE). Incident data comes from the AI Incident Database
(CC BY-SA 4.0) and the risk taxonomy from the MIT AI Risk Repository (CC BY 4.0); both keep their own licences.
