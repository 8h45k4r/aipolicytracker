# Module documentation

One document per module. Each doc has: purpose, schema field table (must match the migrations), interlinks (inbound and outbound, both documented on each side), routes, and open debt. A new module without a doc does not merge (gate 3 in `docs/reference/change-gates.md`).

| Module | Doc | Tables |
|--------|-----|--------|
| Jurisdictions | [jurisdictions.md](jurisdictions.md) | `countries` |
| Policies | [policies.md](policies.md) | `ai_policy_trackers`, `statuses`, `a_i_policy_activity_logs` |
| Updates (news) | [updates.md](updates.md) | `news`, `thumbnails`, `news_future_images` |
| Bookmarks & notifications | [bookmarks.md](bookmarks.md) | `book_marks`, `notifications` |
| Accounts | [accounts.md](accounts.md) | `users`, `user_infos`, `password_reset_tokens`, `sessions` |
| Site content (CMS) | [site-content.md](site-content.md) | `nav_bars`, `contributing_orgs` |

Framework tables (`cache`, `cache_locks`, `jobs`, `job_batches`, `failed_jobs`, `migrations`) are Laravel-managed and not documented as modules.

Planned modules (not yet built, see the product strategy): sources/provenance, instrument versions, obligations, controls, evidence artifacts, framework mappings.
