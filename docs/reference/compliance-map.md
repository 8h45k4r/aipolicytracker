# Compliance map

Maps repository controls to the obligations this project holds itself to. A change that touches a row below must say so in its pull request (gate 4). Changes that touch none of these are recorded as out of scope with a reason.

| Area | Obligation | Control in this repository | Evidence |
|------|------------|----------------------------|----------|
| Source integrity | Every policy entry is traceable to an official or openly licensed source (`SOURCE_ATTRIBUTION.md`). | `whitepaper_document_link` on `ai_policy_trackers`; PR template requires source table for data changes. | PR source table; `DataIntegrityTest`. |
| Change evidence | Edits to policy records are logged. | `a_i_policy_activity_logs` written by `AiPolicyActivityLogHelper` on create/update/delete. | Activity log rows per policy. |
| Personal data minimisation | Only data needed for the service is stored; no plaintext passwords. | `user_infos.password` column dropped (migration `2025_09_10_000000`); `UserInfo::$hidden`; admin-only user views. | Migration; `AdminAccessTest`. |
| Access control | Admin area restricted to configured accounts. | `ADMIN_EMAILS` + `CheckAdmin` middleware + `User::isAdmin()`. | `AdminAccessTest`. |
| Secrets handling | No secrets in the repository. | `.gitignore`, `.env.example` placeholders, GitHub secret scanning with push protection, App Service settings for runtime secrets. | Repository security settings. |
| Transport security | HTTPS only, secure cookies, security headers. | `SecurityHeaders` middleware; `SESSION_SECURE_COOKIE=true`; App Service HTTPS-only; Cloudflare TLS. | `curl -I https://aipolicytracker.org`. |
| Abuse resistance | Rate limits on authentication and write endpoints. | `LoginRequest` throttling; `throttle` middleware on notification route; Cloudflare in front. | Route definitions. |
| Vulnerability handling | Private reporting channel and automated scanning. | `SECURITY.md`; GitHub private vulnerability reporting; CodeQL; Dependabot. | Repository security tab. |
| Licensing | Software under Apache-2.0; third-party licences acknowledged. | `LICENSE`, `NOTICE`. | Files present. |
| Disclaimer | Content is informational, not legal advice. | README disclaimer; `SOURCE_ATTRIBUTION.md`. | Files present. |
