# Policy intelligence

Structured, source-backed policy records: jurisdictions, policy instruments and their versions, sections, obligations, applicability rules, deadlines, enforcement events, procurement rules, framework mappings, evidence artifacts, change events, official source documents, and the contributor review workflow. The canonical data lives in `data/` (YAML validated by JSON Schema); these tables are the read model built by `php artisan policy:import`. Migration: `2026_09_10_000000_create_policy_intelligence_tables`.

Every record type that makes a factual claim carries the shared source-quality columns: `official_source_url`, `source_title`, `source_publisher`, `source_document_date`, `source_reference`, `source_tier`, `last_checked_at`, `last_verified_at`, `review_status`, `confidence_level`, `content_version`, `change_summary`, `reviewed_by`.

## Schema: `jurisdictions`

| Field | Type | Null | Default | Notes |
|-------|------|------|---------|-------|
| id | integer | no |  |  |
| slug | varchar | no |  | Unique; public URL key |
| name | varchar | no |  |  |
| short_name | varchar | yes |  |  |
| iso_code | varchar | yes |  |  |
| jurisdiction_type | varchar | no | 'country' |  |
| region | varchar | yes |  |  |
| subregion | varchar | yes |  |  |
| parent_jurisdiction_id | integer | yes |  | FK → jurisdictions.id (nullable, set null) |
| overview | text | yes |  |  |
| regulatory_status_summary | text | yes |  |  |
| binding_vs_guidance | text | yes |  |  |
| current_priorities | text | yes |  |  |
| how_to_use | text | yes |  |  |
| regulators | text | yes |  |  |
| official_sources | text | yes |  |  |
| faq | text | yes |  |  |
| related_jurisdictions | text | yes |  |  |
| featured | tinyint(1) | no | '0' |  |
| official_source_url | varchar | yes |  |  |
| source_title | varchar | yes |  |  |
| source_publisher | varchar | yes |  |  |
| source_document_date | date | yes |  |  |
| source_reference | varchar | yes |  |  |
| source_tier | integer | no | '1' |  |
| last_checked_at | datetime | yes |  |  |
| last_verified_at | datetime | yes |  |  |
| review_status | varchar | no | 'pending_review' | `App\\Enums\\ReviewStatus` |
| confidence_level | varchar | no | 'medium' | `App\\Enums\\ConfidenceLevel` |
| content_version | integer | no | '1' |  |
| change_summary | text | yes |  |  |
| reviewed_by | varchar | yes |  |  |
| published_at | datetime | yes |  | Null = not shown publicly (site, API, sitemaps) |
| created_at | datetime | yes |  |  |
| updated_at | datetime | yes |  |  |

## Schema: `policy_instruments`

| Field | Type | Null | Default | Notes |
|-------|------|------|---------|-------|
| id | integer | no |  |  |
| slug | varchar | no |  | Unique; public URL key |
| jurisdiction_id | integer | no |  | FK → jurisdictions.id (cascade) |
| title | varchar | no |  |  |
| short_title | varchar | yes |  |  |
| instrument_type | varchar | no |  |  |
| status | varchar | no |  | `App\\Enums\\PolicyStatus` |
| status_note | text | yes |  |  |
| is_binding | tinyint(1) | no | '0' |  |
| issuing_body | varchar | yes |  |  |
| summary_plain | text | yes |  |  |
| scope_summary | text | yes |  |  |
| who_it_applies_to | text | yes |  |  |
| key_dates_summary | text | yes |  |  |
| penalties_summary | text | yes |  |  |
| what_organizations_must_do | text | yes |  |  |
| adopted_on | date | yes |  |  |
| published_on | date | yes |  |  |
| in_force_on | date | yes |  |  |
| applies_from | date | yes |  |  |
| date_notes | text | yes |  |  |
| faq | text | yes |  |  |
| related_policies | text | yes |  |  |
| related_frameworks | text | yes |  |  |
| featured | tinyint(1) | no | '0' |  |
| official_source_url | varchar | yes |  |  |
| source_title | varchar | yes |  |  |
| source_publisher | varchar | yes |  |  |
| source_document_date | date | yes |  |  |
| source_reference | varchar | yes |  |  |
| source_tier | integer | no | '1' |  |
| last_checked_at | datetime | yes |  |  |
| last_verified_at | datetime | yes |  |  |
| review_status | varchar | no | 'pending_review' | `App\\Enums\\ReviewStatus` |
| confidence_level | varchar | no | 'medium' | `App\\Enums\\ConfidenceLevel` |
| content_version | integer | no | '1' |  |
| change_summary | text | yes |  |  |
| reviewed_by | varchar | yes |  |  |
| second_review | text | yes |  | JSON; independent second check by a different roster reviewer (`App\\Services\\Verification\\SecondReview`) |
| published_at | datetime | yes |  | Null = not shown publicly (site, API, sitemaps) |
| created_at | datetime | yes |  |  |
| updated_at | datetime | yes |  |  |

