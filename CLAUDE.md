# Project rules for automated assistants

These rules are binding for every change to `main`, including one-line fixes.

- Commit as `Bhaskar Bhatt <8h45k4r@gmail.com>`. No AI attribution in code, docs, or commit messages.
- Never push directly to `main`. Open a pull request and fill in the five role gates (see `docs/reference/change-gates.md`) with evidence.
- Run the gates in order: Engineering & QA/QC, UI/UX, Documentation, Compliance, Security. Each gate re-checks the previous one.
- A gate that does not apply is marked N/A with a reason; it is never left blank.
- Accepted debt is recorded in `docs/reference/technical-debt.md` with an owner. Undocumented debt does not exist.
- A new module without `docs/modules/<module>.md` does not merge.
- Secrets are stored as digests or in the host's secret store, never in plaintext in the repository.
- Follow `docs/reference/engineering-standard.md` for every change: inspect the architecture, database, APIs, auth/RBAC, integrations and tests before coding; never assume or duplicate existing functionality; never invent business rules, permissions, API fields or database structures.
- Work Analyze → Design → Implement → Test → Validate → Document. Architecture first, code second; fix root causes, not symptoms; prefer small, reversible, well-scoped changes.
- Enforce security, RBAC, publication and per-user isolation, server-side validation and auditability in every change; keep frontend, backend, database, API and docs connected.
- Write real tests where behaviour changes. Never say "it works" until it has been verified with a command whose output is in the pull request; the definition of done is production-ready, secure, tested, maintainable, integrated, documented and regression-safe.
