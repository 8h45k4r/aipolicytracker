# Changelog

All notable changes to this project are documented here. The format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/) and the project uses [Semantic Versioning](https://semver.org/).

## [Unreleased]

### Added
- Azure App Service startup/nginx configuration and GitHub Actions deploy workflow.
- Production `Dockerfile`, entrypoint, `.dockerignore`, and optional `fly.toml`; README deployment notes for containers and Cloudflare.

## [1.0.0] - 2026-09-10

First public open-source release.

### Added
- MIT licence, README, contribution guide, code of conduct, security policy, and source attribution policy.
- GitHub issue templates (bug, policy data correction, new jurisdiction/source, feature), pull request template, and CI workflow (PHP lint/tests, JS lint/build).
- `config/aipolicytracker.php` with environment-driven admin list, public links, contact addresses, analytics ID, and page-size limits.
- `SecurityHeaders` middleware and a restrictive `config/cors.php`.
- ESLint 9 configuration and `npm run lint`.
- Admin access feature tests.
- Migration dropping the legacy `user_infos.password` column.

### Changed
- Admin authorisation now uses `ADMIN_EMAILS` instead of hard-coded addresses.
- Header/footer/about links and contact addresses are read from configuration.
- Google Analytics loads only when `GOOGLE_ANALYTICS_ID` is set.
- JSON error responses no longer include exception messages; page sizes are clamped.
- Country status update validates its input.
- Registration no longer stores the plaintext password.
- Admin seeder creates the first admin from `ADMIN_*` environment variables.
- Tests updated to match the application's redirect behaviour; PHPUnit uses in-memory SQLite.

### Removed
- `/clear-cache` and `/storage-link` web routes.
- Hard-coded personal e-mail addresses, contributor lists, and external document links.
- Duplicate/dead files (copied templates, unused Vue components, duplicate profile pages, unused images) and unused npm packages.
