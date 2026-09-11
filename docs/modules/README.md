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
| Policy intelligence | [policy-intelligence.md](policy-intelligence.md) | `jurisdictions`, `policy_instruments`, `policy_versions`, `policy_sections`, `obligations`, `applicability_rules`, `taxonomy_terms`, `taxonomy_assignments`, `deadlines`, `enforcement_events`, `procurement_rules`, `framework_mappings`, `evidence_artifacts`, `change_events`, `source_documents`, `contributor_submissions`, `reviewer_decisions` |

Framework tables (`cache`, `cache_locks`, `jobs`, `job_batches`, `failed_jobs`, `migrations`) are Laravel-managed and not documented as modules.

Sources/provenance, instrument versions, obligations, evidence artifacts and framework mappings are now delivered by the policy-intelligence module. Still planned: controls, and cross-linking the legacy `ai_policy_trackers` rows to `policy_instruments`.
