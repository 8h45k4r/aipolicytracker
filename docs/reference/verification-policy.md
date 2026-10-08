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

## Independent checks

One reviewer's verification is only as good as that reviewer. A random sample of verified records is therefore re-checked each quarter by a second, independent reviewer, and the agreement between the two is published.

**Protocol.**

1. At the start of each quarter, draw the sample: `php artisan verification:sample` (default 20% of published, verified policy records not yet double-checked; `--percent`, `--seed`, `--quarter`, `--include-checked`). The seed defaults to the quarter label (`2026-Q4`) and the population is sorted by slug before a seeded Mersenne Twister shuffle, so anyone can re-run the draw from the same data and get the same list. A record whose `second_review.sample` names the quarter stays in that quarter's population, so recording checks does not change the draw. Paste the command and its output into the tracking issue. The same draw is on the admin page (below) and in Jobs as "Draw the quarterly verification sample".
2. Assign each sampled record to a reviewer on the published roster who is **not** its `reviewed_by`. The second reviewer opens the official source without looking at the record's values and records, under `second_review` in the policy YAML:
   ```yaml
   second_review:
     reviewed_by: Jane Doe          # a published roster name, a different person from reviewed_by
     reviewed_on: 2026-10-14
     sample: 2026-Q4                # the draw it came from (optional)
     coded:                         # the second reviewer's own values, before comparing
       status: in_force
       is_binding: true
       review_status: verified      # would they sign it as verified?
     agreed: false                  # true exactly when fields_disputed is empty
     fields_disputed:
       - field: status              # status | is_binding | review_status | dates | actors | obligations | penalties | official_source
         first: adopted             # the first reviewer's value; required for status, is_binding, review_status
         resolution: Applies from 2 August 2026 per Art. 113; record corrected.
       - field: dates
         note: Application date of Chapter III
   ```
3. Disagreements are resolved between the two reviewers. The resolution is written on the dispute; if the record was wrong, it is corrected in the same pull request (bump `content_version`, write `change_summary`) and the correction appears in `/corrections`. The `first` value is kept so the statistic reflects what the two reviewers originally recorded, not the corrected record.

**Validation.** `policy:validate` rejects a `second_review` whose reviewer is not on the published roster, is the same person as `reviewed_by` (compared by roster slug and case-insensitively by name), whose `agreed` contradicts `fields_disputed`, whose disputed categorical field lacks the first reviewer's value or records the same value twice, whose field names are unknown, or which is dated in the future. A record whose first reviewer was later replaced, in the admin queue, by the second reviewer is not counted as double-checked.

**The statistic.** `App\Services\Verification\AgreementStatistics` (pure, unit-tested against hand-worked examples) reports, per field, percent agreement (share of double-checked records on which the field was not disputed) and, for status, binding and review status, Cohen's kappa from the two reviewers' coded values. Kappa is reported as undefined when both reviewers used a single value for every record (chance agreement is then total). Nothing per field is published until at least `independent_checks.min_sample` (20) records have been double-checked; below that `/methodology` says how many exist and that no figure is published. With none, it says so plainly.

**Admin page.** `/backend/review/independent-checks` (capability `records.verify`) shows the quarter's sample from the same service as the command, with the first reviewer and whether each record has a second check. It also shows progress, the agreement figures under the same threshold as the public page, and every disputed field, unresolved first. "Export sample CSV" gives reviewers the list to work from. The page writes nothing. Second checks are entered by pull request to `data/`, because the admin never writes YAML from a form.

**Where it shows.** `/methodology#independent-checks`; the API returns `second_review` on each policy record; `PolicyInstrument::secondReview()` and `isDoubleChecked()` are what a record page uses to show "checked by".

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
| Independent checks: rules, statistic, reader | `App\Services\Verification\SecondReview`, `AgreementStatistics`, `IndependentChecks` |
| Sample draw | `App\Services\Verification\VerificationSample`, printed by `App\Console\Commands\VerificationSampleCommand` (`verification:sample`) |
| Admin page | `Backend\Review\IndependentChecksController`, `backend/review/independent-checks` |
| Tests | `tests/Feature/VerificationPolicyTest.php`, `tests/Feature/IndependentChecksTest.php`, `tests/Feature/IndependentChecksAdminTest.php`, `tests/Unit/AgreementStatisticsTest.php` |
