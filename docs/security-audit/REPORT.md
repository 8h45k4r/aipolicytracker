# Security Audit Report — aipolicytracker

## Run metadata

| Field | Value |
|-------|-------|
| Profile | standard |
| Scope | Full repository (no path restriction) |
| Budget | Unbounded |
| Source ref | `96a7b706d05a5cb7b7ce8283dd02bcf718d831bc` |
| Execution policy | Sandboxed source-and-local-only |
| Prior runs | None |
| Deferred coverage | None |
| Out-of-scope | MCP agent server (`agent/server.mjs`) — development tool, not user-facing |
| Carried confirmations | None (first run) |

This is a complete standard-profile pass. All 15 deterministic coverage units received independent review.

## Security posture summary

The application demonstrates a mature security posture for a content-publishing platform with subscription gating. Admin access is defended in depth with role + 2FA + capability-scoped authorization. CSRF protection is correctly applied with explicit exemptions only for stateless webhook and cron routes, each independently authenticated. Content Security Policy uses nonce-based `script-src`. Billing webhooks verify HMAC-SHA256 signatures via StandardWebhooks. Outbound URL fetching has SSRF protection with DNS pinning. CI runs six automated security scans (Gitleaks, Semgrep, SCA, Checkov, Trivy, ZAP).

The single unresolved lead concerns the default proxy trust configuration, whose exploitability depends on the production reverse-proxy architecture. No confirmed vulnerabilities were found during this audit.

## Confirmed findings

None.

## Needs validation

| # | Title | Trace | Blocker |
|---|-------|-------|---------|
| 1 | TRUSTED_PROXIES=\* default trusts all upstream proxy headers when deployed without shipped nginx | `config/app.php:56` → `TrustProxies.php:30` → `LoginRequest.php:97` | Production reverse-proxy architecture not source-visible |

### NV-1: TRUSTED_PROXIES=\* default trusts all upstream proxy headers

**Repository trace:**
- `config/app.php:56` — `'trusted_proxies' => env('TRUSTED_PROXIES', '*')` defaults to wildcard
- `app/Http/Middleware/TrustProxies.php:30` — `proxies()` returns `'*'` to Laravel, accepting `X-Forwarded-For` from any IP
- `app/Http/Requests/Auth/LoginRequest.php:97` — `throttleKey()` constructs rate-limit key as `email|$this->ip()`, where `ip()` returns attacker-controlled value under wildcard trust
- `.env.example:99` — `TRUSTED_PROXIES=*` propagates the wildcard default to new deployments

**Blocker:** Whether the production application process is reachable only through one of the shipped nginx configurations (which force or validate `X-Forwarded-For`) cannot be determined from source alone. If the app is exposed without those configs, the wildcard trust enables IP spoofing affecting rate limits and audit logs.

**Mitigating controls visible in source:**
- Bare-metal nginx (`deploy/nginx-aip.conf:140`) forces `HTTP_X_FORWARDED_FOR` to `$remote_addr`
- Docker nginx (`deploy/nginx-aip-docker.conf:54`) restricts origin access to Cloudflare IP ranges via `geo` block
- `TrustProxies` accepts only `HEADER_X_FORWARDED_FOR | HEADER_X_FORWARDED_PROTO` (line 25), not all forwarded headers

**Bounded local validation plan:** In a local development instance without a reverse proxy, send a POST to `/login` with `X-Forwarded-For: 1.2.3.4`. Verify that `$request->ip()` returns `1.2.3.4` instead of the actual connecting IP. Then trigger 5 failed login attempts with one `X-Forwarded-For` value and confirm that switching to a different value resets the rate limit counter.

**Owner-observed deployment check:** Verify that the production application process (port 8080 in Docker, PHP-FPM socket in bare-metal) is not directly reachable from untrusted networks. Confirm that the reverse proxy in front of the application is one of the shipped nginx configurations or an equivalent that forces `X-Forwarded-For` to the real remote address. If behind Cloudflare plus the shipped Docker nginx, or behind the bare-metal nginx that rewrites `HTTP_X_FORWARDED_FOR` to `$remote_addr`, the wildcard is safe.

## Hardening notes

1. **CSP style-src uses `unsafe-inline`** (`app/Http/Middleware/SecurityHeaders.php`). While this does not bypass the nonce-based `script-src`, it weakens CSP against CSS-based data exfiltration in theoretical injection scenarios. Consider migrating to nonce-based or hash-based style loading.

2. **Sanctum API token default expiry is 1 year** (`config/sanctum.php`, 525600 minutes). Consider reducing the token lifetime to limit the window of exposure if a token is compromised.

3. **55 models use `$guarded = []`** — accepted risk per Semgrep triage. No controller passes unfiltered request input to `create`/`fill`. The `User` model itself uses explicit `$fillable`. Monitor for new controllers that might introduce mass-assignment paths.

4. **RSS alert feed token in URL path** (`app/Http/Controllers/Site/AlertChannelController.php`). The token is visible in server access logs and `Referrer` headers. This is a standard RSS pattern, but the token is long-lived. Consider adding token rotation or using a header-based mechanism for non-RSS clients.

5. **Public API sets `Access-Control-Allow-Origin: *` directly on responses** (`app/Http/Controllers/Api/V1/PublicApiController.php`), bypassing the `cors.php` config. Safe for read-only public data, but creates a maintenance gap if authenticated endpoints are added to the same controller.

## Positive source patterns

- Admin routes enforce a four-layer middleware stack: `auth` + `isAdmin` + `admin.2fa` + `admin.audit` with capability-scoped authorization via `can:<capability>`
- Owner identity is anchored to `ADMIN_EMAILS` env list with verified email; cannot be granted or revoked through the database
- User management refuses to act on owners or self, preventing privilege escalation
- Billing webhook verification uses StandardWebhooks HMAC-SHA256 with constant-time comparison
- Cron endpoints require bearer token matching `CRON_TOKEN` env variable
- Registration and template downloads protected by Turnstile CAPTCHA
- `OutboundUrl` service provides SSRF protection with DNS pinning and private-range blocking
- Docker runtime uses `USER www-data` with read-only docroot and database on isolated bridge network
- CI deploys gated on both CI and Security workflow green status
- All GitHub Actions pinned by SHA with minimal permissions (`contents: read`)

## Coverage summary

| Status | Count |
|--------|-------|
| Covered (no candidates) | 14 |
| Candidate (needs validation) | 1 |
| Blocked | 0 |
| Deferred | 0 |
| **Total** | **15** |

All 15 coverage units received source review. The single candidate unit ("Obvious things" — trusted proxy configuration) produced the needs-validation finding above. No coverage gaps remain unaddressed.