## Schema: `policy_versions`

| Field | Type | Null | Default | Notes |
|-------|------|------|---------|-------|
| id | integer | no |  |  |
| policy_instrument_id | integer | no |  | FK → policy_instruments.id (cascade) |
| version_label | varchar | no |  |  |
| version_date | date | yes |  |  |
| summary | text | yes |  |  |
| official_source_url | varchar | yes |  |  |
| source_reference | varchar | yes |  |  |
| sort_order | integer | no | '0' |  |
| created_at | datetime | yes |  |  |
| updated_at | datetime | yes |  |  |

## Schema: `policy_sections`

| Field | Type | Null | Default | Notes |
|-------|------|------|---------|-------|
| id | integer | no |  |  |
| policy_instrument_id | integer | no |  | FK → policy_instruments.id (cascade) |
| reference | varchar | no |  |  |
| title | varchar | yes |  |  |
| summary | text | yes |  |  |
| official_source_url | varchar | yes |  |  |
| sort_order | integer | no | '0' |  |
| created_at | datetime | yes |  |  |
| updated_at | datetime | yes |  |  |

## Schema: `obligations`

| Field | Type | Null | Default | Notes |
|-------|------|------|---------|-------|
| id | integer | no |  |  |
| slug | varchar | no |  | Unique; public URL key |
| policy_instrument_id | integer | no |  | FK → policy_instruments.id (cascade) |
| policy_section_id | integer | yes |  | FK → policy_sections.id (nullable, set null) |
| title | varchar | no |  |  |
| short_title | varchar(80) | yes |  | Topic as people search for it ("Human oversight"); page title only |
| category | varchar | no |  |  |
| summary | text | yes |  |  |
| practical_action | text | yes |  |  |
| is_binding | tinyint(1) | no | '0' |  |
| applies_from | date | yes |  |  |
| source_reference | varchar | yes |  |  |
| official_source_url | varchar | yes |  |  |
| last_verified_at | datetime | yes |  |  |
| review_status | varchar | no | 'pending_review' | `App\\Enums\\ReviewStatus` |
| confidence_level | varchar | no | 'medium' | `App\\Enums\\ConfidenceLevel` |
| sort_order | integer | no | '0' |  |
| published_at | datetime | yes |  | Null = not shown publicly (site, API, sitemaps) |
| created_at | datetime | yes |  |  |
| updated_at | datetime | yes |  |  |

## Schema: `applicability_rules`

| Field | Type | Null | Default | Notes |
|-------|------|------|---------|-------|
| id | integer | no |  |  |
| policy_instrument_id | integer | no |  | FK → policy_instruments.id (cascade) |
| obligation_id | integer | yes |  | FK → obligations.id (nullable, cascade) |
| description | text | no |  |  |
| actors | text | yes |  |  |
| ai_system_types | text | yes |  |  |
| sectors | text | yes |  |  |
| risk_categories | text | yes |  |  |
| use_cases | text | yes |  |  |
| conditions | text | yes |  |  |
| source_reference | varchar | yes |  |  |
| created_at | datetime | yes |  |  |
| updated_at | datetime | yes |  |  |

## Schema: `taxonomy_terms`

