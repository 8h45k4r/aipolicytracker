# SEO and AEO review, 5 October 2026

A review of the public site against the questions the people who lead in AI search ask (Mike King on retrieval and passages, Koray Tuğberk Gübür on entities and topical maps, Metehan Yeşilyurt on AI search infrastructure, Lily Ray on E-E-A-T and scaled content, Aleyda Solis on measurement and visibility beyond Google). It was made before changing anything: a crawl of every sitemap URL, a read of the structured data, robots and machine-readable files, and a look at the content the site publishes at scale.

## Method

- Crawled all 4,251 sitemap URLs on a local copy with the production data set: status, title, description, canonical, robots and H1 count for each.
- Read the JSON-LD on the home, policy, obligation, incident and risk pages, `/robots.txt`, `/llms.txt` and the analytics hooks.
- Counted the sitemap by section, and measured the risk entries' description lengths.

## What is already right

| Area | Finding |
|---|---|
| Technical | 4,251 sitemap URLs: no non-200, no noindex page listed, no canonical pointing elsewhere, no duplicate title, no title over 60 characters, one H1 on every page, a description on every page. |
| Crawler access | `robots.txt` names 16 answer-engine and assistant crawlers (GPTBot, OAI-SearchBot, ClaudeBot, PerplexityBot, Google-Extended and others) and lets them read what any visitor can. |
| Machine-readable | `/llms.txt`, `/llms-full.txt`, a Markdown file per record, an OpenAPI description and open data downloads under CC BY 4.0. |
| Freshness to Bing and ChatGPT search | IndexNow submissions on import (needs `INDEXNOW_KEY` set in production). |
| Passages | Record pages open with a self-contained answer and use the questions people ask as section headings ("Who does it apply to?", "When do the requirements apply?"). |
| E-E-A-T | Named reviewers, verification dates on the record, official sources cited in the page and in `citation`, methodology, corrections log, and `publishingPrinciples` and `correctionsPolicy` on the Organization. |

## Findings and what was done

| # | Finding | Lens | Done |
|---|---|---|---|
| 1 | The 22 curated glossary terms existed only as anchors on one page, which cannot rank for "what is a high-risk AI system" or be cited as its own passage. | Passages; entities | Each term has a page at `/glossary/{term}`: the definition first, its source, the laws and duties on record that use it, related terms, `DefinedTerm` and FAQ markup. Policy pages link the terms they use ("Terms explained"). In the sitemap and `llms.txt`. |
| 2 | The Organization and WebSite named the site "AIPolicyTracker" only; people search for "AI policy tracker" and the logo spells out "Artificial Intelligence Policy Tracker". | Entities | `alternateName` on both nodes. |
| 3 | `knowsAbout` was the first 40 taxonomy labels alphabetically, so it described the site as knowing about "Access or activity log" and "Approval or sign-off record". | Entities | Now the core subjects, the glossary's concepts and the duty categories. |
| 4 | 75% of the sitemap (3,212 URLs) is incident and risk entries from two outside databases. Risk entries were indexed with any description at all; 554 had fewer than 25 words, on a page whose other content every entry in the subdomain shares. | Scaled content | A risk entry is offered to search engines with at least 25 words of description (1,063 of 1,617). The others stay reachable, linked and `noindex,follow`. Incident pages add the site's own "laws on record" section and keep their gate. |
| 5 | Incident pages listed laws under "Laws that address this harm" from a domain-level match, so a children's-content incident listed a hiring-tool law and a repealed act. | Trust; citation accuracy | Repealed, archived and superseded laws are never listed (filtered when the page is shown, too). Use cases are matched by risk subdomain where the domain misleads (toxic content, security flaws, disinformation and surveillance, environmental harm). The heading reads "Laws on record for this risk area", and the note says a listed law covers the area, not that it applies to the incident. |
| 6 | 54 obligation pages shared a templated meta description. | Snippets | The duty's own summary leads the description. |
| 7 | Nothing separates traffic from answer engines in analytics. | Measurement | An `ai_referral` event (engine and path) when a visit comes from ChatGPT, Perplexity, Claude, Gemini, Copilot, DeepSeek, Mistral, You.com or Meta AI, or carries `utm_source=chatgpt.com`. It fires only where analytics already runs, under the same consent. |
| 8 | "AI regulation statistics" and "how many countries have AI laws" are answered by the quarterly report, but its title and description did not say so. | Query fan-out | Title "State of AI Regulation {quarter}: Statistics, Laws & Changes", a statistics description built from the figures, and two more answers: how many AI laws there are, and how many countries have a national AI strategy. |
| 9 | "Penalties" was the one statement among question headings on a policy page. | Passages | "What are the penalties?" |

Not done, on purpose: a separate statistics page (the quarterly report is that page, and two would compete), and a US state-by-state page (seven states are on record; a page next to fifty-state trackers would be thin until coverage grows).

## For the site owner (outside the code)

1. After deploying, run `php artisan incidents:enrich --refresh` once, so the incident pages' stored law lists use the subdomain matching. The pages already drop lapsed laws without it; overrides in `data/external/incident_overrides.yaml` are kept.
2. Set `INDEXNOW_KEY` in production if it is not set, verify the site in Bing Webmaster Tools and submit the sitemap there: ChatGPT search and Copilot read Bing's index.
3. In GA4, mark `ai_referral` as a key event and add an exploration by `engine`; check Search Console for the `/glossary/` pages being indexed and the short risk entries leaving the index.
4. Create a Wikidata item for the site (name, alternate names, URL, founder, parent organisation) and add it to `sameAs` in `config/aipolicytracker.php`; answer engines use it to resolve the entity.
5. Brand mentions are the off-site half of AI visibility: the quarterly report, the embeddable map and deadline widgets, and the open data are the pieces to offer to journalists, newsletters and university guides.
