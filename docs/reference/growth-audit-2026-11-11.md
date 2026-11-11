# Growth, SEO, AEO and revenue audit, 11 November 2026

This round follows the owner's brief: research first, then fix what the research shows, then build the first paid feature. Its strategic reference is the monetisation strategy review of the same week, which is not committed; its conclusions are summarised in section 6.

Every figure below is labelled:
- **Measured**: counted in this session, on the repository or on a local copy built from it.
- **Third-party**: from a named outside source.
- **Assumption**: a stated input to a model.

There are no search volumes, rankings, traffic numbers or conversion rates of the site's own. This session had no access to Search Console, GA4 or Bing Webmaster Tools, and its network refused connections to the live site. Section 1 says what was used instead.

## 1. Baseline

### Access

| Source | Status in this session | What was used instead |
|---|---|---|
| Google Search Console, Bing Webmaster Tools | No access | The site's own `seo:ctr-audit` command reads a Search Console CSV export when one is supplied (open owner action since October, debt #51) |
| GA4 | No access | The event names the site sends (section 9) |
| Live site | Refused by this session's network policy | A local copy built from the repository: `policy:import` and `external:import`, served with `php artisan serve` and crawled from its own sitemaps. The live-site checks of 10 November (Lighthouse, crawler user agents, edge behaviour) are in `seo-aeo-audit-2026-11-10.md` |
| Keyword tools, Google Trends | Not available, and `trends.google.com` was refused | The question bank in `search-questions-2026-10.md`, the records themselves, and the competitor research of the strategy review |

### Measured on the local copy

| Measure | Value |
|---|---|
| URLs in the sitemaps | 3,360, across 11 section sitemaps, all answering 200 |
| Of which: AI incidents and risk entries (third-party datasets, mirrored) | 2,337 (70%) |
| Policies, jurisdictions, obligations, controls, changes | 244, 156, 117, 27, 164 |
| Pages with a missing or duplicate title or description | 0 |
| Titles over 60 characters / descriptions over 160 | 0 / 0 |
| Pages without exactly one H1 / without a self-referencing canonical | 0 / 0 |
| Sitemap URLs carrying `noindex` | 0 |
| Pages under 150 words in `<main>` | 0 (lowest median of any section: risks, 643 words) |
| Published records verified by a named reviewer | policies 184 of 244; obligations 49 of 117; jurisdictions 211 of 249; changes 139 of 154; controls 26 of 26 |
| Exact-day deadlines still ahead | 6 |
| Guides (long-form) | 12, in `config/content.php` |
| Lighthouse (live, 10 November, from the earlier audit) | SEO 100 and accessibility 100 on the three pages tested; performance 96–100; LCP 1.2–1.9 s; CLS 0 |

The on-page basics are in good order, which five earlier audit rounds put there. The gains left are in what the pages say, how they connect, and whether their dates can be trusted.

## 2. Technical SEO and indexing problems found

| # | Problem | Evidence | Status |
|---|---|---|---|
| T1 | Record pages stated their database row's write time as `dateModified` and `article:modified_time`. A link check, an admin decision re-applied over a file, or a bulk edit moved it with nothing changed for a reader. It also disagreed with the sitemap, which had already been fixed to use the content date. | 71 of 244 policy pages disagreed with their sitemap `lastmod` (measured, before) | **Fixed**: 0 of 244 (after). Same rule now on obligation, change, jurisdiction, guide, comparison and audience pages. Test: `SeoFreshnessTest`. |
| T2 | The 25 indexed comparison pages (`/compare/eu-vs-us-ai-regulation` …) were linked only from `/compare`. The "Compare with" box on a country page linked the on-demand `?j=` comparison, which is noindex. | Inbound links from jurisdiction pages to comparison pages: 0 (measured, before) | **Fixed**: all 25 linked from both of the country pages they compare (after). Test: `ComparisonLinksTest`. |
| T3 | The ten "for your role" pages (`/for/...`) have 2–3 inbound internal links each. | Link graph of the crawl | Open: roadmap R5 |
| T4 | 1,052 of 1,274 incident pages have two or fewer inbound links from sitemap pages. They are reached through paginated browse pages that are not in the sitemap. | Link graph of the crawl | Open: needs Search Console to judge whether they earn their crawl (roadmap R7). Not removed on a hunch. |
| T5 | No `DataCatalog` in the structured data, though the site publishes nine datasets. | Schema types per section | **Fixed**: every `Dataset` names the site's `DataCatalog`. Test: `StructuredDataGraphTest`. |
| T6 | Guides carry no edit date of their own text. The sitemap leaves their `lastmod` empty on purpose, because nothing records when the text changed. | `SitemapController` | Left as decided. The guide page's `dateModified` is now the newest content date of the records it is built from (T1), not the row write time. |
| T7 | Page dates on transition measures, enforcement listings, newsletter issues, risk pages and the deadline engine still use row times. | `withModified(...->updated_at)` | Open: low traffic pages; roadmap R8. |

