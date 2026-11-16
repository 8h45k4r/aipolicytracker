# Search Console, three months to 14 November 2026

## The export

Performance on Search, web search, last three months (15 August to 14 November 2026), as downloaded by the owner: `Chart.csv`, `Queries.csv` (1,000 rows), `Pages.csv` (1,000 rows), `Countries.csv`, `Devices.csv`. The site first appeared in search in the week of 7 September, so the figures cover ten weeks.

It continues [search-console-decline-2026-11-12.md](search-console-decline-2026-11-12.md), which asked for these files.

## Totals

| Measure | Value |
|---|---|
| Clicks | 1,823 |
| Impressions | 212,897 |
| Click-through rate | 0.86% |
| Desktop share of impressions | 89% (189,716 of 212,897) |
| United States share of impressions | 46%, at 0.43% click-through |

By week, clicks have been level since early October while impressions fell and click-through rose:

| Week starting | Clicks | Impressions | CTR |
|---|---|---|---|
| 14 Sep | 376 | 57,126 | 0.66% |
| 28 Sep | 179 | 18,288 | 0.98% |
| 12 Oct | 153 | 14,999 | 1.02% |
| 26 Oct | 150 | 13,591 | 1.10% |
| 9 Nov (six days) | 162 | 13,718 | 1.18% |

## Finding 1: half the impressions with no click are not people

Search Console lists 1,000 queries, covering 42,443 of the impressions and 198 of the clicks. Sorted by what kind of query each is:

| Kind of query | Queries | Impressions | Share | Clicks |
|---|---|---|---|---|
| An identifier run into digits (`ai20223`, `tencent67978`) | 484 | 13,791 | 32% | 0 |
| Eight or more words, or carrying an ISO date | 50 | 7,374 | 17% | 5 |
| Everything else | 466 | 21,278 | 50% | 193 |

Examples of the second kind: "2026-02-26 2026-02-27 omb ai guidance nist ai risk management framework state ai regulation federal preemption" (1,951 impressions) and "what is the total outlay approved by the union cabinet for the indiaai mission in march 2024?" (157). A person does not type these. Assistants that search before answering do, and so do rank checkers. They are shown the result and do not click.

Three things follow.

- **The click-through rate of the site is understated.** On the queries people typed it is 0.91%; the identifier and program-written queries pull the listed figure down to 0.47%.
- **The desktop and United States skew has the same cause.** A program searching reports itself as a desktop browser, usually from a United States address.
- **Being retrieved for these queries is the answer-engine result.** The pages are found; whether they are quoted depends on what the assistant reads when it opens them. That is what the answer box, the Markdown copy of each record and `llms.txt` are for.

`seo:ctr-audit` now reports this share and lists such queries last, so a title is not rewritten for a query no person typed.

## Finding 2: what people search for, and click

| Query | Clicks | Impressions | CTR | Position |
|---|---|---|---|---|
| ai policy tracker | 54 | 238 | 22.7% | 1.1 |
| fundamental rights impact assessment template | 9 | 35 | 25.7% | 4.7 |
| global ai law and policy tracker | 8 | 45 | 17.8% | 3.7 |
| fria template | 6 | 76 | 7.9% | 6.5 |
| ghana national ai strategy 2026 pdf | 4 | 34 | 11.8% | 3.9 |
| national ai policy 2025 | 3 | 57 | 5.3% | 7.6 |
| national ai strategy sri lanka | 3 | 56 | 5.4% | 8.6 |

The pages that win clicks are the same three kinds: a country's national AI strategy or policy, a template, and the site by name. The best pages by click-through are `/policies/algeria-national-ai-strategy-2025` (14.8%), `/guides/tools/ai-impact-assessment-template` (16.4%), `/policies/tunisia-national-ai-strategy-2025` (10.7%), `/policies/sri-lanka-national-ai-strategy-2024` (9.9%) and `/templates/fundamental-rights-impact-assessment` (9.2%).

The head terms are far from page one: "eu ai act" at position 37, "ai regulation" at 41, "ai policy" at 28. No change to a page moves those; they are won by other sites linking here.

## Finding 3: addresses in the export that no longer serve a page

482 of the 1,000 pages in the export are incident and risk pages, or addresses the site has since changed. Each was requested on the live site:

