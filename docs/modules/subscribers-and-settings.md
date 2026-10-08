# Subscribers and settings

Email digest subscriptions (double opt-in), operator-managed settings stored encrypted, and the funders shown on `/funding`.

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

## Schema: `funders`

The funders listed on `/funding`. The owner keeps them in the admin, so a new funder needs no deploy.

| Field | Type | Null | Default | Notes |
|-------|------|------|---------|-------|
| id | bigint | no | | |
| name | varchar(160) | no | | |
| kind | varchar(16) | no | | `grant`, `sponsor`, `subscription` or `other` (`Funder::KINDS`) |
| amount_display | varchar(64) | no | | Free text, shown as written. Never computed |
| period | varchar(64) | yes | | e.g. `a year`, `2027` |
| purpose | text | no | | What the money pays for. Up to 2,000 characters |
| url | varchar(512) | yes | | https only |
| starts_on / ends_on | date | yes | | `ends_on` is on or after `starts_on`. A past `ends_on` shows as "ended" on the page |
| published | boolean | no | false | Only published rows reach `/funding` |
| sort | unsigned int | no | 0 | Order on the page; a new row goes last |
| created_at / updated_at | timestamp | yes | | |

Index: (`published`, `sort`).

How `/funding` reads it (`App\Support\FundingDisclosure`):

- When the table has any row, the page lists its published rows in order. Unpublished rows never show.
- When the table is empty, the page lists `config('funding.funders')`. This keeps old installs working.
- With no funder to show, the page says "None yet".

Admin: `backend.admin.funding.index|store|update|publish|move|destroy` under `/backend/admin/funding`. The capability is `settings.manage`, so only owners reach it. "Add a funder" (also a Ctrl K action) and "Edit" open the form in a side panel (`x-backend.drawer`). Without JavaScript the same links go to `?new=1` or `?edit=<id>`, and the form is shown on the page. A refused save reopens the panel it came from (hidden field `_drawer`) with the typed values. A link that is not https gets "Use a full https:// address". Delete asks for confirmation first and names the funder. The `admin.audit` middleware writes one audit row for every write, with the funder id.

## The settings page

`AppSetting::GROUPS` lists the groups in page order and every key in `AppSetting::KEYS` names its `group`. Each group is its own card, form and Save button. The form posts `group=<id>` and `settingsSave` validates and saves only that group's keys; keys from other groups in the same request are ignored. In a group form:

- An emptied plain field clears the stored value, so the environment or the default applies again.
- An empty secret keeps the stored secret, because a secret is never sent back to the page. The "clear the stored value" box removes it.
- A refused save saves nothing in the group. It returns to `#group-<id>` with the typed values (`old()`) and an error under each field. Secrets are not flashed to the session, so they must be pasted again.

A post with no `group` is the older single form. It validates every key, and an empty field keeps its value.

Each field shows where the value in use comes from: "Saved here", "From environment" or "Default". With the configuration cached the environment cannot be read, so an unsaved field shows "Environment or default".

The tests live in their groups: "Send test email" (Email), "Check keys with Cloudflare" (Bot protection) and a link to the "Can we sell right now?" probe on Billing (Billing). The Billing page says why the probe is disabled (no API key, no webhook secret, no product id) and links to the setting or button that fixes it.

**Live switches.** `billing_enabled`, `dodo_environment`, `email_domain_enforcement` and `analytics_require_consent` are in the `live` group, in a separate section at the end. Each switch shows the value in use and offers one button per other value. A key's `live` metadata names the consequence of each risky value (Checkout on, `live_mode`, enforcement on, consent off). That button asks first through `data-confirm-danger`, and the question names the consequence.

Each group registers a Ctrl K palette action, "Settings: <group>".

## Settings: citation and funding

These keys sit in the "Citation and funding" group on the settings page. None is secret. A saved value wins; an empty one falls back.

