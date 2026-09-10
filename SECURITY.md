# Security Policy

## Supported versions

Only the `main` branch and the latest tagged release receive security fixes.

## Reporting a vulnerability

Please **do not** open a public GitHub issue for security problems.

1. Use GitHub's private vulnerability reporting on this repository ("Security" tab → "Report a vulnerability"), or
2. E-mail the maintainers at the contact address listed on the project's About page with the subject `SECURITY: aipolicytracker`.

Include a description, reproduction steps, affected version/commit, and any proof-of-concept. You will receive an acknowledgement within 5 working days and a resolution target after triage. Please give us reasonable time to fix the issue before public disclosure.

## Scope

In scope: this application's code, its default configuration, and its data-handling. Out of scope: third-party services (Supabase, mail providers, CDNs), denial-of-service testing, and social engineering.

## Security practices in this project

- Admin access is limited to e-mail addresses configured in `ADMIN_EMAILS`; there are no hard-coded accounts.
- Passwords are hashed with bcrypt; plaintext passwords are never stored.
- Login is rate-limited; JSON endpoints clamp page sizes and never return exception details.
- Browser security headers are added by `App\Http\Middleware\SecurityHeaders`.
- Debug/maintenance routes have been removed; use `php artisan` commands instead.
- Uploaded files are validated by MIME type and size and stored under `storage/app/public`.

## Deployment recommendations

Run with `APP_DEBUG=false`, HTTPS only (`SESSION_SECURE_COOKIE=true`), a strong `ADMIN_PASSWORD`, a non-`log` mail driver, and a CDN/WAF in front for rate limiting. Add a Content-Security-Policy at the proxy once your allowed script sources are known.
