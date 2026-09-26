# Alerts (follows, applicability profiles and daily alerts)

The first paid capability. A Pro account follows policies, jurisdictions and obligations server-side; every morning the app checks those records and emails the account when a dated, source-linked change was recorded or an application date reaches a milestone. Quiet days send nothing.

Design rules:

- **Following is per account and per record.** `follows` rows belong to one user (`user_id`, cascade on delete) and point at a published record by slug; the toggle route validates that the record exists and is published, so a follow cannot reference something that is not on the site.
- **Only entitled accounts get alerts.** The follow toggle needs the `saved.server` entitlement and the send needs `alerts.daily` (both Pro; see [billing.md](billing.md)). A lapsed subscription keeps its follows but receives nothing until it is active again.
- **One email per user per day, idempotent.** `alert_deliveries` is unique on (`user_id`, `sent_on`) and is written before the email is sent, so a re-run of the command cannot double-send. The window starts at the previous delivery's `window_end` (capped at seven days back) so nothing is missed between runs.
- **Nothing is invented.** Alerts contain only published `change_events` and `deadlines` rows with their official source links; the email says why it was sent and links to the management page.
- **The browser reading list is unchanged.** "Save" (localStorage, no account) stays free; "Follow" is the server-side, account-bound counterpart.

## Watches beyond a record (P8)

A follow may also point at a **sector** or **use case** (every published instrument recorded against the term), a **framework** (every instrument with a duty mapped to it: `iso-42001`, `nist-ai-rmf`, …), a **change type** (`urgent`, `high`, `routine`: every change at that level) or a **saved search** (the updates hub's filters, stored in `follows.params`; the subject slug is a hash of them so one search is one watch). `App\Services\Alerts\WatchTypes` validates and labels each and resolves the set to instrument ids and change filters for `AlertBuilder`. The toggle route accepts `/follow/<type>/-` with the choice in a `slug` field, so the select-and-watch forms work without JavaScript.

## Channels, deliveries and consent (P8)

| Table | What |
|-------|------|
| `alert_channels` | One row per channel: `kind` (`email`, `rss`, `slack`, `webhook`), `endpoint`, `secret` (feed token or HMAC secret; hidden from serialisation), `enabled`, `confirmed_at`, `last_delivered_at`. No email row means the inbox is on. |
| `channel_deliveries` | One row per attempt-tracked delivery to Slack or a webhook: payload, status (`pending`/`sent`/`failed`), attempts, response code, last error, next attempt. Backoff 15, 60, 240, 960 minutes; five attempts. `alerts:deliver` (hourly) retries what is due; `alerts:send` also runs it. |
| `consent_events` | Every consent decision: kind (`alerts.email`, `alerts.channel`, `api.token`, …), granted, source (`account`, `unsubscribe-link`), detail. No IP, no agent. |

Webhooks receive the same facts as the email as JSON, with `X-AIP-Signature: sha256=<hex HMAC-SHA256 of the raw body with the channel secret>`, `X-AIP-Delivery` (the delivery id) and `X-AIP-Event` (`alert.daily` or `alert.test`). Slack receives the same rows as text. The private feed (`/alerts/feed/<token>.rss`) is the account's watched changes of the last 30 days; the address is the key.

Every alert email carries a signed unsubscribe link (`/alerts/unsubscribe/<user>`, GET confirms, POST acts) that needs no sign-in and writes a consent event. `/account/export.json` returns the account, watches, profiles, channels (no secrets), deliveries, consent events and downloads. `/api/v1/watches` (list, create, delete) works over a Sanctum personal access token created on the account page; creating a new one revokes the old.

## Applicability profiles (change-impact)

A follow answers "did this record change". A profile answers "does this change affect the system I described", which is the capability Pro is sold on.

The free applicability check screens a described system (markets, role, use case, sector, personal data, sensitive domains, generative AI) against recorded instruments and obligations. A Pro account saves those answers as a named profile. The daily send then screens every new change in the window against each profile with `ApplicabilityScreener`, the same implementation the tool page used, and the email labels the change "May affect: <profile name>".

Rules:

- **One screen, two surfaces.** The tool page and the alert call the same service. An alert may never claim relevance the visible tool would not produce.
- **Scope is jurisdictions plus scored instruments.** A profile watches the jurisdictions it selected and the instruments the screen scored above zero. A zero score means nothing in the record overlapped the answers, so a change there is not reported as relevant.
- **Answers only.** A profile stores the questionnaire answers, never a customer's systems, evidence or conclusions. Ten profiles per account (`ApplicabilityProfile::MAX_PER_USER`).
- **Relevance, never a determination.** Every alert repeats that screening is not legal advice and links the official source to verify.
- **Saving the same screen twice is a no-op.** Answers are canonicalised, so a duplicate returns the existing profile instead of creating another.

## Schema: `applicability_profiles`

| Field | Type | Null | Default | Notes |
|-------|------|------|---------|-------|
| id | integer | no |  |  |
| user_id | integer | no |  | FK → `users.id`, cascade on delete |
| name | varchar | no |  | Operator's name for the system or programme |
| answers | text | no |  | JSON, normalised by `ApplicabilityScreener::normalise()` |
| last_matched_at | datetime | yes |  | Set when a daily alert flagged a change for this account |
| created_at | datetime | yes |  |  |
| updated_at | datetime | yes |  |  |

Index (`user_id`, `name`).

## Matching

| Followed record | Changes included | Application dates included |
|-----------------|------------------|----------------------------|
| Jurisdiction | every published change with that `jurisdiction_id` | none |
| Policy | changes with that `policy_instrument_id` | deadlines with that `policy_instrument_id` |
| Obligation | changes of the obligation's instrument | deadlines with that `obligation_id` (plus the instrument's) |

