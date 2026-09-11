# Bookmarks and notifications

Registered users follow policies; updates to a followed policy send a mail and a database notification.

## Schema: `book_marks`

| Field | Type | Null | Default | Notes |
|-------|------|------|---------|-------|
| id | uuid | no | | |
| user_id | bigint | no | | FK → `users.id`, cascade |
| ai_policy_tracker_id | uuid | no | | FK → `ai_policy_trackers.id`, cascade |
| deleted_at | timestamp | yes | | |
| created_at / updated_at | timestamp | no | CURRENT_TIMESTAMP | |

## Schema: `notifications` (Laravel standard)

| Field | Type | Null | Notes |
|-------|------|------|-------|
| id | uuid | no | |
| type | varchar(255) | no | Notification class |
| notifiable_type / notifiable_id | varchar / bigint | no | Polymorphic → `users` |
| data | text | no | JSON payload (`tracker_name`, `tracker_id`, `message`) |
| read_at | timestamp | yes | |
| created_at / updated_at | timestamp | yes | |

## Interlinks

- **Outbound:** `user_id` → [accounts](accounts.md); `ai_policy_tracker_id` → [policies](policies.md).
- **Inbound:** none.

## Scoping

Bookmarks are always filtered by the authenticated user (`AiPolicyTracker::bookmark()`, `BookMark::authUserBookmarkCount()`); there is no database-level RLS (debt #4).

## Routes

`frontend.watch_list.index|add|filtered`, `notifications.markAsRead`. `SendAiPolicyTrackerNotificationJob` fans out `UpdateAiPolicyTrackerNotification` on policy update.
