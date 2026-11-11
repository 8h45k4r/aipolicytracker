# Consultations

**What it is.** Open public consultations and comment periods on AI, collected from official sources and listed on `/calendar` under "Open consultations and comment deadlines", by the date comments close.

**Why it exists.** Compliance, policy and research readers need to know when they can still comment, not only when a law applies. The site's own dated deadlines (`deadlines`) cover the second. This module covers the first, without breaking the rule that facts come from official sources.

**Sources.** Today, one source: the US Federal Register API (`www.federalregister.gov/api/v1`), published by the Office of the Federal Register. It is public and needs no key. A source is added only where the publisher offers its own feed; nothing is scraped from third-party aggregators.
- The EU "Have your say" portal offers no documented public API.
- regulations.gov needs a key.
- Both are left for later, and adding either is a decision for the owner.

## How entries move

| Status | Set by | Shown on `/calendar` |
|---|---|---|
| `collected` | `consultations:sync` | Yes, under "Collected automatically, not yet reviewed", with a note to open the official page before relying on it |
| `confirmed` | A reviewer in the admin, after opening the official page | Yes, under "Reviewed" |
| `hidden` | A reviewer: not about AI governance | Never |

The collector never sets `confirmed`. If the source later moves the close date of a confirmed entry, the entry goes back to `collected` and its reviewer is cleared, so a confirmed date is always one a person has seen. Closed entries (close date before today) drop off the page. Consultations are not in the `.ics` feed, which carries recorded deadlines only.

**Relevance.** The API's full-text search matches a term anywhere in a long rule: on 11 November 2026, 22 open comment periods matched "artificial intelligence" and 2 were about AI. An entry is kept only when its title or abstract matches `config('consultations.relevance')`.

## Schema: `consultations`

| Field | Type | Null | Default | Notes |
|-------|------|------|---------|-------|
| id | integer | no |  |  |
| source | varchar(40) | no |  | `federal_register` |
| external_id | varchar(120) | no |  | The source's id (Federal Register document number); unique with `source` |
| jurisdiction_slug | varchar(80) | yes |  | `us` for the Federal Register |
| title | varchar(500) | no |  |  |
| agency | varchar(300) | yes |  | Agencies, `; `-separated |
| document_type | varchar(60) | yes |  | e.g. Proposed Rule, Notice |
| abstract | text | yes |  | Up to 1,200 characters, as published |
| url | varchar(500) | no |  | The official page |
| comment_url | varchar(500) | yes |  | Where comments are submitted, when the source gives it |
| published_on | date | yes |  |  |
| comments_close_on | date | yes |  | Entries without a close date are not stored |
| status | varchar(20) | no | `collected` | `collected`, `confirmed`, `hidden` |
| reviewed_by | integer | yes |  | FK → `users.id`, null on delete |
| reviewed_at | datetime | yes |  |  |
| fetched_at | datetime | yes |  | Last time the source returned it |
| created_at, updated_at | datetime | yes |  |  |

Unique (`source`, `external_id`); index (`status`, `comments_close_on`).

## Jobs, admin, configuration

- `php artisan consultations:sync`: job `consultations_sync`, daily at 04:30 UTC while `CONSULTATIONS_SYNC` is not false. A source that fails fails the run and leaves stored entries as they were.
- Admin: **Records → Consultations** (`/backend/admin/consultations`, `records.verify`). It lists open entries by status and offers Confirm, Hide and Back to review.
- `config/consultations.php`: the source URL, search terms, page limit, timeout and the relevance pattern.
- Analytics events (consent permitting): `consultation_click`, `consultation_comment`.

## Interlinks

- Inbound: `/calendar`.
- Outbound: each entry links its official page and, where given, the official comment page, both nofollow.

## Tests

`tests/Feature/ConsultationsTest.php` covers:
- only AI entries are collected, once each, with the request the API expects;
- the calendar keeps reviewed and unreviewed apart and never shows hidden or closed entries;
- only a reviewer confirms, and a moved date sends an entry back;
- a failing source keeps what was stored;
- the job is catalogued and scheduled.
