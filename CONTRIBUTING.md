# Contributing

Thanks for helping. Corrections to the data are the most useful thing you can send, and they don't need any code.

## Ground rules

- Every data change needs an official or openly licensed source. [`SOURCE_ATTRIBUTION.md`](SOURCE_ATTRIBUTION.md) says what counts.
- Write summaries in your own words. Don't paste from paid databases, law-firm commentary or standards bodies (ISO/IEC texts are copyrighted).
- Only someone who has opened the source may mark a record `review_status: verified`. Unverified changes don't merge.
- Never commit secrets, `.env` files, database dumps, personal data or hosting details. That includes server addresses, hostnames and account IDs, even in comments or runbooks; use a placeholder such as `<origin-ip>`.
- Be kind ([`CODE_OF_CONDUCT.md`](CODE_OF_CONDUCT.md)).

## Setting up

Fork the repository, clone your fork, then follow "Running it locally" in the [README](README.md).

## Changing data

1. For anything bigger than a typo, open a *Policy data correction* or *New jurisdiction or source* issue first.
2. Edit the YAML under `data/` (the format is in [`data/README.md`](data/README.md)). Bump `content_version` and write a `change_summary` when you edit an existing record.
3. Run `php artisan policy:validate` and `php artisan policy:import`.
4. In the pull request, list each changed field, its source URL and the date you accessed it.

To preview an unpublished record, set `published: true` locally, import it and open `/policies/<slug>`. Set it back before you commit.

## Changing code

Read [`docs/reference/engineering-standard.md`](docs/reference/engineering-standard.md) first. In short: look at how
things work before changing them, fix the cause rather than the symptom, keep changes small, and show the command
output that proves a change works.

- Follow the existing conventions. The public site is server-rendered Blade with Tailwind; the account screens are React with Inertia.
- Validate input on the server. Never send exception messages to the browser.
- Add or update tests in `tests/Feature` for any behaviour change.
- Code, data, API and docs move together: a change that updates one and leaves the others behind isn't finished.
- Before pushing, run:

  ```bash
  php artisan policy:validate && composer lint && composer test && npm run lint && npm test && npm run build
  ```

The full PHP suite takes 20 to 30 minutes; while you work, run the tests you touched with `php artisan test --filter=...`.

`public/build` is committed so the site deploys without Node. Commit it only when your change touches `resources/js`, `resources/css` or `vite.config.js`, and rebuild it in the same commit. A data-only or PHP-only pull request leaves it alone.

CI also runs the tests on PostgreSQL, checks the committed `public/build` manifest and runs the security scans.

## Branches, commits and pull requests

- Branch from `main`: `feat/…`, `fix/…`, `data/<jurisdiction>`, `docs/…`.
- Write commit messages in the imperative ("Add Kenya AI strategy").
- A commit is authored by the person responsible for it. Don't add trailers or footers crediting tools, in commits, comments or docs.
- Add a line to `CHANGELOG.md` under *Unreleased* for anything a reader of the site would notice.
- Use the pull request template. The *Gate check* workflow fails a pull request that doesn't fill in the five gates.

### The five gates

Every change to `main`, however small, records its outcome against the gates in
[`docs/reference/change-gates.md`](docs/reference/change-gates.md): engineering, UI/UX, documentation, compliance and
security. Mark a gate N/A with a reason when it doesn't apply. For a data-only pull request, paste the
`policy:validate` and `policy:import` output under engineering, fill in the sources table under compliance, and
mark the rest N/A.

Debt you knowingly accept goes in [`docs/reference/technical-debt.md`](docs/reference/technical-debt.md) with an
owner. A new module needs its own page in `docs/modules/`.

### Merging

`main` is protected. A change gets in through a pull request with an approving review from a code owner, green
required checks and resolved conversations. Maintainers squash-merge, so make the pull request title a good commit
message.

## Releases

1. In `CHANGELOG.md`, rename *Unreleased* to `[X.Y.Z] - YYYY-MM-DD` and start a new *Unreleased* above it.
2. Check the notes with `scripts/release/notes.sh X.Y.Z`, push to `main`, then push the tag `vX.Y.Z` (or run **Release** from the Actions tab).
3. After the deploy, run **Deployment check** from the Actions tab.

## Licence

Code you contribute is Apache 2.0 ([`LICENSE`](LICENSE)); data you contribute to `data/` is CC BY 4.0
([`data/LICENSE`](data/LICENSE)).

Security problems go to [`SECURITY.md`](SECURITY.md), not a public issue.
