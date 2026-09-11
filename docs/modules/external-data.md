# External data (AI risk and incidents)

Third-party datasets presented on the public site under their own licences. They are stored as reviewed JSON files under `data/external/`, refreshed weekly by `.github/workflows/refresh-external-data.yml` through a pull request, and read by `App\Services\ExternalData\ExternalDataset`. No database tables are involved.

## Files

| File | Source | Licence | Refreshed by |
|------|--------|---------|--------------|
| `data/external/aiid_summary.json` | AI Incident Database weekly Excel export (Responsible AI Collaborative) | CC BY-SA 4.0 (non-commercial use per AIID terms) | `php artisan external:sync-aiid` |
| `data/external/mit_ai_risk_domains.json` | MIT AI Risk Repository spreadsheet, "Domain Taxonomy of AI Risks v1" tab | CC BY 4.0 | `php artisan external:sync-mit-risk` |

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
- **Inbound:** routes `risk.index`, `risk.domain`, `risk.incidents`; sitemap (static section); `llms.txt`.

## Routes

`/ai-risk`, `/ai-risk/{1-7}`, `/ai-risk/incidents` (`Site\RiskController`).

## Attribution rules

Every page shows source, licence link, snapshot date and citation (`x-site.attribution`). Do not remove the attribution block or add report text.