| Field | Type | Null | Default | Notes |
|-------|------|------|---------|-------|
| id | integer | no |  |  |
| taxonomy | varchar | no |  |  |
| slug | varchar | no |  | Unique; public URL key |
| name | varchar | no |  |  |
| description | text | yes |  |  |
| sort_order | integer | no | '0' |  |
| created_at | datetime | yes |  |  |
| updated_at | datetime | yes |  |  |

## Schema: `taxonomy_assignments`

| Field | Type | Null | Default | Notes |
|-------|------|------|---------|-------|
| id | integer | no |  |  |
| taxonomy_term_id | integer | no |  | FK → taxonomy_terms.id (cascade) |
| assignable_type | varchar | no |  | Morph: PolicyInstrument or Obligation |
| assignable_id | integer | no |  |  |
| created_at | datetime | yes |  |  |
| updated_at | datetime | yes |  |  |

## Schema: `deadlines`

| Field | Type | Null | Default | Notes |
|-------|------|------|---------|-------|
| id | integer | no |  |  |
| policy_instrument_id | integer | no |  | FK → policy_instruments.id (cascade) |
| obligation_id | integer | yes |  | FK → obligations.id (nullable, set null) |
| title | varchar | no |  |  |
| due_on | date | yes |  |  |
| date_precision | varchar | no | 'exact' |  |
| date_label | varchar | yes |  |  |
| description | text | yes |  |  |
| source_reference | varchar | yes |  |  |
| official_source_url | varchar | yes |  |  |
| deadline_status | varchar | no | 'scheduled' |  |
| confidence_level | varchar | no | 'medium' | `App\\Enums\\ConfidenceLevel` |
| sort_order | integer | no | '0' |  |
| created_at | datetime | yes |  |  |
| updated_at | datetime | yes |  |  |

## Schema: `enforcement_events`

| Field | Type | Null | Default | Notes |
|-------|------|------|---------|-------|
| id | integer | no |  |  |
| jurisdiction_id | integer | no |  | FK → jurisdictions.id (cascade) |
| policy_instrument_id | integer | yes |  | FK → policy_instruments.id (nullable, set null) |
| title | varchar | no |  |  |
| occurred_on | date | yes |  |  |
| authority | varchar | yes |  | Older name for `regulator`; the importer writes both |
| summary | text | yes |  |  |
| outcome | text | yes |  |  |
| slug | varchar(160) | yes |  | Indexed. Recorded, or derived from policy, date and title (`EnforcementEvent::slugFor`); unique across events (validator) |
| kind | varchar(24) | yes |  | Indexed. `fine`, `order`, `warning`, `settlement`, `court_decision`, `annulment` (`EnforcementEvent::KINDS`) |
| regulator | varchar | yes |  | The authority or court that acted |
| respondent | varchar | yes |  | The organisation acted against; never a private individual |
| amount | decimal(20,2) | yes |  | As published; null when none was published (never estimated) |
| currency | varchar(3) | yes |  | ISO 4217; required when `amount` is set (validator) |
| legal_basis | varchar | yes |  | Provision(s) relied on, as the source states them |
| appeal_status | varchar(24) | yes |  | `not_appealed`, `appeal_pending`, `upheld_on_appeal`, `overturned_on_appeal`, `unknown` |
| official_source_url | varchar | yes |  |  |
| source_title | varchar | yes |  |  |
| source_publisher | varchar | yes |  |  |
| source_document_date | date | yes |  |  |
| source_reference | varchar | yes |  |  |
| source_tier | integer | no | '1' |  |
| last_checked_at | datetime | yes |  |  |
| last_verified_at | datetime | yes |  |  |
| review_status | varchar | no | 'pending_review' | `App\\Enums\\ReviewStatus` |
| confidence_level | varchar | no | 'medium' | `App\\Enums\\ConfidenceLevel` |
| content_version | integer | no | '1' |  |
| change_summary | text | yes |  |  |
| reviewed_by | varchar | yes |  |  |
| published_at | datetime | yes |  | Null = not shown publicly (site, API, sitemaps) |
| created_at | datetime | yes |  |  |
| updated_at | datetime | yes |  |  |

