# SEO and AEO audit, 10 November 2026

## How it was run

Earlier rounds crawled a local copy. This one read the live site, which is the only place the edge, the
response headers and the loading speed can be seen.

- Lighthouse 12.6 (mobile profile, headless Chromium) on the home page, `/policies/eu-ai-act` and `/jurisdictions`.
- Requests with the user agents of 17 search and answer-engine crawlers.
- Requests for the duplicate forms of a URL: `http://`, `www.`, a trailing slash, `/index.php/…`, upper case, a tracking parameter.
- `Accept: text/markdown` on a record page.
- The address shown in each page's footer, as a browser receives it.

## Results

| Check | Result |
|---|---|
| Lighthouse SEO | 100 on all three pages |
| Lighthouse accessibility | 100 on all three pages |
| Lighthouse performance | 96 (home), 100 (policy page), 100 (jurisdictions) |
| Largest contentful paint | 1.2 s to 1.9 s; layout shift 0 |
| Lighthouse best practices | 93: one script refused by the content security policy on every page (below) |
| Crawler access | 200 for all 17 user agents, the same bytes as a browser |
| `http://` and `www.` | 301 to `https://aipolicytracker.org` |
| Tracking parameter | canonical link names the clean URL |
| **Trailing slash, `/index.php/…`** | **200 with a canonical link, not a redirect** (fixed) |
| **Markdown by `Accept` header** | **HTML returned** (fixed) |
| **Footer address** | **shown as "[email protected]" on every page** (fixed for the footer) |
| **Feed discovery** | **only the change log and update pages named a feed** (fixed) |
| Static images under `/brand` | cached for four hours; 9 KiB on a repeat visit. Left as is |

## Fixed in this round

- **Markdown from the record's own URL.** A record page already linked its `.md` file, but a client that asked
  the page URL for `text/markdown` got HTML. Coding agents and several answer engines ask that way. The five
  record kinds (`policies`, `jurisdictions`, `obligations`, `controls`, `changes`) now return the Markdown file
  when the client ranks `text/markdown` ahead of `text/html`. A browser never lists it, and `*/*` does not
  count, so pages are unchanged for readers. The response carries `Vary: Accept`, `Content-Location` and
  `Cache-Control: private`, so no shared cache can hand the Markdown copy to a browser. The page also names the
  file in a `Link` header with `rel="alternate"`.
- **One URL per page.** `/policies/` and `/index.php/policies` returned the page with a canonical link. A
  canonical link is a hint; a permanent redirect is not. Both forms now answer 301 to the clean URL, keeping
  the query string. Only an HTML page is redirected. The API root is published and probed as `/api/v1/`
  (the deploy script rolls a release back unless it answers 200), and a client of a feed or a file may not
  follow a redirect, so every other response is served as before.
- **The footer address.** The edge's email obfuscation replaces every address in the HTML with a placeholder
  that a script decodes. The content security policy allows scripts by nonce only, so the script was refused:
  readers saw "[email protected]" in the footer of every page and the browser logged an error. The footer and
  the funding page now mark the address with the comment tags the edge honours (`<x-site.email>`), so it is
  served as written.
- **A feed on every page.** Every page now names the change log feed in `<link rel="alternate">`, unless it
  has a feed of its own.

## Left for the operator

- **Turn off Email Address Obfuscation at the edge**, or keep it and accept the placeholder. The about,
  privacy and terms pages list the addresses the deployment is configured with (`CONTACT_EMAILS`,
  `LEGAL_PRIVACY_EMAIL`, or the contact address in the admin settings). They are not marked in this round, so
  they still show the placeholder and still log the refused script. Check those settings name the mailbox you
  want published before either marking them or switching the feature off.
- **Core Web Vitals from real visits.** Lighthouse is a lab measurement. Search Console's field data is the
  figure that counts.
- **Index coverage and links.** On-page checks pass. What the site ranks for now depends on which pages are
  indexed and who links to them, and neither can be read from the pages.

## Left as they are, and why

- **Upper-case paths** (`/Policies`) return 404. Nothing on the site or in the sitemaps links one.
- **Four-hour cache on `/brand` images.** The files are not fingerprinted, so a long lifetime would keep an old
  logo in browsers after a change. The saving is 9 KiB on a repeat visit.
- **Unused CSS** (10 KiB of 21 KiB on the home page). One stylesheet serves every page; splitting it would
  add a request to most of them.

## Regression tests

`tests/Feature/AgentReadabilityTest.php`:
- a record URL returns the Markdown file to a client that asks for it, for all five kinds, and never from a shared cache;
- a browser, a wildcard and a client that prefers HTML still get the page;
- a path that only looks like a record (`/changes/feed`) is answered by its own route;
- the trailing-slash and `/index.php` forms redirect once to the clean URL, with the script name set as a web server sets it;
- `/api/v1/`, a feed and a file with a trailing slash are served, not redirected, and a request line naming another host is not turned into a redirect;
- the footer address is marked, and every page names a feed.
