# Security Policy

## Supported versions

Only the `main` branch and the latest tagged release receive security fixes.

## Reporting a vulnerability

Please **do not** open a public GitHub issue for security problems.

1. Use GitHub's private vulnerability reporting: <https://github.com/8h45k4r/aipolicytracker/security/advisories/new>, or
2. E-mail **bhaskar@aipolicytracker.org** with the subject `SECURITY: aipolicytracker`.

The same contact is published in machine-readable form at <https://aipolicytracker.org/.well-known/security.txt> (RFC 9116).

Automated scanning: CodeQL runs on every pull request and weekly (`.github/workflows/codeql.yml`). Dependabot opens weekly update pull requests (`.github/dependabot.yml`), Gitleaks and Semgrep run in `.github/workflows/security.yml`, and `npm audit --omit=dev` and `composer audit` are recorded in every pull request.

Include a description, reproduction steps, affected version/commit, and any proof-of-concept. You will receive an acknowledgement within 5 working days and a resolution target after triage. Please give us reasonable time to fix the issue before public disclosure.

## Scope

In scope: this application's code, its default configuration, and its data-handling. Out of scope: third-party services (the hosting provider, Cloudflare, mail and payment providers, X), denial-of-service testing, and social engineering.

## Security practices in this project

- Admin access is limited to e-mail addresses configured in `ADMIN_EMAILS`; there are no hard-coded accounts.
- Passwords are hashed with bcrypt; plaintext passwords are never stored.
- Login is rate-limited; JSON endpoints clamp page sizes and never return exception details.
- Browser security headers are added by `App\Http\Middleware\SecurityHeaders`, including a nonce-based Content-Security-Policy on every web response: inline scripts must carry the Vite CSP nonce, and third-party script sources are listed explicitly in the middleware.
- Debug/maintenance routes have been removed; use `php artisan` commands instead.
- Tool library uploads (admin only) are validated by sniffed type against an allow-list and by size (10 MB), and stored on the private `local` disk (`storage/app`, or `FILESYSTEM_LOCAL_ROOT`), not under the public web root. They are served through a short-lived signed URL to the signed-in account that requested the download.

## Deployment recommendations

Run with `APP_DEBUG=false`, HTTPS only (`SESSION_SECURE_COOKIE=true`), a strong `ADMIN_PASSWORD`, a non-`log` mail driver, and a CDN/WAF in front for rate limiting. The application already sends its own Content-Security-Policy; do not add a second one at the proxy, because the browser enforces both, and a proxy policy cannot carry the per-request nonce, so it would block the site's own inline scripts. To allow a new script source, change `SecurityHeaders::policy()`.
