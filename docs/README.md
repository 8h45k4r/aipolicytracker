# Documentation

Where to find what. Start with the developer guide if you want to run or change the code, or with
`data/README.md` if you want to change a record.

## Start here

| Document | What it covers |
|----------|----------------|
| [Developer guide](developer-guide.md) | Prerequisites, local setup, tests, linting, data checks, Docker, artisan commands, CI, releases, troubleshooting. |
| [Architecture](architecture.md) | How the parts fit, how a record flows from source to page, and the key design decisions. |
| [`data/README.md`](../data/README.md) | The record format, the review workflow, source tiers and the data-quality fields. |
| [`CONTRIBUTING.md`](../CONTRIBUTING.md) | How to contribute data and code, branches, commits, the five gates, releases. |
| [`AGENTS.md`](../AGENTS.md) | Rules and orientation for AI coding agents working on the repository. |

## Folders

| Folder | What is there |
|--------|---------------|
| [`modules/`](modules/README.md) | One page per module: purpose, field table matching the migrations, routes, interlinks and open debt. |
| [`operations/`](operations/) | How the site is run day to day: [content](operations/content.md), [data updates and verification](operations/data-updates.md), [SEO](operations/seo.md). |
| [`reference/`](reference/) | Standards and policies, plus dated audits and reviews. |
| [`diagrams/`](diagrams/) | SVG drawings of the architecture, the data flow and the free-tools flow. |
| [`archive/`](archive/README.md) | Dated working notes (strategy, roadmap, launch check, early reports). Historical, not current. |

## Standards and policies (in `reference/`)

| Document | What it covers |
|----------|----------------|
| [Engineering standard](reference/engineering-standard.md) | Binding rules for every change. |
| [Change gates](reference/change-gates.md) | What a pull request must prove: engineering, UI/UX, documentation, compliance, security. |
| [Technical debt](reference/technical-debt.md) | Every accepted gap, with an owner and an exit criterion. |
| [Verification policy](reference/verification-policy.md) | How old a fact may be before it must be checked again. |
| [Completeness policy](reference/completeness-policy.md) | What a published record must carry. |
| [Reviewer roster](reference/reviewer-roster.md) | Who may mark a record verified, and their declarations of interest. |
| [Compliance map](reference/compliance-map.md) | Repository controls mapped to the obligations the project holds itself to. |
| [Security scanning](reference/security-scanning.md) | What the security workflow runs, when, and accepted findings. |
| [Machine-readable surfaces](reference/machine-readable-surfaces.md) | What the site serves to things that are not browsers. |
| [Releases and DOIs](reference/releases.md) | How a quarter's data becomes a citable release. |
| [Design system](reference/design-system.md) | Tokens for shape, elevation, motion and interaction. |
| [Personas and jobs](reference/personas-and-jobs.md) | Who uses the site and what they need. |

The other files in `reference/` are dated audits, reviews, research notes and gate records
(their file names carry the date).

## Deployment

| Document | What it covers |
|----------|----------------|
| [`deploy/README-docker.md`](../deploy/README-docker.md) | The production path: Docker host, compose, nginx, automatic deploys, rollbacks, backups. |
| [`deploy/README.md`](../deploy/README.md) | The native PHP-FPM path. It does not apply to the current host (debt #31). |
| [`deploy/CHANGELOG-server.md`](../deploy/CHANGELOG-server.md) | Every command that changed the server's state, in order. |
| [`cloudflare/README.md`](../cloudflare/README.md) | The Cloudflare edge and its Worker. |

## Agents and data access

| Document | What it covers |
|----------|----------------|
| [`agent/README.md`](../agent/README.md) | The MCP server: tools, registration, design notes, tests. |
| [Machine-readable surfaces](reference/machine-readable-surfaces.md) | API, OpenAPI, `llms.txt`, Markdown and JSON record files, exports. |

## Project

| Document | What it covers |
|----------|----------------|
| [`CHANGELOG.md`](../CHANGELOG.md) | What changed, release by release. |
| [`GOVERNANCE.md`](../GOVERNANCE.md) | Who decides what, and how. |
| [`SECURITY.md`](../SECURITY.md) | How to report a vulnerability, scope and security practices. |
| [`.github/SUPPORT.md`](../.github/SUPPORT.md) | Where to get help. |
| [`SOURCE_ATTRIBUTION.md`](../SOURCE_ATTRIBUTION.md) | Which sources count, and how to attribute them. |
| [`CODE_OF_CONDUCT.md`](../CODE_OF_CONDUCT.md) | How we treat each other. |
