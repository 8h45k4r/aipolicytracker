# Admin review, 5 October 2026

A read-only review of the admin (routes, controllers, middleware, views) came before this round of changes. Each finding below was checked against the code and, where it could be, reproduced. Each one says what was done.

## Defects

| # | Finding | Disposition |
|---|---|---|
| 1 | The new CSV exports paged by id (`lazyById`) under a different sort; a 1,200-row export sorted by date returned 999 rows, 500 distinct. | Fixed: `CsvStream` uses `lazy()`, which keeps the list's order. Test exports 1,100 rows sorted by date. |
| 2 | The subscriber export ignored the on-screen state and dumped every address, unsubscribed included. | Fixed: one query serves the list and the export; the export link carries the filters. |
| 3 | `Cache::flush()` after every job run, publish, verify and import erased the whole database cache store: rate-limiter counters (sign-in, two-factor, invitations), the scheduler heartbeat and the AIID sync marker. | Fixed: content caches go through `App\Support\ContentCache`, whose flush moves to a new key generation and touches nothing else. |
| 4 | Audit gaps: bulk actions did not record which records they changed; CSV exports of personal data were not logged; refused forms were logged as 302. | Fixed: bulk rows carry the ids and the action, exports are logged with their filters, and refused requests are logged as 422 (validation) or 400 (error message). |
| 5 | Deleting a user returned to that user's (now missing) page: a 404. | Fixed: returns to the users list. |
| 6 | A user's page loaded every user-management audit row and filtered in PHP. | Fixed: filtered and limited in SQL. |
| 7 | The stale threshold was hard-coded to 180 days on the dashboard and in Needs attention; the setting was honoured in one place only; the item was gated on the wrong capability and led nowhere specific. | Fixed: one threshold (the setting), gated on `records.verify`, linking to a new "never verified or stale" filter in the review queue. |
| 8 | Links shown to roles that get 403 on click (dashboard subscriber, download and submission links; Manage library; Download activity; an Analyst told to resend confirmations). | Fixed: each is gated by the capability its page needs. |
| 9 | Password confirmation on save threw the typed form away (settings API keys, the permission matrix). | Fixed for settings and permissions: confirmed when the page opens, so saving never bounces. |
| 10 | Publishing re-dated already-published records and published every obligation of a policy, including ones left unpublished on purpose. | Fixed: the first published date is kept; publishing a policy brings back only obligations that went down with it. |
| 11 | A run skipped because the job was already running was stored as a failure and raised "job failed". | Fixed: stored with its own code (75), shown as "skipped", never the latest run. |
| 11 | `store()` accepted a published tool with no files. | Fixed: refused, as `update()` already did. |
| 11 | One query per row for tool titles on the downloads page. | Fixed: titles preloaded. |
| 11 | The users export did not neutralise a formula-like email; owner matching was case-sensitive and ignored verification. | Fixed: whole row through `Csv::row`; a `User::owners()` scope matches as `isOwner()` does. |
| 11 | An empty filtered tool list said "No tools yet". | Fixed. |
| 11 | No tie-breaker on sorted lists; `\%` escaping without an `ESCAPE` clause, which SQLite ignores. | Fixed in the shared filter helper and in the public search scopes (a search for `_` matched everything on SQLite). |

## Functional gaps closed

Every admin list now has the same filter bar: search, its own filters, a date range where dates matter, sortable columns, a count, and **Export CSV** of exactly what is filtered. Figures open the rows behind them:

- **Dashboard**: record and audience figures with 30-day trend lines, each linking to its filtered list.
- **Audit log**: person, action, result and date filters; click a person or an action to narrow to it; export.
- **Submissions**: status tabs with counts, type, search, dates, sort and export.
- **Subscribers**: state tabs, email search, topic and source filters, sortable columns, a filtered export.
- **Guides and downloads**: tabs for overview, template requests, tool downloads and users. Each tab has its own filters and export, and the overview rows drill down.
- **Jobs**: run history filtered by job, result and trigger, paged, with output inline and export. A job name opens its history.
- **Tool library**: search, sortable columns, and a download count that links to who downloaded it.
- **Billing**: subscription search and export; a subscriber's email links to their account.
- **Review queue**: a stale filter, and export of the filtered records.
- **User page**: a link to all of that person's actions in the audit log.

The admin reads in Poppins (`resources/css/admin.css`, scoped to `html[data-admin]`); the public site keeps Space Grotesk.

## Reported during the work

- **AI Incident Database sync, 401 "requires that you log in"**: since early October 2026 AIID's GraphQL API refuses anonymous incident queries. The client now raises a specific `AiidLoginRequired`, and the sync records it, prints what to do and exits successfully, so the scheduled job does not go red every day. The dashboard (Needs attention, for `external.sync`) and the External data page say the live sync is paused. Optional `AIID_API_TOKEN` (sent as a bearer token) and `AIID_API_COOKIE` (an AIID account's session cookie) are sent when set. Whether AIID accepts either has not been checked from here, because incidentdatabase.ai is not reachable from the build environment. The weekly backup path (`external:sync-aiid`, `external:sync-aiid-reports`) is unaffected and keeps the incident data current.
- **Existing user data**: nothing in this round migrates, rewrites or deletes accounts, subscribers, downloads or requests. The new pages read, filter and export.

## Not changed

- The bulk user delete still confirms the password on submit. The users list is opened often, and confirming the password on every visit to it would be worse than re-ticking a selection once.