Events are recorded inside the instrument's YAML (`enforcement_events`) and rebuilt on every import. The review queue lists the ones pending review read-only, with the policy file to change; the decision is made in that file by pull request. An optional `jurisdiction` files an event where the authority acted (a national authority under an EU regulation). An event is public while its instrument is. Listed across instruments at `/enforcement` (`EnforcementController`), `GET /api/v1/enforcement`, `/open-data/enforcement.{csv,ndjson}` and the MCP tool `list_enforcement_actions`.

## Schema: `implementation_measures`

Record type for the implementation and standards trackers, imported from `data/implementation/*.yaml` (schema `data/schema/implementation-measure.schema.json`). Migration `2026_10_07_100000_extend_enforcement_events_and_create_implementation_measures`.

| Field | Type | Null | Default | Notes |
|-------|------|------|---------|-------|
| id | integer | no |  |  |
| slug | varchar | no |  | Unique; equals the file name |
| policy_instrument_id | integer | yes |  | FK → policy_instruments.id (nullable, set null). YAML `instrument`; required unless the kind is a standard |
| related_policy_instrument_id | integer | yes |  | FK → policy_instruments.id (nullable, set null). YAML `related_policy` (e.g. the ISO/IEC 42001 record) |
| kind | varchar(32) | no |  | `delegated_act`, `implementing_act`, `guidelines`, `code_of_practice`, `template`, `ai_board_output`, `standardisation_request`, `harmonised_standard`, `iso_work_item` |
| title | varchar | no |  |  |
| status | varchar(24) | no |  | Declared: `unverified`, `planned`, `consultation`, `draft`, `adopted`, `published`. `overdue` is never stored: `ImplementationStatus::derive` computes it at read time |
| legal_basis | varchar | yes |  | Provision(s) that call for or concern the measure |
| summary | text | yes |  | Own words; null on drafts |
| due_on | date | yes |  | Date the instrument sets |
| adopted_on | date | yes |  |  |
| published_on | date | yes |  |  |
| published_on_precision | varchar(8) | no | 'exact' | `exact`, `month`, `year` (a year-only date is 1 January and shown as the year) |
| body | varchar(32) | yes |  | `cen_cenelec_jtc_21`, `iso_iec_jtc_1_sc_42`, `european_commission`, `ai_office`, `ai_board` |
| reference | varchar | yes |  | e.g. `ISO/IEC 42001:2023` |
| stage | varchar | yes |  | Standards stage as the body publishes it |
| oj_citation_expected_on | date | yes |  | Harmonised standards only (validator) |
| oj_citation_on | date | yes |  | Harmonised standards only (validator) |
| notes | text | yes |  |  |
| official_source_url … reviewed_by |  |  |  | The source-quality columns, as on every record; `source_tier` defaults to 4, `review_status` to `draft`, `confidence_level` to `low` |
| published_at | datetime | yes |  | Null = not shown publicly |
| created_at, updated_at | datetime | yes |  |  |

Pages: `/policies/{policy}/implementation` (non-standard measures of one instrument; 404 when it has none) and `/ai-standards` (standards). API `GET /api/v1/implementation-measures` (filters `instrument`, `kind`, `status` (derived), `body`, `standards`); exports `/open-data/implementation.{csv,ndjson}`; MCP `list_implementation_measures`. The include point for an instrument page is `<x-site.implementation-link :policy="$policy" />`, which renders nothing when the instrument has no measure.

Review: implementation measures are a kind in the admin review queue (`implementation_measure`); decisions export to `data/implementation/<slug>.yaml`. Published drafts and records pending review are listed on `/gaps` under their own heading.

Thin-content rule (`App\Services\Implementation\Trackers`): `/enforcement`, `/ai-standards` and each implementation page are `noindex` and left out of the sitemap until they hold 5 recorded (published, non-draft) entries; filtered views are always `noindex`.

## Schema: `procurement_rules`

