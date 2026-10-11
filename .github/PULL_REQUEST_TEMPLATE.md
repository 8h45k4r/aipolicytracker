## Summary

<!-- What does this change and why? Link the issue. -->

Closes #

<!--
Data-only change (only files under data/)? Keep the Sources table, paste the
`php artisan policy:validate` and `php artisan policy:import` output under gate 1,
and leave gates 2–5 as "N/A (data only)". The gate check only needs the headings
to be present.
-->

## Sources (required for any policy or regulatory data change)

| Claim / field changed | Official or openly licensed source URL | Date accessed |
|-----------------------|----------------------------------------|---------------|
|                       |                                        |               |

- [ ] Every data change links to an official or openly licensed source (`SOURCE_ATTRIBUTION.md`).
- [ ] No copyrighted legal commentary, paid database content or standards text reproduced.

## Role gates (see `docs/reference/change-gates.md`)

Write **Pass**, **N/A (reason)** or **Debt #n** (an entry in `docs/reference/technical-debt.md`) for each.

### 1. Engineering & QA/QC
- Checks run (`composer lint`, `composer test`, `npm run lint`, `npm run build`; for data, `policy:validate` and `policy:import`), output pasted:
- Root cause fixed, not the symptom:
- Tests added or updated for changed behaviour:

### 2. UI/UX
- Uses the existing Blade components (`x-site.*`, `x-backend.*`: page-header, drawer, empty, badge, stat) and colour tokens:
- Empty, error and long-content states checked; nothing renders `0` for "unknown":
- Screenshots (before/after) for visible changes:

### 3. Documentation
- `docs/modules/<module>.md` updated (field table matches the migration):
- README / CHANGELOG updated if a reader would notice:

### 4. Compliance
- Licences and attribution respected; personal data not added:

### 5. Security
- Input validated server-side; authorisation checked on new routes:
- `composer audit` / `npm audit --omit=dev`:

## Accepted debt

<!-- technical-debt.md entries added or touched by this PR, or "None". -->
