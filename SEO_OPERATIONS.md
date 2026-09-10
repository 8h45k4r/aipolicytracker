# SEO Operations

Operating manual for keeping aipolicytracker.org indexable, fast and honest. Pair with `CONTENT_OPERATIONS.md` (what we publish) and `DATA_UPDATE_OPERATIONS.md` (how records are verified).

## 1. What is implemented

| Area | Implementation |
|------|----------------|
| Rendering | All public pages are server-rendered Blade templates (`resources/views/site`). Core content needs no JavaScript. `resources/js/public.js` only adds conveniences (filter sheet, copy link, consent, event tracking). |
| Metadata | `App\Support\Seo` value object per page: unique title (trimmed to 70 chars), description (160), canonical, robots, Open Graph, Twitter card, `article:modified_time`, RSS `alternate`. Rendered in `resources/views/site/layouts/app.blade.php`. |
| Canonicals and indexation | Listings: bare listing, single `jurisdiction`, single `status` or single `category` filter are indexable with a clean canonical; any search term, multi-filter, sort or date range is `noindex,follow` (`PolicyCatalog::isIndexableFilterSet`). Paginated pages keep the indexable canonical with `?page=`. Ad-hoc comparisons (`/compare?j=`) and applicability results are `noindex,follow`; curated comparisons (`/compare/{slug}`) are indexable. |
| Content thresholds | `PolicyInstrument::isIndexable()` (summary + scope + official source + published) and `Jurisdiction::isIndexable()` (overview + status + at least one published sourced instrument) gate both the `robots` meta and sitemap inclusion. Unpublished records return 404. |
| Sitemaps | `/sitemap.xml` index → `/sitemap-static.xml`, `-jurisdictions`, `-policies`, `-obligations`, `-changes` (yearly archive pages), `-resources` (landing pages, guides, curated comparisons). `lastmod` from record `updated_at`. |
| robots.txt | `public/robots.txt`: allows all crawlers (including AI crawlers) on public content and assets; disallows admin, auth, legacy map, JSON filter endpoints and API; declares the sitemap index. |
| Structured data | JSON-LD only for visible content: `Organization`, `WebSite`+`SearchAction` (home), `WebPage` (policy, jurisdiction, obligation, comparison), `CollectionPage`+`ItemList` (directories), `BreadcrumbList` (all), `Dataset` (open data), `FAQPage` (only where a visible FAQ exists), `Article` (editorial landings and guides), `WebApplication` (applicability tool). |
| Feeds and machine-readable | `/changes/feed` (RSS 2.0), `/llms.txt`, `/llms-full.txt`, `/openapi.json`, `/policies/{slug}.json`, `/open-data/aipolicytracker-latest.json`, `/api/v1/*`. |
| Redirects and errors | `/about-ai-policy` → `/about` (301), `/dashboard` → `/map` (301). Branded 404 with search. Legacy Inertia shell pages are `noindex`. |
| Verification placeholders | `GOOGLE_SITE_VERIFICATION`, `BING_SITE_VERIFICATION` env vars render the meta tags. |
| Performance | No web fonts, no third-party CSS/JS on public pages, one CSS and one small JS bundle, `Cache-Control` on API/feeds/sitemaps, no layout-shifting async content (all data server-rendered). |

## 2. Google Search Console and Bing setup

1. Add the property `https://aipolicytracker.org/` (URL-prefix) in Search Console. Choose the HTML-tag method, copy the token into `GOOGLE_SITE_VERIFICATION`, deploy, verify.
2. Submit `https://aipolicytracker.org/sitemap.xml` under *Sitemaps*. Child sitemaps are discovered from the index.
3. Repeat in Bing Webmaster Tools with `BING_SITE_VERIFICATION` (or import from Search Console).
4. Confirm `robots.txt` is reachable and not cached stale by Cloudflare (purge after deploy).
5. Request indexing for the home page, `/policies`, `/jurisdictions`, `/policies/eu-ai-act` and each landing page once.

## 3. Weekly checks (30 minutes)