| Field | Type | Null | Default | Notes |
|-------|------|------|---------|-------|
| id | integer | no |  |  |
| jurisdiction_id | integer | no |  | FK → jurisdictions.id (cascade) |
| policy_instrument_id | integer | yes |  | FK → policy_instruments.id (nullable, set null) |
| title | varchar | no |  |  |
| summary | text | yes |  |  |
| applies_to | varchar | yes |  |  |
| official_source_url | varchar | yes |  |  |
| source_title | varchar | yes |  |  |
| source_publisher | varchar | yes |  |  |
| source_document_date | date | yes |  |  |
| source_reference | varchar | yes |  |  |
| source_tier | integer | no | '1' |  |
| last_checked_at | datetime | yes |  |  |
| last_verified_at | datetime | yes |  |  |
| review_status | varchar | no | 'pending_review' | `App\\Enums\\ReviewStatus` |
| confidence_level | varchar | no | 'medium' | `App\\Enums\\ConfidenceLevel` |
| content_version | integer | no | '1' |  |
| change_summary | text | yes |  |  |
| reviewed_by | varchar | yes |  |  |
| published_at | datetime | yes |  | Null = not shown publicly (site, API, sitemaps) |
| created_at | datetime | yes |  |  |
| updated_at | datetime | yes |  |  |

## Schema: `framework_mappings`

| Field | Type | Null | Default | Notes |
|-------|------|------|---------|-------|
| id | integer | no |  |  |
| obligation_id | integer | no |  | FK → obligations.id (cascade) |
| framework | varchar | no |  |  |
| reference | varchar | no |  |  |
| note | text | yes |  |  |
| confidence_level | varchar | no | 'medium' | `App\\Enums\\ConfidenceLevel` |
| is_original | tinyint(1) | no | '1' |  |
| created_at | datetime | yes |  |  |
| updated_at | datetime | yes |  |  |

## Schema: `evidence_artifacts`

| Field | Type | Null | Default | Notes |
|-------|------|------|---------|-------|
| id | integer | no |  |  |
| obligation_id | integer | no |  | FK → obligations.id (cascade) |
| title | varchar | no |  |  |
| description | text | yes |  |  |
| artifact_type | varchar | no | 'document' |  |
| created_at | datetime | yes |  |  |
| updated_at | datetime | yes |  |  |

## Schema: `change_events`

| Field | Type | Null | Default | Notes |
|-------|------|------|---------|-------|
| id | integer | no |  |  |
| slug | varchar | no |  | Unique; public URL key |
| jurisdiction_id | integer | no |  | FK → jurisdictions.id (cascade) |
| policy_instrument_id | integer | yes |  | FK → policy_instruments.id (nullable, set null) |
| occurred_on | date | no |  |  |
| title | varchar | no |  |  |
| what_changed | text | no |  |  |
| practical_impact | text | yes |  |  |
| impact_level | varchar | no | 'routine' | `App\\Enums\\ImpactLevel` |
| status_after | varchar | yes |  |  |
| official_source_url | varchar | yes |  |  |
| source_title | varchar | yes |  |  |
| source_publisher | varchar | yes |  |  |
| source_document_date | date | yes |  |  |
| source_reference | varchar | yes |  |  |
| source_tier | integer | no | '1' |  |
| last_checked_at | datetime | yes |  |  |
| last_verified_at | datetime | yes |  |  |
| review_status | varchar | no | 'pending_review' | `App\\Enums\\ReviewStatus` |
| confidence_level | varchar | no | 'medium' | `App\\Enums\\ConfidenceLevel` |
| content_version | integer | no | '1' |  |
| change_summary | text | yes |  |  |
| reviewed_by | varchar | yes |  |  |
| published_at | datetime | yes |  | Null = not shown publicly (site, API, sitemaps) |
| created_at | datetime | yes |  |  |
| updated_at | datetime | yes |  |  |

## Schema: `source_documents`

