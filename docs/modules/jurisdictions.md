# Jurisdictions

Countries and regions that policies belong to. Currently national level only; supranational bodies (e.g. the EU) are stored as rows in the same table.

## Schema: `countries`

| Field | Type | Null | Default | Notes |
|-------|------|------|---------|-------|
| id | uuid | no | | Primary key |
| symbol | varchar(255) | no | | ISO-3166 alpha-2 code, uppercased on save |
| name | varchar(255) | no | | Display name |
| status | boolean | yes | true | Shown on the public map/filters when true |
| deleted_at | timestamp | yes | | Soft delete |
| created_at / updated_at | timestamp | yes | | |

## Interlinks

- **Outbound:** none.
- **Inbound:** `ai_policy_trackers.country_id` → `countries.id` (cascade delete). Documented in [policies.md](policies.md).

## Routes

Admin: `backend.country.index|store|view|search|updatedStatus`. Public: countries appear in dashboard filters and the map (`frontend.dashboard`).

## Debt

Flat list; no region grouping or sub-national level (see product strategy).
