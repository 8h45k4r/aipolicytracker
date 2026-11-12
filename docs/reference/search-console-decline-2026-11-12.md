# Search Console decline, 12 November 2026

## The figures (Search Console Insights, last 28 days, provided by the owner)

| Measure | Last 28 days | Change on the previous 28 days |
|---|---|---|
| Clicks | 656 | −26% |
| Impressions | 57.2K | −49% |
| Top pages | home, `/templates/fundamental-rights-impact-assessment`, country AI strategy pages | +25% to +500% |

Impressions fell nearly twice as fast as clicks. Click-through rate therefore rose, from about 0.79% to about 1.15%, if the previous period had about 886 clicks and 112K impressions. The pages people click are growing. The site lost appearances for searches that rarely led to a click.

## What changed in the comparison window

The "last 28 days" run from about 15 October to 11 November. The 28 days before them (about 17 September to 14 October) still included pages that the following changes took out of the index or moved. From the repository history:

| Date | Commit | Change | Pages affected (measured on the current data) |
|---|---|---|---|
| 26 Sep | ddb81742 | AI incident, risk and risk-domain pages moved from numeric keys to readable addresses; old keys redirect | 1,663 incident pages and 7 domain pages re-addressed |
| 26 Sep | 8f580c74 | Sensitive incidents (minors, named private people) served noindex and left out of sitemaps | part of the 389 noindex incidents |
| 26 Sep | 548fd7c3 | Country pages for 20 countries moved to `/ai-regulation-<country>`, old addresses redirect; 6 noindex until a second source | 20 pages re-addressed, 6 noindex |
| 5 Oct | 60601472 | Risk entries indexed only with a description of 25 words or more | 1,437 of 2,500 risk entries noindex |
| 7 Oct | 5c8b851d | 8 duplicate landings (e.g. `/eu-ai-act`) retired with 301 to the record page | 8 pages consolidated |
| 14 Oct | 10b3c17d | Filtered and single-country listings noindex; template releases are not news; incidents indexed only with 25+ words and a classification or law | listing variants (count not measured), incidents within the 389 |

Taken together, about 1,830 incident and risk pages left the index and about 1,700 more changed address within four weeks. The same happened to the duplicate landings and many listing variants. Pages like these collect impressions for broad queries (a risk name, an incident headline) and rarely get a click. Removing them cuts impressions far more than clicks, which is the pattern in the figures.

These changes were deliberate quality decisions (thin and sensitive pages kept out of search, one URL per subject). The decline is mostly their expected effect, not a fault.

## Live-site audit, 12 November 2026

Crawled from the live sitemaps (3,424 URLs), four requests at a time:

| Check | Result |
|---|---|
| Status | 3,424 of 3,424 answer 200 |
| Sitemap URLs carrying noindex | 0 |
| Canonical pointing elsewhere / missing | 0 / 0 |
| Duplicate titles | 0 |
| Response time | median 0.74 s, 95th percentile 0.80 s; slowest `/templates` 3.9 s |
| Old addresses | `/eu-ai-act` → `/policies/eu-ai-act`, `/ai-risk/incidents/1` → readable address, `/ai-risk/3` → `/ai-risk/misinformation`, `/ai-governance-uae` → `/jurisdictions/uae`: each a single 301 to a page answering 200 |
| Crawler access | Googlebot, Bingbot, GPTBot, OAI-SearchBot, ClaudeBot, PerplexityBot all get 200 |
| Answer-engine surfaces | `llms.txt` (91 KB), Markdown copies at `.md` and by `Accept: text/markdown`, all answering |

Two things in the crawl to note:
- **Redirects to noindex pages.** An old incident address can redirect to an incident that is now noindex, for example `/ai-risk/incidents/1`. That is consistent with the rules above, and it is one more reason those impressions went.
- **Slow `/templates` page.** It took 3.9 s against a median of 0.74 s. Worth profiling, though it is not a cause of the decline.

## What could be a real loss, and how to tell

The removals above explain the shape of the drop. They do not prove nothing else happened. Three things need Search Console data, which this audit did not have:
1. **The moved country pages** (`/ai-regulation-<country>`). A redirect usually passes rankings on, but not always at once.
   - Check: Performance → Pages, compare periods, filter `ai-regulation-`.
2. **The moved incident pages** that stayed indexed.
   - Check: Pages filtered `/ai-risk/incidents/`, old and new addresses together.
3. **Queries that lost impressions on pages that were not changed.** This would be a ranking loss, for example to AI Overviews or a competitor.
   - Check: Performance → Queries, compare periods, sort by impressions lost, and look for queries landing on policy, obligation or guide pages.

## What to send, and what runs on it

| Export from Search Console | Used by |
|---|---|
| Performance → Pages, "Compare: last 28 days vs previous period", CSV | Split of the impression loss by page type (`/ai-risk/*`, listings, retired landings, the rest) |
| Performance → Queries, same comparison | `php artisan seo:ctr-audit` (low-CTR queries on pages that rank) |
| Indexing → Pages, the "Why pages aren't indexed" table with examples | Confirms the noindex and redirect counts above; `php artisan seo:sensitive-indexed` checks sensitive pages still in the index |

## Recommendation

- **Do not undo the quality changes.** The pages that bring clicks are growing, and click-through rate is up.
- **Judge the next weeks on clicks and non-branded clicks, not impressions.** Impressions should settle at the new, smaller set of pages.
- **Act on the three checks above once the exports exist.** The country-page migration is the one that could hide a real loss.
