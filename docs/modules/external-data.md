# External data (AI risk and incidents)

Third-party datasets presented on the public site under their own licences. They are stored as reviewed JSON files under `data/external/`, refreshed weekly by `.github/workflows/refresh-external-data.yml` through a pull request, and read by `App\Services\ExternalData\ExternalDataset`. The two row-level files are additionally imported into read-model tables by `php artisan external:import` (runs on deploy) so they can be filtered, charted and exported.

## Files

| File | Source | Licence | Refreshed by |
|------|--------|---------|--------------|
| `data/external/aiid_summary.json` | AI Incident Database weekly Excel export (Responsible AI Collaborative) | CC BY-SA 4.0 (non-commercial use per AIID terms) | `php artisan external:sync-aiid` |
| `data/external/mit_ai_risk_domains.json` | MIT AI Risk Repository spreadsheet, "Domain Taxonomy of AI Risks v1" tab | CC BY 4.0 | `php artisan external:sync-mit-risk` |

## Files (row level)

| File | Rows | Imported into |
|------|------|---------------|
| `data/external/aiid_incidents.json` | one object per incident: `incident_id, date, title, description (≤500), deployers[], developers[], harmed[], report_count, mit_domain, mit_subdomain, entity, intent, timing, sectors[], countries[], harm_level` | `external_incidents` |
| `data/external/mit_risks.json` | `papers[] {quick_ref, title, paper_id, risks}` and `risks[] {ev_id, quick_ref, paper_title, level, risk_category, risk_subcategory, description (≤600), entity, intent, timing, domain, subdomain}` | `external_risks` |

## Schema: `external_incidents`

| Field | Type | Null | Notes |
|-------|------|------|-------|
| incident_id | unsigned int | no | Primary key (AIID id) |
| occurred_on | date | no | Indexed |
| year | smallint | no | Indexed |
| title | varchar(200) | no | |
| description | varchar(500) | yes | |
| deployers / developers / harmed | json | yes | Entity names (≤5) |
| report_count | smallint | no | |
| mit_domain | varchar(80) | yes | AIID label; indexed |
| mit_subdomain | varchar(120) | yes | Indexed |
| entity / intent / timing | varchar(40) | yes | MIT causal coding |
| sectors / countries | json | yes | CSET classification |
| harm_level | varchar(60) | yes | Indexed |
| snapshot_date | date | yes | |
| created_at / updated_at | timestamp | yes | |

## Schema: `external_risks`

| Field | Type | Null | Notes |
|-------|------|------|-------|
| ev_id | varchar(32) | no | Primary key (repository Ev_ID) |
| quick_ref | varchar(80) | no | Paper key; indexed |
| paper_title | varchar(200) | no | |
| level | varchar(32) | no | Risk Category / Risk Sub-Category / Additional evidence |
| risk_category / risk_subcategory | varchar(200) | yes | |
| description | varchar(600) | yes | Extracted evidence (CC BY 4.0) |
| entity / intent / timing | varchar(24) | yes | Indexed |
| domain | tinyint | yes | 1–7; indexed |
| subdomain | varchar(8) | yes | e.g. 2.1; indexed |
| created_at / updated_at | timestamp | yes | |

## `aiid_summary.json` fields

| Field | Type | Notes |
|-------|------|-------|
| source, source_url, export_file, snapshot_date, generated_at | string | Provenance |
| license, license_url, terms_url, citation | string | Attribution shown on every page |
| totals.incidents / classified_mit / with_country | int | |
| incidents_per_year | map year → int | Incident date, not report date |
| by_mit_domain | map label → int | Labels as used by AIID's MIT taxonomy |
| by_sector, by_country, by_harm_level | map → int | CSET classification; partial coverage |
| domain_by_year | map year → (label → int) | 2016 onward |
| latest[] | {id, date, title, domain, countries[]} | 30 most recent; links to `incidentdatabase.ai/cite/{id}` |

Report texts, images and submissions are never stored (excluded from AIID's licence).

## `mit_ai_risk_domains.json` fields

| Field | Type | Notes |
|-------|------|-------|
| source, source_url, sheet_url, navigator_url, edition, generated_at | string | Provenance |
| license, license_url, citation, changes_note | string | Attribution and statement of changes |
| domains[] | {id, name, description, aiid_domain_label, use_cases[], subdomains[]} | Names/definitions verbatim (CC BY 4.0); `use_cases` is our editorial mapping to the site's use-case taxonomy |
| domains[].subdomains[] | {id, name, description} | 24 subdomains |

## Interlinks

- **Outbound:** `domains[].use_cases` → `taxonomies/terms.yaml` (`use_case` slugs) → published policy instruments ([policy-intelligence.md](policy-intelligence.md)).
- **Inbound:** routes `risk.*`; sitemap (static section); `llms.txt`; Open data page; admin External data page.
- **Interlinks:** `external_risks.domain` → `mit_ai_risk_domains.json` ids (1–7, 100 % resolve); `external_incidents.mit_domain` → `domains[].aiid_domain_label`.

## Routes

`/ai-risk`, `/ai-risk/{1-7}`, `/ai-risk/incidents` (`Site\RiskController`); `/ai-risk/incidents/browse`, `/ai-risk/incidents/export.{csv|json}`, `/ai-risk/risks`, `/ai-risk/risks/export.{csv|json}`, `/ai-risk/frameworks` (`Site\RiskBrowseController`). Exports prepend the source, licence and citation.

## Attribution rules

Every page shows source, licence link, snapshot date and citation (`x-site.attribution`). Do not remove the attribution block or add report text.
