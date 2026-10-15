# Full code audit, 14 October 2026

Five read-only reviews of `main` (security; correctness and data integrity; performance; dependencies and platform; features, UX and accessibility), run against a migrated copy of the database. The findings marked **verified** were reproduced by the reviewer and re-checked against the code; the others were read from the code path end to end. Items 1 to 10 of the first table were fixed on 15 October 2026 (see the CHANGELOG and `tests/Feature/AuditFixesTest.php`); the separate invitation token table in item 8 is still open, as is everything below the first table.

## Fix first: wrong results or exposure today

| # | Area | Finding | Where | Fix |
|---|---|---|---|---|
| 1 | Correctness | **The weekly digest goes out every other week (verified).** It skips anyone mailed since `now()-7d` at midnight, and last week's 07:00 send falls inside that window; the change window also spans eight days, so boundary-day changes appear twice. | `SendDigestCommand.php:28,36` | Skip only when sent in the last six days (or since the last `DigestIssue`); window changes on `(last issue, now]`. |
| 2 | Correctness | **Daily alerts drop changes (verified).** The window is on `occurred_on > previous window end`, so a change imported the day after it happened, or backdated, is never alerted. The digest has the same weakness. | `AlertBuilder.php:67` | Window on `first_published_at` (when the row appeared), not `occurred_on`. |
| 3 | Data integrity | **Admin verifications override newer YAML forever (verified).** `RecordVerification::applyAll()` re-applies every stored decision after every import, including exported ones, so a record later marked `needs_update` in `data/` shows as verified. | `RecordVerification.php:19-27`, `PolicyImporter.php:66` | Apply only unexported rows, or only when newer than the YAML's `last_verified_at`. |
| 4 | Data integrity | **An admin unpublish is undone by the next import**, and unpublish then republish loses the first-published date. | `PolicyImporter.php:425-433`, `ReviewController.php:429-445` | Store the publish decision like verifications and honour it; keep the first date in its own column. |
| 5 | Security | **SSRF through alert webhook channels (verified).** Any https URL is accepted (loopback, private, link-local), redirects are followed, and the test button shows the raw error, so a signed-in user can probe the internal network. | `AlertChannelController.php:29`, `WebhookDispatcher.php` | Resolve and refuse private/reserved IPs at save and send time; no redirects; generic error text. |
| 6 | Security | **An admin can rename themselves to a rostered reviewer (verified)** through the template download form, which overwrites `name` without the admin restriction; verifications then show that reviewer's name. | `FreeToolController.php:91-95` | Do not overwrite an admin's name there; match the reviewer roster on a stable id. |
| 7 | Security | Admin TOTP: 5 guesses a minute with no lockout (about 50% success within a month for someone holding the password). | `routes/backend/web.php:27` | Daily failure cap with lockout and an email to the account holder. |
| 8 | Security | API tokens survive suspension and password reset and never expire; password-reset tokens are accepted by the invitation endpoint for 7 days. | `User.php:178`, `config/auth.php:96-108` | Delete tokens in `endAllSessions()`, refuse suspended users, set `sanctum.expiration`; separate invitation token table. |
| 9 | Correctness | The digest mails unpublished and superseded deadlines (and the public newsletter archive keeps them). | `SendDigestCommand.php:30`, `AlertBuilder.php:96` | Filter on published instruments and `deadline_status = scheduled`. |
| 10 | UX | **Every public "Sign in" link opens the admin sign-in** ("Sign in to the admin", "access is limited to addresses listed in the admin configuration"), with no link to create an account, and returns to the home page instead of the record. | `auth/login.blade.php`, `follow-button.blade.php`, `AuthenticatedSessionController` | A public sign-in view with "Create a free account" and a return URL. |
| 11 | Content | The EU AI Act record, the site's flagship page, is `pending_review` (with two other policies), so it shows "Pending review". | `data/policies/eu/eu-ai-act.yaml` | A named reviewer verifies it against the official text. Not something code should change. |

## Next: correctness and data

- Records removed or renamed in `data/` stay published (policies, jurisdictions, changes): no unpublish pass for missing slugs (`PolicyImporter.php`).
- The AIID incremental sync can skip incidents permanently after a bulk upstream edit (sorted by id, capped at 300, cursor moves on) (`SyncAiidApiCommand.php`).
- API `/api/v1/incidents?domain=N` returns no rows on real data: the parameter is numeric, the stored value is the domain name (`PublicApiController.php:315,325`).
- "Frozen" quarterly reports are overwritten if the freeze job is re-run (`StateOfAiRegulation.php:109`): use `firstOrCreate`.
- `first_published_at` is set when a row is created even if unpublished, so a later-published change misses the news sitemap and the X window (`ChangeEvent.php:56`).
- A deadline whose status changed but whose date did not shows as "Moved" (`PolicyImporter.php:285`).
- On SQLite only, re-running alerts the same day double-sends (`SendAlertsCommand.php:41`); the API country filter is case-sensitive on PostgreSQL (`PublicApiController.php:327`).
- Validation gaps the importer tolerates silently: obligation `section` and deadline `obligation` references, obligation term slugs, a removed `parent:`.

