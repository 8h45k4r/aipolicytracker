# Reviewer roster and declarations of interest

Who may mark a record verified, what they must declare before they do, and how a reader
checks both.

## The rule

**A record cannot claim to be verified by a name that is not on the published roster, and
nobody is on the roster without a declaration of interest.** Both halves are enforced by
`php artisan policy:validate`, which runs in CI on every change to `data/`:

- `reviewer.schema.json` requires `interests` with at least one entry. `None declared.` is a
  declaration; leaving the field out fails the build.
- A record with `review_status: verified` must carry `reviewed_by`, and that name must match a
  published roster entry. Otherwise `reviewed_by` is an unchecked string and the roster is
  decoration.

An entry with `published: false` stays in the repository but vouches for nothing.

## Why it lives in `data/`

A declaration of interest is only worth something if it is auditable. Keeping the roster in
version control means every declaration, and every later change to one, arrives through a pull
request with a date, a diff and a second pair of eyes — the same treatment the policy records
get. A database row edited in an admin screen has none of that.

## What `/reviewers` publishes

| Shown | Source |
|-------|--------|
| Published records, and how many were verified by a named reviewer | The records themselves |
| Reviewers on the roster | `data/reviewers/*.yaml` |
| Records verified by an unlisted name | Records whose `reviewed_by` is not on the roster |
| Per reviewer: role, joined date, expertise, jurisdictions covered, affiliations, every declared interest with its scope, mitigation and date | `data/reviewers/*.yaml` |
| Per reviewer: how many records they have verified | The records, **not** the roster file |

That last row is the point. A roster that lists what someone is responsible for, with no way to
see what they have done, is a credentials page. The two can disagree, and when they do the page
shows the honest number: a reviewer who has verified nothing reads "nothing verified yet".

The page leads with the corpus standing so a list of impressive names cannot stand in for work
that has not happened. While no record has been verified, the page says so in those words and
points at the verification policy, which counts every record as overdue.

## The join between a roster entry and a verification

`reviewed_by` stores the reviewer's account name from the admin review queue, and the roster's
`name` must match it. Verifications survive a re-import of `data/` (they are kept in
`record_verifications` and re-applied by slug), so the name is what both sides carry.

`policy:export-verifications` writes an admin verification back into the YAML. Until the
matching roster entry is committed, `policy:validate` rejects the export — which is the
intended order of work: publish the declaration, then sign the record.

## Adding a reviewer

1. Add `data/reviewers/<slug>.yaml` following `data/reviewers/README.md`. `interests` is
   required; scope an interest with `affects` and say what is done about it in `mitigation`.
2. `php artisan policy:validate` — it must pass before the person verifies anything.
3. Open a pull request. The declaration is reviewed like any other data change.

## Code map

| Concern | File |
|---------|------|
| The record format | `data/schema/reviewer.schema.json`, `data/reviewers/README.md` |
| Reading the roster | `PolicyDataRepository::reviewers()` |
| The two rules | `PolicyDataValidator::validateReviewers()` and `checkVerification()` |
| Roster joined to activity | `app/Services/Reviewers/ReviewerRoster.php` |
| `/reviewers` | `app/Http/Controllers/Site/ReviewersController.php` |
| Tests | `tests/Feature/ReviewerRosterTest.php` |
