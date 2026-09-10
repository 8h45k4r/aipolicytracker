## Summary

<!-- What does this change and why? Link the issue. -->

Closes #

## Type of change

- [ ] Bug fix
- [ ] New feature
- [ ] Policy / regulatory data change
- [ ] Documentation
- [ ] Refactor / chore

## Sources (required for any policy or regulatory data change)

| Claim / field changed | Official or openly licensed source URL | Date accessed |
|-----------------------|----------------------------------------|---------------|
|                       |                                        |               |

- [ ] Every data change links to an official or publicly licensed source (`SOURCE_ATTRIBUTION.md`).
- [ ] No copyrighted legal commentary, paid database content, or standards text reproduced.

## Role gates (binding, see `docs/reference/change-gates.md`)

Fill every gate. Use **Pass**, **N/A (reason)**, or **Debt #n** (entry in `docs/reference/technical-debt.md`). Never leave a gate blank.

### 1. Engineering & QA/QC
- Static checks (`npm run lint`, `npm run build`, `composer lint`, `composer test`):
- No fake success:
- No demo tables/rows shipped:
- Scoping enforced database-side / RLS or equivalent on new tables:
- Interlinks proven by query (total = resolves), paste counts:
- Module has real inbound and outbound links:

### 2. UI/UX
- Platform primitives only (PageHeader, DataTable, FormDialog, ConfirmDialog, skeleton/empty/error):
- Semantic colour tokens:
- Null renders `—`, never `0`:
- Simulated values labelled; unresolvable ids show "Unavailable":
- No dead-end records:
- Screenshots (before/after):

### 3. Documentation
- `docs/modules/<module>.md` exists and field table matches schema:
- Interlinks documented both ways:
- README and CHANGELOG updated:
- Migration carries a why comment:

### 4. Compliance
- Mapped in `docs/reference/compliance-map.md` or out of scope with reason:
- Evidence chain not weakened:
- Secrets stored as digests / host secret store, never plaintext:

## Accepted debt

<!-- List technical-debt.md entry numbers added or touched by this PR, with owners. -->
