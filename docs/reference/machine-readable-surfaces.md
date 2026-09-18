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

## Structured data on the pages themselves

Applied 2026-09-18. The exports above serve a client that already knows this site exists. The
JSON-LD on each page is what tells one that it does, in a vocabulary it already reads.

Every page now publishes the same three things, assembled by `Seo::jsonLdBlocks()` and rendered
by the site layout:

| Node | What it says |
|------|--------------|
| `Organization` | Who publishes this, with `areaServed` and `knowsAbout` read from the published corpus rather than asserted |
| `WebSite` | The site the page belongs to, and how to search it |
| One page node | What this page is: `WebPage`, `CollectionPage`, `AboutPage`, `ContactPage` or `Article`, with `@id`, `inLanguage`, `isPartOf`, `publisher` and a link to its own breadcrumb trail |

**Why this changed.** `Organization` and `WebSite` used to be emitted on the homepage alone,
while every inner page carried `isPartOf: #website` and `publisher: #organization` references
that resolved to nothing when that page was fetched on its own. A record page fetched on its own
is exactly how an answer engine reads this site. Thirteen page types carried nothing but a
breadcrumb trail.

**Controllers set a type, not a node.** `withPageType()` and `withPageProperties()` merge into
the generated page node, so a page cannot end up with a thin hand-made copy missing the
identifier, the language or the publisher. `StructuredDataGraphTest` asserts every page has
exactly one page node and that **no page references an identifier it does not define**.

**Entities beside the page.** A record page also publishes a `Dataset` naming the files that
actually serve it (`.json`, `.md`) under the corpus licence, so a client is told the page is a
structured record at a stable URL rather than prose about one. A binding instrument is described
as `Legislation` with its legal force. Pages that enumerate records carry an `ItemList` whose
stated count matches what it lists, because a list that claims 186 and shows 20 is describing
something it is not. The change log and the deadline calendar name their feed and calendar
distributions, so a client can follow them instead of re-reading the page.

**What is not claimed.** `areaServed` names only regions with published jurisdictions, and
`knowsAbout` comes from the taxonomy the records are filed under. A global remit the corpus does
not support would be the same overclaim this project exists to avoid, in machine-readable form;
a test compares the claim against the database. `FAQPage` appears only where a record carries
real questions. Nothing is invented to fill a node.

## Social preview cards

Applied 2026-09-18. `/og/{kind}/{slug}.png` draws the image a platform shows when a page is
shared. Kinds: `policy`, `jurisdiction`, `obligation`, `site`.

**Why this exists.** Every page shared one static PNG, and that file had counts painted into it:
it read *117 jurisdictions · 182 instruments* against a corpus of 212 and 186. A number inside an
image cannot be kept current, and nothing in the system could tell that it had drifted. On a site
whose argument is that its figures can be trusted, that is the worst place to carry a stale one.
The site card now reads its figures from the database at render time, so the same drift cannot
recur.

| Card | Shows |
|------|-------|
| `policy` | Title, jurisdiction, instrument type, binding or not, status, and whether a person has verified it |
| `jurisdiction` | The jurisdiction, how many instruments are recorded for it, or that none is |
| `obligation` | Whether it is a legal requirement or guidance, the instrument it comes from, the jurisdiction |
| `site` | The tagline and live corpus counts |

**The version token matters.** Each card URL carries `?v=` derived from the record's own update
time. Platforms cache a preview against its URL and re-fetch only when it changes, so a card at a
fixed URL keeps showing an old title forever. Nothing reads the parameter when rendering; its
only job is to move.

**Fonts are the one external dependency.** The brand faces are served from a font CDN rather than
vendored, so cards are drawn with whichever system face resolves first, DejaVu by default. If none
resolves, nothing is drawn and the static image is served, so a host without fonts behaves exactly
as the site did before rather than serving a broken preview. `php artisan social:doctor` reports
which face resolved on a given host and writes a sample to look at; `--out=` copies it somewhere
readable. `SOCIAL_CARDS_ENABLED=false` turns the whole thing off.

**Caching.** Rendered cards are keyed by their content and kept on disk, so a retitled record
produces a new file rather than needing a cache cleared. They are disposable: deleting them costs
one redraw. A response is cached for a day, not marked immutable, because the bytes at a path do
change when a record is retitled — it is the version token in the markup that makes a platform
fetch the new one.

**A caveat worth stating.** Platforms cache previews hard and on their own schedule. After a
deploy the old card can persist for days on links already shared; each platform's own debugger
forces a re-scrape.

## Sitemaps

Eight sections under `/sitemap.xml`: `static`, `jurisdictions`, `policies`, `obligations`,
`changes`, `resources`, `incidents`, `risks`. Every entry carries a `lastmod` taken from a real
record timestamp.

The last two were added on 2026-09-18 and are most of the file by volume. Roughly 1,700 incident
pages and 2,500 risk entries were reachable, indexable and listed in no sitemap at all, which is
most of what Search Console reported as discovered and not indexed. The index went from 593 URLs
to 4,756.

Both sections read lazily in chunks. They are the largest by an order of magnitude, and a sitemap
that has to hold the whole table in memory to be served is one that stops being served as the
corpus grows.

Adding a section means adding it to `SitemapController::index()`, to the `match` in `section()`,
and to the route constraint in `routes/public.php`. `StructuredDataGraphTest` asserts each new
section lists every row, that each entry has a `lastmod`, and that the first URL it advertises is
actually served.

## Code map

| Concern | File |
|---------|------|
| Markdown rendering | `app/Services/MachineReadable/RecordContext.php` |
| Flat rows for CSV and NDJSON | `app/Services/MachineReadable/BulkExport.php` |
| Routing, streaming, schemas, health | `app/Http/Controllers/Site/AgentSurfaceController.php` |
| Agent server and its test | `agent/server.mjs`, `agent/server.test.mjs`, `agent/README.md` |
| Social preview cards | `app/Services/Social/SocialCard.php`, `app/Http/Controllers/Site/SocialCardController.php`, `config/social.php`, `php artisan social:doctor` |
| Page structured data | `app/Support/Seo.php` (`jsonLdBlocks`, `graph`, `dataset`, `itemList`, `legislation`, `howTo`) |
| Tests | `tests/Feature/MachineReadableSurfacesTest.php`, `tests/Feature/StructuredDataGraphTest.php`, `tests/Feature/StructuredDataAndInterlinksTest.php`, `npm test` |

## Adding a dataset

1. Add it to `BulkExport::DATASETS` and give it a `row()` branch that includes the provenance
   columns. The test asserts those columns are present for every record dataset.
2. Nothing else: the routes, the CSV header, the NDJSON stream and the open-data page all read
   the same declaration.
