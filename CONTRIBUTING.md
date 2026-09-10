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

## Pull requests

Use the pull request template. Maintainers look for: linked issue, sources for data, passing CI, screenshots for UI changes, and no secrets or personal data in the diff.

## Reporting security issues

Please follow `SECURITY.md` rather than opening a public issue.
