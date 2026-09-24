# Policy data (canonical source of truth)

This directory is the reviewable, version-controlled source for every policy record on
aipolicytracker.org. The database is a read model built from these files.

```
data/
  schema/         JSON Schema for each record type (validated in CI)
  taxonomies/     controlled vocabularies (actors, sectors, use cases, ...)
  jurisdictions/  one YAML file per jurisdiction
  policies/       one YAML file per policy instrument, grouped by jurisdiction
  controls/       one YAML file per organisational control (see docs/modules/controls.md)
  changes/        dated change-log entries, one file per year
  reviewers/      one YAML file per reviewer, with their declared interests
  email/          address-quality lists (not policy records; see docs/modules/accounts.md)
```

`email/` is the one directory here that does not hold policy records. It holds the
trusted-provider, throwaway-domain and throwaway-exchanger lists used to keep temporary
mailboxes out of accounts and the newsletter. It is version-controlled for the same reason
the policy records are: a rule that decides whether somebody can sign up should be readable
and reviewable, not buried in code. It is not validated by `policy:validate`; it is covered
by `EmailDomainPolicyTest`.

## Licence

The records here are CC BY 4.0 (`data/LICENSE`); `external/` keeps its upstream licences.
The code is Apache-2.0. By contributing a record you license it under CC BY 4.0.

## Workflow

1. Edit or add a YAML file. Copy an existing record as a template. The schema for each type is
   in `schema/` (for policies, `schema/policy.schema.json`) and states every required field and
   limit; for example `summary_plain` needs at least 80 characters and `scope_summary` at least 40.
   Set `last_checked_at` to the date you opened the official source.
2. Run `php artisan policy:validate` — schema, enum, URL, date and cross-reference checks.
3. Run `php artisan policy:import` — idempotent upsert into the database (also runs on deploy).
4. Open a pull request using the template; list every changed field with its official source URL.
5. A reviewer opens each `official_source_url`, confirms the fields, sets `review_status: verified`
   and `last_verified_at`, and merges. The reviewer must already be published in `reviewers/`
   with a declaration of interest: `policy:validate` rejects a `reviewed_by` that is not on the
   roster (see `docs/reference/reviewer-roster.md`). Reviewers can also do this in Admin → Review queue; run
   `php artisan policy:export-verifications` to write those decisions back into these files.

## Source-of-truth hierarchy (`source_tier`)

1. Official government, legislative, regulator, court, standards body or intergovernmental source.
2. Official consultation, guidance, enforcement, procurement or government-agency source.
3. Reputable institutional secondary source — only when clearly labelled as secondary.
4. Community submission, always `pending_review` until a reviewer promotes it.

## Data-quality fields

Every record that makes a factual claim carries: `official_source_url`, `source_title`,
`source_publisher`, `source_document_date`, `source_reference`, `source_tier`,
`last_checked_at`, `last_verified_at`, `review_status`, `confidence_level`,
`content_version`, `change_summary`, and `reviewed_by` (name or handle, optional).

`last_verified_at` must only be set by a human who opened the official source. If a fact
cannot be established from the source, leave the field empty and set `confidence_level`
to `unavailable` rather than guessing.

## Publishing

Records with `published: false` (or missing `published`) are imported but not shown publicly
or included in sitemaps/API responses. Maintainers can also unpublish from the admin review area.

## What not to add

No copyrighted legal commentary, no ISO/IEC standard text, no long verbatim excerpts.
Summaries are original plain-language descriptions with article/section references.
