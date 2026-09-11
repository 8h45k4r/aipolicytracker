# Guides and downloads

Free tools (templates, checklists, registers, a starter plan) listed on `/guides` next to the editorial guides. Anyone can read the guides and preview every field of a tool; downloading a file needs a free account, an explicit licence acceptance, and is served through a short-lived signed URL to the account that requested it.

## Source of truth

- Resource metadata: `config/resources.php` (`tools`, `guide_tags`, filter vocabularies, licence text). No admin CRUD yet (debt #19).
- Files: `resources/downloads/<slug>/<file>` (outside `public/`; never linked directly). Every file carries version, date and the informational-only notice.
- Editorial guides remain in `config/content.php`; `guide_tags` gives them the same framework/topic filters.

## Schema: `resource_downloads`

| Field | Type | Null | Default | Notes |
|-------|------|------|---------|-------|
| id | bigint | no | | |
| user_id | bigint | no | | FK → users.id (cascade) |
| resource_slug | varchar(120) | no | | Key in `config('resources.tools')` |
| file_name | varchar(160) | no | | Last file served (first format until a file is served) |
| version | varchar(16) | no | | Resource version at download time |
| terms_accepted_at | timestamp | no | | Licence acceptance for this download |
| ip_hash | varchar(64) | yes | | sha256 of the client IP (security metadata only) |
| user_agent | varchar(255) | yes | | |
| referrer | varchar(255) | yes | | |
| downloaded_at | timestamp | yes | | Set when a file is actually served |
| created_at / updated_at | timestamp | yes | | |

## Schema additions: `users`

| Field | Type | Null | Notes |
|-------|------|------|-------|
| terms_accepted_at | timestamp | yes | Set at registration and on first download |
| marketing_consent_at | timestamp | yes | Set only when the user ticks the (unticked) updates box |
| organization_name | varchar | yes | Optional at sign-up |
| signup_source | varchar(64) | yes | `free-tool` when sign-up started from a tool gate, else `site` |

## Interlinks

- **Outbound:** `resource_downloads.user_id` → [accounts](accounts.md); `resource_slug` → `config/resources.php`; tools link to `policy_instruments` (`related_policies`) and guides (`related_guides`).
- **Inbound:** `/guides` cards, guide pages ("related tools" via `related_guides`), admin Guides and downloads page and dashboard tiles.

## Routes

Public: `guides.index` (filters `q`, `type`, `framework`, `topic`, `access`; filtered pages are `noindex,follow`), `tools.show`, `tools.gate` (stores `url.intended`). Auth: `tools.download` (POST, throttled, requires `terms`), `tools.ready`, `tools.file` (`signed` middleware, 30-minute links, owner only). Admin: `backend.admin.downloads`, `backend.admin.downloads.export`.

## Access rules

Read guide: public · Preview tool: public · Download: authenticated + licence accepted · File URL: signed, owner only · Manage resources: repository (config) · View users/downloads: admin only.

## Not yet implemented (debt #19)

Google/GitHub sign-in, PDF/DOCX formats, admin CRUD for the library, welcome email with file links, enforced email verification before download.
