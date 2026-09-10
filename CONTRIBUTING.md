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

## Code contributions

1. Follow existing conventions: Laravel controllers/requests/models on the backend, React function components with Inertia on the frontend, Tailwind for styling.
2. Validate all request input in a Form Request or `$request->validate()`.
3. Do not return exception messages or stack traces to the client.
4. Add or update tests in `tests/Feature` for behaviour changes.
5. Run the full check before pushing:

   ```bash
   composer lint && composer test && npm run lint && npm run build
   ```

## Data contributions

1. Open a *Policy data correction* or *New jurisdiction or source* issue first if the change is non-trivial.
2. Enter data through the admin UI or a seeder; include the official document link in `whitepaper_document_link`.
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

Editing existing content (policy entries, news, descriptions) follows the same flow: change the seeder or data via the admin UI export, cite the source, open a PR.

## Pull requests

Use the pull request template. Maintainers look for: linked issue, sources for data, passing CI, screenshots for UI changes, and no secrets or personal data in the diff.

## Licence of contributions

By contributing you agree that your contributions are licensed under the Apache License 2.0 (see `LICENSE`).

## Reporting security issues

Please follow `SECURITY.md` rather than opening a public issue.
