# Policies

The core entity: one row per AI policy, strategy, or regulation instrument.

## Schema: `ai_policy_trackers`

| Field | Type | Null | Default | Notes |
|-------|------|------|---------|-------|
| id | uuid | no | | Primary key |
| country_id | uuid | no | | FK → `countries.id`, cascade |
| status_id | uuid | no | | FK → `statuses.id`, cascade |
| ai_policy_name | varchar(255) | yes | | Required by the form request |
| governing_body | varchar(255) | yes | | |
| announcement_year | varchar(255) | yes | | Stored as a date string (`Y-m-d`) |
| whitepaper_document_link | varchar(255) | yes | | Official source URL |
| technology_partners | varchar(255) | yes | | Free text |
| governance_structure | varchar(255) | yes | | Free text |
| main_motivation | varchar(255) | yes | | Free text |
| description | text | yes | | Rich text (CKEditor HTML) |
| gov_ai_index | varchar(255) | no | 'policy' | `policy` or `strategy` (`GovAiIndexHelper`) |
| deleted_at | timestamp | yes | | Soft delete |
| created_at / updated_at | timestamp | no | CURRENT_TIMESTAMP | |

## Schema: `statuses`

| Field | Type | Null | Default | Notes |
|-------|------|------|---------|-------|
| id | uuid | no | | Primary key |
| name | varchar(255) | no | | Seeded: research, whitepaper, pilot, development, launched, cancelled |
| deleted_at | timestamp | yes | | |
| created_at / updated_at | timestamp | yes | | |

## Schema: `a_i_policy_activity_logs`

| Field | Type | Null | Default | Notes |
|-------|------|------|---------|-------|
| id | uuid | no | | Primary key |
| user_id | bigint | yes | | Actor; not a DB-level FK |
| ai_policy_tracker_id | uuid | no | | Logical FK → `ai_policy_trackers.id` (no DB constraint) |
| activity_name | text | no | | `added data`, `status update`, `updated data`, `delete data` |
| description | text | yes | | Human-readable message (HTML) |
| created_at / updated_at | timestamp | yes | | |

## Interlinks

- **Outbound:** `country_id` → [jurisdictions](jurisdictions.md); `status_id` → `statuses` (this doc).
- **Inbound:** `news.policy_tracker_id` ([updates.md](updates.md)); `book_marks.ai_policy_tracker_id` ([bookmarks.md](bookmarks.md)); `a_i_policy_activity_logs.ai_policy_tracker_id` (this doc).

## Routes

Admin: `backend.ai_policy_tracker.index|store|edit|update|delete|search`. Public: `frontend.dashboard`, `frontend.dashboard.filtered`, `frontend.single_ai_policy_tracker.index`, `frontend.time_line.index`.

## Debt

No source/provenance or version model; status vocabulary is product-lifecycle rather than legal status; free-text columns should be structured. See `docs/reference/technical-debt.md` #3.