## Performance (production runs PostgreSQL over the network)

- **29 queries on every request at boot**: each setting is a separate `find()` and decrypt (`AppSettingsServiceProvider.php`). Load all settings once.
- **Every anonymous request starts a session and writes a row**, and HTML is `private, no-cache`, so nothing can be cached at the edge; with four PHP-FPM workers that caps the site near 30 requests a second. Make anonymous GETs, sitemaps, feeds and share images cookie-free, then let Cloudflare cache HTML.
- **Cache rows up to 1 MB** (whole Eloquent models: `home.data`, `catalog.filter_options`), read on every page, and the database cache store never prunes old generations. Cache plain arrays; prune expired rows hourly.
- **Foreign keys without indexes** (PostgreSQL does not add them): `policy_instruments.jurisdiction_id`, `obligations.policy_instrument_id`, `change_events.*_id`, `deadlines.policy_instrument_id` and others. One index cut the `/jurisdictions` query from 25 ms to 8 ms.
- N+1 queries: `/coverage` 160 queries, `/llms-full.txt` 239, country hubs, the quarterly report, obligation templates.
- Mail is sent inside web requests and the digest loop (`QUEUE_CONNECTION=sync`): queue mailables and run a worker.
- Sitemaps at 10x data: `lazyById()` with selected columns, and cache the XML per section.

## Platform and dependencies

- **PHP 8.2 reaches end of life on 31 December 2026**, and the 8.2 pin (composer platform, two CI workflows) hides Laravel 13, PHPUnit 12 and symfony/yaml 8. Production already runs 8.3. Move the floor to 8.3, test on 8.3 and 8.4, then upgrade Laravel 13 this quarter (Laravel 12 is security-fixes only until about February 2027).
- **CI's `npm audit --omit=dev` checks only amCharts**: every other shipped package (react-dom, axios, Inertia) is a devDependency. Audit all, allowing the build-only braces advisory.
- The committed `public/build` is stale against the lock file and built on Node 22 while CI and the image use Node 26.
- CodeQL action v3 is deprecated in December 2026: move to v4, pinned by SHA.
- Unused: flowbite, flowbite-react (also the only thing pinning Tailwind 3 and the braces advisory), axios, laravel/sail, laravel/breeze.
- Local `vendor/` and `node_modules` are behind the lock files (production builds from the locks).

## Accessibility and UX

- Mobile filter sheet on `/policies` and `/obligations`: every filter ID is duplicated, so labels point at the hidden copy; no focus trap, and focus is not returned on close.
- `/saved` shows its toolbar on an empty list (`flex` overrides `hidden`).
- Sign-in and register pages in dark mode: low-contrast labels (1.87:1) and an invisible wordmark; no h1 at phone width.
- Search misses incidents, risks, framework and transition pages ("facial recognition" returns nothing; the incident browser has 67), and its empty state has no next step.
- "Save" (browser only) and "Follow" (account) sit side by side with no bridge; the header never shows "Following".
- The subscribe form is about 8,300px tall on a phone; the jurisdiction picker used elsewhere has search.
- "Enforcement tracker" in the main menu opens an empty, noindexed page.

## Features worth building (from data the site already has)

1. Show "Recorded incidents in this risk area" on policy and country pages (1,366 incidents already link to the EU AI Act), with a `policy=` filter on the incident browser.
2. One-click "follow all saved records" on `/saved`, and "Following" in the header.
3. Tell contributors the outcome: an acknowledgement on submission and a decision email (the address and the public note are stored).
4. Admin user page: plan status and watch count, and complimentary Pro for reviewers.
5. Show deadline history ("originally X, now Y") on policy pages and the calendar; the data model exists.
6. Widen search to incidents, risks, frameworks and transition measures, reusing the incident browser's query.

## Checked and fine

Admin authorisation and capabilities; IDOR checks on user-owned records; 2FA replay protection and secret storage; login throttling and session handling; billing webhook signatures and idempotency; cron endpoint tokens; CSRF exemptions; mass assignment; SQL bindings and LIKE escaping; Blade and JSON-LD escaping and the nonce CSP; uploads and signed downloads; outbound URLs from config; security headers and cookies; CSV formula injection; the import transaction and cascades; slug uniqueness; JobRun locking; unsubscribes; ContentCache generations; division-by-zero guards; incident pagination; share-image caching.
