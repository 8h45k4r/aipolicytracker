# Search questions in this niche, and where the site answers them

October 2026. Built from web searches on the niche's head terms (EU AI Act compliance, AI regulation tracker, US state AI laws, ISO 42001 vs NIST AI RMF, AI governance templates). The searches show the questions that rank and the phrasing the top results answer. They are not a keyword-volume tool: confirm volumes and positions in Search Console and a keyword tool before reprioritising.

Every answer on the site comes from the records (or from the templates built from them), never from the pages below. Where a record is `pending_review`, the answer inherits that state.

| Question as searched | Where it is answered | Source of the answer |
|---|---|---|
| Which countries have AI laws? | `/jurisdictions` FAQ | computed: jurisdictions with a binding instrument in force |
| When does the EU AI Act apply? | `/policies/eu-ai-act` FAQ | EU record `key_dates_summary` (hand-written FAQ corrected to match it) |
| Does the EU AI Act apply to companies outside the EU / US companies? | `/policies/eu-ai-act` FAQ | EU record `scope_summary` (Article 2) |
| What is a high-risk AI system? | `/policies/eu-ai-act` FAQ, `/glossary#high-risk-ai-system` | glossary definition |
| What are the fines under the EU AI Act? | `/policies/eu-ai-act` FAQ | `penalties_summary` (Article 99) |
| What does the EU AI Act prohibit? (and any law with prohibitions) | every policy page with prohibited-practice duties | obligations in category `prohibited_practice` |
| Is there a federal AI law in the US? | US jurisdiction page FAQ | US record, or computed by instrument type for any country with states |
| Which US states have AI laws? | US jurisdiction page FAQ | computed from state records with a binding instrument adopted or in force |
| Is ISO 42001 certification mandatory / does it satisfy the AI Act? | ISO 42001 vs EU AI Act guide | guide content |
| What should an AI acceptable use policy / risk register / impact assessment / system inventory include? | `/templates` FAQ | the templates' own sections and columns |

Fixed along the way: the EU record's hand-written dates answer still gave the pre-Omnibus timetable beside the generated one; the US record listed Colorado SB 24-205 as binding and named it in a hand-written states answer, though the Colorado record shows it repealed by SB 26-189 before taking effect.

## Search suggestions mapped to pages (2026-10-05)

From Google's autocomplete and related searches for *ai policy tracker*. Brand searches for other trackers (IAPP, Similarweb, HSF, OECD, Paragon, "AI policy labs/network") are not targeted by name; the generic intent behind them is.

| Suggestion | Page | What changed |
|---|---|---|
| ai policy tracker, ai policy tracker free, global ai law and policy tracker, artificial intelligence tracker, global ai policy | `/` | title, description and eyebrow; home FAQ in FAQPage markup |
| ai policy map, ai regulations around the world | `/jurisdictions` | title, description and H1 |
| ai policy examples | `/ai-policy-examples` (new) | national AI policies and strategies from the records, by region |
| ai policy template, ai policy tracker template | `/templates/acceptable-use-policy` | `seo_title` *AI Policy Template: Acceptable Use Policy* |
| ai policy training | `/templates/ai-literacy-training-plan` | `seo_title` *AI Policy Training and AI Literacy Plan Template* |
| ai policy nepal, ai policy 2082 | Nepal landing page | title and H1 carry the Nepali year 2082 |
| ai policy in india, ai policy south africa | jurisdiction pages | titles read *{Country} AI Policy & Regulation {year}*; India landing says *AI policy* |
| ai policy transparency | AI transparency self-assessment, EU AI Act page | covered by the existing transparency obligations |

## Sources consulted

- [hellowarrant.com: EU AI Act compliance in 2026](https://hellowarrant.com/blog/eu-ai-act-compliance-in-2026-what-tech-companies-need-to-do-now)
- [orrick.com: The Artificial Intelligence Act of the EU](https://orrick.com/Insights/2024/10/The-Artificial-Intelligence-Act-of-the-European-Union)
- [recordpoint.com: Global AI regulations tracker](https://recordpoint.com/global-ai-regulations-tracker)
- [techpolicy.press: where state AI legislation stands half way into 2026](https://techpolicy.press/where-state-ai-legislation-stands-half-way-into-2026)
- [urmconsulting.com: ISO 42001, the NIST AI RMF and the EU AI Act](https://urmconsulting.com/blog/artificial-intelligence-frameworks-and-regulations-iso-42001-the-nist-ai-rmf-and-the-eu-ai-act)
- [erdalozkaya.com: AI governance policy template](https://erdalozkaya.com/ai-governance-policy-template/)
