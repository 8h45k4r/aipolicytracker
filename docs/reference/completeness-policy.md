# Completeness policy

What a published record must carry to be worth publishing, how it is measured, and where a
reader sees the result.

This policy is deliberately separate from the [verification policy](verification-policy.md).
That one asks **how old** a record's facts are. This one asks **whether the facts are there at
all**. A record checked yesterday can still be missing its source link, and a record carrying
every field below can still be years out of date, so the two are measured and published
separately and neither number is allowed to stand in for the other.

## What it is not

It measures completeness of what is published, not coverage of the world. It can say that 46
instruments carry no source date; it cannot say how many instruments exist that have never been
recorded here. Measuring that needs a sourced denominator — a published list of instruments that
ought to exist — and the project does not have one. `/coverage` says so in those words, and the
gap is recorded as debt so it is not quietly forgotten.

## The checks

Defined in `App\Services\Completeness\CompletenessChecks`, which lives in `app/` rather than
`config/` because the checks carry closures and `php artisan config:cache` cannot serialize a
closure — and `azure/startup.sh` runs that command on every deploy. Each check names one field or relationship, the record
kind it applies to, and a severity:

| Severity | Meaning | Gated |
|----------|---------|-------|
| `required` | A record should not be published without it. | Yes — `policy:coverage` fails when required gaps exceed the budget. |
| `expected` | The record works without it but is less useful. | No. |

Expected gaps are counted and published but never gated, on purpose: gating them would reward
filling boxes over checking facts, which is the failure mode this project exists to avoid.

| Record kind | Required | Expected |
|-------------|----------|----------|
| Policy instrument | Link to the official text; name of the publishing body; plain-language summary | Source document date; who it applies to; at least one dated milestone; duties mapped out of a binding instrument in force |
| Obligation | The article or section the duty comes from; what the duty requires | What an organisation actually does about it |
| Change log entry | Link to the official announcement | What the change means in practice |
| Jurisdiction profile | Link to an official source; where the jurisdiction currently stands | Who regulates AI there; what is binding and what is only guidance |

A check may carry an `applies` closure so it runs only against the records it makes sense for —
a strategy document is not "missing" obligations it was never meant to carry. A record the check
does not apply to produces no row at all, rather than a passing row, so the "applies to" column
is the honest denominator.

## The gate

`php artisan policy:coverage` prints every check with the number missing and the number it
applies to, optionally lists the queue (`--list=40`, narrowed with `--kind=` and `--check=`),
offers `--json`, and exits non-zero when required gaps exceed `required_budget`. The
**Validate policy data** workflow runs it on every change to `data/`, the policy or the service.

**The budget is a ratchet, not a target.** It launched at **0**, measured over the full corpus
(594 published records, 0 required gaps). It may only be lowered. Raising it is a deliberate act
argued for in the pull request that does it.

> Measure against the **full** corpus before changing the budget. A development database
> holding a subset of the records understates every count, and the gate in continuous
> integration will catch the difference. Import everything first:
> `php artisan migrate --force && php artisan db:seed --force && php artisan policy:import`.

## What a reader sees

| Page | What it publishes |
|------|-------------------|
| `/coverage` | Every check, the count missing, the count it applies to, and per-record-kind totals. Computed by the same service the gate uses, so the page and the check cannot disagree. |
| `/gaps` | The open queue, required first, filterable by record kind and by check. Every row links to the record and to the correction form with the record and field already selected. |
| `/corrections` | What readers reported and what was decided, including refusals. |

## The corrections log

`/corrections` publishes every submission a reviewer has **decided**. A pending report is an
unchecked claim about a record, so it never appears.

It never publishes the submitter's identity, the submitter's own words, or the reviewer's
internal `notes`: none of those were written for publication and the site never moderated them
for it. Each entry publishes structured facts — what kind of report, which record and field,
when it arrived, what was decided and when — plus `reviewer_decisions.public_note`, a sentence
the reviewer writes deliberately for that page in the admin review queue. Null means the entry
publishes its structured facts only.

Refusals are published alongside acceptances. A log that shows only accepted corrections is a
testimonial, not a record.

## Code map

| Concern | File |
|---------|------|
| The checks (closures; not in config/, which must stay cacheable) | `app/Services/Completeness/CompletenessChecks.php` |
| The checks | `app/Services/Completeness/CompletenessChecks.php` |
| Record kinds and the budget | `config/completeness.php` |
| Applying them to the corpus | `app/Services/Completeness/CompletenessReport.php` |
| The gate | `app/Console/Commands/CoverageCommand.php` |
| `/coverage` and `/gaps` | `app/Http/Controllers/Site/CoverageController.php` |
| `/corrections` | `app/Http/Controllers/Site/CorrectionsController.php` |
| Field labels shared with the correction form | `app/Support/SubmissionFieldLabels.php` |
| Tests | `tests/Feature/CompletenessPolicyTest.php` |

## Adding a check

1. Add it to `App\Services\Completeness\CompletenessChecks` with a `label`, a `why` a reader will understand, a
   severity and a `missing` closure. Use `applies` when it does not cover every record of its kind.
2. Point `field` at a field the correction form accepts for that kind
   (`ContributeController::CORRECTABLE_FIELDS`), or the "Fill this in" link will silently drop it.
   A test asserts this for every check, so a mismatch fails the build rather than the reader.
3. Run `php artisan policy:coverage` against the **full** corpus. A new `required` check that
   finds gaps will fail the gate until they are filled — that is the point.
