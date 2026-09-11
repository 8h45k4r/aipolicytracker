# Updates (news)

Dated news items, optionally tied to a policy, shown on the public news pages and the policy detail page.

## Schema: `news`

| Field | Type | Null | Default | Notes |
|-------|------|------|---------|-------|
| id | uuid | no | | Primary key |
| policy_tracker_id | uuid | yes | | Logical FK → `ai_policy_trackers.id` (no DB constraint) |
| status_id | uuid | yes | | Logical FK → `statuses.id` (category) |
| title | varchar(255) | no | | |
| description | text | yes | | Rich text |
| upload_date | date | no | | Publication date |
| deleted_at | timestamp | yes | | |
| created_at / updated_at | timestamp | yes | | |

## Schema: `thumbnails`

| Field | Type | Null | Default | Notes |
|-------|------|------|---------|-------|
| id | uuid | no | | |
| news_id | uuid | no | | FK → `news.id`, cascade |
| type | varchar(255) | no | | MIME type |
| name | varchar(255) | no | | Stored file name |
| path | varchar(255) | no | | Path on the `public` disk |
| deleted_at | timestamp | yes | | |
| created_at / updated_at | timestamp | no | CURRENT_TIMESTAMP | |

`news_future_images` has the same shape as `thumbnails` and is currently unused by the application.

## Interlinks

- **Outbound:** `policy_tracker_id` → [policies](policies.md); `status_id` → `statuses`.
- **Inbound:** `thumbnails.news_id`, `news_future_images.news_id` (this doc).

## Routes

Admin: `backend.news.*`. Public: `news.index`, `news.single`, `frontend.news.filtered`, `frontend.showAdvancedInfoPaginate`.