## 3. Keyword and search-intent opportunities

No search volumes were measured; the "Evidence" column says what the cluster rests on. Competitor coverage comes from the strategy review's research:
- the IAPP global tracker is behind membership;
- OECD.AI and the Future of Life Institute's AI Act Explorer are free;
- none of the AI-specific trackers found sells a priced self-serve API.

| Cluster | Intent | Page that answers it | Evidence | Action |
|---|---|---|---|---|
| Global AI regulation tracker | navigational / research | `/`, `/jurisdictions` | Autocomplete research (Oct); site's core | Keep. Titles were tuned in October. |
| AI laws by country | research | `/jurisdictions/{country}`, country hubs | 156 indexable country pages | Done this round: country pages now link their comparisons (T2) |
| EU AI Act requirements and deadlines | research → compliance | `/policies/eu-ai-act`, `/guides/eu-ai-act-digital-omnibus-new-deadlines` | Third-party estimate: about 8,100 US searches a month for "eu ai act" (seodata.dev, method not stated) | Keep. The record holds the Omnibus timetable. |
| Upcoming AI compliance dates | transactional / planning | `/calendar` | The site holds the dated records; this page did not answer the question | **Done**: the page leads with the next deadline and a 12-month count, and lists upcoming and recently passed dates with sources |
| ISO/IEC 42001 and NIST AI RMF mapping | research | `/frameworks/*`, two comparison guides | Existing pages, 5,600–6,500 words | Keep |
| AI risk / impact assessment, templates and checklists | transactional | `/templates`, `/self-assessments` | 41 templates, 12 guides | Roadmap R3: link templates from the workbook and from obligation pages |
| AI procurement and contract clauses | research → transactional | `/obligations?category=vendor_governance`, `/templates/ai-contract-clauses` | Free official clause sets exist (EU MCC-AI, OMB M-25-22), which limits paid value | Roadmap R6: a procurement guide citing the official clause sets |
| Transparency, disclosure, human oversight | research | `/obligations?category=transparency`, `?category=human_oversight`, guide on labelling | Category pages are indexable | Keep |
| Shadow AI | definitional | `/glossary/shadow-ai` (368 words) | Survey evidence only; no search data | No new page. The paid inventory is a declared-system feature, not discovery (section 6). |
| AI regulatory updates | news | `/changes`, `/updates/*`, RSS | 164 change pages | Keep |

## 4. AEO and answer-engine visibility

**Already in place.** The October and November rounds built most of the machine-readable surface:
- `llms.txt` and `llms-full.txt`;
- a Markdown file for every record, also served to `Accept: text/markdown`;
- an OpenAPI description and JSON-LD on every page type;
- answer boxes and FAQ markup built from the records;
- reviewer identity and dates;
- named answer-engine crawlers allowed in `robots.txt`;
- IndexNow.

