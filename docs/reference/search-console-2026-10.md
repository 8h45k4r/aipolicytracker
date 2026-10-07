# Search Console, last three months (to 2026-10-07)

Source: Search Console exports (Web, last 3 months: Pages, Queries, Devices). Totals: **900 clicks, 122,556
impressions, CTR 0.73%, average position about 8**. The page export covers all clicks; the query export covers
only 119 of them, because Google withholds rare queries. Read alongside `research-2026-10-01.md` and
`seo-aeo-review-2026-10-05.md`. The live site could not be reached from the analysis environment, so the URL
behaviour below was checked against this checkout's routes.

## By page type

| Type | Pages | Clicks | Impressions | CTR | Avg position |
|---|---:|---:|---:|---:|---:|
| Policy records | 164 | 286 | 41,119 | 0.70% | 7.8 |
| Jurisdiction pages | 105 | 243 | 35,701 | 0.68% | 8.6 |
| AI incidents | 440 | 210 | 23,847 | 0.88% | 8.1 |
| Obligations | 39 | 5 | 3,637 | 0.14% | 10.3 |
| Home | 1 | 58 | 3,006 | 1.93% | 13.5 |
| MIT risk entries | 75 | 11 | 2,514 | 0.44% | 9.9 |
| Change pages | 17 | 1 | 1,531 | 0.07% | 6.4 |
| Templates (old `/guides/tools/` URLs) | 9 | 27 | 597 | **4.52%** | 17.1 |
| Templates (`/templates`) | 7 | 5 | 282 | 1.77% | 9.8 |

| Device | Clicks | Impressions | CTR | Position |
|---|---:|---:|---:|---:|
| Desktop | 717 | 114,846 | 0.62% | 8.6 |
| Mobile | 176 | 12,295 | 1.43% | 10.0 |
| Tablet | 6 | 460 | 1.30% | 9.0 |

## Findings, ranked by what acting on them is worth

### 1. Duplicate pages split the queries that matter most, and earn almost nothing

| Subject | URL | Impressions | Clicks | Position |
|---|---|---:|---:|---:|
| EU AI Act | `/eu-ai-act` (landing) | 1,247 | 0 | 22.3 |
| | `/jurisdictions/eu` | 513 | 0 | 11.8 |
| | `/policies/eu-ai-act` (the record) | 164 | 0 | **7.4** |
| United States | `/jurisdictions/us` | **3,172** | 1 | 12.9 |
| | `/ai-regulation-usa` (landing) | 57 | 0 | 13.6 |
| India | `/jurisdictions/india` | 493 | 0 | 9.6 |
| | `/ai-regulation-india` (landing) | not in the export | | |
| Nepal | `/ai-policy-nepal` (landing) | 200 | 0 | 6.6 |
| | `/jurisdictions/nepal` | 198 | 1 | 6.4 |
| | `/ai-regulation-nepal` (hub) | 5 | 2 | 4.0 |
| Singapore | `/ai-governance-singapore` (landing) | 177 | 0 | 18.3 |
| Australia | `/jurisdictions/australia` | 524 | 5 | 9.1 |
| | `/ai-regulation-australia` (landing) | 83 | 0 | 17.9 |

The site's most important subject, the EU AI Act, earned **zero clicks from 1,924 impressions spread over three
URLs**. The record page ranks best (7.4) but is shown least. Google has already chosen between each pair; the site
should agree with it.

**Recommendation.** For each subject, keep one page and 301 the others to it, folding any landing copy worth keeping
into the survivor's introduction:

| Subject | Keep | 301 to it |
|---|---|---|
| EU AI Act | `/policies/eu-ai-act` | `/eu-ai-act` |
| United States | `/jurisdictions/us` | `/ai-regulation-usa` |
| India, UK, Australia | `/jurisdictions/<slug>` | `/ai-regulation-<country>` landings |
| Singapore | `/jurisdictions/singapore` | `/ai-governance-singapore` |
| Nepal | `/ai-regulation-nepal` (the hub, already canonical for `/jurisdictions/nepal`) | `/ai-policy-nepal` |

This was left open as an editorial decision in `research-2026-10-01.md` (item 1) and is U15 in the strategy note.
The data now decides it.

### 2. A third of query impressions are identifier strings that nobody clicks

