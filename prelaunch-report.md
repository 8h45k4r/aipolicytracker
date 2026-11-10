# Pre-launch audit: AIPolicyTracker

> **Status after remediation (9 November 2026): READY WITH CONDITIONS.** Every finding that can be fixed in the repository is fixed and tested; the release blockers (D1, I1e) are closed and the real production image passed its boot and stop test in CI. The conditions are owner and host actions: backups (F13), edge encryption and the origin block (R4, I6), mail settings (F10, F18), and the post-deploy steps listed under "Remediation update". The audit as found at `4d37a09` follows unchanged below it.

## Remediation update (9 November 2026, after the audit)

The owner asked for every finding to be fixed. Ten commits (`e926280..b3f9261`) fix what can be fixed in the repository. The audit sections below are kept as they were at `4d37a09`, so the evidence trail stays intact. **`prelaunch-checklist.csv` now carries the current status of every check**, with the audit-time status recorded in its evidence column.

| Commit | What it fixes |
|---|---|
| `e926280` | I1e (env files kept out of the image, plus a CI check), D1 (obsolete assertion) |
| `7135175` | C2, R1, R2, R8, and R13: CSP-blocked inline handlers broke the deletion confirm and the jurisdiction sort |
| `beb1726` | E13 (mail timeouts), F3 (throttled error alert mail), F7/I8 (supervision, signal forwarding, 60-minute scheduler locks), B13/F10 (production env-check), R10 (rollback image), log rotation |
| `1f838f2` | M1, M3, M5, M6, M9, N6, N7, R5, R6, R7, N4 (terms link only), H4 (disabled label) |
| `9169bef` | C11 (`'strict-dynamic'`), E8, F17 (31 FK indexes), B12, B1, B11, B4, B5, H1, H4 (focus ring), H9 (partly), E9, R11 |
| `0bbdb86` | D4: Playwright smoke suite and the "Browser smoke tests" CI job |
| `2f97b2c` | Three existing tests brought in line with the deliberate behaviour changes (email change now needs the password; the map is a labelled group) |
| `eb74441` | The CI container smoke test passes an https `APP_URL`, which the new production start-up check requires |
| `eaadf16` | The entrypoint's stop sequence is bounded, so the container always exits cleanly. The real-image smoke test showed `docker stop` timing out: a non-interactive shell starts background jobs with SIGQUIT ignored. Reproduced and fixed in an Alpine container |
| `b3f9261` | The image stops with SIGTERM, and the entrypoint also handles SIGQUIT. The official php-fpm base image sets `STOPSIGNAL SIGQUIT`, so `docker stop` was never reaching the entrypoint's handler. Reproduced in `php:8.3.36-fpm-alpine` as `www-data`: 31 s and exit 137 before; 12–13 s and exit 0 after, with php-fpm finishing gracefully |

Verification after the fixes:
- **Full PHP suite (isolated worktree at `0bbdb86`):** 950 passed and 3 failed. All 3 were tests of behaviour changed on purpose; they were updated in `2f97b2c` and re-run in their files (38 passed). The other 950 ran against the same application code. CI runs the full suite again on `main`.
- **Node tests:** 18/18 passed.
- **ESLint and Pint:** pass.
- **Browser smoke tests:** 7/7 passed against a local site under the production CSP.
- **Supervise and stop logic:** tested under dash and under Alpine's busybox and in the official php-fpm Alpine image as `www-data` (stop in 12–13 s with exit 0 under SIGTERM or SIGQUIT; before the fixes it ran to SIGKILL, exit 137).
- **FK index migration:** verified up and down on a fresh schema.
- **Security workflow on the branch** (builds the real Alpine image and runs the container smoke test and the env-file check): **green** (run 42675779872 at `b3f9261`: gitleaks, Semgrep, dependency audits, IaC, ZAP DAST, and the container job: image build, no env file in the image, PHP pin, boot against PostgreSQL with every probe 200, 8 parallel requests, cache headers, dot files and stray PHP 404, healthy, clean stop with exit 0, Trivy image scan clean). The two earlier runs failed and drove `eb74441`, `eaadf16` and `b3f9261`.