| Key | Falls back to | Read by | Rule |
|-----|---------------|---------|------|
| `dataset_doi` | `DATASET_DOI` → `aipolicytracker.dataset_doi` | `App\Support\DatasetCitation::doi()` | Must pass `DatasetCitation::parse()`: a bare DOI, `doi:` name or doi.org URL. Set it only after Zenodo has minted the DOI ([releases.md](../reference/releases.md)) |
| `sponsor_url` | `SPONSOR_URL` → `funding.sponsor_url` | `FundingDisclosure::sponsorUrl()` | https URL, up to 512 characters. Shown on `/funding` |
| `funding_threshold` | `funding.disclosure_threshold` | `FundingDisclosure::threshold()` | Whole US dollars a year, 0 to 10,000,000 |

These are read from `app_settings` when the page renders, not copied into config, so `config:cache` is unaffected.

## Interlinks

- **Inbound (billing):** `app_settings` keys `dodo_environment`, `dodo_api_key`, `dodo_webhook_secret`, `dodo_product_pro_monthly`, `dodo_product_pro_yearly` are read by `App\Services\Billing\BillingConfig` with environment fallbacks ([billing.md](billing.md)).

- **Accounts:** an account's "weekly digest" choice is a `subscribers` row for its address (`App\Services\Subscribers\AccountDigest`). A verified address is subscribed directly, since verification is the opt-in; a choice made at sign-up takes effect on the `Verified` event. Unticking it, or unsubscribing from any issue, turns both off. Each change writes a `digest.email` consent event.
- **Outbound:** `subscribers.topics` → `jurisdictions.slug` (logical); `app_settings.updated_by` → [accounts](accounts.md).
- **Inbound:** `digest:send` reads published `change_events` and `deadlines` ([policy-intelligence.md](policy-intelligence.md)); `AppSettingsServiceProvider` applies settings to `mail.*` and `services.resend.key`.

## Routes

Public: `subscribe.show` (GET `/subscribe`, jurisdiction picker grouped by region), `subscribe.store` (POST, throttled, honeypot), `subscribe.confirm`, `subscribe.unsubscribe` (GET and RFC 8058 POST), `cron.digest` (POST, bearer token). Admin (`auth` + `isAdmin`): `backend.admin.dashboard|submissions|subscribers|subscribers.export|subscribers.resend|subscribers.delete|external|settings|settings.save|settings.test`.

The subscribers list shows each address's `source`. This is the page the public form was sent from (for example `subscribe-page`), or `account` for an address subscribed from account settings. Filter it with `?source=`, or `?source=(none)` for rows with no source. An "account" badge marks an address that a verified account uses. It links to the user page for admins who hold `users.manage`. Filter with `?account=yes|no`. The CSV export has `source` and `has_account` columns.

## Email templates

All mail (`SubscriptionConfirmMail`, `WeeklyDigestMail`, `SubmissionReceivedMail`, `TestMail`) renders through `resources/views/emails/site/layout.blade.php`: centred logo (`/brand/logo-on-light.png`), tagline, organisation name, body slot, "Follow us on social" icons from `config('aipolicytracker.social')` (PNG icons under `public/brand/social/`, SVG for the website footer), copyright and unsubscribe line. Plain-text alternates ship with every mailable.

## Scheduling

`.github/workflows/weekly-digest.yml` calls `POST /cron/digest` on Mondays 06:00 UTC and `.github/workflows/daily-alerts.yml` calls `POST /cron/alerts` daily at 06:30 UTC ([alerts.md](alerts.md)), both with the `CRON_TOKEN` repository secret; the same value is stored (encrypted) as the `cron_token` setting or in the `CRON_TOKEN` environment variable.

## Saved records (reading list)

`/saved` (`PageController::saved`) renders a shell; the list itself is kept in the visitor's browser under the `localStorage` key `apt-saved` by `resources/js/public.js`. Record pages carry `<x-site.save-button>` (via `correction-cta`). Nothing is transmitted or stored server-side, so there is no schema, no personal data and no retention question; copying the list as Markdown or JSON is client-side only. The header shows the count with `data-saved-count`.

## Privacy

Only the address, topics, timestamps and the originating page are stored. Confirmation is required before any digest is sent; every digest carries `List-Unsubscribe` headers and a one-click unsubscribe link. Admins can export or delete subscribers.