**This round:**
- **Dates an answer engine can trust (T1).** Freshness is one of the few signals an answer engine can read off a page. A date that moves with nothing behind it teaches it to ignore the date.
- **A direct, computed answer on `/calendar`** ("The next recorded deadline is … on …"). It is marked with `data-answer`, and an `ItemList` of the upcoming dates goes in the page's main entity.
- **A `DataCatalog`** for dataset indexes.

**Monitoring framework.** No AI-citation results are claimed here.
1. **Question set.** Use a fixed list of 20 questions: the ten in `search-questions-2026-10.md`, plus:
   - "When do EU AI Act high-risk rules apply?"
   - "What AI laws start on 1 January 2027?"
   - "Is the Colorado AI Act in effect?"
   - "Which countries have AI laws?"
   - "What does Article 50 of the AI Act require?"
   - "What does ISO 42001 require that the AI Act does not?"
   - "What must a deployer of a high-risk AI system do?"
   - "What AI rules apply to hiring in New York?"
   - "What is the next AI compliance deadline?"
   - "Where can I download an AI risk register template?"
2. **Engines.** Each month, ask each question in Google (AI Overview or AI Mode where shown), ChatGPT with search, Perplexity, Copilot and Gemini, signed out where possible.
3. **Record per answer.** The date, the engine and the question; whether aipolicytracker.org is cited, linked or named; which URL; whether the answer is correct against the record; and a screenshot.
4. **Metric.** Citation share = questions where the site is cited, divided by questions asked, per engine.
5. **Limits.** Answers change with location, account and phrasing. Engines do not publish citation data. A monthly sample of 20 shows direction, not rank.
6. **Server-side signal.** The `ai_referral` event (section 9) counts visits arriving from answer engines.

## 5. Competitor content and positioning gaps

From the strategy review (third-party, checked in that review):

| Competitor | Model | Gap the site fills |
|---|---|---|
| IAPP Global AI Law and Policy Tracker | Membership | Free, record-level, CC BY data with permanent ids and an API |
| OECD.AI policy navigator | Free | Obligations and controls, not only policy listings. Dated deadlines with sources. |
| FLI AI Act Explorer | Free, EU only | Every jurisdiction in one schema; crosswalks to ISO 42001 and NIST AI RMF |
| Governance platforms (Credo AI, OneTrust, Vanta, Drata) | Quote-only or bundled | No neutral, open, citable source for the regulatory layer they all need |
| Legislative trackers (LegiScan, Plural, FiscalNote, Quorum) | Paid API from free to about $1,000 a year per state (third-party, sources disagree), and up to enterprise contracts | AI-specific obligations, not bill text |

The site's position is "the open reference behind other people's compliance work". It earns references by being correct, dated and citable, not by volume.

## 6. Conversion and monetisation problems

**Measured from the code and configuration:**

- **Selling is off** (`BILLING_ENABLED` false). No revenue can have been collected. The production database was not queried.
- **The listed price and the provider price differ.** The site lists Builder at $49 a month or $490 a year, and the payment provider's products are $29 and $290 (production-readiness report, 9 November). The checkout guard refuses to sell a plan whose prices differ. This is an owner decision, still open, and no price was changed here.
- **Plans against what is built.** The pricing page already separates "included", "agreed by contract" and "coming", and lists only what exists:
  - **Open (free):** every record, exports, the API at the public rate, three watches, templates one by one, and the applicability register download.
  - **Builder:** 500 watches, alert channels, saved profiles, five times the API rate and the template bundle. **New:** the compliance workbook.
  - **Team ($199 a month, by agreement):** one invoice and onboarding. Team accounts and a hosted MCP endpoint are marked as coming.
  - **Commercial (from $5,000 a year, by agreement):** an attribution waiver, an SLA and a licence agreement. A webhook delta feed, a verified-only feed and a white-label widget are marked as coming.
- **What sold before this round.** Before this round, Builder sold delivery volume (watches, channels), not a deliverable a compliance lead could show their manager. The workbook is that deliverable.
- **No feature found advertised but missing.** Every "included" line has code behind it. Every unbuilt one is marked as coming.

