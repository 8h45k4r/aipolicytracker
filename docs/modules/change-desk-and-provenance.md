# Change desk and record provenance

What makes a record something to rely on rather than a page to read: a permanent id and
citation, a visible history, a one-line standing, a routine that keeps it current with a
published speed target, and provenance that travels with the data.

## Permanent ids

Every policy, obligation, change and control carries `aipt_id` in `data/`:
`AIPT-POL-0001`, `AIPT-OBL-0001`, `AIPT-CHG-0001`, `AIPT-CTL-0001`.

- Assigned once, never changed and never reused. The validator refuses a missing id, a
  wrong prefix and an id used twice. `php artisan policy:next-id OBL` prints the next free one.
- `/id/{id}` redirects to the record wherever it lives now (JSON with `Accept: application/json`),
  so a citation, a GRC ticket or a calendar entry holding the id survives a slug rename. On a
  rename the importer moves the id from the row left under the old slug to the new one.
- The API (`id`, `permanent_url`), alert webhooks (`id`, `policy_id`), the Markdown context files
  and the MCP server (`resolve_record_id`) all carry it.

The citation on every record names the id, the record version, the reviewer and the date:

    AIPolicyTracker (2026). "EU AI Act" [AIPT-POL-0073, record version 3, verified 26 September 2026 by Bhaskar Bhatt]. https://aipolicytracker.org/id/AIPT-POL-0073 (accessed …). Data licensed CC BY 4.0.

## Record history and standing

The importer compares each record's tracked fields (`RevisionRecorder::FIELDS`) with the database
before updating it and writes a `record_revisions` row when any differ: field, from, to, when.
Prose fields are marked "revised" rather than quoted. History starts on 15 October 2026 (CHANGEDESK_HISTORY_SINCE); earlier
wordings are in git. Policy and obligation pages show the list.

`RecordStanding` gives each policy and obligation page one line, the strongest that applies:
no longer in force; a date moved in the last 180 days; a change logged in the last 30 days; the
record revised in the last 30 days; otherwise "no change recorded", with the last source check.

## Change desk

Admin → Content → Change desk (`records.verify`). Current instruments, most urgent first: due
by cadence (binding instruments every 7 days, others every 30, `config/changedesk.php`), binding
before non-binding, most watched first. Each row records a check: no change, change found and
logged, or source unreachable. "Deepen next" lists watched instruments with fewer than five duties.

`/freshness` publishes the speed target (3 days from an official text to its entry here) and the
measured result month by month, computed from `source_document_date` (or `occurred_on`) and
`first_published_at`, for changes first published since `changedesk.measured_since`.

## Reliance

Alert email links carry `src=alert`. When a signed-in account opens one, `account_weeks` marks
that week (no IP, no path, nothing for anonymous visitors). The admin dashboard shows accounts
that acted on an alert in each of the last four weeks: the north-star measure.

## Schema

`record_revisions`: id, record_type (policy, obligation, change, control), record_slug, aipt_id,
content_version, change_summary, fields (json: field, label, from, to), source, changed_at, timestamps.

`source_checks`: id, record_type, record_slug, outcome (no_change, change_logged,
source_unreachable), note, user_id (FK users, set null), checked_at, timestamps. Never touched by the import.

`account_weeks`: id, user_id (FK users, cascade), week_start, alert_clicks, timestamps; unique (user_id, week_start).

`aipt_id` (varchar 24, unique, nullable) on `policy_instruments`, `obligations`, `change_events`, `controls`.

## Interlinks

- Inbound: the policy importer (ids, revisions), alert emails (reliance), the admin (checks).
- Outbound: policy and obligation pages, `/freshness`, `/independence`, the API, webhooks, the MCP server.
