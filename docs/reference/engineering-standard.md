# Engineering standard

Binding for every change to this repository, by a person or an automated assistant, including one-line fixes. It sits above the [change gates](change-gates.md): the gates are the evidence a change must present; this standard is how the change is made so that the evidence exists.

We are building production-grade software through proper software engineering. Act as a principal architect and senior engineer: understand first, design second, code third, and prove every claim.

## 1. Before coding: inspect, do not assume

Read the parts of the system the change touches, and the parts that touch them:

| Area | Where to look |
|------|---------------|
| Architecture and domain boundaries | `docs/modules/README.md` and the module doc for each affected module; `app/Services/*` for domain services; `app/Http/Controllers/Site`, `Api`, `Backend` for the three delivery surfaces |
| Database | `database/migrations/*` (every migration carries a *why* comment); field tables in `docs/modules/<module>.md` are generated from the schema and must stay in sync |
| APIs | `routes/public.php`, `routes/api.php`, `routes/backend/web.php`; `resources/openapi/openapi.php` for the public contract |
| Auth and RBAC | `auth` and `isAdmin` middleware (`App\Http\Middleware\CheckAdmin`), `ADMIN_EMAILS`, `User::isAdmin()`; signed URLs and owner checks on user-bound routes |
| Data and provenance | `data/` (canonical YAML), `data/schema/*.json`, `App\Services\PolicyData\*`; verification rules in `DATA_UPDATE_OPERATIONS.md` |
| Integrations | `docs/modules/external-data.md`, `.github/workflows/*.yml`, `azure/`, `cloudflare/`, `docker/` |
| Tests | `tests/Feature/*` (feature and integration), `tests/Feature/Site/PublicSiteTest.php` (public surface, SEO rules, API, review workflow, and the foreign-key interlink proof in `test_policy_intelligence_interlinks_resolve`) |
| Known gaps | `docs/reference/technical-debt.md`, `docs/reference/compliance-map.md`, `docs/reference/vapt-<date>.md` |

Do not duplicate what exists: search for an existing service, component, scope, command or test before adding one. Do not invent business rules, permissions, API fields or database structures; if a rule is missing, say so in the pull request and propose it explicitly.

## 2. The workflow for every change

Analyze → Design → Implement → Test → Validate → Document. Each step leaves an artefact a reviewer can check.

1. **Analyze.** State the problem, the root cause (not the symptom), the affected modules and the blast radius. Confirm the behaviour today with a test, a query or a reproduction.
2. **Design.** Decide the smallest reversible change that fixes the root cause. Keep domain boundaries clean: data rules in `data/` and the importer, read rules in services, HTTP concerns in controllers and requests, presentation in Blade components. Name the security, RBAC, isolation, validation and audit consequences before writing code.
3. **Implement.** Follow existing conventions (Form Requests or `$request->validate()` for input, query scopes for publication and ownership, enums for controlled vocabularies, `x-site.*` components for public UI). No secrets in code. No exception details to clients.
4. **Test.** Write or extend real tests where behaviour changed: feature tests for routes and workflows, integrity tests for new foreign keys, dataset tests for new data rules. A change that cannot be tested is explained in the PR, not skipped silently.
5. **Validate.** Run the checks and paste the output into the pull request:
   ```bash
   php artisan policy:validate
   composer test
   composer lint
   npm run lint
   npm run build
   ```
   For UI changes, check the page at 390, 820 and 1440 px. For security-relevant changes, run the gate 5 probes.
6. **Document.** Update the module doc, `README.md`, `CHANGELOG.md`, the compliance map or debt register, and the operations manuals that describe the changed behaviour.

## 3. Non-negotiables

- **Security and access control.** Every admin route sits behind `auth` and `isAdmin`; every user-bound route checks ownership database-side; every POST is CSRF-protected; public write endpoints are throttled and honeypotted. Never widen access to make a test pass.
- **Isolation.** Public surfaces expose only rows with `published_at` set through the `published()` scope; unpublished detail pages return 404. Per-user data is filtered by user id in the query, never in the view.
- **Validation.** All input is validated server-side; enum values come from `App\Enums\*` or `data/taxonomies/terms.yaml`; data records pass `policy:validate` before import.
- **Auditability.** Provenance and verification fields are never removed or made optional; reviewer decisions, verifications and activity logs are written, not implied. A record becomes `verified` only when a named human has opened the official source.
- **Truthful UI.** Missing values render as `—` or a labelled "not yet" state; pending, low-confidence and simulated values are labelled; no page reports success before the write happened.
- **Integration.** Frontend, backend, database and API stay connected: a new field appears in the migration, the model, the importer, the serializer, the OpenAPI document, the module doc and a test.
- **Errors, edge cases, performance, regressions.** Handle empty states, missing records, malformed input and external-service failure explicitly; add eager loads and cache headers where a page or API is read-heavy; keep old URLs working with 301s; never remove a test to get green.
- **Small and reversible.** One concern per pull request; feature flags or `published: false` rather than half-shipped behaviour; migrations with a working `down()`.

## 4. Definition of done

A change is done only when all of the following are true and evidenced in the pull request:

- [ ] Root cause identified and fixed; no symptom patches.
- [ ] Security, RBAC, isolation, validation and audit consequences stated and covered.
- [ ] Tests added or extended; `composer test`, `npm run lint`, `npm run build`, `composer lint` and `php artisan policy:validate` pass, with output pasted.
- [ ] Frontend, backend, database, API and docs updated together.
- [ ] Documentation updated (module doc, README, CHANGELOG, compliance map or debt register, operations manuals as relevant).
- [ ] The five [change gates](change-gates.md) are filled in with evidence; accepted debt is recorded with an owner.
- [ ] No regression: existing tests unchanged unless the behaviour they assert was deliberately changed and the change is explained.

## 5. Language of verification

Never write "it works", "should work" or "verified" without saying how. State the command, the environment and the observed result ("54 tests passed on SQLite in CI; PostgreSQL parity is debt #15"). If something could not be verified in the environment at hand, say so and record it as debt or as a manual task with an owner.