Status counts after remediation:

| Category | Checks | PASS | FAIL | NEEDS_VERIFICATION | NOT_APPLICABLE | DISABLED |
|---|---|---|---|---|---|---|
| A Secrets & Environment | 9 | 8 | 0 | 0 | 1 | 0 |
| B Code Quality | 18 | 16 | 1 | 0 | 1 | 0 |
| C Security | 35 | 31 | 1 | 1 | 2 | 0 |
| D Tests | 4 | 4 | 0 | 0 | 0 | 0 |
| E Build & Performance | 15 | 11 | 1 | 1 | 2 | 0 |
| F Reliability | 21 | 18 | 1 | 2 | 0 | 0 |
| G Codebase Hygiene | 14 | 14 | 0 | 0 | 0 | 0 |
| H Accessibility | 9 | 8 | 0 | 1 | 0 | 0 |
| I Deploy Config | 15 | 10 | 1 | 0 | 4 | 0 |
| J SEO & Meta | 14 | 12 | 0 | 1 | 1 | 0 |
| K PWA & Service Worker | 5 | 1 | 0 | 0 | 4 | 0 |
| L E-commerce Tracking | 13 | 0 | 0 | 0 | 13 | 0 |
| M Billing & Subscription | 9 | 9 | 0 | 0 | 0 | 0 |
| N Legal & Compliance | 7 | 6 | 1 | 0 | 0 | 0 |
| O Agent Readiness | 22 | 9 | 0 | 0 | 13 | 0 |
| X Additional reviewer checks | 13 | 10 | 1 | 2 | 0 | 0 |
| **Canonical total** | **210** | **157** | **6** | **6** | **41** | **0** |
| **All (incl. reviewer)** | **223** | **167** | **7** | **8** | **41** | **0** |

**Still open** (FAIL or NEEDS_VERIFICATION), and why:

| ID | Severity | Status | Check |
|---|---|---|---|
| F13 | High | FAIL | No automated database backups |
| I6 | High | FAIL | Nginx hardening (tokens, gzip, SSL, rate limit) |
| R4 | High | FAIL | Edge-to-origin encryption (Cloudflare SSL mode) |
| F10 | Medium | NEEDS_VERIFICATION | Emails sent without a transactional email provider |
| F18 | Medium | NEEDS_VERIFICATION | Sending domain cannot use custom SPF/DKIM |
| N4 | Medium | FAIL | No refund/cancellation policy at checkout |
| R3 | Medium | NEEDS_VERIFICATION | Client IP accuracy behind the Cloudflare Worker |
| B7 | Low | FAIL | Overly complex functions (complexity > 15) |
| C23 | Low | NEEDS_VERIFICATION | Outdated TLS versions allowed |
| C25 | Low | FAIL | Sensitive PII fields stored unencrypted |
| E11 | Low | NEEDS_VERIFICATION | Response compression not enabled |
| H9 | Low | NEEDS_VERIFICATION | Touch targets smaller than 24x24px |
| J14 | Low | NEEDS_VERIFICATION | Google Search Console verification missing |
| R12 | Low | NEEDS_VERIFICATION | Container memory budget on HEAD |
| E12 | Informational | FAIL | Modern image formats (WebP/AVIF) not configured |

**Not fixed in code, deliberately:**

| ID | Why it was not fixed | What would close it |
|---|---|---|
| C25 (alert-channel secrets in plaintext) | Encrypting it means rewriting existing user rows, which the owner has said not to touch. | An owner decision, then a migration that encrypts in place. |
| B7 (two very long methods) | A refactor of the data validator and importer is a risk with no user-visible gain right now. | A separate change. |
| E12 (WebP images) | Optional; the photos are already small. | — |
| N4 (refund and renewal terms) | Legal text is the owner's to write. | The owner writes the terms or links the merchant of record's. |