**Decisions from the strategy review, applied here:**
- workbooks first;
- the enterprise feed sold as a service around open data, never as exclusive rights to it;
- the calendar limited to official dates;
- the AI-system inventory as a feature of saved profiles, not a discovery product;
- no vendor directory, paid placement, sponsorship or affiliate listing, in line with `/independence`.

### Phase C delivered: compliance workbook v1

See `docs/modules/compliance-workbooks.md`. A saved profile is a declared AI system with a name, description, owner and stage. Its workbook has seven sheets: gap analysis with columns to fill in, deadlines, controls, evidence, sources, version history and the system. A version is stored only when the screened records change, and the owner is emailed what changed. The free register download is unchanged. The workbook is gated to Builder once selling is on.

### Enterprise feed: architecture prepared, not built

Most parts already exist; what is missing is the delivery contract.

| Commercial line | Built on | Missing |
|---|---|---|
| Signed webhook of every change | Builder's signed alert webhooks (`AlertChannel`, `alerts:deliver`); `record_revisions` holds field-level diffs | A firehose channel kind with no watch filter; a delivery log the customer can query; replay by date |
| Bulk delta feed | `record_revisions` plus `/open-data/*.ndjson` | `GET /api/v1/changes/since?cursor=` over revisions, paginated by a stable cursor |
| Verified-only feed | `review_status`, `last_verified_at`, `reviewed_by` on every record | A `verified=1` filter on the exports and the delta feed |
| Historical snapshots | `DatasetVersion`, `report:freeze` for quarterly figures | A dated, immutable copy of the full corpus per release, kept on the same storage as backups |
| Freshness SLA | `/freshness` measures it per jurisdiction | Alerting to the operator when a contracted jurisdiction passes its re-check date |

**Pricing.** Keep "from $5,000 a year" until two customers have specified what they need. Third-party benchmarks put legislative data APIs from free to about $1,000 a year per state, and enterprise policy platforms at $7,000–$67,000 a year (Spendhound and Vendr estimates). A price for delivery, an SLA and an attribution waiver of $5,000–$6,000 a year sits at the low end of the second group. It is defensible only with a written SLA and a named contact, which are the contract terms already listed.

## 7. Revenue model

