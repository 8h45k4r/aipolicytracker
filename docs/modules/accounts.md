# Accounts

Laravel Breeze authentication with e-mail verification, plus a profile extension table.

## Schema: `users`

| Field | Type | Null | Notes |
|-------|------|------|-------|
| id | bigint | no | Primary key |
| name | varchar(255) | no | |
| email | varchar(255) | no | Unique |
| email_verified_at | timestamp | yes | |
| password | varchar(255) | no | bcrypt hash |
| remember_token | varchar(100) | yes | |
| created_at / updated_at | timestamp | yes | |

## Schema: `user_infos`

| Field | Type | Null | Default | Notes |
|-------|------|------|---------|-------|
| id | uuid | no | | |
| user_id | bigint | yes | | Logical FK → `users.id` |
| phone_no | varchar(255) | no | | |
| organization_name | varchar(255) | yes | | |
| organization_email | varchar(255) | yes | | |
| ip_address | varchar(45) | yes | | Captured at registration |
| user_agent | text | yes | | Captured at registration |
| last_activity | integer | no | | Unix timestamp |
| status | boolean | yes | true | |
| terms_condition | boolean | yes | false | |
| deleted_at | timestamp | yes | | |
| created_at / updated_at | timestamp | yes | | |

The legacy plaintext `password` column was dropped by migration `2025_09_10_000000`.

`password_reset_tokens` and `sessions` are Laravel standard tables.

## Interlinks

- **Inbound (policy intelligence):** `reviewer_decisions.reviewer_user_id` → `users.id` ([policy-intelligence.md](policy-intelligence.md)); nullable, set null on user deletion.

- **Outbound:** none.
- **Inbound:** `user_infos.user_id`; `notifications.notifiable_id`; `reviewer_decisions.reviewer_user_id` and `app_settings.updated_by` ([policy-intelligence.md](policy-intelligence.md), [subscribers-and-settings.md](subscribers-and-settings.md)).

## Admin access

`ADMIN_EMAILS` → `User::isAdmin()` → `CheckAdmin` middleware. `User::scopeNonAdmin` excludes admins from counts.

## Routes

`routes/auth.php` (Breeze), `profile.*`.
