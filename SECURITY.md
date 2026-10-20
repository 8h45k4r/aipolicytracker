# Security Policy

We take the security of AIPolicyTracker seriously, and we are grateful to everyone who reports a
problem responsibly. This page says how to report one, what to expect, and what is in scope.

## Reporting a vulnerability

Please **do not** open a public GitHub issue for a security problem. A public issue discloses it
before a fix is available.

Report it privately with GitHub private vulnerability reporting:

1. Open the [new advisory form](https://github.com/8h45k4r/aipolicytracker/security/advisories/new)
   (Security tab → Advisories → "Report a vulnerability").
2. Include a description, reproduction steps, the affected URL or commit, the impact, and any
   proof-of-concept or suggested fix.
3. Submit. This opens a private thread that only you and the maintainer can see.

If you cannot use GitHub, e-mail **bhaskar@aipolicytracker.org** with the subject
`SECURITY: aipolicytracker`, and treat the contents as confidential.

The same contact is published in machine-readable form at
<https://aipolicytracker.org/.well-known/security.txt> (RFC 9116).

## Supported versions

The site is deployed continuously from `main`, so `main` is what runs in production and what
receives fixes.

| Version | Supported |
|---------|-----------|
| `main` (the live site) | Yes |
| Tagged releases (`v1.0.0`) | No separate fixes. A fix lands on `main` and ships in the next release. |

Release notes are in [`CHANGELOG.md`](CHANGELOG.md).

## What to expect

- An acknowledgement within **5 working days**.
- A resolution target after triage, and updates while we work on a fix.
- Agreement with you on the disclosure date once a fix or mitigation is ready.

These are targets, not contractual guarantees. AIPolicyTracker is maintained by one person.

## Disclosure policy

We follow coordinated disclosure. Please give us reasonable time to fix the issue before you
disclose it publicly. Once it is fixed we publish an advisory and, unless you prefer to stay
anonymous, credit you in it.

## Safe harbour

We will not pursue or support legal action against research done in good faith under this policy.
Good faith means you:

- test only within the scope below, and only against accounts and data you own or have permission
  to use;
- stop and report as soon as you reach someone else's data, and do not keep, copy or share it;
- do not degrade the service for others (no denial-of-service, no load testing, no spam);
- give us reasonable time to fix the problem before disclosure.

If in doubt about whether something is allowed, ask first through the advisory form.

## Scope

In scope:

- The site at aipolicytracker.org, including the API (`/api/v1`) and the machine-readable files.
- The MCP server in [`agent/`](agent/).
- The code in this repository, its default configuration, and its data handling.

Out of scope:

- Third-party services: the hosting provider, Cloudflare, Resend and other mail providers,
  Dodo Payments, X.
- Denial-of-service testing.
- Social engineering of the maintainer, reviewers or users.

## Automated scanning

CodeQL runs on every pull request and weekly (`.github/workflows/codeql.yml`). Dependabot opens weekly update pull requests (`.github/dependabot.yml`), and `.github/workflows/security.yml` runs on every pull request and push to main: Gitleaks over the full history, Semgrep, `composer audit`, `scripts/npm-audit.mjs` (every npm dependency, failing on a high or critical advisory outside its short, reasoned allowlist), and Trivy over the lockfiles and the production container image. Changes to deploy files are also checked by Trivy config and Checkov, and a ZAP baseline and API scan run weekly. Results are summarised in [`docs/reference/security-scanning.md`](docs/reference/security-scanning.md).

## Security practices in this project

- Admin access is limited to e-mail addresses configured in `ADMIN_EMAILS`; there are no hard-coded accounts.
- Passwords are hashed with bcrypt; plaintext passwords are never stored.
- Login is rate-limited; JSON endpoints clamp page sizes and never return exception details.
- Browser security headers are added by `App\Http\Middleware\SecurityHeaders`, including a nonce-based Content-Security-Policy on every web response: inline scripts must carry the Vite CSP nonce, and third-party script sources are listed explicitly in the middleware.
- Debug/maintenance routes have been removed; use `php artisan` commands instead.
- Tool library uploads (admin only) are validated by sniffed type against an allow-list and by size (10 MB), and stored on the private `local` disk (`storage/app`, or `FILESYSTEM_LOCAL_ROOT`), not under the public web root. They are served through a short-lived signed URL to the signed-in account that requested the download.

## Deployment recommendations

Run with `APP_DEBUG=false`, HTTPS only (`SESSION_SECURE_COOKIE=true`), a strong `ADMIN_PASSWORD`, a non-`log` mail driver, and a CDN/WAF in front for rate limiting. The application already sends its own Content-Security-Policy; do not add a second one at the proxy, because the browser enforces both, and a proxy policy cannot carry the per-request nonce, so it would block the site's own inline scripts. To allow a new script source, change `SecurityHeaders::policy()`.
