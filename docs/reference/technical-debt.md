# Technical debt register

Every accepted gap has an owner. Remove entries only when the fix is merged.

| # | Debt | Gate | Owner | Accepted | Exit criterion |
|---|------|------|-------|----------|----------------|
| 1 | UI primitives (`PageHeader`, `DataTable`, `FormDialog`, `ConfirmDialog`, skeleton/empty/error states) do not exist; pages use ad-hoc `Model`, `Table`, and inline markup. | UI/UX | @8h45k4r | 2026-09-11 | Primitives created in `resources/js/Components/` and every admin page migrated. |
| 2 | `AiPolicyTrackerSeeder` ships fictional policies with invented URLs and is labelled illustrative only in code comments; the production startup script does not run it, but the Azure database was seeded from it during the first deployment. | Engineering | @8h45k4r | 2026-09-11 | Sample rows deleted from production and replaced with the eight sourced jurisdictions. |
| 3 | No source/provenance model: a policy has a single `whitepaper_document_link` and no verification date, verifier, or confidence. | Compliance | @8h45k4r | 2026-09-11 | `sources` table and verification fields added per `docs/modules/README.md` roadmap. |
| 4 | Row-level security is not available on the current stack (Laravel/Eloquent over PostgreSQL without RLS policies); scoping relies on `User::scopeNonAdmin`, `BookMark` user filters, and the `isAdmin` middleware. | Engineering | @8h45k4r | 2026-09-11 | Either Postgres RLS policies added for user-scoped tables or a documented decision that Eloquent scopes are sufficient. |
| 5 | `npm run typecheck` is a no-op because the front end is plain JavaScript. | Engineering | @8h45k4r | 2026-09-11 | TypeScript adopted or JSDoc `checkJs` enabled with a passing baseline. |
| 6 | Laravel Pint style check is advisory in CI (`continue-on-error`). | Engineering | @8h45k4r | 2026-09-11 | Run `composer lint:fix` once, commit, and make the step blocking. |
| 7 | Null values render as empty strings or `0` in several tables (`Backend/Users/Index.jsx`, dashboard counters). | UI/UX | @8h45k4r | 2026-09-11 | Shared `formatValue` helper renders `—` for null and is used everywhere. |
| 8 | Public single-policy page requires a verified login; anonymous users hit a dead-end "denied" page. | UI/UX | @8h45k4r | 2026-09-11 | Public content public; login only for bookmarks and alerts. |
| 9 | CKEditor HTML is stored and rendered raw; DOMPurify is a dependency but not applied on every render path. | Compliance | @8h45k4r | 2026-09-11 | All rich-text renders pass through DOMPurify or content moves to Markdown. |
| 10 | Mail transport is `log` in production; verification e-mails are not delivered. | Engineering | @8h45k4r | 2026-09-11 | SMTP configured in App Service settings. |
