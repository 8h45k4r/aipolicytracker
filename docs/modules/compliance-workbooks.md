# Compliance workbooks

**What it is.** A saved applicability profile is treated as one AI system that the account declares. The account names it and may add what it does, who owns it and its lifecycle stage. Nothing is discovered: there is no endpoint, mailbox or identity-provider scanning. For each such system the site keeps a compliance workbook, one XLSX file with these sheets:

| Sheet | What is in it |
|---|---|
| Gap analysis | Every duty the applicability screen finds, with its permanent id, instrument, source reference, application date, what it requires, the recorded controls, the expected evidence and the record's review status. Columns for the account to fill: status, gap, owner, target date, evidence link. |
| Deadlines | Application dates and recorded deadlines, in date order, marked as in force, upcoming or passed. |
| Controls | Each recorded control and the duties it satisfies or supports. |
| Evidence | What a reviewer expects to see for each duty, with columns for whether it is held and where. |
| Sources | The official source behind each instrument. |
| Version history | What changed in the records since the first version. |
| System | The system as the account described it, and the answers the screen used. |

**Why it exists.** The free applicability check already offers a register download (`/tools/applicability-check/register.{xlsx,csv,json,pdf}`). That file is stateless and stays free. The workbook is the paid service around the same data. It is kept per system, versioned and rebuilt when the records behind it change, and the owner is told what changed. That matches the published rule that money pays for the service around the data, never for the data. The strategy behind it is in `docs/reference/growth-audit-2026-11-11.md`.

**What it never does.** It never decides that a duty applies or is met: the status columns are the account's own. A record pending review says so on every row. The rows come from `ApplicabilityScreener::screen()`, the same screen the tool page and the daily alert use, so the workbook never claims more than the public records.

## Schema: `profile_workbook_versions`

| Field | Type | Null | Default | Notes |
|-------|------|------|---------|-------|
| id | integer | no |  |  |
| applicability_profile_id | integer | no |  | FK → `applicability_profiles.id`, cascade on delete |
| version | integer | no |  | 1, 2, 3… per profile; unique with the profile |
| content_hash | char(64) | no |  | SHA-256 of the snapshot; a new version is stored only when it differs |
| snapshot | text | no |  | JSON: the screened rows, keyed by obligation slug |
| record_changes | text | yes |  | JSON `{added, removed, changed}` against the previous version; null on version 1. Not named `changes`, which is an internal Eloquent property. |
| dataset_version | varchar(40) | yes |  | `DatasetVersion::current()` when built |
| notified_at | datetime | yes |  | When the owner was emailed about this version |
| created_at | datetime | yes |  | The build date shown in the file and on the page |
| updated_at | datetime | yes |  |  |

Unique (`applicability_profile_id`, `version`). The three columns added to `applicability_profiles` (`system_description`, `system_owner`, `lifecycle`) are documented with that table in [alerts.md](alerts.md).

## How a version is made

`App\Services\Workbooks\ComplianceWorkbook`:

- `snapshot()` screens the profile's answers and keeps, per duty, everything a reader of the file sees. That covers title, instrument, status, binding force, reference, the effective application date and its basis, summary, practical action, review state and date, source, controls, evidence and deadlines. It leaves out volatile fields such as `updated_at` or `last_checked_at`, so a re-import of unchanged data makes no new version (tested).
- `refresh()` stores a new version, under a row lock, when the hash differs from the latest one.
- `diff()` names the added, removed and changed duties, and the fields that changed.
- `xlsx()` writes the file through `App\Services\Templates\Workbook`, the writer the templates use.

`php artisan profiles:workbooks` (job `profile_workbooks`, daily 06:00 UTC, after the import and the template build and before the daily alert) refreshes every profile whose account has `profiles.workbook`. It emails the owner (`WorkbookUpdatedMail`) when a version after the first has changes. It does not email when the account has turned alert emails off or has not verified its address; the version is still stored. A failure is logged without the address and the run is marked failed.

## Entitlement

`profiles.workbook` in `config/billing.php`: false on the free tier, true on both Pro plans. While billing is off, every signed-in account has it, as for every other capability (`Entitlements::value()`). The system page and its details form are open to the profile's owner. The workbook downloads need the entitlement (`subscribed:profiles.workbook`), and without it the page offers the free register download instead.

## Surfaces

| Surface | Route | Notes |
|---|---|---|
| The system's page | `GET /profiles/{profile}` | Owner only (another account gets 404); noindex; screen summary, workbook versions with what changed, details form |
| Details | `PATCH /profiles/{profile}` | name, description, owner, lifecycle |
| Latest workbook | `GET /profiles/{profile}/workbook.xlsx` | builds version 1 on first use |
| A past version | `GET /profiles/{profile}/workbook-v{n}.xlsx` | 404 for a number that does not exist |

`robots.txt` already disallows `/profile`, which also covers `/profiles`. Analytics events (consent permitting): `workbook_open`, `workbook_download`, `workbook_version_download`, `workbook_upsell`.

## Interlinks

- Inbound: the "Following" page lists each saved system with a link to its page. The daily alert names profiles (alerts module).
- Outbound: each workbook row links its obligation record, each control row its control, each source row the instrument record and the official source.

## Tests

`tests/Feature/ProfileWorkbookTest.php`:
- the file holds every screened duty, with sources and history;
- a version is stored only on a real change, and a re-import makes none;
- the job emails once per change and respects "email off";
- only the owner can see, change or download a system;
- the details reach the file;
- with selling on, the workbook is Pro and the free register stays free;
- the job is catalogued and scheduled.

## Open debt

- No team sharing yet: a workbook belongs to one account (the Team plan lists "Team accounts" as coming).
- The workbook does not keep the account's own status and gap entries between versions; each version is a fresh file. Carrying entries forward needs either stored answers or an upload, which is a design decision left open.