- **Coverage / Pages report:** new "Excluded" reasons. Expected exclusions: `noindex` on search/filter URLs, "Alternate page with proper canonical" for paginated filters. Investigate anything else, especially "Crawled, currently not indexed" on policy or jurisdiction pages (usually thin content: add sourced detail or unpublish).
- **Broken links:** run `php artisan route:list` diff after deploys and a crawl (for example `wget --spider -r -nd -nv https://aipolicytracker.org/ 2>&1 | grep -B1 "404"`). Internal links must never 404; official source links may rot: see `DATA_UPDATE_OPERATIONS.md`.
- **Sitemap health:** Search Console shows "Success" for all six child sitemaps; `lastmod` dates move when data changes.
- **Core Web Vitals:** LCP, INP, CLS in the CWV report. Public pages should stay "Good"; if CLS regresses, look for images without dimensions or newly added async content.
- **Rich results:** Enhancements report for Breadcrumb, FAQ, Dataset, Sitelinks searchbox. Validate any changed template with the Rich Results Test before merge.

## 4. Monthly review (2 hours)

1. **Queries and CTR:** export Performance → Pages with impressions ≥ 100 and CTR below 2 %. For each page, rewrite the title/description to match the query intent shown in the Queries tab; keep titles ≤ 60 chars, descriptions ≤ 155, dates only where meaningful. Change only `Seo::make()` inputs in the controller or the `config/content.php` entry; never stuff keywords.
2. **Query mining:** queries with impressions but no matching page become candidates for a guide, landing page or jurisdiction record. Log them in a GitHub issue labelled `content-request` with the query and impressions.
3. **Indexation audit:** compare sitemap URL counts with indexed counts. A large gap means thin pages or blocked resources.
4. **Canonical drift:** spot-check ten URLs with the URL Inspection tool; "Google-selected canonical" must equal ours.
5. **Metadata uniqueness:** `composer test` runs `PublicSiteTest::test_titles_are_unique_across_key_pages`; extend the list when adding page types.

## 5. Responsible metadata changes

- Titles state what the page is, in the words users search for, and never claim more than the record supports ("requirements, deadlines and compliance actions", not "complete guide").
- Descriptions summarise the answer block; they must be true even if the reader never clicks.
- Do not add dates to titles unless the page is a dated digest or year archive.
- One title change per page per month, then measure; do not churn.

## 6. Fixing sitemap, canonical, schema and CWV issues

| Symptom | Where to look |
|---------|---------------|
| URL missing from sitemap | `SitemapController` filters with `isIndexable()`; check the record has summary, scope, official source and `published_at`. |
| Wrong canonical | `PolicyCatalog::canonicalFor()` for listings; `Seo::make()` third argument elsewhere. |
| Schema warning | The JSON-LD arrays in the controller; only describe what the page shows. Remove `FAQPage` if the FAQ section is removed. |
| Slow page | Query counts (`DB::enableQueryLog()` in a test) and eager loads in the controller; API and export responses are cached for 10–15 minutes. |
| Duplicate content | Two URLs render the same record: add a redirect in `routes/public.php` or set `noindex` and a canonical. |

## 7. Analytics events available

`data-track` attributes fire GA4 events (only after consent when `ANALYTICS_REQUIRE_CONSENT=true`): `home_search`, `header_search_click`, `quick_filter`, `filter_apply`, `source_click`, `github_click`, `certifyi_click`, `correction_click`, `submission_submit`, `applicability_submit`, `compare_submit`, `dataset_download`, `api_docs_view`, `rss_click`, `newsletter_click`. Page views for jurisdiction and policy pages come from standard page-view tracking by path. Cloudflare Web Analytics needs only `CLOUDFLARE_ANALYTICS_TOKEN`.

## 8. Release checklist (before each deploy touching public pages)

- [ ] `composer test`, `npm run lint`, `npm run build` green
- [ ] `php artisan policy:validate` green
- [ ] New page types added to `PublicSiteTest` route list and title-uniqueness list
- [ ] New indexable routes added to `SitemapController`
- [ ] Any removed URL has a 301 in `routes/public.php`
- [ ] Rich Results Test on one page per changed template
- [ ] Purge Cloudflare cache for `/sitemap*.xml`, `/robots.txt`, `/llms*.txt`
