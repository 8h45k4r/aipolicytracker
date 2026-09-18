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

## Schema: `admin_audit_logs`

| Column | Type | Null | Notes |
|--------|------|------|-------|
| user_id | bigint FK users | yes | Null on delete; `user_email` survives it |
| user_email | varchar(190) | yes | Kept so a deleted account's actions stay attributable |
| method | varchar(8) | no | POST, PUT, PATCH, DELETE, or CLI for the reset command |
| route_name | varchar(120) | yes | Falls back to the path |
| path | varchar(512) | no | |
| route_params | json | yes | Identifiers only; a bound model is reduced to its key |
| status | smallint | no | The status the visitor received |
| ip_hash | varchar(64) | yes | SHA-256, never the address |
| user_agent | varchar(255) | yes | |
| created_at | timestamp | no | No `updated_at`: rows are never edited |

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
- **Inbound (alerts):** `follows.user_id`, `alert_deliveries.user_id`, `applicability_profiles.user_id` → `users.id` ([alerts.md](alerts.md)); cascade on user deletion. `/following` lists an account's follows; the profile page links to it.
- **Inbound (billing):** `billing_customers.user_id` (unique), `subscriptions.user_id`, `billing_checkouts.user_id` → `users.id` ([billing.md](billing.md)); cascade on user deletion. `User::activeSubscription()`, `planKey()` and `entitled()` answer from the billing module; the profile page shows the plan and a "Manage billing" hand-off.

- **Outbound:** none.
- **Inbound:** `user_infos.user_id`; `notifications.notifiable_id`; `reviewer_decisions.reviewer_user_id` and `app_settings.updated_by` ([policy-intelligence.md](policy-intelligence.md), [subscribers-and-settings.md](subscribers-and-settings.md)).

## Admin access

`ADMIN_EMAILS` → `User::isAdmin()` → `CheckAdmin` middleware. `User::scopeNonAdmin` excludes admins from counts.

Three further gates stand in front of the backend (2026-09-18):

| Gate | Middleware | What it does |
|------|-----------|--------------|
| Second factor | `admin.2fa` (`EnsureAdminSecondFactor`) | Every admin session must prove a TOTP code. An admin who has not enrolled is redirected to enrolment; one who has not proved a code this session is redirected to the challenge. Bound to the user id, so a pass does not carry across a re-login as someone else. |
| Audit | `admin.audit` (`RecordAdminAction`) | Writes one `admin_audit_logs` row per state-changing admin request: who, route, route parameters, status, hashed IP. Never request bodies. |
| Fresh password | `password.confirm` | Required on the actions that change secrets or remove things: settings save, billing provisioning, tool and tool-file deletion, recovery-code regeneration. |

**Two-factor.** `App\Services\Security\Totp` implements RFC 6238 in about a hundred lines rather than adding a dependency; the RFC's own test vectors run in `tests/Unit/TotpTest.php`. The secret and the eight recovery codes are `encrypted` model casts and are in `$hidden`, so a database dump reveals neither and neither is ever serialised. Recovery codes are bcrypt-hashed inside that encryption and each works once. The enrolment page shows the key as text and an `otpauth://` link; it never sends the secret to a third-party service to be drawn as a QR code.

**Lost device.** `php artisan admin:two-factor-reset {email}` clears the enrolment so the admin enrols again at next sign-in. It needs shell access to the host — a stronger proof of control than a web form could ask — and writes its own audit row.

**Audit log.** Admin → Audit log lists actions newest first. Reads are not recorded. A request refused by an earlier gate (bounced to password confirmation) is recorded as an attempt. One gap is deliberate: route-model binding runs in the `web` group, ahead of all route middleware, so a POST naming a record that does not exist 404s before the audit middleware runs — nothing was changed in that case.

## Routes

All account pages are server-rendered Blade in the site theme: `/login`, `/register`, `/forgot-password`, `/reset-password/{token}`, `/verify-email`, `/confirm-password` (through `x-auth-shell`) and `/profile` (profile, password, downloads, consent, account deletion). The profile form also stores `organization_name` and toggles `marketing_consent_at`.

### Original route list

`routes/auth.php` (Breeze), `profile.*`.
