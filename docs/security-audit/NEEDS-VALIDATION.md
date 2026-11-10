# Needs Validation — aipolicytracker

## NV-1: TRUSTED_PROXIES=\* default trusts all upstream proxy headers when deployed without shipped nginx

**Fingerprint:** `trusted-proxies-wildcard/config-app-56`

### Source trace

1. **Entrypoint** — `config/app.php:56`
   `'trusted_proxies' => env('TRUSTED_PROXIES', '*')` sets wildcard as the fallback when the environment variable is unset.

2. **Propagation** — `app/Http/Middleware/TrustProxies.php:30`
   `proxies()` reads `config('app.trusted_proxies', '*')` and returns `'*'` to Laravel, which then accepts `X-Forwarded-For` and `X-Forwarded-Proto` from any IP. Accepted headers limited to `HEADER_X_FORWARDED_FOR | HEADER_X_FORWARDED_PROTO` (line 25).

3. **Sink** — `app/Http/Requests/Auth/LoginRequest.php:97`
   Rate limit key constructed as `email|$this->ip()` where `ip()` returns the attacker-controlled `X-Forwarded-For` value under wildcard proxy trust.

### Verified evidence

- `config/app.php:56` — `'trusted_proxies' => env('TRUSTED_PROXIES', '*')` sets wildcard as the fallback when the environment variable is unset.
- `app/Http/Middleware/TrustProxies.php:25` — Accepted headers limited to `HEADER_X_FORWARDED_FOR | HEADER_X_FORWARDED_PROTO`, reducing scope but not eliminating the IP spoofing vector when wildcard trust is active.
- `.env.example:99` — `TRUSTED_PROXIES=*` in the example environment file propagates the wildcard default to new deployments.
- `deploy/nginx-aip.conf:140` — Bare-metal nginx forces `fastcgi_param HTTP_X_FORWARDED_FOR $remote_addr`, overwriting any client-supplied value — mitigates the wildcard when this config is in use.
- `deploy/nginx-aip-docker.conf:54` — Docker nginx uses `geo $realip_remote_addr` to reject non-Cloudflare traffic, ensuring only edge-rewritten headers reach the app — mitigates the wildcard when this config is in use.

### Additional affected sinks

- `app/Providers/AppServiceProvider.php:101,110` — Rate limiters for `admin` and `public-api` use `$request->ip()`
- `app/Http/Middleware/RecordAdminAction.php:73` — Audit log hashes `$request->ip()`
- `app/Services/Security/Turnstile.php:63` — Turnstile verification passes `$request->ip()`
- `app/Http/Controllers/Auth/RegisteredUserController.php:85` — Registration stores `$request->ip()`

### Exact blocker

Whether the production application process is reachable only through one of the shipped nginx configurations (which force or validate `X-Forwarded-For`) cannot be determined from source alone. If the app is exposed without those configs, the wildcard trust enables IP spoofing affecting rate limits and audit logs.

### Affected boundary

Rate limiting (`LoginRequest::throttleKey`, admin and public-api rate limiters), admin audit logging, Turnstile CAPTCHA verification, and registration IP recording.

### Bounded local validation plan

In a local development instance without a reverse proxy, send a POST to `/login` with the header `X-Forwarded-For: 1.2.3.4`. Verify that `$request->ip()` returns `1.2.3.4` instead of the actual connecting IP. Then trigger 5 failed login attempts with one `X-Forwarded-For` value and confirm that switching to a different value resets the rate limit counter. This demonstrates that the wildcard trust allows rate-limit bypass when no reverse proxy rewrites the header.

### Owner-observed deployment check

1. Verify that the production application process (port 8080 in Docker, PHP-FPM socket in bare-metal) is not directly reachable from untrusted networks — it should be accessible only through the reverse proxy.
2. Confirm that the reverse proxy in front of the application is one of the shipped nginx configurations or an equivalent that forces `X-Forwarded-For` to the real remote address.
3. If behind Cloudflare plus the shipped Docker nginx (`deploy/nginx-aip-docker.conf`), or behind the bare-metal nginx (`deploy/nginx-aip.conf`) that rewrites `HTTP_X_FORWARDED_FOR` to `$remote_addr`, the wildcard default is safe.
4. If the `TRUSTED_PROXIES` environment variable is set to a specific IP or CIDR range in production (rather than `*`), the wildcard default does not apply and this lead is resolved.