| Saved profile | changes in its jurisdictions and in the instruments its screen scored above zero | deadlines of those same instruments |

An email is sent when at least one change falls in the window, or when a deadline is exactly 30, 7 or 1 days away (`AlertBuilder::DEADLINE_MILESTONES`). A profile alone can trigger both: an account that follows nothing but saved a profile still receives the alert when an instrument in that profile's scope changes or has a date falling due. Deadlines within the next 30 days (`DEADLINE_HORIZON_DAYS`) are listed as context in every alert. When exactly one profile is affected, the subject line names it.

## Schema: `follows`

| Field | Type | Null | Default | Notes |
|-------|------|------|---------|-------|
| id | integer | no |  |  |
| user_id | integer | no |  | FK → `users.id`, cascade on delete |
| subject_type | varchar | no |  | `policy`, `jurisdiction`, `obligation` (`Follow::TYPES`) |
| subject_slug | varchar | no |  | Slug of the followed record |
| created_at | datetime | yes |  |  |
| updated_at | datetime | yes |  |  |

Unique (`user_id`, `subject_type`, `subject_slug`); index (`subject_type`, `subject_slug`).

## Schema: `alert_deliveries`

| Field | Type | Null | Default | Notes |
|-------|------|------|---------|-------|
| id | integer | no |  |  |
| user_id | integer | no |  | FK → `users.id`, cascade on delete |
| sent_on | date | no |  | Unique with `user_id` |
| window_start | datetime | no |  | Start of the change window covered |
| window_end | datetime | no |  | End of the window; the next run starts here |
| changes_count | integer | no | '0' |  |
| deadlines_count | integer | no | '0' |  |
| created_at | datetime | yes |  |  |
| updated_at | datetime | yes |  |  |

## Interlinks

- **Outbound (accounts):** `follows.user_id`, `alert_deliveries.user_id` → `users.id` ([accounts.md](accounts.md)); `User::follows()`, `User::alertDeliveries()`.
- **Outbound (policy intelligence):** `follows.subject_slug` → `policy_instruments.slug`, `jurisdictions.slug` or `obligations.slug` by `subject_type` (logical link, validated on write; a record that is later unpublished shows as "no longer published" on the list and never matches). Alerts read `change_events` and `deadlines` ([policy-intelligence.md](policy-intelligence.md)).
- **Outbound (billing):** entitlements `saved.server` (follow and save a profile) and `alerts.daily` (send) via `Entitlements` ([billing.md](billing.md)).
- **Outbound (policy intelligence):** a profile's answers reference `jurisdictions.slug` and taxonomy term slugs; the screen reads `policy_instruments`, `obligations` and `taxonomy_assignments` ([policy-intelligence.md](policy-intelligence.md)).
- **Inbound:** none.

## Code map

| Piece | Location |
|-------|----------|
| Matching and window logic | `App\Services\Alerts\AlertBuilder` |
| Screening shared by the free tool and the paid alert | `App\Services\Applicability\ApplicabilityScreener` |
| Save, list and delete profiles | `Site\ApplicabilityProfileController`, `site/account/following` |
| Daily send (idempotent per user and day) | `App\Console\Commands\SendAlertsCommand` (`alerts:send {--dry-run}`) |
| Email | `App\Mail\DailyAlertMail`, `emails/site/alert` (+ text) |
| Follow toggle and list | `Site\FollowController`, `site/account/following` |
| Follow button (Pro form or pricing link) | `components/site/follow-button`, rendered inside `components/site/correction-cta` on policy, jurisdiction and obligation pages |
| Scheduled trigger | `Site\CronController::alerts`, `.github/workflows/daily-alerts.yml` (06:30 UTC daily, `CRON_TOKEN`) |

## Routes

| Method | Path | Name | Access |
|--------|------|------|--------|
| GET | `/following` | `following.index` | `auth`, `verified` |
| POST | `/follow/{type}/{slug}` | `follow.toggle` | `auth`, `verified`, `subscribed:saved.server`, throttle 60/min; 404 for unknown type or unpublished record |
| POST | `/profiles` | `profiles.store` | `auth`, `verified`, `subscribed:saved.server`, throttle 30/min |
| DELETE | `/profiles/{profile}` | `profiles.destroy` | `auth`, `verified`; owner only |
| POST | `/cron/alerts` | `cron.alerts` | bearer `cron_token`, no CSRF, throttle 5/min |

## Tests

`tests/Feature/AlertsTest.php`: saved profiles are Pro only, normalise answers, refuse duplicates, scope the alert to the matching profile, name it in the subject and body, never match a profile in another market, and delete only for the owner; guests and free accounts see "Follow with Pro" and cannot follow; Pro toggles on and off, unknown records and types are refused, lists are per account; the daily send reaches followers by policy and by jurisdiction, skips unrelated, lapsed and free followers, is idempotent per day and quiet when nothing changed; a deadline milestone alone sends an alert and obligation follows match their instrument; the cron endpoint requires the token.

## Open debt

See `docs/reference/technical-debt.md` #22 (remaining Pro capabilities) and #23.
