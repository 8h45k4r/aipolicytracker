# Security scanning

Every change is scanned automatically by `.github/workflows/security.yml`. This page says what
runs when, what makes it fail, and where accepted findings are recorded.

| Stage | Scan | Tool | Fails the build when |
|-------|------|------|----------------------|
| Every PR and push to `main` | Secrets, full history | Gitleaks | Any secret not allowlisted by value in `.gitleaks.toml`. |
| Every PR and push to `main` | SAST | Semgrep (registry packs: php, security-audit, secrets, javascript, github-actions, dockerfile) | An ERROR-severity finding whose rule is not in `SEMGREP_ACCEPTED`. All findings go to *Security → Code scanning*. |
| Every PR and push to `main` | SCA | `composer audit`, `npm audit --audit-level=high`, Trivy fs | Any advisory against `composer.lock`; a high or critical npm advisory; a fixable HIGH/CRITICAL CVE. |
| Every PR and push to `main` | IaC | Trivy config, Checkov | A HIGH/CRITICAL misconfiguration in the Dockerfile or workflows. |
| Every PR and push to `main` | Container | Trivy on the built image | A fixable HIGH/CRITICAL CVE in the image's OS packages or PHP dependencies. |
| Weekly (Monday 03:17 UTC) and on demand | DAST | ZAP baseline against the app started in production mode | A rule marked FAIL in `.zap/rules.tsv`. |
| Weekly and on demand | API | ZAP active scan driven by `/openapi.json` | Same rules: injection, XSS, traversal, error disclosure. |

Also running: CodeQL (`codeql.yml`) and Dependabot (`.github/dependabot.yml`: composer, npm,
Actions and Docker base images, weekly, plus security updates as advisories appear).

## Accepting a finding

A finding is accepted only with a reason, next to the rule that ignores it:
`.gitleaks.toml` (by exact value, never by path), `SEMGREP_ACCEPTED` in the workflow, or
`.zap/rules.tsv`. Add a line to the current assessment in `docs/reference/vapt-*.md`.

## Running the scans locally

```bash
docker run --rm -v "$PWD:/repo" zricethezav/gitleaks:v8.30.1 git /repo --config /repo/.gitleaks.toml
docker run --rm -v "$PWD:/src" -w /src semgrep/semgrep:1.178.0 semgrep scan --config p/php --config p/security-audit
composer audit && npm audit
docker run --rm -v "$PWD:/src" aquasec/trivy:latest fs --scanners vuln,misconfig /src
```

For DAST, start the app (`php artisan serve`) and run
`docker run --rm --network host zaproxy/zap-stable zap-baseline.py -t http://127.0.0.1:8000`.