**Owner and host actions** (unchanged in kind):
- **F13:** backups and a rehearsed restore.
- **R4 and I6:** origin certificate, Cloudflare Full (strict), the hardened server block.
- **F10 and F18:** mail provider settings and SPF/DKIM.
- **R3:** check the client IP behind the Worker.
- **C23 and E11:** edge TLS and compression settings.
- **J14:** Search Console verification.
- **After this deploy:**
  - prune old images and decide on key rotation (I1e);
  - reinstall `aip-deploy` (R10);
  - re-run Billing → Provision before enabling billing (M1).


---


Audit date: 9 November 2026. Audit type: read-only. No application code was changed during the audit, and nothing was committed, pushed or deployed.

## 1. Executive summary

**Recommendation: NOT READY** for this commit as it stands. With two small repository fixes and owner action on infrastructure, it becomes **READY WITH CONDITIONS** (see section 6).

**Basis for the recommendation:**
- The assessed commit fails its own test suite: 918 passed, 1 failed.
- Separately, the production image build copies the production secrets file into the image, because of a `.dockerignore` pattern gap. This was reproduced.
- Three further High-severity risks concern production infrastructure:
  - no verified backups;
  - an unencrypted edge-to-origin hop;
  - an origin reachable around the CDN.

  The repository documents all three as open; the host was not inspected.
- Security fundamentals are otherwise strong:
  - authorization on every admin route;
  - nonce-based CSP;
  - SSRF guard with DNS pinning;
  - signed URLs;
  - HMAC-verified webhooks;
  - no high or critical dependency advisories;
  - a broad, passing test suite (except the one regression);
  - clean lint and a reproducible build.

**Verified release blockers.** Fix these before the next deploy:

| # | Finding | Severity | Status |
|---|---|---|---|
| D1 | `tests/Feature/SeoIndexQualityTest.php:211` reads `resources/views/app.blade.php`, which unpushed commit b2f46ca deleted. CI would be red and the deploy gate would refuse it. | Medium | Confirmed by a full-suite run |
| I1e | `.dockerignore` excludes `.env` only at the context root. Production keeps its environment in `deploy/.env` inside the build context, so `COPY . .` puts APP_KEY, database, mail and billing secrets into the image. This affects current production too. | High | Reproduced with a scratch build using the repo's `.dockerignore` and dummy files |

