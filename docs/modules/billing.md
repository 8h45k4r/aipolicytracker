# Billing (Dodo Payments)

Paid plans on top of the free, open dataset. Dodo Payments is the merchant of record: it hosts checkout, stores payment methods, calculates tax, issues invoices and handles disputes. This application never sees card data. It creates checkout sessions, verifies webhooks and keeps a local, auditable mirror of subscriptions from which entitlements are decided.

Design rules:

- **Access is granted only by a verified webhook.** The provider's return URL never activates anything; the return page shows "confirming" until the `subscription.active` event has been applied.
- **Every webhook is stored exactly once** (`billing_events.event_id` is the provider's `webhook-id`), so retries are idempotent, and every entitlement change is traceable to a signed event.
- **Out-of-order delivery cannot change access incorrectly.** An event older than the last applied one for the same subscription is recorded with outcome `stale` and skipped.
- **Nothing is sold while checkout is off.** The switch is the `billing_enabled` setting (Admin → Settings, `on`/`off`), falling back to `BILLING_ENABLED`. While off the pricing page is `noindex` and shows plans as "Not yet available", checkout and portal routes return 404, and the webhook endpoint still records events so a test-mode integration can be verified before launch.
- **The plan we sell is the plan in `config/billing.php`.** A subscription whose product id is not one of ours is mirrored (for the audit trail) but never grants access. Admin → Billing compares each configured product with the provider and flags a price mismatch.

## Plans and entitlements

| Plan key | Name | Price (config) | Entitlements |
|----------|------|----------------|--------------|
| `free` (implicit) | Free | 0 | `alerts.weekly` |
| `pro_monthly` | Pro | $29 / month | `alerts.weekly`, `alerts.daily`, `saved.server` |
| `pro_yearly` | Pro (annual) | $290 / year | same as Pro |

Only entitlements with a consumer in the code are listed: `alerts.daily` and `saved.server` are read by the alerts module ([alerts.md](alerts.md)), `alerts.weekly` describes the digest that is open to everyone. A key with no reader is a promise the product does not keep, so it does not belong in the config or on the pricing page.

Entitlement keys are strings such as `alerts.daily`; `User::entitled('alerts.daily')` answers from the covering subscription's plan or the free tier. `User::planKey()` returns `free` or the plan key. The `subscribed` middleware (`subscribed` for any plan, `subscribed:saved.server` for one capability) guards routes: guests go to sign-in, members without the entitlement go to `/pricing` (402 JSON for API callers). `follow.toggle` uses it.

Access rules (`Entitlements::covers`):

| Status | Access |
|--------|--------|
| `active` | yes |
| `on_hold`, `past_due` (renewal failed) | yes for `BILLING_ON_HOLD_GRACE_DAYS` (default 7) after `on_hold_at`; a `SubscriptionPaymentFailedMail` is sent once on the transition |
| `cancelled` | yes until `current_period_end` (cancel at period end), otherwise no |
| `pending`, `paused`, `failed`, `expired`, past `expires_at`, unknown product | no |

## Schema: `billing_customers`

| Field | Type | Null | Default | Notes |
|-------|------|------|---------|-------|
| id | integer | no |  |  |
| user_id | integer | no |  | FK → `users.id`, unique, cascade on delete |
| provider | varchar | no | 'dodo' |  |
| provider_customer_id | varchar | no |  | Unique; Dodo `customer_id` |
| email | varchar | yes |  | Email known to the provider |
| created_at | datetime | yes |  |  |
| updated_at | datetime | yes |  |  |

## Schema: `subscriptions`

| Field | Type | Null | Default | Notes |
|-------|------|------|---------|-------|
| id | integer | no |  |  |
| user_id | integer | no |  | FK → `users.id`, cascade on delete |
| billing_customer_id | integer | yes |  | FK → `billing_customers.id`, set null on delete |
| provider | varchar | no | 'dodo' |  |
| provider_subscription_id | varchar | no |  | Unique; Dodo `subscription_id` |
| product_id | varchar | yes |  | Dodo product id |
| plan_key | varchar | yes |  | Resolved from `product_id` via `config/billing.php`; null when not one of our products |
| status | varchar | no |  | `pending`, `active`, `on_hold`, `paused`, `past_due`, `cancelled`, `failed`, `expired` |
| current_period_end | datetime | yes |  | Dodo `next_billing_date` |
| cancel_at_period_end | tinyint | no | '0' | Dodo `cancel_at_next_billing_date` |
| cancelled_at | datetime | yes |  |  |
| on_hold_at | datetime | yes |  | Set on the transition to `on_hold`; grace period counts from here |
| expires_at | datetime | yes |  |  |
| last_event_at | datetime | yes |  | Provider timestamp of the last applied event; older events are `stale` |
| last_event_type | varchar | yes |  |  |
| metadata | text | yes |  | JSON; carries `app_user_id`, `plan_key`, `checkout_id` set at checkout |
| created_at | datetime | yes |  |  |
| updated_at | datetime | yes |  |  |

Indexes: `plan_key`, `status`, (`user_id`, `status`).

## Schema: `billing_events`

| Field | Type | Null | Default | Notes |
|-------|------|------|---------|-------|
| id | integer | no |  |  |
| provider | varchar | no | 'dodo' |  |
| event_id | varchar | no |  | Unique; the `webhook-id` header |
| event_type | varchar | no |  | e.g. `subscription.active`, `payment.succeeded` |
| provider_subscription_id | varchar | yes |  | Indexed |
| event_at | datetime | yes |  | Provider timestamp from the payload |
| payload | text | no |  | Full JSON body as received |
| received_at | datetime | no |  |  |
| processed_at | datetime | yes |  |  |
| outcome | varchar | yes |  | `applied`, `ignored`, `stale`, `error` |
| error | text | yes |  | Exception message when `outcome = error` |
| created_at | datetime | yes |  |  |
| updated_at | datetime | yes |  |  |

## Schema: `billing_checkouts`

| Field | Type | Null | Default | Notes |
|-------|------|------|---------|-------|
| id | integer | no |  |  |
| user_id | integer | no |  | FK → `users.id`, cascade on delete |
| plan_key | varchar | no |  |  |
| product_id | varchar | no |  |  |
| provider_session_id | varchar | yes |  | Unique; Dodo checkout session id |
| status | varchar | no | 'created' | `created` → `returned` (customer came back) → `completed` (webhook applied); `abandoned` when the provider call failed |
| error | text | yes |  | Provider response (status and body) when the session could not be created; shown in Admin → Billing |
| created_at | datetime | yes |  |  |
| updated_at | datetime | yes |  |  |

## Interlinks

- **Outbound (accounts):** `billing_customers.user_id`, `subscriptions.user_id`, `billing_checkouts.user_id` → `users.id` ([accounts.md](accounts.md)); rows are deleted with the account. `User` gains `billingCustomer()`, `subscriptions()`, `activeSubscription()`, `planKey()` and `entitled()`.
- **Outbound (subscribers and settings):** provider keys live in `app_settings` (`dodo_environment`, `dodo_api_key`, `dodo_webhook_secret`, `dodo_product_pro_monthly`, `dodo_product_pro_yearly`), encrypted, with environment fallbacks ([subscribers-and-settings.md](subscribers-and-settings.md)).
- **Inbound (alerts):** `saved.server` gates the follow toggle and `alerts.daily` gates the daily send ([alerts.md](alerts.md)). API keys will follow the same pattern.

## Code map

| Piece | Location |
|-------|----------|
| Plan definitions, feature lists, grace period | `config/billing.php` |
| Key resolution (settings over env), environment base URL | `App\Services\Billing\BillingConfig` |
| Plans, product-id lookup, price formatting | `App\Services\Billing\PlanCatalog` |
| Provider calls (checkout session, portal link, product lookup, webhook and product provisioning) | `App\Services\Billing\Contracts\BillingGateway` → `DodoGateway` (official `dodopayments/client` SDK); tests bind an in-memory fake |
| One-click provider setup (endpoint by URL, products by name, keys stored encrypted) | `App\Services\Billing\Provisioner` |
| "Can we sell right now?" probe (asks the provider to open a session, shows its answer, charges nothing, writes no attempt row) | `Backend\Admin\BillingController::probe` |
| Signature verification (Standard Webhooks, HMAC-SHA256, 5-minute tolerance) | `App\Services\Billing\WebhookVerifier` |
| Event storage and subscription mirror | `App\Services\Billing\WebhookProcessor` |
| Access decisions | `App\Services\Billing\Entitlements`, `App\Http\Middleware\EnsureSubscribed` |
| Public pages | `Site\BillingController` (`site/pricing`, `site/billing/redirect`, `site/billing/return`), plan card on `site/account/profile` |
| Webhook endpoint | `Site\BillingWebhookController` |
| Admin | `Backend\Admin\BillingController` (`backend/admin/billing`), Dodo section in Settings |
| Mail | `SubscriptionPaymentFailedMail` (`emails/site/payment-failed`, text alternate) |

## Routes

| Method | Path | Name | Access |
|--------|------|------|--------|
| GET | `/pricing` | `pricing` | public; indexable only while billing is enabled |
| POST | `/billing/checkout/{plan}` | `billing.checkout` | `auth`, `verified`, throttle 10/min; 404 unless the plan is purchasable |
| GET | `/billing/return/{checkout}` | `billing.return` | `auth`, `verified`; owner only |
| POST | `/billing/portal` | `billing.portal` | `auth`, `verified`, throttle 10/min |
| POST | `/webhooks/dodo` | `billing.webhook` | signature-authenticated, no CSRF, throttle 120/min |
| GET | `/backend/admin/billing` | `backend.admin.billing.index` | admin |
| POST | `/backend/admin/billing/check` | `backend.admin.billing.check` | admin |
| POST | `/backend/admin/billing/provision` | `backend.admin.billing.provision` | admin |
| POST | `/backend/admin/billing/probe` | `backend.admin.billing.probe` | admin |

Checkout and portal hand-offs render an interstitial page with a nonce-carrying redirect script and a plain link, because the site's CSP restricts `form-action` to `'self'`.

## Webhook contract

Headers `webhook-id`, `webhook-timestamp`, `webhook-signature` (`v1,<base64>`), signed content `id.timestamp.body`. Payload: `{business_id, type, timestamp, data: {payload_type, subscription_id, product_id, status, next_billing_date, cancel_at_next_billing_date, cancelled_at, expires_at, customer: {customer_id, email, name}, metadata}}`.

Handled: `subscription.active`, `subscription.renewed`, `subscription.plan_changed`, `subscription.on_hold`, `subscription.cancelled`, `subscription.failed`, `subscription.expired`, `subscription.paused` (status taken from `data.status` when valid, otherwise derived from the event name). `payment.*` events are stored with outcome `ignored`. User resolution order: `metadata.app_user_id`, then `customer.customer_id` against `billing_customers`, then `customer.email` (case-insensitive). Unresolvable events are stored as `ignored`.

Responses: 400 for a missing or invalid signature or a stale timestamp (nothing stored); 200 `{outcome}` for applied, ignored, stale and duplicate; 500 when applying failed (the row keeps the error; the provider retries with the same id).

## Operator setup

1. Dodo dashboard → Developer → API keys: create a key for the environment (test-mode first).
2. Admin → Settings → Billing: set the Dodo environment and paste the API key (stored encrypted). Save.
3. Admin → Billing → "Provision webhook and products". The app registers `https://aipolicytracker.org/webhooks/dodo` for every subscription and payment event (reusing an endpoint with that URL), creates "AIPolicyTracker Pro" ($29/month) and "AIPolicyTracker Pro (annual)" ($290/year) as SaaS subscriptions (reusing products with the same name), and stores the signing secret and product ids as encrypted settings. The success message lists what was created or reused.
4. Admin → Billing → "Check products against the provider": both plans must read "Price matches".
5. In test mode, buy a plan with card `4242 4242 4242 4242` and confirm the subscription appears with status `active`; decline with `4000 0000 0000 0002` and confirm nothing is granted.
6. Admin → Settings → Billing → Checkout `on` to open the pricing page for purchase. (Environment variables `BILLING_ENABLED`, `DODO_PAYMENTS_*` and `DODO_PRODUCT_*` remain the fallback for hosts without the settings table.)
### When checkout fails

The customer always sees a neutral message. The provider's own answer is kept on the checkout attempt (`billing_checkouts.error`, listed under Admin → Billing), shown inline on `/pricing` to admins only, and reproducible on demand with Admin → Billing → "Can we sell right now?", which opens a session for the first purchasable plan and prints the result. A refusal there is the provider's verdict, not the application's: typically an unverified business, a product that cannot sell in that environment, or a key without payment permission.

7. Going live: complete business verification in the Dodo dashboard, create a live API key, switch the environment setting to `live_mode`, paste the live key, run provisioning again (live objects are separate from test ones), re-run the product check, and keep Checkout `on`.

## Tests

`tests/Feature/BillingTest.php`: admin provisioning (endpoint and products created once, keys stored encrypted, stored secret verifies real webhooks, rerun reuses); checkout switch in settings overriding the environment; pricing page disabled and enabled states; checkout guards, attempt record and hand-off; provider failure reported not faked; webhook signature rejection (missing, wrong secret, stale timestamp, no secret); signed activation grants entitlements and duplicate delivery is idempotent; unknown product never grants access; cancellation keeps access to period end, expiry revokes; failed renewal grace period and one email; stale events cannot reactivate; unknown customer ignored; email fallback resolution; portal hand-off; `subscribed` middleware; admin page, encrypted settings and product price check.

## Open debt

See `docs/reference/technical-debt.md` #21 to #23.
