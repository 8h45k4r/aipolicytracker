# Production readiness, 9 November 2026

The owner's brief asked for AI Policy Tracker to be taken to production-ready, with the fixes implemented rather than only reported. This file records:
- **Phase A:** what was found.
- **Phase B:** what changed, one reviewable commit per item.
- **Phase C:** how it was verified.
- **Phase D:** what only the owner can do.

## Phase A: findings

| Area | Finding | Evidence |
|---|---|---|
| Template update emails | The request form offered "email me when this template gets a new version, and about related templates and guides". The answer was stored (`marketing_consent_at`), but nothing ever sent those emails. | No mailable, job or command read the field. |
| PR #156 | The `data-nosnippet` commit also wrapped the citation block. That block appears only on policy, obligation, change, control and jurisdiction pages, and hides the AIPT permanent ids, which are meant to be cited. Its premise ("ai20223" queries come from incident ids) does not match any identifier in `data/`: incident ids are integers. | `<x-site.cite>` usage; `data/external/*.json` |
| PR #156 | The maintenance workflow had five problems: <ul><li>Without the maintenance key it fell back to the deploy key and deployed `main` HEAD, skipping the CI and Security gates.</li><li>It ran `docker system prune -a --volumes` on a host shared with other services.</li><li>It took no lock against a running deploy.</li><li>`aip:cache-clear` ran `cache:clear`, which empties the database cache store holding the scheduler tick, the incident sync cursor, locks and rate limits.</li><li>It ignored step exit codes.</li></ul> | `.github/workflows/maintenance.yml`, `deploy/remote-maintenance.sh` on the PR branch |
| Server (#32) | Production served with `php artisan serve`, PHP's built-in development server. | `docker/entrypoint.sh` |
| Edge and origin (#34, #36) | The edge-to-origin hop is Flexible (unencrypted). The origin accepts port 80 from any address because the corrected server block is not installed on the host. | Debts #34, #36 |
| Backups (#33) | `deploy/backup.sh` exists, but nothing shows a cron on the host, an off-host copy, a rehearsed restore, or a backup of the `aip-storage` volume. | Debt #33 |
| Dev-only advisories (#54) | `braces` through Tailwind 3's watcher; build-time only. | Debt #54 |
| React/Inertia | The `resources/js/Pages` account screens were dead: every account route returned Blade. The Inertia middleware still ran on every response and replaced the app's own `Vary` header with `Vary: X-Inertia`. | Trace of controllers, middleware and build |
| Pricing | The site shows $49/$490 (`config/billing.php`); the Dodo products are $29/$290. The admin page flagged the difference, but a checkout would have charged the provider's price. Billing is off. | `BillingController::checkout` |
| Review workflow | 98 records are pending (60 policies, 38 jurisdictions). Several were recorded from search-engine extracts and say so in their status note. The queue showed the source link, confidence and status, but not the dates and source details a reviewer has to compare. | Review queue |
| SEO | `/ai-governance-uae` duplicated the verified `/jurisdictions/uae` record. The Search Console CSVs were not provided, so no title was rewritten on guesswork. | Debt #51 |
| Privacy | The retention table left out template requests, template update emails and licence enquiries. Enquiries had no delete action for an erasure request. | Debt #57 |

## Phase B: changes (commits on `main` unless noted)

1. **Template update emails.** One email per new version per address that ticked the box for that template:
   - It carries the changelog, a link, related templates and RFC 8058 one-click unsubscribe.
   - Delivery rows are unique per version and address, and each row is claimed atomically before sending.
   - A failed send is retried with backoff, up to three attempts. An invalid address is failed at once.
   - An unsubscribe suppresses waiting and future notices. Nobody is added to the digest.
   - The checkbox no longer promises guides.
   - Full description: [`docs/modules/guides-and-downloads.md`](../modules/guides-and-downloads.md#new-version-emails).
2. **`data-nosnippet` on external incident and risk ids** (from PR #156, without the citation block).
3. **Safe maintenance workflow** (reworked from PR #156):
   - It uses its own forced-command key and takes the deploy lock.
   - It never deploys.
   - Prune covers only this compose project's stopped containers and dangling images.
   - The cache clear leaves the cache store alone.
4. **React and Inertia removed.** Packages and build chunks go too. `Vary: X-Inertia` is gone.
5. **Checkout price guard.** A plan whose provider price differs from the page, or cannot be checked, is not sold. Prices are unchanged.
6. **`/ai-governance-uae` answers 301** to `/jurisdictions/uae` (debt #51).
7. **Review queue checklist.** Each record shows what to compare against its source: source title and publisher, status, dates, and the last decision with its notes.
8. **Privacy retention rows** for requests and enquiries, stating today's behaviour. Enquiries get an audited delete.
9. **nginx and php-fpm in the container** (debt #32). **On the working branch only, not merged**: it changes how production serves, so it waits for the owner's go-ahead. See Phase D.

## Phase C: verification

Tests were executed, not assumed. Where something could not be executed, that is stated.

- **PHP suite.** The full suite ran in an isolated worktree at the commit that holds items 1–8. Result: see the summary posted with this work.
- **Targeted runs while working:**
  - template notifications: 15 tests;
  - billing and pricing: 34;
  - review queue: 19;
  - country hubs and public site: 34;
  - auth and account: 56;
  - Blade-only front end, maintenance command and nosnippet: 7.
- **Front end.** `npm run lint` passed. `npm test` passed 15 of 15. `npm run build` succeeded, and `public/build` was rebuilt.
- **Lint.** `composer lint` (Pint) passed.
- **Server change (item 9)**, tested on a harness:
  - **Harness.** Ubuntu PHP 8.3, running the repository's `docker/nginx.conf`, `docker/php-fpm-pool.conf` and `docker/entrypoint.sh` against PostgreSQL 16. The sandbox cannot reach Alpine's package mirrors, so the Alpine image itself was not built here.
  - **Results:**
    - every deploy probe answered 200;
    - 8 parallel requests took 0.25 s;
    - `/build` assets carried immutable caching;
    - `.htaccess` and stray `.php` answered 404;
    - the health check reported healthy;
    - `docker stop` took 1 s and exited 0;
    - memory was about 190 MB under load;
    - killing php-fpm stopped the container with exit 1, so the restart policy recovers it.
  - **Official Alpine image checks:**
    - `php-fpm -tt` in `php:8.3.36-fpm-alpine` confirmed the effective pool (`listen = 127.0.0.1:9000`, 6 workers, limits);
    - `nginx -t` with Alpine nginx as a non-root user passed.
  - **CI.** The Security workflow now builds the real image and runs the same smoke test before any deploy can proceed.
- **Screens.** The review queue checklist was checked at desktop width in a browser.

## Phase D: owner action register

| # | Action | Why only the owner | Rollback |
|---|---|---|---|
| 1 | ~~**Authorise the php-fpm server change**~~ **Done 9 November 2026**: authorised by the owner ("fix all"), built and smoke-tested from the real image in CI (Security run 42675779872), merged with the audit remediation (`prelaunch-report.md`). Original text: (commit on `claude/affectionate-volta-fx0dxa`), then merge it to `main`. The deploy builds and probes it and rolls back automatically if a probe fails. | It changes how production serves. | Revert the commit and deploy, or `aip-deploy <previous sha>`. |
| 2 | **Choose the price.** Either set the Dodo products to $49/$490, or change `config/billing.php` to $29/$290. Then run Admin → Billing → Check products, and only then set `BILLING_ENABLED`. | Business pricing and live payments. | Leave billing off. |
| 3 | **Cloudflare Full (strict) and origin restriction** (debts #34, #36). Install the origin certificate at `/etc/ssl/aip/`, install the corrected `deploy/nginx-aip-docker.conf`, run `nginx -t` and reload, check that a non-edge request gets 403, then switch SSL mode to Full (strict). | Live Cloudflare and firewall settings. | Restore the previous server block and reload; set SSL mode back to Flexible. Keep the old file as `aip.conf.bak` before installing. |
| 4 | **Backups** (debt #33). <ul><li>Set `BACKUP_RCLONE_REMOTE`.</li><li>Install `/etc/cron.d/aip-backup` (`deploy/cron-install.sh` does it).</li><li>Run `deploy/backup.sh --verify-latest` once and record the date in debt #33.</li><li>Decide whether the `aip-storage` volume (uploads, social cards) needs its own backup.</li></ul> | Host and off-site credentials. | None needed. |
| 5 | **Maintenance key.** Follow `deploy/README-docker.md` → Maintenance: generate the key, install the script, add `authorized_keys`, add the `MAINTENANCE_SSH_KEY` secret. | Host root and repository secrets. | Remove the `authorized_keys` line. |
| 6 | **Retention period and governing law** (debts #29, #57). | Legal commitments. | — |
| 7 | **98 pending records.** A rostered reviewer opens each official source and records the decision in the review queue, then runs `policy:export-verifications` and opens a pull request. | Verification is a human act. | — |
| 8 | **Reviewer biographies.** Joyce and Kailash stay unpublished until they supply their own declarations. | Their words, not ours. | — |
| 9 | **Search Console exports** (queries and pages CSVs) for the low-CTR pages. Titles change only with that evidence. | Account access. | — |
| 10 | **PR #156.** Close it as superseded by commits 2 and 3 above, or keep it open for reference. | The owner's PR. | — |
| 11 | Host housekeeping: run `apt upgrade` on the host; cap Certifyi build memory (`COMPOSE_PARALLEL_LIMIT=1`, a lower `--max-old-space-size`); set the Cloudflare purge secrets. | Separate systems. | — |
