# Module documentation

One document per module. Each doc has: purpose, schema field table (must match the migrations), interlinks (inbound and outbound, both documented on each side), routes, and open debt. A new module without a doc does not merge (gate 3 in `docs/reference/change-gates.md`).

| Module | Doc | Tables |
|--------|-----|--------|
| Accounts | [accounts.md](accounts.md) | `users`, `user_infos`, `password_reset_tokens`, `sessions` |
| Subscribers and settings | [subscribers-and-settings.md](subscribers-and-settings.md) | `subscribers`, `app_settings` |
| External data (AI risk, incidents) | [external-data.md](external-data.md) | none (JSON under `data/external/`) |
| Policy intelligence | [policy-intelligence.md](policy-intelligence.md) | `jurisdictions`, `policy_instruments`, `policy_versions`, `policy_sections`, `obligations`, `applicability_rules`, `taxonomy_terms`, `taxonomy_assignments`, `deadlines`, `enforcement_events`, `procurement_rules`, `framework_mappings`, `evidence_artifacts`, `change_events`, `source_documents`, `contributor_submissions`, `reviewer_decisions` |

Framework tables (`cache`, `cache_locks`, `jobs`, `job_batches`, `failed_jobs`, `migrations`) are Laravel-managed and not documented as modules.

Sources/provenance, instrument versions, obligations, evidence artifacts and framework mappings are now delivered by the policy-intelligence module. Controls shipped as their own module. Still planned: cross-linking the legacy `ai_policy_trackers` rows to `policy_instruments`.
| Guides and downloads | [guides-and-downloads.md](guides-and-downloads.md) | `resource_downloads`, `users` additions | Free tools on `/guides`, gated downloads, admin activity |
| Billing | [billing.md](billing.md) | `billing_customers`, `subscriptions`, `billing_events`, `billing_checkouts` | Pro plans via Dodo Payments (merchant of record), webhook-driven entitlements, `/pricing`, admin billing |
| Controls | [controls.md](controls.md) | `controls`, `control_evidence`, `control_framework_references`, `control_obligation` | What an organisation operates to meet a duty, the evidence it produces, the risks and clauses it maps to; `/controls`, API and exports |
| Alerts | [alerts.md](alerts.md) | `follows`, `alert_deliveries`, `applicability_profiles` | Pro follows and saved applicability profiles; daily change and deadline alerts that name the system a change may affect (`alerts:send`, `/cron/alerts`) |

Product reference: [docs/reference/personas-and-jobs.md](../reference/personas-and-jobs.md) describes who uses the site, their pain points and the single USP that every feature must serve.