| Field | Type | Null | Default | Notes |
|-------|------|------|---------|-------|
| id | integer | no |  |  |
| jurisdiction_id | integer | yes |  | FK → jurisdictions.id (nullable, cascade) |
| policy_instrument_id | integer | yes |  | FK → policy_instruments.id (nullable, cascade) |
| title | varchar | no |  |  |
| publisher | varchar | yes |  |  |
| url | varchar | no |  |  |
| document_date | date | yes |  |  |
| document_type | varchar | no | 'official' |  |
| source_tier | integer | no | '1' |  |
| language | varchar | no | 'en' |  |
| license_note | varchar | yes |  |  |
| retrieved_at | datetime | yes |  |  |
| snapshot_path | varchar | yes |  |  |
| checksum | varchar | yes |  |  |
| sort_order | integer | no | '0' |  |
| created_at | datetime | yes |  |  |
| updated_at | datetime | yes |  |  |

## Schema: `record_verifications`

| Field | Type | Null | Default | Notes |
|-------|------|------|---------|-------|
| id | bigint | no | | |
| record_type | varchar(24) | no | | policy or jurisdiction |
| record_slug | varchar(160) | no | | Unique with `record_type`; logical FK to `policy_instruments.slug` / `jurisdictions.slug` |
| review_status | varchar(32) | no | | verified, pending_review, needs_update |
| confidence_level | varchar(16) | no | | high, medium, low, unavailable |
| last_verified_at | date | yes | | Set only when the reviewer confirmed opening the official source |
| reviewed_by | varchar(120) | no | | Reviewer name at the time |
| source_checked_url | varchar(2048) | yes | | Official source URL on the record when verified |
| notes | text | yes | | |
| user_id | bigint | yes | | FK → users.id (null on delete) |
| exported | boolean | no | false | True once `policy:export-verifications` wrote it into data/ |
| created_at / updated_at | timestamp | yes | | |

Workflow: Admin → Review queue → open the official source → tick the rows (or "apply to all matching the filter") → save status and confidence once for the selection (verified requires the "source opened" attestation and a name on the published roster). `record_type` is any key of `App\Services\Review\ReviewableTypes`: `policy`, `jurisdiction`, `control`, `change`, `transition_measure`, `implementation_measure`. The queue skips a record the data check would refuse after export: an implementation measure with no official source (only a draft may omit it), or a transition measure verified without one. It names the skipped slugs. `PolicyImporter` re-applies every stored decision after each import, so deploys never undo a verification. `php artisan policy:export-verifications` writes the review fields into the file that holds each record (`YamlRecordPatch`: line-level, so the rest of the file is untouched; a change log entry is one item inside its yearly file); commit them through a pull request so `data/` remains the source of truth.

Routes (`routes/backend/web.php`, all behind `auth`, `isAdmin`, `admin.2fa`, `admin.audit`): `GET /backend/review?type=&q=&review=&published=` (`submissions.decide`); `POST /backend/review/verify/{type}/{slug}` and `POST /backend/review/verify-many/{type}` (`records.verify`; body `slugs[]`, or `scope=filtered` with the filter fields); `POST /backend/review/publish/{type}/{slug}` and `POST /backend/review/publish-many/{type}` (`records.publish`; `publish` 0/1); `POST /backend/review/submissions/{submission}/decide` and `POST /backend/review/submissions/decide-many` (`submissions.decide`; `ids[]`). Reviewed in [docs/reference/admin-review-2026-09-26.md](../reference/admin-review-2026-09-26.md).

## Schema: `contributor_submissions`

| Field | Type | Null | Default | Notes |
|-------|------|------|---------|-------|
| id | integer | no |  |  |
| type | varchar | no |  |  |
| subject_type | varchar | yes |  | policy, jurisdiction, obligation, change, incident (AIID id), risk (MIT `ev_id`, `#` written `--`), other |
| subject_slug | varchar | yes |  |  |
| summary | varchar | no |  |  |
| details | text | yes |  |  |
| proposed_source_url | varchar | yes |  |  |
| submitter_name | varchar | yes |  |  |
| submitter_email | varchar | yes |  |  |
| submitter_affiliation | varchar | yes |  |  |
| payload | text | yes |  | JSON captured at submission for corrections: `field`, `current_value`, `proposed_value`, `record_title`, `record_url`, `record_official_source_url`, `record_content_version`. Correctable fields per record type are listed in `ContributeController::CORRECTABLE_FIELDS`. |
| status | varchar | no | 'pending_review' | `App\\Enums\\SubmissionStatus`; defaults to pending_review |
| source_page | varchar | yes |  | Canonical URL of the record when the form was opened from a record page, else the referrer |
| created_at | datetime | yes |  |  |
| updated_at | datetime | yes |  |  |

