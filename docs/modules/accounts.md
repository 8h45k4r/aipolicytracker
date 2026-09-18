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

## Address quality

Applied 2026-09-18. A throwaway address breaks the product's only promise — that a reader hears when a deadline moves — and it inflates the subscriber and download figures the site publishes about itself. Work addresses and personal addresses are both accepted; a mailbox that expires is not.

`App\Rules\NotDisposableEmail` runs on registration (`email` and `organization_email`), a change of address on the profile, the newsletter form, and the optional contact address on a contribution. It is deliberately **absent** from sign-in, password reset and the free-tool download form: an account that already exists must always be able to get back in, whatever its address, so a rule added today cannot strand somebody who signed up before it. The profile rule only fires when the address actually changes, for the same reason.

`App\Services\Security\EmailDomainPolicy` decides, in this order:

| # | Check | Source | Effect |
|---|-------|--------|--------|
| 1 | Trusted providers | `data/email/trusted-domains.txt` | Accepted outright. Nothing later can overrule it, so no heuristic can take a fifteen-year-old personal mailbox away from a reader. |
| 2 | Reserved names | `config/email.php` (`reserved`) | Refused. RFC 2606 and RFC 6761 names reach nobody. |
| 3 | Known throwaway domains | `data/email/disposable-domains.txt` + the operator overlay | Refused. |
| 4 | Where the domain's mail goes | MX lookup vs `data/email/disposable-mail-hosts.txt` | Refused if the exchanger belongs to a throwaway service, or if the domain has no mail route at all. |

Matching is by suffix on label boundaries, so `mailinator.com` covers `team.mailinator.com` and never `notmailinator.com`.

**Check 4 is the one that works.** A throwaway service rotates thousands of domains but runs its own mail servers, so blocking the exchanger stops the domains no list has catalogued yet. Measured against a random sample of 400 known throwaway domains: 62.5% refused, of which the domain list accounted for 0.2% and the mail route for 62.3% (36.5% by exchanger, 25.8% with no mail route). The same policy accepted 50 out of 50 legitimate work, regulator, university, hospital and freemail domains. The static domain list is the weakest of the four and is never relied on alone.

**Shared infrastructure is deliberately excluded** from the exchanger list — Cloudflare Email Routing, Google Workspace, Microsoft 365, Amazon SES, Mailgun, ImprovMX, Forward Email, Zoho — because real organisations use it and a line there would refuse every business behind it. A test asserts each of those names stays out.

**Alias and relay services are not throwaway.** SimpleLogin, addy.io, Apple's Hide My Email, DuckDuckGo and Firefox Relay forward to a mailbox their owner reads, so they are accepted. Refusing them would punish exactly the privacy-conscious readers this site is written for.

**Everything uncertain is accepted.** If the resolver cannot answer, the address passes. A control domain is resolved before "no mail route" is believed, so a DNS outage cannot become a sign-up outage for the whole site.

**Operating it.**

| Need | Command or setting |
|------|--------------------|
| Explain a refusal | `php artisan email:check someone@example-domain.tld` — prints the domain, verdict, reason and the exact list entry that decided. |
| Block a new service without a deploy | Add a line to the overlay file named by `email.overlay` (`storage/app/email/disposable-domains.txt`), or run `php artisan email:domains-refresh --source=https://…` with a list whose licence you have read. The overlay is untracked, so the project never redistributes a third-party list. |
| Stop enforcing entirely | `EMAIL_DOMAIN_ENFORCEMENT=false`. |
| Skip the DNS lookup | `EMAIL_CHECK_DELIVERABILITY=false` — leaves the curated lists working. |

Enforcement is off under `testing` by default: every other suite uses RFC 2606 addresses, which have no mail route by design. `EmailDomainPolicyTest` turns it on and supplies its own resolver, so the decision under test is the policy's and not the day's DNS.

## Terms and privacy

Added 2026-09-18. The sign-up form asks readers to accept terms and a privacy policy, and the download gate writes `terms_accepted_at` against that acceptance. Both links resolved to `/about` whenever `SITE_LINK_TERMS_OF_USE` and `SITE_LINK_PRIVACY_POLICY` were unset, which is how the site shipped. An acceptance checkbox pointing at a page that does not contain the terms is not consent.

`/privacy` and `/terms` are now served by `App\Http\Controllers\Site\LegalController` and linked from the footer of every page, from the sign-up form and from the sitemap. The two environment variables still win when set, so a policy hosted elsewhere overrides the built-in page without a code change.

The text is built from `config/legal.php` and from the application's real schema. The account table on the privacy page lists the columns that exist, not a boilerplate inventory, and `LegalPagesTest` asserts that the page mentions each of them. Two facts that cannot be derived from the codebase are deliberately absent by default rather than invented:

| Value | Setting | Effect while unset |
|---|---|---|
| Address for data requests | `LEGAL_PRIVACY_EMAIL`, falling back to `CONTACT_EMAILS` | The page routes requests to the contribution form instead of a mailbox. |
| Governing law and venue | `LEGAL_GOVERNING_LAW` | The governing-law clause is omitted from the terms entirely. |

Both are recorded as debt #29. Debt #28 records the related honesty problem the privacy page surfaced: `user_infos.ip_address` keeps the sign-up address in full while `resource_downloads` and `admin_audit_logs` both hash theirs. The page says so in plain words rather than glossing it.

## Routes

All account pages are server-rendered Blade in the site theme: `/login`, `/register`, `/forgot-password`, `/reset-password/{token}`, `/verify-email`, `/confirm-password` (through `x-auth-shell`) and `/profile` (profile, password, downloads, consent, account deletion). The profile form also stores `organization_name` and toggles `marketing_consent_at`.

### Original route list

`routes/auth.php` (Breeze), `profile.*`.
