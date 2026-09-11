# Change gates

Every change to `main` must pass the four role gates below, in order. Each gate re-checks the one before it. This is binding, not advisory, and applies to one-line changes as much as to features. Record the outcome of all four gates in the pull request description with evidence (query output, counts, command output). A gate that does not apply is marked **N/A with a reason**, never left blank.

## 1. Engineering & QA/QC

- Static checks clean: `npm run lint`, `npm run build`, `composer lint`, `composer test`.
- No fake success: no endpoint or UI path reports success without the underlying write or read having happened.
- No demo `<name>_table` or demo rows shipped to production; sample data lives only in seeders that are explicitly labelled illustrative and are not run by the production startup script.
- Organisation/user scoping is enforced database-side (query scopes or policies), never only in the client.
- Row-level security (or the equivalent database policy) on new tables where the database supports it; on this stack, scoping is enforced through Eloquent global scopes and authorisation policies, and that must be stated in the gate.
- Every interlink is proven with a query: for each foreign key, `total rows` must equal `rows whose reference resolves`. `tests/Feature/DataIntegrityTest.php` runs these checks in CI.
- The module has real inbound and outbound links (it is referenced by, and references, other modules).

## 2. UI/UX

- Platform primitives only: `PageHeader`, `DataTable`, `FormDialog`, `ConfirmDialog`, and all three of skeleton / empty / error states. (Until these exist in `resources/js/Components/`, this is recorded as debt, see `technical-debt.md`.)
- Semantic colour tokens from `tailwind.config.js` (`primary`, `secondary`), never raw hex in components.
- Null renders `—`, never `0`.
- Simulated or sample values are labelled as such.
- Unresolvable ids show "Unavailable".
- No dead-end records: every record page links back to its parent and to its related records.

## 3. Documentation

- `docs/modules/<module>.md` exists and its field table matches the actual schema (migrations).
- Interlinks documented in both modules' docs.
- `README.md` and `CHANGELOG.md` updated.
- Every migration carries a comment explaining *why*.
- A new module without a module doc does not merge.

## 4. Compliance

- The change is mapped in `docs/reference/compliance-map.md`, or recorded there as out of scope with a reason.
- The evidence chain is never weakened (sources, verification dates, activity logs are not removed or made optional).
- Secrets are stored as digests or in the host secret store, never in plaintext.

## Accepted debt

Debt accepted during a gate goes in `docs/reference/technical-debt.md` with an owner. Undocumented debt does not exist and will be found by an auditor instead of by us.