| Addresses | Impressions | Clicks | What the live site does |
|---|---|---|---|
| 255 old incident and risk addresses | 19,165 | 213 | One redirect to a page that is indexed |
| 115 old incident and risk addresses | 8,239 | 99 | One redirect to a page that is now `noindex` |
| 94 current incident and risk addresses | 4,488 | 55 | 200; 25 of them now `noindex` |
| 12 template releases under `/changes/template-…` | 3,315 | 1 | 200, `noindex` |
| 3 day archives under `/updates/` | 315 | 0 | 404: the day's only entries were template releases |
| 3 others | 5 | 3 | 404: a renamed record, a retired tool, a mistyped address |

Nothing here is broken. The redirects answer in one step, and the `noindex` and 404 answers are the rules the site set on 26 September and 14 October doing what they were written to do. Those rules are why impressions fell between September and October.

The live site also answers 404 for `/updates/2026-11`: no change in the law has been recorded with a November date.

## Not changed, and why

- **Titles and descriptions.** Headings and descriptions were rewritten on 13 November, and twenty short titles the same day; the deploy carrying the short titles failed on 14 November (a framework security advisory turned the Security workflow red), so those reach the live site with the next deploy. This export ends on 14 November and cannot show the effect of either. Changing them again now would leave nothing to measure.
- **Template releases under `/changes/`.** The twelve addresses held position 6 on average and drew one click. They are `noindex` by the decision of 14 October that a template release is not news. Redirecting each to its template page would pass that position to the page that converts; it would also end the release entry as a page. That is the owner's call, so it is recorded here and not made.
- **Empty day archives.** `UpdatesHubTest` states that an archive exists only when it has entries. A 404 is the answer that rule gives.
- **Filtered listings** such as `/policies?jurisdiction=bangladesh` (54 addresses, 3,542 impressions). They have been `noindex` since 14 October and are leaving the index.

## What to do next

1. **Record changes in the law for November.** An updates section with nothing dated this month cannot rank for "ai policy updates" (position 11, 63 impressions). Issue #139 lists two that are waiting.
2. **Merge the weekly incident refresh when it opens its pull request.** It had failed on every scheduled run since 22 September (the first and the latest logs show the same error), so the report lists still come from the backup of 7 September: 6,434 reports for 1,647 incidents, against 6,763 for 1,785 in the backup of 9 November.
3. **Compare the same export four weeks from now** using "Compare: last 28 days with the previous period". That is the measure of the 13 November titles.
4. **When the comparison is in, consider what the description says.** The descriptions built on 13 November give the name, the status and "Official sources linked." On 34 policy pages that rank on page one (18,831 impressions) that comes to under 90 characters, for example "IndiaAI Mission: adopted on 7 Mar 2024. Official sources linked." The record's own summary says what the programme is and what it funds, which is what the searches that lead to a click ask about ("ghana national ai strategy 2026 pdf", "national ai strategy sri lanka"). A clause from that summary, where it fits in 155 characters, is the test to run next. It is not made here, for the reason given above.
5. **Send queries by page for the ten pages below.** Search Console exports queries and pages separately, so this file cannot say what each page is shown for. In Performance, filter to one page, open Queries and export. With that, `seo:ctr-audit` names the query each title should lead with.

| Page | Impressions | Clicks | Position |
|---|---|---|---|
| `/policies/bangladesh-national-artificial-intelligence-policy-2026-2030` | 6,250 | 26 | 6.6 |
| `/jurisdictions/us` | 4,367 | 4 | 16.3 |
| `/jurisdictions/south-africa` | 4,340 | 2 | 8.5 |
| `/policies/eu-ai-act` | 3,759 | 0 | 28.0 |
| `/jurisdictions/new-zealand` | 2,895 | 7 | 7.3 |
| `/policies/uk-ico-ai-data-protection-guidance` | 2,782 | 1 | 8.2 |
| `/policies/hong-kong-pcpd-model-personal-data-protection-framework-for-ai` | 2,667 | 4 | 7.2 |
| `/jurisdictions/us-texas` | 2,355 | 1 | 9.2 |
| `/policies/kenya-kenya-artificial-intelligence-strategy-2025-2030` | 2,222 | 1 | 6.0 |
| `/ai-regulation-kenya` | 1,947 | 0 | 4.7 |