**Verified high risks that need owner action** (outside the repository; evidence is the project's own debt register):
- **F13:** no automated or off-host backup is confirmed, no restore has been rehearsed, and the uploads volume is never backed up.
- **R4:** Cloudflare SSL mode is Flexible, so the edge-to-origin hop is unencrypted.
- **I6:** the hardened origin server block is not installed, so the origin accepts port 80 from any address and bypasses edge protections.

**Important unverified risks:**
- **R3:** client IP accuracy behind the Cloudflare Worker. If wrong, every per-IP limit is shared by all visitors.
- **R9:** the new nginx and php-fpm image has not yet been built from the real Alpine base. CI will do this.
- **F10:** the production mailer setting.
- **F18:** SPF and DKIM records.
- **C23:** the TLS minimum version at the edge.
- **R12:** the container memory budget.

**Must be fixed before billing is switched on** (billing is currently disabled, so these do not block this release):
- **M1:** refund and dispute webhooks are not subscribed.
- **N4:** there are no subscription, renewal or refund terms at checkout.
- **N7:** account deletion does not cancel the provider subscription.
- **N6:** the data export omits billing records.
- **M3 and M9:** a webhook can be stranded after a crash, and failure emails are sent synchronously inside the transaction.

## 2. Scope and architecture

**Commits:**
- **Assessed:** `4d37a0966d632e3ecbfd4375d301005166330332`, the local tip of `claude/affectionate-volta-fx0dxa` (2026-11-09 17:05 UTC).
- **Production runs:** `origin/main` = `baa4946`.
- **Not yet pushed:** the 10 commits between them, including an nginx + php-fpm server change awaiting owner authorisation.
- **Where verdicts differ:** if a verdict differs between the two (for example E7, F7), the evidence column says so.

**Stack:**
- Laravel 12.70 on PHP 8.3 (image `php:8.3.36-fpm-alpine` at HEAD; `cli-alpine` with `artisan serve` on main).
- Server-rendered Blade with small plain-JS modules, built by Vite and Tailwind 3 (build image Node 26.12.1).
- PostgreSQL 16 in Docker; SQLite for tests and the gating CI job.
- Composer and npm, each with a committed lockfile.

**Entry points (331 routes):**

| Group | Routes | Notes |
|---|---|---|
| Public site | 170 | |
| Auth and account | 22 | |
| Admin | 103 | Session + admin role + TOTP second factor + capability gates |
| Read-only API | 32 | `/api/v1`, public and Sanctum tokens, throttled |
| Inbound | 4 | Dodo billing webhook (Standard Webhooks HMAC) and `/cron/*` (bearer token) |

**Background processes:**
- Scheduler and queue worker in the app container.
- Local stdio MCP server (`agent/server.mjs`) that calls the public API.
- Cloudflare Worker in front of the origin.

**Integrations:**
- Dodo Payments, as merchant of record. Billing is disabled.
- Resend (mail).
- Cloudflare: Turnstile, Web Analytics, cache purge.
- Google Analytics 4, consent-gated.
- X posting (off by default).
- IndexNow.
- AI Incident Database sync.

**Trust boundaries:**
- internet → Cloudflare → host nginx → container;
- anonymous → authenticated reader → admin (2FA);
- inbound webhooks and cron;
- user-supplied outbound webhook URLs (SSRF-guarded).

**Deployment target:** a single Docker host (`deploy/compose.yml`), deployed by a forced-command SSH script with probes and rollback, gated by the CI and Security workflows.

**Checklist source:**
- GoShipIt canonical template `skills/goshipit/assets/template.md`, fetched from `raw.githubusercontent.com/Capta1nRaj/goshipit/HEAD` on 2026-11-09 as data only (sha256 `6c7dccab5b71743491465b20067e1b355fecb7c8574fc032d9b2c998ac376f99`).
- The commit SHA could not be recorded: the GitHub API is not enabled for this session.
- The repository has no `goshipit.md`, so the template defaults apply: all 210 checks enabled, `fix_mode: audit-only`.
- Deviation: the template default `build: no` was overridden because the brief asked for build verification. The build ran into a scratch directory, so the tree was not modified.
- 12 additional reviewer checks (R1–R12) are reported separately and not counted as canonical.

**Excluded systems and constraints:**
- Not touched:
  - the production host;
  - Cloudflare;
  - DNS;
  - the payment provider;
  - email delivery;
  - analytics services.
- Not sent:
  - real emails;
  - payments;
  - webhooks;
  - tracking events.
- **Runtime checks:** these ran against a local copy at `http://127.0.0.1:8123` (`php artisan serve`, APP_ENV=local, APP_DEBUG=true, SQLite, mail=log, billing off). Production-only behaviour was judged from code and configuration.
- **Tools available:** semgrep, Trivy, gitleaks, PHPStan, axe, Lighthouse and shellcheck are not installed, and nothing was installed, so no scan from those tools is claimed. The repository's CI runs several of them on every push.
- **Docker:** the Alpine package mirrors are unreachable from the sandbox, so the real production image could not be built locally.
- **Node:** local Node is 22; the project pins 26.

## 3. Coverage

Every heading in the table below is a category, not a check. The checks are the 210 canonical checks (rows A–O in the CSV) plus 12 reviewer checks (R1–R12).

| Category | Checks | PASS | FAIL | NEEDS_VERIFICATION | NOT_APPLICABLE | DISABLED |
|---|---|---|---|---|---|---|
| A Secrets & Environment | 9 | 8 | 0 | 0 | 1 | 0 |
| B Code Quality | 18 | 10 | 7 | 0 | 1 | 0 |
| C Security | 35 | 29 | 3 | 1 | 2 | 0 |
| D Tests | 4 | 2 | 2 | 0 | 0 | 0 |
| E Build & Performance | 15 | 9 | 3 | 1 | 2 | 0 |
| F Reliability | 21 | 15 | 4 | 2 | 0 | 0 |
| G Codebase Hygiene | 14 | 14 | 0 | 0 | 0 | 0 |
| H Accessibility | 9 | 8 | 0 | 1 | 0 | 0 |
| I Deploy Config | 15 | 8 | 3 | 0 | 4 | 0 |
| J SEO & Meta | 14 | 12 | 0 | 1 | 1 | 0 |
| K PWA & Service Worker | 5 | 1 | 0 | 0 | 4 | 0 |
| L E-commerce Tracking | 13 | 0 | 0 | 0 | 13 | 0 |
| M Billing & Subscription | 9 | 5 | 4 | 0 | 0 | 0 |
| N Legal & Compliance | 7 | 4 | 3 | 0 | 0 | 0 |
| O Agent Readiness | 22 | 9 | 0 | 0 | 13 | 0 |
| X Additional reviewer checks | 12 | 0 | 9 | 3 | 0 | 0 |
| **Canonical total** | **210** | **134** | **29** | **6** | **41** | **0** |
| **All (incl. reviewer)** | **222** | **134** | **38** | **9** | **41** | **0** |

**Disabled checks:** none. No project `goshipit.md` exists, so all 210 are enabled by default.

**Not applicable (41), with reasons:**
- **L1–L13:** there is no product catalogue, cart, GA4 e-commerce, Meta Pixel or server-side tracking. The single subscription is sold on hosted checkout, which is disabled.
- **K2–K5:** there is no service worker; this is a server-rendered content site.
- **O2, O6, O9, O10, O11, O14–O20, O22:** optional agent-discovery protocols that are not implemented. These are product decisions, not defects. The MCP server is local stdio; the API has no OAuth; nothing is sold to agents.
- **A7:** single production deployment with a per-host env file.
- **B17, E2:** no TypeScript.
- **E15:** no client framework after the React removal.
- **C21:** no GraphQL or WebSockets.
- **C26:** single-tenant.
- **I2, I3:** no Vercel, Render or Fly configuration.
- **I10, I11:** no Kubernetes.
- **J12:** no Ads or Meta tags; GA is not injected before opt-in.

## 4. Findings

Severity counts for FAIL and NEEDS_VERIFICATION rows, including reviewer checks:

| Severity | Count |
|---|---|
| High | 4 |
| Medium | 15 |
| Low | 25 |
| Informational | 3 |

The CSV has every check with its evidence.

### 4.1 Confirmed: fix before deployment

**I1e: production secrets copied into the Docker image** (High, High confidence)
- **Evidence:**
  - `.dockerignore:8-9` (`.env`, `.env.*`) matches only the context root.
  - The build context is the repo root (`deploy/compose.yml:56-58`).
  - The env file is `deploy/.env` (`compose.yml:67`).
  - `COPY . .` appears at `Dockerfile:14` and `:43`.
- **Reproduction (safe, done):**
  1. A `FROM scratch` build of a scratch context containing the repo's `.dockerignore` and dummy `.env`, `deploy/.env` and `deploy/.env.backup`.
  2. `docker export` listed `ctx/deploy/.env` and `ctx/deploy/.env.backup`; the root `.env` was excluded.
- **Impact:** anyone with access to the image, its layers, the build cache or a future registry push obtains APP_KEY and the database, mail and billing credentials. These can forge sessions and signed URLs and decrypt stored secrets.
- **Mitigations:** the file is outside the web root; the image is built and kept on the host only.
- **Fix:**
  1. Add `**/.env`, `**/.env.*`, `!.env.example` (and `deploy/`) to `.dockerignore`.
  2. Rebuild, and prune old images and the build cache.
  3. Check with `docker run --rm --entrypoint ls aip-app:latest -la deploy/.env` (should fail).
  4. Rotate APP_KEY and the provider keys if an image or cache ever left the host. Rotating APP_KEY invalidates sessions and signed links; plan for that.
- **Regression test:** a CI step that builds with a dummy `deploy/.env` and asserts it is absent from the image.

**D1: test suite fails** (Medium, High confidence)
- **Evidence:** a full run in an isolated worktree at aac6e42 gave 918 passed, 1 failed (`SeoIndexQualityTest.php:211`). The test still asserts on the Inertia root view that commit b2f46ca removed.
- **Fix:** remove that one obsolete assertion. The file it guards no longer exists, and the rest of the test still covers the token-route behaviour. Then re-run the suite.
- **Limitation:** the PostgreSQL CI job was not run locally.

### 4.2 Confirmed: High, owner action outside the repository

| ID | Finding | Evidence | Fix |
|---|---|---|---|
| F13 | No confirmed automated or off-host backup; restore never rehearsed; uploads volume not backed up | `deploy/backup.sh`; `cron-install.sh:79-96` installs cron only with `BACKUP_RCLONE_REMOTE`; debt #33 | Set the off-host remote, install the cron, rehearse `--verify-latest`, record the date, add an `aip-storage` backup |
| R4 | Edge-to-origin hop unencrypted (Flexible) | Debt #34 | Install the origin certificate, then switch Cloudflare to Full (strict) |
| I6 | Hardened origin block not installed; port 80 reachable around the CDN | Debt #36 | Install `deploy/nginx-aip-docker.conf`, run `nginx -t`, check that a non-edge request gets 403 |

These rows are confirmed from the repository's own records. The host and Cloudflare state was not inspected.

### 4.3 Confirmed: Medium

| ID | Finding | Key evidence | Minimal fix | Regression test |
|---|---|---|---|---|
| C2 | Login has no per-IP limit, so password spraying is possible; reset-password, password update and profile delete are unthrottled | `routes/auth.php:26,40,73`; `LoginRequest.php:95-98`; 15 distinct-email attempts got no lockout (local) | IP-keyed login limiter; `throttle:5,1` on the other three routes | 21 logins with distinct emails from one IP → 429 |
| E13 | Mail sent inside the request with no timeout; a provider stall can occupy all PHP workers | `vendor/resend/resend-php/src/Resend.php` (`new GuzzleClient()`); `TemplateController.php:353` and others | Guzzle timeout of about 10 s, or queue the mail | Fake transport that hangs → response within the time limit |
| F3 | No error tracking or 5xx alerting | No package or config | Error tracking or log alerting; log rotation | — |
| F7 | On stop, the queue worker, scheduler and imports are killed; scheduler mutexes can block a job for up to 24 h | `docker/entrypoint.sh:99-106`; `routes/console.php` | Forward SIGTERM; `stop_grace_period`; `withoutOverlapping(N)` | Stop during a job → lock released |
| I8 | Nothing restarts a dead queue worker or scheduler | entrypoint | Supervise loop or separate services | — |
| D4 | No browser or end-to-end tests | No config; `npm test` covers the MCP server and pure helpers only | Small Playwright smoke suite in CI | — |
| F10 | Production mailer may be `log` | `config/mail.php:17`; env-check | env-check fails on `log` in production | EnvCheck test |
| R3 | Client IP behind the Worker may be shared | `cloudflare/worker.js` | Verify in origin logs | — |
| R9 | New server image not yet built on Alpine | Sandbox constraint | Let CI build it and run the smoke test | Smoke test in `security.yml` |

**Before enabling billing:** M1, N4, N6 and N7 are Medium.
- **M1:** `Provisioner.php:18-22` omits `refund.succeeded` and `dispute.*`.
- **N4:** no renewal or refund terms at checkout.
- **N6:** the export omits billing and email-keyed records.
- **N7:** deletion does not cancel the provider subscription and leaves billing payloads and email-keyed rows.

### 4.4 Confirmed: Low and Informational

| ID | Finding | Fix |
|---|---|---|
| B1 | Webhook exception messages, which can contain Slack URLs, are logged | Log the host and channel kind only |
| B5 | Unused dependencies: typescript, breeze, sail | Remove them |
| B7 | Complex methods: `PolicyDataValidator::run` and `PolicyImporter::importPolicies` | Split per section |
| B11 | Unhandled promises: clipboard, map import, and the Worker's upstream fetch | Add catch handlers; Worker returns 502 |
| B12 | Unpaginated API lists: enforcement, implementation measures and others | Paginate the growing ones |
| B13 | Start-up env check does not refuse a localhost APP_URL or APP_DEBUG=true in production | Extend `EnvCheckCommand` |
| C11 | CSP allowlists the GTM host without `strict-dynamic` | Add `'strict-dynamic'` |
| C25 | Alert channel endpoints and secrets stored in plaintext | Encrypted cast; hashed feed token |
| E8 | N+1 on jurisdiction pages (12 extra queries) | Eager-load |
| F17 | 31 foreign keys without an index | Add the indexes |
| M3 | Webhook stranded by a crash between insert and processing | Retry stale rows |
| M6 | Checkout session created without an idempotency key | Reuse the session or add a key |
| M9 | Synchronous mail inside the webhook transaction | Queue the mail |
| R1 | Reset form reveals whether an account exists | Return a generic response |
| R2 | Email change without re-authentication | Require the current password |
| R5 | GA can record signed unsubscribe URLs | Extend the route exclusion list |
| R6 | Privacy statement on payment data is inaccurate; processors not named | Correct it; legal review |
| R7 | Analytics consent cannot be withdrawn | Add a preferences control |
| R8 | Wrong-password error on account deletion is not shown (error bag mismatch) | `validateWithBag` |
| R10 | Rollback rebuilds rather than reusing the previous image | Tag images by commit |

Informational items:
- **B4:** `null-dash` component is unused.
- **E12:** no WebP or AVIF images.
- **R11:** stale wording in `.env.example`.

### 4.5 Validation requests (NEEDS_VERIFICATION)
- **C23:** minimum TLS version at the edge.
- **E11:** edge compression.
- **F18:** SPF and DKIM.
- **H9:** 80 crowded touch targets on mobile; manual check needed.
- **J14:** Search Console verification.
- **R12:** memory under import load.
- **R3, R9, F10:** described above.

### 4.6 Claims checked and rejected
The audit agents made three claims that did not hold up and are not reported:
- That AGENTS.md still describes React account screens. It does not at HEAD.
- That the Security workflow does not gate deploys. `deploy.yml:95-112` requires a green Security run.
- That dependency advisories (debt #54, `braces`) are still present. Today's npm audit no longer reports them.

## 5. Validation log

All commands ran locally. Results:

| Command or check | Result |
|---|---|
| `git rev-parse HEAD` / `origin/main` | `4d37a09` / `baa4946` |
| Fetch GoShipIt template (raw GitHub) | 200, 12,228 bytes. GitHub API returned 403 (not enabled for the session), so no commit SHA. `goshipit.md` returned 404 upstream |
| Security tool discovery | semgrep, Trivy, gitleaks, trufflehog, osv-scanner, snyk, PHPStan, psalm, shellcheck, actionlint, hadolint, checkov, ZAP, axe, pa11y and Lighthouse are **absent**. phpunit and pint are present |
| Env safety check (key names only, values never printed) | APP_ENV=local, MAIL_MAILER=log, BILLING_ENABLED=false, DODO test_mode |
| `php artisan test` (full suite, isolated worktree at aac6e42, SQLite) | **918 passed, 1 failed, 0 skipped**, 38,429 assertions, 1,526 s |
| `php artisan test --filter analytics_never_loads` (re-run) | Fails: `file_get_contents(resources/views/app.blade.php)` no such file |
| `composer audit --format=json` | 0 advisories, 0 abandoned |
| `npm audit --json` | 6 moderate (dev-only: eslint/minimatch GHSA-g3hv-75wg-m249; glob GHSA-3g3m-2fjr-6pw4 via sucrase). 0 high, 0 critical. Source: GitHub Advisory Database via npm, retrieved 2026-11-09 |
| `npm audit --omit=dev` | 0 vulnerabilities |
| `composer validate --no-check-publish` | valid |
| `composer lint` (Pint) | passed |
| `npm run lint` (ESLint) | exit 0 |
| `npm test` | 15/15 passed |
| `npx vite build --outDir <scratch>` | exit 0; one >500 kB chunk warning; **byte-identical** to the committed `public/build` (`diff -r` exit 0); no sourcemaps |
| `curl -sI` local pages | Headers seen: CSP (nonce), XFO, nosniff, Referrer-Policy, Permissions-Policy, COOP; HttpOnly/SameSite cookies; API `X-RateLimit-Limit: 120` |
| `php artisan route:list --json` | 331 routes |
| Scratch Docker build with the repo `.dockerignore` | `deploy/.env` and `deploy/.env.backup` included in the image (I1e) |
| Source spot checks | Provisioner events, `ProfileController::destroy`, auth route throttles, Resend client, `deploy.yml` gates, ProfileTest error bag: all as reported |

Delegated review agents (read-only, with command logs in the session scratchpad) ran these checks:
- **Secrets:** git-history secret regex scan.
- **Code quality:** env-key comparison, complexity estimates, duplicate-window scan, OpenAPI/route parity.
- **Security:** route middleware analysis and source tracing for C1–C35, plus local probes:
  - 15 failed logins;
  - 12 reset attempts;
  - one forgot-password POST;
  - cron and webhook requests.

  All went to the local server only, with mail logged locally.
- **Accessibility (Playwright DOM checks, not axe):**
  - 17 public pages at 1440 and 390 px, plus dark-mode samples;
  - 4 admin pages;
  - keyboard tab walks and dialog Escape/focus tests.
- **Performance:** query counts on 13 pages against a scratch database copy; foreign-key index scan with PRAGMA; header and cache checks; sitemap validation (xmllint, 3,360 URLs, 185 sampled URLs fetched); JSON-LD parsing.

**Not run:**
- the PostgreSQL test job;
- the Alpine production image build (mirrors blocked);
- DAST/ZAP;
- semgrep, Trivy and gitleaks (not installed; they run in CI);
- axe and Lighthouse;
- screen readers;
- Windows script checks.

The command `gh` was not used.

## 6. Remediation plan

**Fix before deployment** (repository changes, each with its regression test):
1. **D1:** remove the obsolete assertion and re-run the full suite.
2. **I1e:** fix the `.dockerignore` patterns and add the CI check. On the host: rebuild, prune images and build cache, and decide on key rotation.
3. **R9:** let the Security workflow build and smoke-test the nginx + php-fpm image, then authorise its deploy.
4. **C2:** add the login per-IP limiter and the throttles.
5. **E13:** add a mail timeout.

**Fix soon after deployment:**
- Owner/host: F13 backups; R4 and I6 edge and origin; F10 and F18 mail configuration; R3 IP check.
- Then F7 and I8 process supervision and signal forwarding; F3 error tracking.
- Then B13 env-check hardening; R1, R2, R5, R8; C25; C11; E8; F17; B1; B11; D2 gaps; D4 smoke suite.

**Before enabling billing:**
- M1, M3, M6, M9, N4, N6, N7.
- R6 and R7 privacy items, with legal review.

**Optional:** B4, B5, B7, B12, E12, H4 and H9 refinements, R10, R11, R12, and the agent-discovery protocols (product decisions).

## 7. Residual risk

**What this audit could not establish:**
- Production host, Cloudflare and DNS state. The High infrastructure findings rely on the project's debt register.
- Whether secrets have already left the host inside an image.
- Runtime behaviour of the real Alpine image.
- Production environment values.
- Exploitability beyond the local reproductions described above.

**Not established by this audit, and needing separate work:**
- WCAG 2.2 AA conformance. The automated checks cannot establish it; a manual audit with assistive technology is needed.
- Legal compliance of the privacy policy, terms, data export and deletion, refund terms, and retention. These need counsel.
- Load behaviour.
- Static analysis coverage. No PHPStan was available; CI's semgrep and CodeQL results were not reviewed.

**History scan limit:** the visible git history starts on 2026-10-05, so earlier secret leaks in rewritten history cannot be ruled out.
