# SEO and AEO audit, 19 October 2026

## How it was run

Every URL in the sitemaps was checked twice:

1. `php artisan seo:audit`, the project's own crawl, which checks titles, H1s, descriptions and leaked identifiers.
2. A crawl over HTTP of a locally served copy, built from a fresh import (249 jurisdictions, 244 instruments, 117 obligations, 154 changes). This one checks what the first does not:
   - status codes;
   - noindex pages listed in a sitemap;
   - canonicals;
   - JSON-LD that does not parse;
   - FAQ markup whose answers are not on the page;
   - HowTo markup;
   - social images and image alt text;
   - thin pages;
   - every internal link target (6,151).

The live site was not reachable from where the audit ran, so Core Web Vitals, real index coverage and cache headers are not covered here; they need Search Console and PageSpeed Insights.

## Results

| Check | Pages or links | Result |
|---|---|---|
| Sitemap URLs answering 200 | 1,024 | all |
| Title, H1, description, identifier rules (`seo:audit`) | 1,024 | no violations |
| Noindex page listed in a sitemap | 1,024 | none |
| Canonical missing, duplicated or pointing elsewhere | 1,024 | none |
| JSON-LD that does not parse | 1,024 | none |
| HowTo markup | 1,024 | none |
| Page without a social image | 1,024 | none |
| Thin page (under 150 words) | 1,024 | none |
| FAQ markup with answers not on the page | 740 FAQ pages | **1 page: `/ai-risk`** (fixed) |
| Broken internal links | 6,151 targets | **107** (fixed) |
| Internal links through a redirect | 6,151 targets | 543. All but one are `/id/<permanent id>` citation links, which redirect by design; the other was the old template address (fixed) |

"Duplicate title" on 24 AI-risk subdomain pages was a false positive: their charts carry SVG `<title>` elements, and the page titles are distinct.

## Fixed in this round

- **`/ai-risk` FAQ hidden from readers.** The two answers existed only in the markup, and the second ("which domain has the most incidents") was hard-coded. Both are now computed from the incident snapshot and shown on the page with the site's FAQ component.
- **102 links to empty updates pages.** Country and state pages linked "All updates for X" twice: in the changes section and in the key facts. `/updates/<jurisdiction>` is a 404 until a change is recorded, so those links now appear only when one exists; otherwise the key fact links the RSS feed.
- **4 links to framework pages that do not exist.** ISO/IEC 23894, ISO/IEC 42005, the OECD AI Principles and the OWASP LLM Top 10 have no page until a duty maps to them. Controls and the framework comparison now link only frameworks that have a page (`FrameworkCrosswalk::hasPage`).
- **A dead link on `/open-data`** to a repository file. It now points to the file on GitHub.
- **The old template address on `/ai-risk`.** It now links the template page directly.
- **`llms.txt`:**
  - the regional hubs ran into the next list item, because a Blade loop at the end of the line swallowed the newline;
  - "Compare" was listed twice;
  - nothing told an assistant that commercial use is allowed or where licences are handled. A pricing and licence line now does.
- **`ai_specific` in the API, the CSV and NDJSON exports, and the OpenAPI schema**, so data users get the same distinction the pages make.

## Regression tests

`tests/Feature/SeoAeoAuditTest.php`:

- FAQ answers are visible on the main page types;
- the AI-risk answer comes from the data;
- `llms.txt` has one item per line and the licence line;
- no link to an empty updates page;
- framework links resolve;
- the API and the export carry `ai_specific`.

## Still to do (outside the code)

- Search Console:
  - submit the sitemap index;
  - watch "Crawled, not indexed" for the 57 pending-review sub-national records and the new `/developers`, `/mcp` and `/pricing` pages;
  - export Pages and Queries monthly for `seo:ctr-audit`.
- PageSpeed Insights on `/jurisdictions` (the map script), a policy page and the home page.
- Set the Cloudflare purge secrets so the edge stops serving stale `robots.txt`, sitemaps and `llms.txt` after a deploy.
- Links and listings: MCP directories, `awesome-artificial-intelligence-regulation`, public-apis, and a Zenodo DOI for the dataset.