Produced by `scripts/revenue-model.py`, which states every input. None of the inputs is measured, because selling has never been on.
- The scenarios differ in signups, conversion, churn, Team and Commercial cadence, reviewer cost and content spend.
- Costs common to all of them are:
  - $100 a month for hosting and $20 for email;
  - a 5% plus $0.50 payment fee (check the provider's rate);
  - 2 support hours a month per 10 Builder accounts, at $50 an hour;
  - 6 onboarding hours per contract;
  - 15% annual non-renewal of Team and Commercial contracts.
- The owner's own time is not priced, so conservative CAC reads $0. That is an artefact of the model, not a finding.

| Month 12 | Conservative | Base | Upside |
|---|---|---|---|
| New verified accounts a month at start / monthly growth | 40 / 3% | 100 / 5% | 250 / 7% |
| Free-to-paid conversion / Builder churn a month | 1.5% / 6% | 3% / 4% | 4% / 3% |
| MRR | $695 | $3,571 | $10,447 |
| ARR | $8,336 | $42,856 | $125,358 |
| Builder accounts / Team / Commercial contracts | 6 / 0 / 0.9 | 39 / 2.8 / 2.8 | 156 / 4.6 / 4.7 |
| Builder ARPA a month | $47 | $47 | $46 |
| Builder LTV | $561 | $864 | $1,186 |
| Gross margin | 71% | 74% | 77% |
| Cash CAC / payback | $0 / – | $126 / 3.6 months | $101 / 2.8 months |
| Net a month | $494 | $1,151 | $4,056 |
| Break-even Builder accounts (fixed costs only) | 3 | 47 | 122 |

| Month 24 | Conservative | Base | Upside |
|---|---|---|---|
| MRR | $1,301 | $8,165 | $27,807 |
| ARR | $15,607 | $97,985 | $333,684 |
| Builder / Team / Commercial | 12 / 0 / 1.8 | 95 / 6.0 / 6.1 | 459 / 9.4 / 9.5 |
| Commercial ARR in force | $8,762 | $30,668 | $57,074 |
| Qualified Commercial enquiries needed a year | 5 | 16 | 20 |
| Cumulative revenue / cost | $14,935 / $5,468 | $90,759 / $56,505 | $287,992 / $162,999 |

**Reading it.**
- In the base case Builder carries a little over half of MRR at month 24. Commercial is the largest single lever per sale, and its pipeline needs about 16 qualified enquiries a year at an assumed 25% win rate.
- The model is most sensitive to free-to-paid conversion and to churn. Both stay unknown until selling has run for two or three months, so recalibrate it then.
- Weighted pipeline: no Commercial enquiry was read in this session. The figure is meaningful only once real enquiries are scored, which `licence_enquiries` already records.

**What belongs on which plan:**

| Plan | What belongs there |
|---|---|
| Free | Every record and source; exports; the API at the public rate; the applicability check and its register download; templates one by one; three watches. This is the citation engine and must stay open. |
| Builder | Volume, delivery and upkeep: watches, channels, saved systems, the compliance workbook with versions and change emails, the template bundle, the higher API rate. |
| Team | Shared systems and workbooks across an organisation, with one invoice. Not built: the Team plan stays "by agreement" until shared accounts exist. |
| Commercial ($5,000–$6,000+) | The written SLA, the attribution waiver, the delta and verified-only feeds, onboarding. The $6,000 point is justified only once the feed rows in section 6 are built. |
| Strongest revenue against cost | The workbook (built on existing services) and the delta feed (built on `record_revisions` and the existing webhook signer). The weakest: a separate inventory or discovery product, already declined. |

## 8. Changed files and decisions

| Area | Files |
|---|---|
| Content dates (T1) | `app/Support/ContentDates.php` (`forRecord`, `forPolicy`); `Policy`, `Obligation`, `Change`, `Jurisdiction`, `Landing`, `Compare`, `Audience` controllers; `tests/Feature/SeoFreshnessTest.php` |
| Comparison links (T2) | `app/Services/Hubs/HubCatalog.php` (`comparisonUrl`, `comparisonsFor`); `JurisdictionController`; `jurisdictions/show.blade.php`; `tests/Feature/ComparisonLinksTest.php` |
| Deadlines page | `CalendarController`; `site/pages/calendar.blade.php`; `tests/Feature/DeadlineCalendarTest.php` |
| Data catalogue (T5) | `app/Support/Seo.php`; `tests/Feature/StructuredDataGraphTest.php` |
| Workbook | migration `2026_11_11_090000_create_profile_workbook_versions`; `ProfileWorkbookVersion`; `ApplicabilityProfile`; `Services/Workbooks/ComplianceWorkbook`; `ProfileWorkbookController`; `RefreshProfileWorkbooksCommand`; `WorkbookUpdatedMail` and its two views; `account/profile-show.blade.php`; `account/following.blade.php`; routes; `config/billing.php`; `config/faq.php`; `pricing.blade.php`; `EnsureSubscribed`; `JobRun`; `routes/console.php`; `tests/Feature/ProfileWorkbookTest.php`; `docs/modules/compliance-workbooks.md`, `alerts.md`, `README.md` |
| Model | `scripts/revenue-model.py` |

**Decisions:**
- No URL changed, so no redirects were needed.
- No record was marked verified.
- No price was changed, and selling stays off.
- The new account page is noindex and already covered by the `Disallow: /profile` line in `robots.txt`.

## 9. KPI and reporting specification

Revenue (Dodo webhooks into `billing_payments`), pipeline (`licence_enquiries`) and projections (this model) are reported separately and never added together.

| Stage | KPI | Source | Exists today |
|---|---|---|---|
| Acquisition | Organic clicks, impressions, CTR, position; non-branded share; by page and by cluster (section 3) | Search Console export → `php artisan seo:ctr-audit` | Command yes; export is an owner action |
| Acquisition | Indexed pages, crawl errors | Search Console, Bing | Owner action |
| Acquisition | Referral and institutional traffic (`.edu`, `.gov`, `.int`, think tanks) | GA4 referrals | Yes (GA4, with consent) |
| Acquisition | Visits from answer engines | `ai_referral` event (15 engine domains or `utm_source`) | Yes |
| Engagement | Record views by type; search use; filter use | GA4 page views; `home_search`, `filter_apply` | Yes |
| Engagement | Template and dataset downloads; API use | `template_download`, `dataset_download`, `export_click`; `resource_downloads`; API logs | Yes |
| Engagement | Digest subscriptions, return visitors | `subscribers` table; GA4 | Yes |
| Conversion | Registrations | `users` (verified) | Yes |
| Conversion | Saved records, saved systems, workbook use | `follows`, `applicability_profiles`, `profile_workbook_versions`; `save_record`, `profile_save`, `workbook_open`, `workbook_download`, `workbook_upsell` | **Yes, events added this round** |
| Conversion | Builder upgrades, Team and Commercial enquiries, lead to customer | `billing_checkouts`, `subscriptions`; `licence_enquiries` with tier | Yes |
| Revenue | MRR, ARR, expansion, churn | `subscriptions`, `billing_payments` | Yes, once selling is on |

**Dashboard.** The admin dashboard already shows downloads and the funnel (`/backend/admin/downloads`). Add a monthly table with four parts:
- this section's conversion rows;
- revenue taken;
- open enquiries by tier;
- the model's projection for the same month, labelled as a projection.

## 10. 30/60/90-day roadmap

Ranked by impact, confidence, effort and cost.

| # | Item | When | Impact | Confidence | Effort | Owner |
|---|---|---|---|---|---|---|
| R1 | Decide the Builder price (set Dodo to $49/$490 or the site to $29/$290), then run the full checkout in test mode, then turn selling on | 30 | High: no revenue without it | High | Low | Owner |
| R2 | Export Search Console queries and pages (debt #51) and run `seo:ctr-audit`; start the monthly AEO question log (section 4) | 30 | High: the first measured baseline | High | Low | Owner |
| R3 | Workbook v1.1: link the templates that fit each duty; keep the account's own status and gap entries between versions (open debt in the module doc) | 30–60 | High | Medium | Medium | Engineering |
| R4 | Reviewer throughput: 68 obligations and 60 policies pending review. Verified records are what answer engines and customers trust. | 30–90 | High | High | Ongoing | Reviewers |
| R5 | Link the `/for/*` pages from the obligation and template pages of the matching role or sector (T3) | 60 | Medium | Medium | Low | Engineering |
| R6 | Procurement guide citing the EU model AI clauses and OMB M-25-22, linked to `vendor_governance` duties | 60 | Medium | Medium | Low | Editorial, with sources |
| R7 | With Search Console data, decide whether the 2,337 mirrored incident and risk pages earn their crawl (T4) | 60–90 | Medium | Low until data | Low | Owner + engineering |
| R8 | Content dates on the remaining page types (T7) | 60 | Low | High | Low | Engineering |
| R9 | Enterprise delta feed and verified-only filter (section 6), after two Commercial enquiries confirm need | 90 | High per contract | Medium | Medium | Engineering |
| R10 | Quarterly "state of AI regulation" report from `report:freeze`, sent to researchers and policy communities as a citable dataset release (the corpus takes a DOI through `DATASET_DOI`; whether one is minted was not checked) | 90 | Medium: earns references | Medium | Medium | Editorial |

## Remaining gaps, plainly

- No measured traffic, ranking, conversion or revenue figure exists in this report. Getting one needs the owner's Search Console, GA4 and Bing access (R2).
- The live site was not crawled from this session. The local copy has the same code and data, but not the edge or the production database.
- Team accounts, the delta feed and the verified-only feed are not built and are still shown as coming on the pricing page.
