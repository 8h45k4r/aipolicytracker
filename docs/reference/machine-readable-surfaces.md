# Machine-readable surfaces

What the site serves to something that is not a browser, and the rule every one of them
follows.

## The rule

**Provenance travels inside the artefact.** A context file, an exported row and a tool result
each carry the official source, the review status, the confidence level and the date the facts
were last confirmed. These formats are the ones most likely to be quoted without the page
around them, so the caveats live in the file or they do not survive the trip.

A record that has never been confirmed says `never confirmed against the official source` in
those words rather than leaving the field out. An absent line reads as "fine" to both a person
and a model.

## What is served

| Surface | Shape | For |
|---------|-------|-----|
| `/open-data/health.json` | JSON | How far the corpus can be trusted right now: freshness, completeness and review standing in one document. |
| `/policies/{slug}.md` and the same for `jurisdictions`, `obligations`, `changes` | Markdown | One record, in one file, with a provenance block. Dropped into a context window without scraping. |
| `/open-data/{dataset}.csv` | CSV | A spreadsheet. Nested lists are JSON-encoded in the cell rather than flattened, so their links survive. |
| `/open-data/{dataset}.ndjson` | Newline-delimited JSON | A pipeline. One self-contained record per line, streamed in chunks so the corpus is never held in memory whole. |
| `/schema/{name}.schema.json` | JSON Schema | Validators, at the URL each schema's own `$id` declares. |
| `agent/server.mjs` | MCP over stdio | An assistant querying the surfaces directly. See `agent/README.md`. |

Datasets: `jurisdictions`, `policies`, `obligations`, `changes`, `deadlines`.

These sit alongside what already existed — the nested bundle at
`/open-data/aipolicytracker-latest.json`, the read-only JSON API at `/api/v1`, `/openapi.json`,
`llms.txt` and `llms-full.txt`. The bundle is one document you load whole; the exports here are
the other shape.

## The schema `$id` defect

Every file in `data/schema` declares `$id: https://aipolicytracker.org/schema/<name>.schema.json`
and nothing was ever served there, so a validator resolving the identifier got a 404. The
identifiers were claims the site did not honour. `/schema/{name}.schema.json` serves them, and
`MachineReadableSurfacesTest` asserts for **every** schema in the directory that it is reachable
and that the document served there declares that exact `$id` — so a new schema with a wrong or
copied identifier fails the build rather than the caller.

## Cross-origin reads

The context files, the exports, the schemas and the health document send
`Access-Control-Allow-Origin: *`. Without it the surfaces exist but a browser-based tool or an
assistant on another origin cannot use them, which is the same as not publishing them. Asserted
by test.

## Structure cannot be injected through a record

A stored paragraph is flattened to a single line before it is written into a Markdown document
or a CSV cell, so a heading or a bullet inside a record's text cannot become structure in the
output. Asserted by test with a deliberately hostile summary.

## Code map

| Concern | File |
|---------|------|
| Markdown rendering | `app/Services/MachineReadable/RecordContext.php` |
| Flat rows for CSV and NDJSON | `app/Services/MachineReadable/BulkExport.php` |
| Routing, streaming, schemas, health | `app/Http/Controllers/Site/AgentSurfaceController.php` |
| Agent server and its test | `agent/server.mjs`, `agent/server.test.mjs`, `agent/README.md` |
| Tests | `tests/Feature/MachineReadableSurfacesTest.php`, `npm test` |

## Adding a dataset

1. Add it to `BulkExport::DATASETS` and give it a `row()` branch that includes the provenance
   columns. The test asserts those columns are present for every record dataset.
2. Nothing else: the routes, the CSV header, the NDJSON stream and the open-data page all read
   the same declaration.
