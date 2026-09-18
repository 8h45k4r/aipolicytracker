# Verification policy

How old a fact may be before it must be checked again, and what happens when it is not.

The dataset's value is that every claim traces to an official source **and** carries the date someone confirmed it there. A source link alone ages badly: law changes, guidance is withdrawn, application dates move. This policy sets the maximum age for each kind of record, publishes the corpus's standing against it, and fails the data check when the critical parts slip.

## The rules

Defined in `App\Services\Verification\VerificationRuleset`. A record is governed by the **first** rule whose `applies` closure matches it, so the order in that file is the policy.

The rules live in `app/` rather than `config/` because they carry closures and `php artisan config:cache` cannot serialize a closure. `azure/startup.sh` runs that command on every deploy, so a closure in `config/` aborts the container's startup before the data import. Anything in `config/` must stay serializable; a test enforces it.

| Rule | Track | Maximum age | Critical |
|------|-------|-------------|----------|
| Binding instruments in force | Binding law in force | 90 days | Yes |
| Binding instruments adopted but not yet applying | Binding law in force | 180 days | Yes |
| Obligations | Obligations and deadlines | 180 days | Yes |
| Guidance, strategies and standards | Proposed and pending instruments | 365 days | No |
| Change log entries | Change log entries | 365 days | No |
| Jurisdiction profiles | Jurisdiction background | 365 days | No |

Age is days since `last_verified_at`. **A record that has never been confirmed is never fresh**: it counts as overdue from the moment it is published, whatever its age. That is deliberate, because the alternative is a corpus that looks current because nobody has looked at it.

Tracks name who owns the re-check queue. They exist so an overdue count has an owner rather than being everyone's problem.

## The gate

`php artisan policy:freshness` reports the corpus and exits non-zero when critical breaches exceed `critical_budget`. The data workflow (`.github/workflows/validate-data.yml`) runs it on every change to `data/`, to the policy itself, or to the service, so the dataset cannot quietly decay.

```
php artisan policy:freshness            # table, plus the longest overdue records
php artisan policy:freshness --json     # machine-readable summary for dashboards
php artisan policy:freshness --list=0   # counts only
```

### The budget is a ratchet

`critical_budget` holds the number of critical breaches measured over the whole corpus on the day the policy was introduced: 2026-09-17, 594 records under the policy, 99 of them critical and never confirmed. It exists so the check can be enforced immediately without blocking every other change on a backlog nobody has worked through yet.

Measure it against the full corpus, not a partial local database. A development database seeded with a subset reports a smaller number; the gate runs after a full import and will fail if the budget was set from the subset. That is the gate working, not a false alarm.

It may only be **lowered**. After verifying a batch of records, run the command, read the new critical count, and lower the budget to it in the same pull request. Raising it is a deliberate act that must be argued for in the pull request description and recorded in the technical debt register.

## Becoming verified

A record becomes `verified` only when a named reviewer opens the official source, confirms each dated claim against it, and records the decision with their name and the date. That is stored in `record_verifications`, survives re-import of the underlying data, and is exported back to `data/` by `policy:export-verifications`. See `docs/modules/policy-intelligence.md` and `docs/reference/engineering-standard.md`.

Until then a record is published with its `review_status` and `confidence_level` shown to the reader, and it is counted as overdue here.

## What the public sees

`/verification` publishes the table above, the current counts, and the longest overdue records, computed from the same service the gate uses. There is no marketing number anywhere on that page: if the corpus is in poor shape, the page says so.

This is a deliberate trade. Publishing the backlog costs a little credibility today and buys the right to be believed when a record does say verified.

## Code map

| Piece | Location |
|-------|----------|
| The rules | `app/Services/Verification/VerificationRuleset.php` |
| Tracks and the budget | `config/verification.php` |
| Assessment and report | `App\Services\Verification\VerificationPolicy` |
| Command and gate | `App\Console\Commands\VerificationFreshnessCommand` (`policy:freshness`) |
| Public page | `Site\VerificationController`, `site/pages/verification` |
| Continuous integration | `.github/workflows/validate-data.yml` |
| Tests | `tests/Feature/VerificationPolicyTest.php` |
