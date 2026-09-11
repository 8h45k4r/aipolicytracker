# Admin and legacy audit (2026-09-11)

## Findings

| Area | Used by the public site? | Decision |
|------|--------------------------|----------|
| Legacy map dashboard (`/map`), news (`/news`), timeline, bookmarks, single-policy view, GovAI assessment (Inertia/React; tables `ai_policy_trackers`, `news`, `countries`, `book_marks`) | **No.** Zero links from the Blade site or its components; no `Site` controller or service reads these models. Reachable only by direct URL, served `noindex`. | Disabled behind `LEGACY_MAP_ENABLED` (default off). Old URLs 301 to the equivalent new pages. Code and tables retained so the map can be re-enabled. |
| Legacy admin CRUD (map policies, news, countries, accounts, logos) | Only feeds the legacy pages above. | Disabled with the same flag; hidden from the admin navigation. |
| Review queue (`/backend/review`) | Yes: controls `published_at` of imported records and records submission decisions. | Kept; moved into the shared admin layout; jurisdictions publish table added. |
| Submissions, subscribers, external data, settings | Yes (new in #33). | Kept. |
| Accounts / profile / auth | Yes (admin sign-in, email verification). | Kept. |

## Why the legacy code is gated rather than deleted

Deleting roughly sixty files and four tables is irreversible for anyone who wants the map back. The flag makes the state explicit, keeps tests green and lets a dedicated PR remove the code after one release cycle (`technical-debt.md` #17). All 68 legacy instruments were already bridged into `data/` in #22.

## Outcome

Removed in the follow-up PR (2026-09-11): legacy controllers, models, jobs, notifications, helpers, React pages and components, seeders, `database/data/ai_policies.json`, and the tables `countries`, `statuses`, `ai_policy_trackers`, `news`, `thumbnails`, `news_future_images`, `a_i_policy_activity_logs`, `book_marks`, `nav_bars`, `contributing_orgs` (migration with a reversible `down()`). `/map`, `/news`, `/timeline`, `/bookmarks` redirect permanently to the new pages. Auth, profile and registration are unchanged.
