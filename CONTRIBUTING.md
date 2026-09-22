# Contributing to AI Policy Tracker

Thank you for helping build an open, source-backed view of AI policy. This guide covers code and data contributions.

## Ground rules

- Be respectful. See `CODE_OF_CONDUCT.md`.
- Never commit secrets, `.env` files, database dumps, personal data, or production exports.
- Every policy or regulatory data change must cite an **official or openly licensed source** (see `SOURCE_ATTRIBUTION.md`).
- Keep pull requests focused. Small, reviewable changes merge faster.

## Getting started

```bash
composer install && npm install
cp .env.example .env && php artisan key:generate
touch database/database.sqlite && php artisan migrate --seed
php artisan serve   # and, in another terminal, npm run dev
```

## Branches and commits

- Branch from `main`: `feat/<topic>`, `fix/<topic>`, `data/<jurisdiction>`, `docs/<topic>`.
- Write clear commit messages in the imperative mood ("Add Kenya AI strategy entry").
- Update `CHANGELOG.md` under *Unreleased* for user-visible changes.
- A commit is authored by the person accountable for it. Do not add trailers or
  footers crediting tooling, in commit messages, code comments or documentation.
  The history records who is answerable for a change, not what was used to write
  it.

## Code contributions

Every change follows `docs/reference/engineering-standard.md`: inspect before coding, Analyze → Design → Implement → Test → Validate → Document, fix root causes, keep changes small and reversible, and never claim something works without the command output that proves it.

Every change carries its own enforcement: access control and per-user isolation, server-side validation, publication rules and auditability. Frontend, backend, database, API and documentation move together — a change that updates one and leaves another behind is incomplete.

1. Follow existing conventions: Laravel controllers/requests/models on the backend, React function components with Inertia on the frontend, Tailwind for styling.
2. Validate all request input in a Form Request or `$request->validate()`.
3. Do not return exception messages or stack traces to the client.
4. Add or update tests in `tests/Feature` for behaviour changes.
5. Run the full check before pushing:

   ```bash
   php artisan policy:validate && composer lint && composer test && npm run lint && npm run build
   ```

## Data contributions

1. Open a *Policy data correction* or *New jurisdiction or source* issue first if the change is non-trivial.
2. Edit or add YAML records under `data/` (see `data/README.md`); every record needs `official_source_url` and the other source-quality fields. Run `php artisan policy:validate` before pushing. Only a reviewer who has opened the source may set `review_status: verified`.
3. In the pull request table, list each changed field, its source URL, and the date accessed.
4. Mark the verification status honestly. Unverified changes are not merged into `main`.
5. Summaries must be your own words. Do not paste text from paid databases, law-firm commentary, or standards bodies (e.g. ISO/IEC documents).

## How changes get in (change request → review → merge)

1. **Propose:** open an issue describing the change (or a *Policy data correction* for data). Small fixes can go straight to a pull request.
2. **Fork and branch:** anyone can fork the repository; maintainers work on branches in this repository. Direct pushes to `main` are disabled.
3. **Pull request:** fill in the template (sources, verification status, tests, screenshots). CI (tests, lint, build, CodeQL) must pass.
4. **Review:** at least one maintainer approval is required. Reviewers may request changes; push follow-up commits to the same branch.
5. **Merge:** maintainers squash-merge. The PR title becomes the commit message, so keep it descriptive.
6. **Release:** maintainers tag releases from `main` and update `CHANGELOG.md`.

Editing existing content follows the same flow: change the YAML record in `data/`, bump `content_version` and write `change_summary`, cite the source, open a PR. Web-form submissions from `/contribute` are triaged at `/backend/review` and applied through the same PR process.

## The five role gates (binding)

Every change to `main`, including one-line fixes, must pass the five gates in `docs/reference/change-gates.md`, in order: Engineering & QA/QC, UI/UX, Documentation, Compliance, Security (VAPT). Record the outcome of each gate in the pull request description with evidence (command output, query counts). Mark a gate that does not apply as N/A with a reason; never leave it blank. Accepted debt goes in `docs/reference/technical-debt.md` with an owner. A new module without `docs/modules/<module>.md` does not merge.

## Pull requests

Use the pull request template. Maintainers look for: linked issue, sources for data, passing CI, screenshots for UI changes, and no secrets or personal data in the diff.

## Licence of contributions

By contributing you agree that your contributions are licensed under the Apache License 2.0 (see `LICENSE`).

## Reporting security issues

Please follow `SECURITY.md` rather than opening a public issue: use GitHub's private vulnerability reporting or write to bhaskar@aipolicytracker.org.

## Merging into `main`

- `main` is protected: changes arrive only through pull requests with at least one approving review from a code owner, all required checks green (PHP tests, JS build, Gate check), and every review conversation resolved.
- The **Gate check** workflow fails a pull request whose description does not document the five role gates and the accepted-debt section; use the template.
- **Hosting details never go into the repository**: origin IP addresses, server
  hostnames, account or zone identifiers, cloud resource names, credentials.
  This holds for runbooks, comments, commit messages and the debt register as
  much as for code. Deploy targets are GitHub Actions variables and secrets; the
  edge Worker reads its origin from an environment variable. Where a runbook
  needs to refer to a host, it uses a placeholder such as `<origin-ip>`.
  An origin address in particular is what a proxying CDN exists to conceal:
  publishing it lets the edge be bypassed, so it is a security defect and not
  merely untidy.
