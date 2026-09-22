# Controls

**What it is.** A control is what an organisation operates to meet one or more legal duties: a policy, a process, a technical measure, a contractual term or a training programme. Each control records the evidence it produces, the owner and frequency, the MIT AI Risk Repository subdomains it addresses and the standards clauses it corresponds to. Obligations reference controls as `satisfies` (the control, operated properly, does the work the duty asks for) or `supports` (it contributes but the duty needs more). One control usually serves several duties in several jurisdictions; the control page lists all of them, which is the reuse a compliance lead is looking for.

**Why it exists.** An obligation said what the law requires and listed example evidence, and nothing stood between the two. The chain the site now publishes is LAW → OBLIGATION → CONTROL → EVIDENCE, with risks as a property of the control. See `docs/strategy/ai-governance-intelligence.md` for the reasoning and the build order.

## Data

| Where | What |
|---|---|
| `data/controls/<slug>.yaml` | One file per control. Schema: `data/schema/control.schema.json`. The slug must equal the file name. |
| `data/taxonomies/terms.yaml` → `evidence_type` | The controlled vocabulary for `evidence[].type`. |
| `data/policies/**/*.yaml` → `obligations[].controls` | `{ control, relationship, note?, confidence_level? }`. Validated: the control must exist. |
| `config/frameworks.php` | Registry of the standards, frameworks and threat models a control may cite, each with a `kind` (management standard, risk framework, assessment method, threat model, principles). |

Rules the validator enforces (`php artisan policy:validate`): schema; unique slugs; evidence types from the taxonomy; `related_controls` resolve; obligation → control references resolve; a `verified` control names a published reviewer. Standards are cited by clause, function, entry or mitigation identifier only; no standard text is reproduced.

## Read model

`policy:import` writes `controls`, `control_evidence`, `control_framework_references` and the `control_obligation` pivot. A control missing from `data/` after an import is unpublished, never deleted, so no link dangles. Models: `App\Models\Control`, `ControlEvidence`, `ControlFrameworkReference`; `Obligation::controls()`.

`App\Services\PolicyData\ControlIntelligence` supplies the joins: risk subdomains with live incident and risk-entry counts, and per-framework counters (controls, evidence types, risk areas, incidents).

## Surfaces

| Surface | Route |
|---|---|
| Index, filterable by kind and search | `/controls` |
| One control, every duty it serves | `/controls/{slug}` |
| Markdown context file | `/controls/{slug}.md` (noindex, canonical to the page) |
| API | `/api/v1/controls?kind=&framework=`, `/api/v1/controls/{slug}`; `controls[]` on every obligation |
| Exports | `/open-data/controls.csv`, `/open-data/controls.ndjson` |
| Sitemap | `/sitemap-controls.xml` |
| Framework pages | counters and a "controls that cite this" table |
| Obligation pages | "Which controls meet this duty?" and an evidence table |

Indexability: a control is indexable when it is published, has a purpose, at least one evidence item and at least one published duty (`Control::isIndexable()`); the sitemap applies the same rule.

## Tests

`tests/Feature/ControlsTest.php`: the corpus validates, every obligation names a control and every control is referenced; the control page lists every duty; the API, exports, sitemap, llms-full and OpenAPI carry controls; framework pages count them.
