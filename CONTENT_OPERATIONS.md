# Content Operations

How editorial pages are created, refreshed and retired. Editorial text lives in `config/content.php` (landing pages, guides, curated comparisons); everything factual lives in `data/` and is governed by `DATA_UPDATE_OPERATIONS.md`.

## 1. Page types and where they come from

| Page type | Source | Indexable when |
|-----------|--------|----------------|
| Jurisdiction page `/jurisdictions/{slug}` | `data/jurisdictions/*.yaml` + live policies, deadlines, changes | overview, status summary and ≥ 1 published instrument with official source |
| Policy page `/policies/{slug}` | `data/policies/**/*.yaml` | summary, scope, official source, published |
| Obligation page `/obligations/{slug}` | obligations inside a policy file | summary present and parent policy indexable |
| Change log `/changes`, `/changes/{year}` | `data/changes/*.yaml` | always (year pages need ≥ 1 change) |
| Landing page `/eu-ai-act`, `/ai-regulation-{x}` | `config/content.php` → `landings` + live records | ≥ 1 published instrument for the jurisdiction(s) |
| Guide `/guides/{slug}` | `config/content.php` → `guides` + live records | always (guides must reference real records) |
| Curated comparison `/compare/{slug}` | `config/content.php` → `comparisons` + `ComparisonBuilder` | both jurisdictions published |
| Ad-hoc comparison, applicability results, search | generated | never (noindex,follow) |

Programmatic pages are never mass-generated: a landing page or comparison exists only when a maintainer adds it to `config/content.php`, and it stays `noindex` until the data threshold is met.

## 2. Quality bar before publishing a new jurisdiction or policy page

A page may be published (`published: true`) only when all of the following hold:

1. **Official source:** `official_source_url` points to a government, legislature, regulator, court, standards body or intergovernmental page; secondary sources are labelled tier 3 and never the only source.
2. **Original text:** `summary_plain`, `scope_summary` and obligation summaries are written by the contributor in plain language with article/section references. No pasted commentary, no standards text.
3. **Answer first:** the first paragraph answers "what is it, is it binding, when does it apply" without hedging language that hides uncertainty.
4. **Status honesty:** `status`, `status_note` and `confidence_level` reflect what the source shows today. Proposed or delayed items are labelled as such.
5. **Dates:** every date has a source reference; unknown dates use `date_precision: tbd` with a label, never a guess.
6. **Minimum depth:** a policy needs at least one obligation or one deadline plus a scope statement; a jurisdiction needs an overview, status summary, binding-vs-guidance text and at least one regulator link.
7. **Disclaimer context:** no sentence reads as a legal conclusion about a specific reader ("you must"); use "organisations in scope must" tied to the source.
8. **Human review:** a reviewer has read the page in the browser at mobile and desktop width and checked all links open.

## 3. Editorial templates

- **Jurisdiction:** "AI regulation in {name}" overview → current status → binding vs guidance → key instruments → upcoming deadlines → current priorities → latest changes → applicable sectors/use cases → how to use this information → official sources → FAQ.
- **Policy:** "What is {name}?" → who it applies to → when requirements apply (table) → what organisations must do (obligation accordions) → penalties → key sections → public-sector rules → official sources → change history → FAQ.
- **Obligation:** what it requires → practical action → who it applies to → evidence examples → framework mappings → similar obligations.
- **Use-case / sector pages:** not separate pages yet; served as indexable single-filter listings (`/policies?use_case=…`, `/obligations?category=…`). Promote a filter to an editorial landing page only when it has ≥ 5 published records and a query demand signal from Search Console.
- **Framework crosswalks:** guides with `framework` set render the live mapping table from `framework_mappings`; add mappings in the policy YAML, not in the guide text.
- **Monthly digest:** create `/changes/{year}` archives automatically; a written monthly digest is a guide entry summarising the month's urgent/high changes with links. Add only if the month had ≥ 3 high-impact events.
- **Research reports:** publish as guides with `Article` schema; must cite records by URL.

## 4. Monthly refresh process

1. Pull the list of records with `last_verified_at` older than 150 days: `php artisan tinker --execute='...'` or the admin review page. Assign reviewers.
2. Re-read the three most-visited jurisdiction pages against their sources; update `regulatory_status_summary` and `current_priorities` if anything moved.
3. Update landing page `answer` paragraphs in `config/content.php` when the underlying status changes; keep them free of dates that will rot.
4. Check that every FAQ answer is still true; remove FAQs rather than leave stale ones.
5. Review Search Console query mining output (see `SEO_OPERATIONS.md`) and open content requests.
6. Bump `content_version` and write `change_summary` on every edited record.

## 5. Change-log publishing workflow

1. A maintainer or contributor spots a development on an official channel.
2. Add an entry to `data/changes/{year}.yaml` with `impact_level` (urgent: binding obligations start or change now; high: adopted law, delay, major guidance; routine: consultations, minor guidance), `status_after`, official source and `review_status: pending_review`.
3. Update the affected policy record (status, deadlines, `content_version`, `change_summary`).
4. Run `php artisan policy:validate`, open a PR with the source table.
5. Reviewer verifies, sets `verified` and `last_verified_at`, merges. The RSS feed and API update on deploy.

## 6. Retiring content

Never delete a published record; set `status: superseded|repealed|archived`, keep sources, and add a change event. Set `published: false` only for records that should not have been published (for example unsourced). Add a redirect if a URL changes.

## 7. AI assistance policy

AI tools may draft summaries and detect changes. Drafts enter as `pending_review` and are never marked `verified` without a human opening the source. The methodology page discloses this.
