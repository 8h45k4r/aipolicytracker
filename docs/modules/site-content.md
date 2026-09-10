# Site content (CMS)

Admin-managed header logo and contributing-organisation logos shown on the public site.

## Schema: `nav_bars`

| Field | Type | Null | Notes |
|-------|------|------|-------|
| id | uuid | no | |
| user_id | bigint | no | FK → `users.id`, cascade; uploader |
| name | varchar(255) | no | Stored file name |
| file_path | varchar(255) | no | Path on the `public` disk |
| deleted_at | timestamp | yes | |
| created_at / updated_at | timestamp | yes | |

## Schema: `contributing_orgs`

| Field | Type | Null | Notes |
|-------|------|------|-------|
| id | uuid | no | |
| user_id | bigint | no | FK → `users.id`, cascade; uploader |
| name | varchar(255) | no | Stored file name |
| file_path | varchar(255) | no | Path on the `public` disk |
| url | varchar(255) | yes | Organisation website |
| deleted_at | timestamp | yes | |
| created_at / updated_at | timestamp | yes | |

## Interlinks

- **Outbound:** `user_id` → [accounts](accounts.md).
- **Inbound:** none. The first `nav_bars` row is shared to every page as the `logo` Inertia prop.

## Routes

`backend.header_menu.index|store|showContributingOrgIndex|contributingOrgDelete`.
