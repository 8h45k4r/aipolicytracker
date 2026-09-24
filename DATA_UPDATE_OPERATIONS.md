# Data Update Operations

How policy records are created, verified, kept current and published.

## 1. Source of truth and flow

```
official source  →  data/*.yaml (PR)  →  policy:validate (CI)  →  review  →  merge  →  policy:import (deploy)  →  site, API, feeds
```

- `data/` is canonical. The database is a read model; the admin review area only triages submissions and toggles visibility.
- Every deploy runs `php artisan policy:import` (see `docker/entrypoint.sh`, `azure/startup.sh`). The command validates first and refuses to import invalid data.
- `php artisan policy:export` writes a dated JSON bundle to `storage/app/exports` for archival releases; the live download at `/open-data/aipolicytracker-latest.json` is generated from the database.

## 2. Commands

| Command | Purpose |
|---------|---------|
| `php artisan policy:validate` | JSON Schema + cross-reference checks (unique slugs, taxonomy terms, jurisdiction and policy references, verified-requires-date-and-reviewer). |
| `php artisan policy:import` | Idempotent upsert of all records; replaces child rows per policy; clears caches. `--skip-validation` only for local experiments. |
| `php artisan policy:export --out=storage/app/exports` | Versioned JSON bundle. |
| `php artisan migrate --seed` | Fresh database with the admin user and the tool library. Records are not seeded: run `php artisan policy:import` next. |

## 3. Verification cadence

| Record state | Action | Cadence |
|--------------|--------|---------|
| `pending_review` | Reviewer opens `official_source_url`, checks every field, sets `review_status: verified`, `last_verified_at`, `reviewed_by`. | Within 30 days of publication; all initial seed records are in this state. |
| `verified` | Re-check the source; update `last_checked_at`; if anything changed, edit, bump `content_version`, add a change event. | Every 90 days for binding instruments with upcoming deadlines; every 180 days otherwise. Records older than 180 days are flagged stale in the UI. |
| `needs_update` | Set when a change event affects the record but the edit is not done yet. | Resolve within 14 days. |
| Source link check | Automated: crawl all `official_source_url` and source document URLs; report non-200. | Weekly (add a GitHub Action calling a small script; until then run `php artisan tinker` with `Http::head()` over `SourceDocument::pluck('url')`). |

Human review requirements: a reviewer must not verify their own draft; reviewers for binding legal instruments should have legal, policy or compliance experience in that jurisdiction; every verification is recorded in the PR (source table, date accessed) and in the record (`reviewed_by`, `last_verified_at`).

## 4. Adding a record

1. Copy the closest existing file in `data/policies/{jurisdiction}/` and rename to the new slug (`kebab-case`, jurisdiction-prefixed).
2. Fill the source-quality block first; if you cannot fill `official_source_url`, stop.
3. Write `summary_plain` (2–5 sentences), `scope_summary`, `who_it_applies_to`, `key_dates_summary`, `what_organizations_must_do` in your own words.
4. Add sections, obligations (with `category`, `actors`, `source_reference`, evidence examples, optional framework mappings), deadlines (`date_precision`, `deadline_status`) and sources (tiered).
5. Add taxonomy terms only from `data/taxonomies/terms.yaml`; propose new terms in the same PR with a description.
6. Set `published: false` if the record is a draft for discussion; otherwise leave `published: true` and `review_status: pending_review`.
7. `php artisan policy:validate`, then `php artisan policy:import` locally and check the page at `/policies/{slug}`.
8. Open a PR using the template; list every claim with its source URL and access date.

## 5. Handling contributor submissions

1. Submissions from `/contribute` land in `contributor_submissions` with `status: pending_review` and are visible at `/backend/review` (admins only).
2. Triage weekly: approve, reject, or request information; the decision is stored in `reviewer_decisions` with the reviewer's user id.
3. Approved submissions are applied by a maintainer as a PR to `data/`; the submission id goes in the PR description. Nothing is published directly from the form.
4. Submitter contact details are used only for follow-up and are not exposed publicly.

## 6. Change detection

- Watch lists per jurisdiction: official journal / gazette, legislature bill trackers, regulator news pages, consultation portals, and the Commission/AI Office pages for the EU. Record the watched URLs in the jurisdiction file's `official_sources` so they are visible.
- Any dated development becomes a change event (`data/changes/{year}.yaml`) with `impact_level`; affected records get `needs_update` until edited.
- Do not create change events from secondary reporting alone; wait for the official publication or cite the official announcement.

## 7. What must never happen

- Inventing dates, penalties, obligations, article numbers or enforcement outcomes. Use `confidence_level: unavailable` and leave the field empty.
- Marking `verified` without a human opening the source.
- Reproducing copyrighted commentary or standards text (ISO/IEC, IEEE) in any field.
- Publishing a record without an official source URL.
- Editing the database directly instead of `data/` (the next import will overwrite it).