## Schema: `reviewer_decisions`

| Field | Type | Null | Default | Notes |
|-------|------|------|---------|-------|
| id | integer | no |  |  |
| contributor_submission_id | integer | no |  | FK → contributor_submissions.id (cascade) |
| reviewer_user_id | integer | yes |  | FK → users.id (nullable, set null) |
| decision | varchar | no |  |  |
| notes | text | yes |  |  |
| decided_at | datetime | no |  |  |
| created_at | datetime | yes |  |  |
| updated_at | datetime | yes |  |  |

## Interlinks

- **Outbound:** `reviewer_decisions.reviewer_user_id` → `users` ([accounts.md](accounts.md)). All other foreign keys are internal to this module (listed in the field tables above); `taxonomy_assignments` is a polymorphic pivot to `policy_instruments` and `obligations`.
- **Inbound:** none from legacy modules. The legacy [policies](policies.md) module (`ai_policy_trackers`) is independent and still powers the `/map` dashboard; records are not yet cross-linked (see debt).
- **Files:** `data/jurisdictions/*.yaml`, `data/policies/**/*.yaml`, `data/implementation/*.yaml`, `data/changes/*.yaml`, `data/taxonomies/terms.yaml`, `data/schema/*.json`.

## Routes

Public (Blade, server-rendered): `home`, `policies.index|show|json`, `jurisdictions.index|show`, `obligations.index|show`, `compare.index|show`, `changes.index|year|feed`, `tools.applicability`, `open-data`, `open-data.download`, `methodology`, `about`, `contribute`, `contribute.store`, `guides.index|show`, `landing`, `sitemap.index|section`, `llms`, `llms.full`, `openapi`.
Search (public, throttled, never indexed): `search` (grouped results with a top match) and `search.suggest` (JSON typeahead, up to 8 `{title, type, url}`, `Cache-Control: public, max-age=600`). Laws are ordered by `App\Services\Search\PolicyRanker`: how well the query names the record (exact short title, title, slug, bracketed name or jurisdiction plus short title; then the query as whole words in a name; every word at a word start; matching published duties; text), then legal force (in force, adopted, guidance, proposed, then repealed, superseded and archived), binding force, number of matching duties, most recent date, and title.
API (read-only, throttled, cached): `api.v1.root|jurisdictions|jurisdiction|policies|policy|obligations|obligation|changes|taxonomies`.
Admin (auth + `isAdmin`): `backend.review.index|decide|publish`; `backend.checks.index|export` (independent checks, `records.verify`).

## Commands

`policy:validate` (schema and cross-reference checks), `policy:import` (idempotent upsert, runs on deploy), `policy:export` (dated JSON bundle).

## Scoping and access

Public routes expose only rows with `published_at` set. Contributor submissions, reviewer decisions and submitter contact details are readable only through the admin review area (`auth` + `isAdmin` middleware). There is no per-user data in this module, so no user-scoped queries are needed; publication is enforced by the `published()` query scope on every public query and by `404` on unpublished detail pages (covered by `tests/Feature/Site/PublicSiteTest.php`).

## Debt

See `docs/reference/technical-debt.md` #11 (seed records pending human verification), #12 (legacy `ai_policy_trackers` not cross-linked to `policy_instruments`), #13 (`composer.lock` incompatible with PHP 8.4).

## Bridged records

Records whose `change_summary` begins with "Imported from the source-backed legacy dataset" were generated from `database/data/ai_policies.json` (68 instruments researched from official sources). They carry only what the official page states (title, issuing body, dates, summary, sources, milestones), have empty obligation/deadline lists, and stay `pending_review` until a reviewer enriches and verifies them. Entries already curated by hand (EU AI Act, UK white paper, US EO 14179, NIST AI RMF, Nepal AI Policy, Singapore Model Framework, Australia Voluntary Standard, India Governance Guidelines, UAE Strategy 2031) were not duplicated.
