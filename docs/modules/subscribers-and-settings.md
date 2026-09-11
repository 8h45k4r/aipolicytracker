# Subscribers and settings

Email digest subscriptions (double opt-in) and operator-managed settings stored encrypted.

## Schema: `subscribers`

| Field | Type | Null | Default | Notes |
|-------|------|------|---------|-------|
| id | bigint | no | | |
| email | varchar(190) | no | | Unique, lower-cased |
| token | varchar(64) | no | | Unique; used in confirm and unsubscribe links |
| topics | json | yes | | Jurisdiction slugs or `["all"]` |
| frequency | varchar(16) | no | weekly | |
| source | varchar(64) | yes | | Page the form was submitted from |
| confirmed_at / unsubscribed_at / last_sent_at | timestamp | yes | | |
| created_at / updated_at | timestamp | yes | | |

## Schema: `app_settings`

| Field | Type | Null | Notes |
|-------|------|------|-------|
| key | varchar(64) | no | Primary key; allowed keys in `AppSetting::KEYS` |
| value | text | yes | Encrypted with `APP_KEY` (`Crypt::encryptString`) |
| secret | boolean | no | Masked in the admin UI |
| updated_by | bigint | yes | FK → `users.id` (null on delete) |
| created_at / updated_at | timestamp | yes | |

## Interlinks

- **Outbound:** `subscribers.topics` → `jurisdictions.slug` (logical); `app_settings.updated_by` → [accounts](accounts.md).
- **Inbound:** `digest:send` reads published `change_events` and `deadlines` ([policy-intelligence.md](policy-intelligence.md)); `AppSettingsServiceProvider` applies settings to `mail.*` and `services.resend.key`.

## Routes

Public: `subscribe.store` (POST, throttled, honeypot), `subscribe.confirm`, `subscribe.unsubscribe` (GET and RFC 8058 POST), `cron.digest` (POST, bearer token). Admin (`auth` + `isAdmin`): `backend.admin.dashboard|submissions|subscribers|subscribers.export|subscribers.resend|subscribers.delete|external|settings|settings.save|settings.test`.

## Email templates

All mail (`SubscriptionConfirmMail`, `WeeklyDigestMail`, `SubmissionReceivedMail`, `TestMail`) renders through `resources/views/emails/site/layout.blade.php`: centred logo (`/brand/logo-on-light.png`), tagline, organisation name, body slot, "Follow us on social" icons from `config('aipolicytracker.social')` (PNG icons under `public/brand/social/`, SVG for the website footer), copyright and unsubscribe line. Plain-text alternates ship with every mailable.

## Scheduling

`.github/workflows/weekly-digest.yml` calls `POST /cron/digest` on Mondays 06:00 UTC with the `CRON_TOKEN` repository secret; the same value is stored (encrypted) as the `cron_token` setting or in the `CRON_TOKEN` environment variable.

## Privacy

Only the address, topics, timestamps and the originating page are stored. Confirmation is required before any digest is sent; every digest carries `List-Unsubscribe` headers and a one-click unsubscribe link. Admins can export or delete subscribers.
