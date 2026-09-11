# Project rules for automated assistants

These rules are binding for every change to `main`, including one-line fixes.

- Commit as `Bhaskar Bhatt <8h45k4r@gmail.com>`. No AI attribution in code, docs, or commit messages.
- Never push directly to `main`. Open a pull request and fill in the four role gates (see `docs/reference/change-gates.md`) with evidence.
- Run the gates in order: Engineering & QA/QC, UI/UX, Documentation, Compliance. Each gate re-checks the previous one.
- A gate that does not apply is marked N/A with a reason; it is never left blank.
- Accepted debt is recorded in `docs/reference/technical-debt.md` with an owner. Undocumented debt does not exist.
- A new module without `docs/modules/<module>.md` does not merge.
- Secrets are stored as digests or in the host's secret store, never in plaintext in the repository.