426 of the 1,000 exported queries look like `ai41467`, `ai20224` and `tencent67978`. Together they have **7,551
impressions (29% of query-level impressions) and no clicks**, at positions 2–9. None of these strings is in
`data/`, and the current site emits no such identifiers (roadmap P1). They behave like rank-tracker or scraper
queries. No action is needed, but exclude them when measuring CTR, or every title change will look like it did
nothing.

### 3. News-shaped queries put record pages in front of readers who want a news story

The four largest queries are news events:

| Query | Impressions | Position |
|---|---:|---:|
| A hacker used Claude to steal Mexican government data (two phrasings) | 4,037 | 7–8 |
| "texas federal agencies ai compliance deadlines" | 2,017 | 9.2 |
| OMB AI guidance, NIST AI RMF and state pre-emption, February 2026 | 1,836 | 9.6 |

Together these are about 8,000 impressions with no clicks. They explain the 4,000+ impressions with one click on
`/jurisdictions/south-africa`, `/ai-risk/incidents/1430`, `/jurisdictions/us` and `/jurisdictions/us-texas`.
A record page cannot win a news click with a better title, and should not try to. What wins this traffic is a dated,
sourced item on `/updates` the week it happens, which is the editorial habit the roadmap asked for (log changes
weekly), and the enforcement tracker (F1).

### 4. Head terms are out of reach for now; "tracker" terms are not

| Query | Impressions | Position |
|---|---:|---:|
| eu ai act | 271 | 34.5 |
| ai policy | 260 | 24.9 |
| ai regulation | 256 | 27.0 |
| ai policies | 131 | 37.0 |
| ai policy tracker (brand) | 85 | **1.7** (24 clicks, 28% CTR) |
| ai regulation tracker | 32 | 18.6 |
| ai legislation tracker | 29 | 37.0 |
| global ai regulation tracker | 18 | 12.1 |

Head terms are won with authority (links, citations, mentions), which is the credibility plan in the strategy note,
not with titles. The "… tracker" variants are close. The home page title is "AI Policy Tracker: Free Global AI Law
and Policy Tracker". It already carries "law" and "policy", but its first screen and description do not say
"regulation tracker" or "legislation tracker". Adding both to the description and the H1 is a cheap test.

### 5. Templates convert best

The old template URLs (now 301 to `/templates/*`) have the best CTR on the site at 4.5%, and "fundamental rights
impact assessment template", "ai impact assessment template" and "ai system inventory template" are among the
few queries with clicks. Templates are the strongest acquisition surface, which is why it matters that template
pages say the same thing everywhere about how a file is delivered (U6: "sent to your work email" against "no
account needed").

### 6. Obligation and change pages are shown and not chosen

Obligations: 3,637 impressions, 5 clicks (0.14%). Change pages: 1,531 impressions, 1 click (0.07%). Obligation
titles are cut mid-phrase ("UK GDPR Article 35: Carry out a data protection impact…"), and sixteen EU AI Act duty
pages rank between 4 and 22 with one click between them. A title that names the law and the duty in plain words, such as "EU AI Act human
oversight duty (Art. 14): who, what, when", is the test to run on the ten obligation pages with the most
impressions. `scripts/seo/ctr-audit` proposes rewrites when given a page-and-query export (Search Console →
Performance → filter one page → Queries → export).

### 7. Question queries are answered on the results page

"What is the total outlay approved by the Union Cabinet for the IndiaAI Mission in March 2024?" was seen 213 times
in four phrasings, at position about 4, with no clicks. The record carries the figure (INR 10,371.92 crore, Cabinet
approval 7 March 2024), so the answer most likely appears in the snippet itself. The same holds for "when does texas
traiga take effect?" (1 January 2026, on record). This is the answer-first design working. These impressions build
recognition rather than visits, and their CTR should not be "fixed".

## Desktop and mobile

Desktop has 90% of impressions at 0.62% CTR; mobile has 1.43%. The desktop share is unusually high for consumer
search and is consistent with research and professional use. The low desktop CTR comes mostly from findings 2 and
3, both desktop-heavy query types.

## Next exports to take

1. **Page and query together** for the ten pages with the most impressions. `ctr-audit` needs this to suggest titles.
2. **The same three months a year from now**, or a 28-day comparison after the redirects in finding 1, to measure
   the consolidation.
3. **Bing Webmaster Tools**, if it is not set up yet: ChatGPT search draws heavily on Bing's index.
