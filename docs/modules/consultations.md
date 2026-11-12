# Consultations

**What it is.** Two lists on `/calendar`, both collected from official sources:
- **Open consultations and comment deadlines:** comment periods on AI, by the date comments close.
- **Upcoming public meetings and hearings:** meetings, hearings and workshops on AI announced in official notices, by the date they are held.

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

The collector never sets `confirmed`. If the source later moves the close date (or meeting date) of a confirmed entry, the entry goes back to `collected` and its reviewer is cleared, so a confirmed date is always one a person has seen. Closed entries (close date before today) drop off the page. Consultations are not in the `.ics` feed, which carries recorded deadlines only.

**Meetings.** The Federal Register gives no structured meeting date, only the notice's own words (its `dates` text, for example "Monday, November 9, 2026; 2:00 p.m."). A notice is kept when all of these hold:
- its title names a meeting, hearing, workshop, listening session, roundtable, webinar, forum or symposium;
- its title or abstract is about AI;
- its dates text contains a full date ("Month D, YYYY") that is today or later.

The first such date is stored as `event_on`, and the text is stored verbatim as `dates_text` and shown beside it. A notice without a full date is skipped rather than guessed. The meeting pass reads notices published in the last 120 days. On 11 November 2026 it found four AI meeting notices, all already past, so none were listed.

**Relevance.** The API's full-text search matches a term anywhere in a long rule: on 11 November 2026, 22 open comment periods matched "artificial intelligence" and 2 were about AI. An entry is kept only when its title or abstract matches `config('consultations.relevance')`.

## Schema: `consultations`

| Field | Type | Null | Default | Notes |
|-------|------|------|---------|-------|
| id | integer | no |  |  |
| source | varchar(40) | no |  | `federal_register` |
| kind | varchar(20) | no | `comment_period` | `comment_period` or `meeting` |
| external_id | varchar(120) | no |  | The source's id (Federal Register document number); unique with `source` |
| jurisdiction_slug | varchar(80) | yes |  | `us` for the Federal Register |
| title | varchar(500) | no |  |  |
| agency | varchar(300) | yes |  | Agencies, `; `-separated |
| document_type | varchar(60) | yes |  | e.g. Proposed Rule, Notice |
| abstract | text | yes |  | Up to 1,200 characters, as published |
| url | varchar(500) | no |  | The official page |
| comment_url | varchar(500) | yes |  | Where comments are submitted, when the source gives it |
| published_on | date | yes |  |  |
| comments_close_on | date | yes |  | For a comment period: when comments close (comment periods without one are not stored) |
| event_on | date | yes |  | For a meeting: the first full date in `dates_text` |
| dates_text | varchar(300) | yes |  | The source's own words for when the meeting happens, shown verbatim |
| status | varchar(20) | no | `collected` | `collected`, `confirmed`, `hidden` |
| reviewed_by | integer | yes |  | FK → `users.id`, null on delete |
| reviewed_at | datetime | yes |  |  |
| fetched_at | datetime | yes |  | Last time the source returned it |
| created_at, updated_at | datetime | yes |  |  |

Unique (`source`, `external_id`, `kind`); indexes (`status`, `comments_close_on`) and (`status`, `kind`, `event_on`).

## Jobs, admin, configuration

- `php artisan consultations:sync`: job `consultations_sync`, daily at 04:30 UTC while `CONSULTATIONS_SYNC` is not false. A source that fails fails the run and leaves stored entries as they were.
- Admin: **Records → Consultations** (`/backend/admin/consultations`, `records.verify`). It lists open entries by status and offers Confirm, Hide and Back to review.
- `config/consultations.php`: the source URL, search terms, page limit, timeout and the relevance pattern.
- Analytics events (consent permitting): `consultation_click`, `consultation_comment`, `meeting_click`.

## Interlinks

- Inbound: `/calendar`.
- Outbound: each entry links its official page and, where given, the official comment page, both nofollow.

## Tests

`tests/Feature/ConsultationsTest.php` covers:
- only AI entries are collected, once each, with the request the API expects;
- the calendar keeps reviewed and unreviewed apart and never shows hidden or closed entries;
- only a reviewer confirms, and a moved date sends an entry back;
- a failing source keeps what was stored;
- the job is catalogued and scheduled;
- meetings are listed by their stated date, and a notice with no stated date, about something else, or already past is not stored.
