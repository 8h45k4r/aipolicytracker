# External data (AI risk and incidents)

Third-party datasets presented on the public site under their own licences. They are stored as reviewed JSON files under `data/external/`, refreshed weekly by `.github/workflows/refresh-external-data.yml` through a pull request, and read by `App\Services\ExternalData\ExternalDataset`. The row-level files are additionally imported into read-model tables by `php artisan external:import` (runs on deploy) so they can be filtered, charted and exported.

Incidents are also **synced live** from the AI Incident Database GraphQL API by `php artisan external:sync-aiid-api` (see "Live sync" below), so the latest records, their entities, classifications, editor notes, similar incidents and report metadata appear on the site within hours rather than after the weekly export.

## Live sync (AI Incident Database API)

| Item | Detail |
|------|--------|
| Command | `php artisan external:sync-aiid-api` (`--since=<ISO>`; `--full`; `--max=600`; `--write-json`; `--no-db`) |
| Client | `App\Services\ExternalData\AiidApiClient` → `https://incidentdatabase.ai/api/graphql`. The endpoint answers only requests carrying the site's own `Origin` header; the client sends it with a User-Agent that names this project and links to the open-data page. Pages of 100, two retries, 60 s timeout. |
| Default window | Records with `date_modified` after the newest `modified_at` in `external_incidents` minus one day; on a database without synced rows, seven days before the snapshot date. |
| Fields | `incident_id, date, date_modified, title, description, editor_notes`, alleged deployer / developer / harmed parties (name and `entity_id`), `implicated_systems`, `editor_similar_incidents`, top-five `nlp_similar_incidents`, report metadata (`report_number, title, url, source_domain, date_published, authors, language`). Published classifications in the `MIT` (risk domain, subdomain, entity, intent, timing; numeric prefixes stripped to match the snapshot labels) and `CSETv1` (AI harm level, sector of deployment, location country) namespaces. Report texts, images and embeddings are never requested. |
| Precedence | A snapshot-derived classification is kept when the API has none for that record. `external:import` skips rows whose `synced_at` is on or after the file's `snapshot_date` and never prunes rows with `synced_at`, so a deploy cannot downgrade or delete live-synced records. |
| Triggers | `.github/workflows/sync-aiid.yml` every six hours → `POST /cron/external-sync` (bearer `CRON_TOKEN`, incremental, up to 300 records per call, HTTP 502 when the API fails); Admin → External data → "Sync now"; weekly `refresh-external-data.yml` runs `--full --write-json --no-db` so the committed JSON carries the same fields for clean deploys and CI. |
| Status | Admin → External data shows synced rows, latest id, last sync time and the last run summary (cache key `aiid-api-last-run`); `/ai-risk/incidents` shows the sync time and latest id. The hourly narrative cache is cleared after each run. |
| Fallback | If the API refuses requests, the weekly Excel/backup path continues to populate the tables; the command exits non-zero and the workflow run shows red. |

## Files

| File | Source | Licence | Refreshed by |
|------|--------|---------|--------------|
| `data/external/aiid_summary.json` | AI Incident Database weekly Excel export (Responsible AI Collaborative) | CC BY-SA 4.0 (non-commercial use per AIID terms) | `php artisan external:sync-aiid` |
| `data/external/mit_ai_risk_domains.json` | MIT AI Risk Repository spreadsheet, "Domain Taxonomy of AI Risks v1" tab | CC BY 4.0 | `php artisan external:sync-mit-risk` |

## Files (row level)

| File | Rows | Imported into |
|------|------|---------------|
| `data/external/aiid_incidents.json` | one object per incident: `incident_id, date, title, description (≤500), deployers[], developers[], harmed[], report_count, mit_domain, mit_subdomain, entity, intent, timing, sectors[], countries[], harm_level` plus, once merged by the live sync, `editor_notes, entities, implicated_systems, similar_incidents, modified_at` (file-level `api_synced_at`) | `external_incidents` |
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
| snapshot_date | date | yes | Date of the weekly export the row came from |
| editor_notes | text | yes | AIID editor notes (≤2000 chars); live sync only |
| entities | json | yes | `{deployers[], developers[], harmed[]}` of `{id, name}` (AIID `entity_id`); live sync only |
| implicated_systems | json | yes | `[{id, name}]`; live sync only |
| similar_incidents | json | yes | `{editor: [ids], nlp: [{id, similarity}]}`; live sync only |
| modified_at | datetime | yes | AIID `date_modified`; indexed; drives the incremental window |
| synced_at | datetime | yes | Last live sync of this row; indexed; null for snapshot-only rows |
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

## Schema: `external_incident_reports`

| Field | Type | Null | Default | Notes |
|-------|------|------|---------|-------|
| report_number | int | no | | Primary key; AIID report number |
| incident_id | int | no | | FK (logical) → external_incidents.incident_id; rows for unknown incidents are skipped on import |
| title | varchar(300) | no | | |
| url | varchar(2048) | no | | Original publisher URL |
| source_domain | varchar(190) | yes | | |
| date_published | date | yes | | |
| authors | json | yes | | Up to six names |
| language | varchar(8) | yes | | |
| synced_at | datetime | yes | | Set by the live sync; such rows are never pruned by `external:import` |
| created_at / updated_at | timestamp | yes | | |

Source: `data/external/aiid_reports.json`, produced by `external:sync-aiid-reports` from the weekly AIID mongodump backup (`incidents.csv` gives the incident→report links, `reports.csv` the metadata). Article text and descriptions are never copied. Shown on incident profiles as "News reports".

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

`/ai-risk` (narrative: totals and 12-month growth, annotated incident timeline with policy milestones from `config('content.risk_milestones')`, domain share shift, harmed parties and deployers, harm levels, country coverage gap, instruments per domain, persona paths, and domain→subdomain treemaps; numbers cached for an hour in `RiskController::narrative`), `/ai-risk/{1-7}`, `/ai-risk/{1-7}/{d.n}` (subdomain profile: definition, entity/intent/timing and level breakdowns, incidents per year, frameworks, paginated risk entries, recent incidents, related policies), `/ai-risk/incidents` (`Site\RiskController`; the "Latest recorded incidents" list is read from `external_incidents`, newest first, with the sync time, and links to each profile); `/ai-risk/incidents/browse`, `/ai-risk/incidents/export.{csv|json}`, `/ai-risk/risks`, `/ai-risk/risks/export.{csv|json}`, `/ai-risk/frameworks`, `/ai-risk/incidents/{incident_id}` (single incident profile: all stored fields, editor notes, entity links to AIID entity pages, implicated systems, news-report metadata, incidents AIID relates to it (editor picks and similarity model), incidents in the same subdomain and by the same deployer, MIT risk entries for the same subdomain, sync and last-edited dates, links to the AIID cite page and Discover report list) and `/ai-risk/risks/{ev_id}` (single MIT entry: description, domain and subdomain definition, paper siblings, other frameworks in the same subdomain, matching incidents; `#n` de-duplication suffixes are written `--n` in URLs) (`Site\RiskBrowseController`). Exports prepend the source, licence and citation.

## Digest

`digest:send` adds up to five incidents from the look-back window (with the count) to the weekly email; a digest is sent even when no policy change matched if incidents exist.

## Attribution rules

Every page shows source, licence link, snapshot date and citation (`x-site.attribution`). Do not remove the attribution block or add report text.
