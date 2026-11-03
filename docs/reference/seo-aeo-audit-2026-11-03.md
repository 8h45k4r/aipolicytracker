# SEO and AEO audit, 3 November 2026

## How it was run

This round checked the site against the rules in [claude-seo](https://github.com/AgriciDaniel/claude-seo)
(MIT), an open SEO audit pack for Claude Code. The pack's own scripts were not run. Its checklists were read
and turned into checks that need only the pages, `robots.txt`, the sitemaps, `llms.txt` and the response
headers, with no external API.

The crawl ran over HTTP against a locally served copy built from a fresh import:
- 249 jurisdictions, 244 instruments, 117 obligations and 154 changes;
- the incident and risk mirrors.

That gave 3,361 sitemap URLs and 14,628 distinct internal link targets. `php artisan seo:audit` ran too.

The live site was not reachable from where the audit ran, so these are not covered:
- Core Web Vitals;
- real index coverage;
- the HTTPS-only headers such as HSTS.

## Results

| Check (claude-seo rule) | Result |
|---|---|
| Titles, H1s, descriptions and identifiers (`seo:audit`) | 3,361 pages, no violations |
| Duplicate meta descriptions (META-DESC-UNIQUE) | **31 risk-entry pages** (fixed) |
| Duplicate titles (META-TITLE-UNIQUE) | 6, all false positives: SVG chart `<title>` elements, not page titles |
| Heading levels skipped (HEADING-SKIP) | **10 change-by-year pages**, H1 straight to H3 (fixed) |
| Images without width and height (IMG-DIMENSIONS) | **every policy page**: the status badge had a height only (fixed) |
| Open Graph and Twitter tags (OG-TAGS, TWITTER-TAGS) | none missing |
| Viewport, alt text, empty links and buttons, unlabelled form fields, duplicate ids | none |
| JSON-LD `@context`, invalid JSON, relative URLs, placeholders | none |
| Deprecated schema types (SD-DEPRECATED, HowTo and others) | none |
| Broken internal links | none |
| Pages with no link from another sitemap page (ORPHAN) | **4 hub pages and 17 update archives** (fixed); 1,302 incident and risk entries, reached through the crawlable pagination of their browse pages, which is by design |
| Internal links through a redirect | 120 `/id/<permanent id>` citation links, by design; 1 account link to `/profile` for signed-out readers |
| HTML over 2 MB (HTML-SIZE) | none; `/jurisdictions` is the largest at 775 KB uncompressed (map data and 249 rows) |
| `robots.txt` (ROBOTS-GROUP-SELECTION, AI-CRAWLER-SPLIT) | AI crawlers named in their own group, with every rule repeated |
| `llms.txt` (LLMS-TXT) | passes the Lighthouse rules: plain text, H1, Markdown links |
| Soft 404 (SOFT-404) | unknown pages and `/.well-known/ai-catalog.json` return 404 |
| Markdown for agents (MARKDOWN-NEGOTIATION) | every record links its `.md` file with `rel="alternate"`; there is no `Accept` negotiation, which is the pack's alternative and not required |

## Fixed in this round

- **Risk entries that shared a description.** Entries the MIT AI Risk Repository extracted from one passage of a paper carry the same description, so their search snippets were identical. The description now starts with the entry's own name ("Scams: …", "Disinformation: …").
- **Skipped heading level on `/changes/<year>`.** The list of changes now has its own H2 ("Every recorded change in 2025"), so the H3 of each change sits under it.
- **Badge image size.** The status badge on policy pages, its copyable snippet and the `/embed` example now give `width` as well as `height`, so the page does not move when the image loads.

- **Hub pages reachable only from `/explore`.** The quarterly report (`/state-of-ai-regulation`), `/ai-policy-examples`, `/ai-regulation-south-asia` and `/ai-governance-uae` were linked only from the `/explore/*` section pages, which are `noindex,follow`. Search engines come to treat links on pages that stay noindexed as weak. Now:
  - the home page links the quarterly report and the policy examples;
  - each country an editorial overview covers links to it (India and Nepal to South Asia, the UAE to its page);
  - the region in a country's header links to its regional hub.
- **Update archives without a link.** The updates page listed only the last 18 months, and no page linked a single day. It now also lists every older month the sitemap publishes, and each month page lists its days that have a page of their own.

## Left as they are, and why

- **`<changefreq>` and `<priority>` in the sitemaps.** Google ignores both; the pack rates them "info". Removing them changes nothing a search engine reads.
- **Static pages without `lastmod`** (111 static and 13 resource URLs). The tag is optional. A date is given only where the content has a real last-changed date; a made-up one would be worse than none.
- **Hub pages typed `Article` without `datePublished`** (25 pages such as `/for/providers` and the regional hubs). The property is recommended, not required, and the site does not record when a hub was first published. Inventing a date would break the rule that nothing is backdated.
- **`Google-Agent` not named in `robots.txt`.** It is a user-triggered agent that does not read `robots.txt`, so naming it would change nothing.
- **The duplicate-description quote "mdoels".** It is the MIT repository's text, quoted as published.

## Regression tests

`tests/Feature/ClaudeSeoAuditTest.php`:
- two risk entries with one description get different meta descriptions;
- the change-by-year page skips no heading level;
- the badge image and its snippet carry its width and height;
- the home page and the covered countries link the hub pages;
- every month and day archive in the updates sitemap is linked from the updates pages.
