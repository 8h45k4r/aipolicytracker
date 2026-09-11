# Guides and downloads

Free tools (templates, checklists, registers, a starter plan) listed on `/guides` next to the editorial guides. Anyone can read the guides and preview every field of a tool; downloading a file needs a free account, an explicit licence acceptance, and is served through a short-lived signed URL to the account that requested it.

## Source of truth

- Tools live in the `tools` and `tool_files` tables and are managed in Admin → Tool library (create, edit, upload files, activate/deactivate files, archive). `database/seeders/ToolSeeder.php` seeds the initial five from `config/resources.php` and copies their files from `resources/downloads/` onto the private disk; it never overwrites admin edits.
- Files: private local disk under `tools/<slug>/<file>` (outside `public/`; never linked directly). Allowed: XLSX, CSV, Markdown, PDF, DOCX, JSON, text, up to 10 MB. Every file should carry version, date and the informational-only notice.
- Filter vocabularies (`frameworks`, `topics`) and the licence text stay in `config/resources.php`; editorial guides remain in `config/content.php` with `guide_tags` for the same filters.

## Schema: `tools`

| Field | Type | Null | Default | Notes |
|-------|------|------|---------|-------|
| id | bigint | no | | |
| slug | varchar(120) | no | | Unique; public URL `/guides/tools/{slug}` |
| title | varchar(160) | no | | |
| type | varchar(24) | no | | guide, template, checklist, register |
| short | varchar(300) | no | | Card and meta description |
| purpose | text | yes | | |
| fields | json | yes | | `[[name, description], ...]` shown as the preview table |
| instructions | json | yes | | List of steps |
| frameworks / topics | json | yes | | Slugs from `config/resources.php` |
| related_guides / related_policies | json | yes | | Guide slugs (`config/content.php`) and policy slugs |
| next_slug | varchar(120) | yes | | "Next step" tool |
| version | varchar(16) | no | 1.0 | |
| updated_on | date | yes | | Shown publicly and in structured data |
| featured | boolean | no | false | |
| status | varchar(16) | no | draft | draft, published, archived; only published with an active file is public |
| seo_title / seo_description | varchar | yes | | Optional overrides |
| sort_order | int | no | 0 | |
| updated_by | bigint | yes | | FK → users.id (null on delete) |
| created_at / updated_at | timestamp | yes | | |

## Schema: `tool_files`

| Field | Type | Null | Default | Notes |
|-------|------|------|---------|-------|
| id | bigint | no | | |
| tool_id | bigint | no | | FK → tools.id (cascade) |
| file_name | varchar(160) | no | | Unique per tool; slugified on upload |
| label | varchar(40) | no | | XLSX, CSV, Markdown, PDF ... |
| disk_path | varchar(255) | no | | Path on the private local disk |
| mime | varchar(120) | yes | | |
| size | bigint | no | 0 | Bytes |
| checksum | varchar(64) | yes | | sha256 of the stored file |
| version | varchar(16) | no | 1.0 | |
| is_active | boolean | no | true | Inactive files are not offered |
| download_count | int | no | 0 | Incremented when served |
| sort_order | int | no | 0 | |
| created_at / updated_at | timestamp | yes | | |

## Schema: `resource_downloads`

| Field | Type | Null | Default | Notes |
|-------|------|------|---------|-------|
| id | bigint | no | | |
| user_id | bigint | no | | FK → users.id (cascade) |
| resource_slug | varchar(120) | no | | `tools.slug` (kept as slug so history survives archiving) |
| tool_file_id | bigint | yes | | FK → tool_files.id (null on delete); set when a file is served |
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

Public: `guides.index` (filters `q`, `type`, `framework`, `topic`, `access`; filtered pages are `noindex,follow`), `tools.show`, `tools.gate` (stores `url.intended`). Auth: `tools.download` (POST, throttled, requires `terms`), `tools.ready`, `tools.file` (`signed` middleware, 30-minute links, owner only). Admin: `backend.admin.downloads`, `backend.admin.downloads.export`, `backend.admin.tools.*` (index, create, store, edit, update, destroy = archive, files.store, files.toggle, files.destroy, files.download).

## Access rules

Read guide: public · Preview tool: public · Download: authenticated + licence accepted · File URL: signed, owner only · Manage resources: repository (config) · View users/downloads: admin only.

## Not yet implemented (debt #19)

Google/GitHub sign-in, generated PDF/DOCX for the seeded tools (admins can upload them), welcome email with file links, enforced email verification before download.
