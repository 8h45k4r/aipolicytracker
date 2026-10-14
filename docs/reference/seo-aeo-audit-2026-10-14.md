# SEO and AEO audit, 14 October 2026

Follows `seo-aeo-review-2026-10-05.md`. The audit was made against `main` at 6e4329d, served locally with the production data set, before any change; this page records what was found, what was changed (the High and Medium items) and what was left.

## Findings and changes

| Priority | Finding (evidence) | Change |
|---|---|---|
| High | A listing filtered by a value that names nothing (`/policies?jurisdiction=zz`, `/obligations?category=zz`) and a page past the last (`/policies?page=999`, `/changes?page=999`) were served `index,follow` and canonical to themselves: an unbounded set of empty, duplicate-titled pages. | Only a bare listing or a single *known* status or category is indexable (`PolicyCatalog::isIndexableFilterSet`, `isKnownValue`); anything else is `noindex,follow` with the canonical on the base listing. A page past the last is a 404 on policies, obligations and changes. |
| High | 121 sitemap URLs were `/policies?jurisdiction=X`, each listing exactly the instruments its country page lists (checked for Nepal, Japan, the EU, the US, the UK, China and India). | A single-country listing is `noindex,follow` and out of the sitemap; the country page is the one answer to "what AI laws apply in X". The listing is still served and linked. |
| High | 44 of 192 change entries are new versions of this site's templates (`template-*`). Their "official source" is the template page here, yet they were `NewsArticle`, indexed and eligible for the news sitemap. | `ChangeEvent::isSiteUpdate()` and `scopeRegulatory()`. A site update is a `WebPage`, `noindex,follow`, out of the changes and news sitemaps, and still listed in the change log. The X publisher uses the same scope. |
| High | `ANALYTICS_REQUIRE_CONSENT` defaults to on, and `ai_referral` was checked once at load, before analytics could load, so a visitor who accepted on the landing page was never counted. | The engine is worked out on landing and held in memory only; the event is sent from `loadAnalytics()` once the reader consents. Engines matched by domain or subdomain; Grok, Microsoft 365 Copilot, Poe and Phind added. Parameters are now `ai_engine` and `landing_path` (path only). |
| High | Subscription confirm and unsubscribe pages render at URLs carrying the subscriber's token, inside the layout that loads analytics. | `App\Support\Analytics::allowedHere()`: no analytics on a route whose URL carries a token (password reset, invitation, email check, subscription confirm and unsubscribe). The account shell also loads analytics only where consent is not required. |
| Medium | IndexNow was called only by the nightly import and the manual command, so records published or edited in the admin reached Bing late. | An hourly `indexnow` job (Admin → Jobs) submits what changed in the last 70 minutes, only pages offered to search engines (no noindex records, no site updates). Off while `INDEXNOW_KEY` is unset. |
| Medium | Policy, obligation, incident and transition-measure pages were typed `Article`; the reviewer and review date shown on the page could not be stated (`reviewedBy` and `lastReviewed` are WebPage properties), while `NewsArticle` pages carried both. | Record pages are `ItemPage`; a policy's `mainEntity` is its `Legislation` node, and `isBasedOn` names the official text and the open-data record. `Seo` drops `reviewedBy`/`lastReviewed` from article types. An incident page no longer gives the incident's date as its `datePublished`. |
| Medium | Each policy, obligation, control and country page declared a `Dataset` of one record (an obligation's only "distribution" was its Markdown file). | Removed from those pages; kept where a real collection is offered (home, open data, quarterly report, change log, calendar, controls index, enforcement, implementation, transition). The Markdown and JSON alternates stay. |
| Medium | 128 indexable incidents had under 25 words of description; 157 had neither a risk classification nor a law on record, so the page added nothing to the AI Incident Database's text. | An incident page is indexed with at least 25 words of description and either a classification or a law on record: 1,559 → 1,274. The rest are `noindex,follow`. |
| Medium | Risk-domain pages went from the heading straight to "Subdomains"; the snapshot has no description for any of the seven domains. | An answer first, composed from the taxonomy and the counts: the domain's place in the MIT taxonomy, its kinds of risk, and how many incidents and risk entries are classified under it. No definition is written that the repository does not give. The policies section asks "Which laws on record cover this risk area?" and says a listed law covers the area, not that it applies. |
| Medium | Four country answers read "(2024, guidance, guidance)". | Wording corrected in Canada, Japan, Malaysia and Saudi Arabia; no fact changed; `policy:validate` passes. |
| Medium | Obligation pages linked no glossary term. | "Terms explained" (up to four) on obligation pages, as on policy pages. |

Corrections to the audit as first written: the risk domains carry no MIT description to quote (hence the composed answer), and no page now renders through the account shell (`resources/views/app.blade.php`); the account pages are standalone views without analytics, so the token exposure was on the subscription pages only.

## Left for later (Low)

- Social cards for glossary terms and the quarterly report (they use the default image).
- Add `['china', 'us']` to `config/hubs.php` compare pairs once both records are checked.
- A Wikidata item in `sameAs` (config only; do not invent the URL) and a check that every listed profile resolves.
- Confirm the production web server redirects trailing slashes (the canonical is already right).

## Indexation rules

| Rule | Templates |
|---|---|
| Index, follow, in sitemap | Home and section hubs; published policies, obligations and controls on an indexable policy; country pages over their threshold; glossary terms; the quarterly report; regulatory change entries; listings filtered by one known status or obligation category; update pages over their threshold; risk entries with 25+ words; incidents with 25+ words and a classification or a law, not sensitive; listed comparison pairs. |
| Noindex, follow, out of sitemap | Search; multi-filter, sorted, dated or unknown-value listings; single-country listings; site-update change entries; short or unclassified incidents; short and "Additional evidence" risk entries; sensitive incidents; unlisted comparison pairs; incident browser pages after the first. |
| Canonical elsewhere | Tracking and unknown parameters to the clean URL; noindexed listings to the base listing; reversed pairs and numeric incident or risk addresses 301 to the canonical slug. |
| 404 | A listing page past the last; unknown slugs. |
| Noindex, nofollow | Token pages, account and admin. |

## Measurement (GA4)

- Event `ai_referral`, parameters `ai_engine` (chatgpt, perplexity, claude, gemini, copilot, deepseek, mistral, grok, meta_ai, you, poe, phind) and `landing_path`. Sent once per page view, after consent. Register both as event-scoped custom dimensions and mark the event as a key event.
- Explorations: `ai_engine` by `landing_path` with engaged sessions and key events; session source matching `chatgpt|perplexity|claude|gemini|copilot|deepseek|mistral|grok` as a cross-check (it also catches visits that consent on a later page); landing page containing `/glossary/` with the organic channel, alongside Search Console's queries for those pages.
- A `content_group` per template and a `jurisdiction` parameter would allow country-level reporting; not implemented in this round.

## Checks

`tests/Feature/SeoIndexQualityTest.php` covers each change above; `StructuredDataGraphTest` keeps the sitemap equal to the indexable records. On a deployed site: the static sitemap drops by 121 URLs, the changes sitemap by the site updates, the incidents sitemap to about 1,274; the Rich Results Test and the schema.org validator report no unknown properties on a policy, obligation, incident and change page; GA4 DebugView shows one `ai_referral` for `/?utm_source=chatgpt.com` after accepting consent and none after declining.
