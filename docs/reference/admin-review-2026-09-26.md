# Backend review, 26 September 2026

A read of every admin controller, route, view and test (`app/Http/Controllers/Backend`,
`routes/backend/web.php`, `resources/views/backend`, the `Admin*`, `BulkVerification`
and `ReviewerRoster` tests), prompted by a production 500 and by the request for
"select all, approve all, verify all" on every backend table. Everything marked
*fixed* is on `main`; the rest is listed so it is a decision, not an omission.

## Defects found

| # | Where | What | Severity | State |
|---|-------|------|----------|-------|
| 1 | `ReviewerRoster::verifiedRecords` | Every `/reviewers/{slug}` page asked the `obligations` table for `reviewed_by`, a column it does not have. PostgreSQL refuses the query; the page answered 500. SQLite reads an unknown double-quoted identifier as a string, so the same query passed every local test and the crawl. This was the production 500. | High | Fixed. The list walks the attributable kinds only; a test listens to the queries the page makes and fails if `obligations` is asked. A production-like crawl on PostgreSQL (migrate, import, `templates:build`, `route:cache`, `APP_ENV=production`) now returns 0 violations on the static, jurisdiction, control and template sections. |
| 2 | `errors/500.blade.php` | The reference the page asked readers to quote was a random number generated on the page and written nowhere. A quoted reference could not be found. | High (operability) | Fixed. `AssignRequestId` runs first in the stack: accepts a well-formed id from the proxy or mints one, shares it with every log line, sets `X-Request-Id` on the response; the exception handler sets the same header on the error response; nginx passes its own `$request_id`. |
| 3 | `subscribers`, `users`, `tools/form` views | `onsubmit="return confirm(...)"` on delete, suspend, reset-factor and remove-file forms. The Content-Security-Policy sends `script-src 'self' 'nonce-…'` with no `'unsafe-inline'`, so browsers never ran the handler: every one of these went through on the first click, with no confirmation. | High | Fixed. Forms carry `data-confirm`; the page script asks, with `{n}` filled from the selection. A test asserts no `onsubmit=` remains on the users page. |
| 4 | `PolicyExportVerificationsCommand` | Only policies and jurisdictions were written back to `data/`. A control verified in the queue printed "No file for control …" and was never exported, so the decision lived only in the database. | Medium | Fixed. `ReviewableTypes::file()` resolves every kind; `YamlRecordPatch` writes the five review fields line by line, including one item inside a yearly `changes/*.yaml`. Covered for all five kinds. |
| 5 | `RecordVerification::applyAll` | The `default` arm of the match treated any unknown `record_type` as a jurisdiction. | Low | Fixed. Resolution goes through the registry; an unknown kind is skipped. |
| 6 | `AdminController::settings` | `env()` at request time. With the configuration cached (every production release) `.env` is never read, so the "environment: …" hints were empty on the host and implied nothing was set. | Medium (misleading) | Fixed. The page says the hints cannot be shown while the configuration is cached; the effective values are what the site uses. |
| 7 | `UserController::index` | `LIKE` search is case-sensitive on PostgreSQL, so "bhaskar" did not find Bhaskar on the host. | Medium | Fixed. `LOWER()` on both sides; the review queue search does the same. |
| 8 | `public.js` bulk block | The one "select all shown" checkbox ticked every `[data-bulk-item]` on the page, whichever table it belonged to. Harmless while only policies had checkboxes; wrong the moment a second table did. | Low | Fixed. Selection is keyed on the form id each row points at. |
| 9 | Review queue routes | `verify-many` accepted `policy|jurisdiction|control` while the view offered the bulk form for policies only; jurisdictions had no verify control at all, controls had a per-row form and no selection. | UX | Fixed, see below. |
| 10 | Backend tables | Several tables had no `<caption>` and header cells without `scope="col"` (dashboard, jobs history, downloads, billing, subscribers, tools). | A11y | Fixed. |

Not changed, deliberately:

- **`Cache::flush()` after every verify and publish.** It clears the whole store, rate-limit buckets included. It is what keeps the public pages honest immediately after a decision, and the store is small. A tagged flush would be the right shape if the cache ever moves to Redis with sessions in it.
- **Users page has no bulk actions.** Every write there goes through `guardFor()` (never yourself, never an owner) and each is destructive in its own way. One row at a time, with a confirmation that now actually runs, is the intended friction.
- **Owner-only pages (settings, billing).** Forms, not tables; nothing to select.
- **Legacy admin CRUD** behind `LEGACY_MAP_ENABLED` was not reviewed; it is off and scheduled for removal.

## The review queue now

`/backend/review` is one queue per kind: policy instruments, jurisdictions, controls,
change log entries, transition measures. Each tab shows its total and how many are not
yet verified. Under it: search (title or slug, case-insensitive), review-status and
published filters with counts, and a sticky action bar.

A row is a checkbox and a publish switch. The header checkbox ticks the page; shift-click
ticks a range; the bar shows "N selected" and its buttons wait for a selection. Actions
on the selection:

- **Save review**: status, confidence, optional internal note, and one attestation whose
  wording is the kind's own ("I opened the official source of every selected record",
  "I checked the clause references…"). Every record still gets its own dated, named
  `record_verifications` row, which survives re-import and exports to `data/`. A name
  not on the published roster is refused, as before.
- **Publish / Unpublish selected**: an instrument's obligations follow it. Unpublish asks
  first and says the records leave the site, API and sitemaps at once.
- **Apply to all N matching the filter, not only this page**: the Gmail rule. The server
  recomputes the filtered set from the same filter fields, capped at 1,000.

Submissions, on the queue and on Submissions and feedback, take one decision (with the
same internal and public note) for every ticked card. Subscribers can be re-sent a
confirmation (only the unconfirmed among the selection are sent) or removed together.
Tools take one status together; publishing still skips a tool with no active file and
names it.

Capabilities are unchanged: `records.verify`, `records.publish`, `submissions.decide`,
`subscribers.manage`, `tools.manage` gate the bulk routes exactly as the single ones,
and the bar hides the controls a role does not hold.

## What this cannot catch

SQLite's double-quoted-identifier quirk means a query for a column that does not exist
passes on SQLite and fails on PostgreSQL. The CI matrix runs the suite on both; a red
PostgreSQL job is the only local signal for this class of bug, so it must stay
blocking. `main` should be checked for a red PostgreSQL run before the next deploy.

## Tests

`RequestIdTest` (3), `ReviewerRosterTest` (+1 query listener), `ReviewQueueTest` (8:
every kind renders and verifies in bulk, filtered scope, attestation and roster gates,
publish-many with duties, decide-many, capability on bulk routes, export for all five
kinds), `YamlRecordPatchTest` (4), `AdminBulkActionsTest` (5: resend and delete many,
tool status many with the no-empty-publish rule, case-insensitive search, no inline
handlers). Existing `BulkVerificationTest`, `PublicSiteTest`, `Admin*` suites pass
unchanged.
