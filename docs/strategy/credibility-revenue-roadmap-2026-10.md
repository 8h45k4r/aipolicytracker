# Credibility, revenue, features and UX: research and plan

Date: 2026-10-07. Author: maintainer. Status: research note and proposed plan, not a commitment.

Read with [`ai-governance-intelligence.md`](ai-governance-intelligence.md) (the data model and mandate),
[`../plans/roadmap.md`](../plans/roadmap.md) (P1–P10, all shipped) and
[`../reference/research-2026-10-01.md`](../reference/research-2026-10-01.md) (search, admin, templates).
This note does not repeat them. It answers four questions those notes leave open: how the site becomes a
resource that academics, journalists and regulators cite; how it earns money without losing that standing;
which features are worth building next; and what the interface gets wrong. It closes with a review of the
Dodo Payments integration.

Every repository statement below was read from the code or the data on the date above. Third-party claims
carry a link; figures marked *unverified* came from secondary or aggregator sources and must be checked
before they are relied on. EUR-Lex, eto.tech, zenodo.org and dodopayments.com were blocked from the research
environment, so nothing here was confirmed against those sites directly.

## 0. Process

The work follows the binding [engineering standard](../reference/engineering-standard.md)
(Analyze → Design → Implement → Test → Validate → Document) and the five [change gates](../reference/change-gates.md).
That standard already covers the generic SDLC prompt this note was commissioned with, and is stricter on evidence:
no claim of "works" without command output, and data changes only from sources actually read. Nothing in
this note changes it. Every build item in §5 is sized so that it can pass the gates as one branch.

## 1. Where the site stands

| Signal | State on 2026-10-07 | Read from |
|---|---|---|
| Coverage | 212 jurisdictions, 187 policy records, 26 controls | `data/` |
| Verification | 184 records `verified`, 3 `pending_review`; **all 184 signed by one person**, the maintainer, between September and October 2026 | `reviewed_by` in `data/policies/**` |
| Reviewer roster | 2 entries: the maintainer and the editorial desk (also the maintainer) | `data/reviewers/` |
| Declared interest | The maintainer is associated with Certifyi, a commercial AI-governance platform; disclosed on the roster and About page | `data/reviewers/bhaskar-bhatt.yaml` |
| EU AI Act | Carries the Digital Omnibus dates (Reg. (EU) 2026/1744, OJ 24 July 2026; Annex III duties from 2027-12-02) but is still labelled *pending review against the Official Journal* ten weeks after publication | `data/policies/eu/eu-ai-act.yaml` |
| Enforcement | `enforcement_events` exists in the policy schema and renders on policy pages, but is **empty on every record**; no hub page | `data/schema/policy.schema.json`, `data/policies/**` |
| Revenue | Pro plan built (Dodo Payments, $29/mo, $290/yr), **checkout switched off**; Dodo business verification outstanding (debt #21) | `docs/modules/billing.md` |
| Persistent identifiers | No DOI, no `CITATION.cff`, no data paper | repository root |

The engineering is ahead of the institution. The code already does more than most trackers do: per-record provenance,
frozen quarterly snapshots, an API, an MCP server and generated templates. What the site lacks is outside
confirmation that the records are right. Each item below is ranked by how much it closes that gap.

## 2. Credibility: from a well-built site to a cited source

### 2.1 What the cited sources have that this site lacks

Every incumbent spreads its authority beyond one person:

| Source | What carries its authority |
|---|---|
| Stanford AI Index | Institutional home (HAI) and a named steering committee ([HAI](https://hai.stanford.edu/news/stanford-hais-ai-index-welcomes-six-new-steering-committee-members)) |
| OECD.AI policy database | Joint EC–OECD product, a network of 350+ nominated experts, and government survey input ([OECD.AI experts](https://oecd.ai/en/network-of-experts)) |
| CSET AGORA | A peer-reviewed data paper at AIES (DOI 10.1609/aies.v7i1.31615) and versioned Zenodo releases ([AIES](https://ojs.aaai.org/index.php/AIES/article/view/31615)) |
| Epoch AI | CC BY datasets with a citation and BibTeX block on every dataset page ([Epoch](https://epoch.ai/benchmarks/use-this-data)) |
| AlgorithmWatch, FLI | Published funding with amounts ([AlgorithmWatch](https://algorithmwatch.org/en/transparency/), [FLI](https://futureoflife.org/about-us/finances/)) |

Wikipedia, which answer engines draw on heavily, accepts a self-published source only when its author has
already been published by reliable, independent outlets. One maintainer verifying every record, with a declared
commercial interest, fails that test however well the conflict is disclosed.

### 2.2 Recommendations, ranked

| # | Action | Why | Effort | Cost |
|---|---|---|---|---|
| C1 | **Second verifier and published agreement.** Recruit 2–4 reviewers (law students, policy-school fellows, retired regulator staff). Double-code a random 20% sample of records each quarter; publish Cohen's kappa per field (status, dates, binding, actors) on `/methodology`. Show "verified by A, checked by B" on records with two sign-offs. `RecordVerification` and `ReviewerDecision` already store decisions; this adds a second decision and a statistic. | Turns "trust the maintainer" into "here is how often two people agree". It is the single largest credibility gain available. | M | $0–5k (volunteer, or paid student coders) |
| C2 | **Close the Omnibus review now.** Read Reg. (EU) 2026/1744 against the record and clear `pending_review` on the EU AI Act and the 13 records added from secondary sources in September. | The flagship record is the one most often checked, and it still says it has not been checked. | S | $0 |
| C3 | **Editorial independence policy and advisory board.** A page stating that Certifyi has no editorial say, no early access and no data not offered to everyone; that sponsors and funders never review content before publication; and that a 3–5 person board (academia, civil society, a former regulator) rules on disputes. Add a funding page listing every source above $1k. Link both from `Organization.publishingPrinciples` and `ownershipFundingInfo`, which already exist. | It must exist **before** any revenue switches on; afterwards it reads as damage control. | S–M | $0 |
| C4 | **DOIs for every quarterly snapshot.** Turn on the Zenodo–GitHub integration so each tagged release gets a version DOI under one concept DOI. Add `CITATION.cff` (`type: dataset`). Put the DOI in the Dataset JSON-LD `identifier` and in the existing "Cite this record" box, with BibTeX. `report:freeze` already produces the snapshot; a release tag on the same day is the only new step. | Academics cite DOIs, not URLs. DataCite DOIs are indexed by Google Dataset Search directly. | S | $0 |
| C5 | **Data paper.** arXiv preprint, then a *Scientific Data* Data Descriptor or AIES/FAccT, describing the schema, the provenance model, the verification protocol and the agreement statistics from C1 ([Scientific Data](https://www.nature.com/sdata/for-authors)). | The strongest route into academic citation, and external peer review of exactly the question C1 raises. Requires C1 and C4. | L | Journal APC (not confirmed) |
| C6 | **Academic partner.** A university lab or policy school as co-host or validating partner; invite national experts to validate their own jurisdiction's records, as OECD does with governments. | Adds an institution, unlocks grants that require one, and passes the Wikipedia test. | M–L | $0 or grant-funded |
| C7 | **Public correction statistics.** Quarterly count of corrections received, accepted and fixed, with median time to fix, on `/methodology`. The correction form and `ContributorSubmission` already hold the data. | Matches the Trust Project's "actionable feedback" indicator ([Trust Project](https://thetrustproject.org/)); a source that shows its errors is believed more. | S | $0 |
| C8 | **Earned citations.** Offer snapshot extracts to journalists and to the tracker round-ups ([HKS list](https://hksaitechpolicy.notion.site/AI-Legislation-Trackers-146610af47d780899780ca1c2de55308)); submit to the OECD.AI and AI Index source lists. | Answer engines lean on news, Wikipedia and forums more than on markup ([arXiv 2507.05301](https://arxiv.org/pdf/2507.05301)). | M | $0 |

Answer-engine markup is already strong (`research-2026-10-01.md`). The one remaining technical step is Bing
Webmaster Tools plus the existing IndexNow, with `INDEXNOW_KEY` set in production. ChatGPT search citations
track Bing's index closely (second-hand figure, *unverified*). An observational study of 1,702 answer-engine
citations found metadata freshness, semantic HTML and structured data most associated with being cited
([GEO-16, arXiv 2509.10762](https://arxiv.org/abs/2509.10762)), which the site already does.

Not worth pursuing: CoreTrustSeal (it certifies repositories, and depositing on Zenodo gets the benefit); IFCN
(for fact-checkers); a composite country score. If a score is ever published, its weights and a sensitivity
analysis must be published too; the Tortoise index is the cautionary case ([arXiv 2402.10122](https://arxiv.org/html/2402.10122v1)).

## 3. Revenue without losing neutrality

### 3.1 Constraints

- **The data cannot be sold.** It is CC BY 4.0, which already permits commercial reuse. What can be sold is the
  service around it: speed, alerts, an SLA, bulk feeds, support, and time saved. Moving data to a
  non-commercial licence would be read as a bait and switch and must not happen.
- **Free trackers already exist.** IAPP's tracker and Holistic AI's Tracker 2.0 are free and feed paid products
  ([Holistic AI](https://www.holisticai.com/news/introducing-holistic-ai-tracker-2-0)). The data is the funnel, not the product.
- **The Certifyi association is the largest commercial risk.** Any feature that routes readers to Certifyi, ranks
  vendors, or gates data behind a sales form will be read as lead generation. Keep the policy in C3 in force
  across every revenue line below.

### 3.2 Price reference points

| Product | Price | Note |
|---|---|---|
| AI Governance Library newsletter | $10/mo, $100/yr | [aigl.blog](https://www.aigl.blog/membership/) |
| IAPP membership | $295/yr; AIGP exam $649–799 | [certcrush](https://www.certcrush.app/blog/iapp-aigp-explained-domains-cost-worth-it-2026) |
| Axios Pro | $599/yr per vertical (2022) | [Nieman Lab](https://www.niemanlab.org/2022/01/axios-launches-a-premium-subscription-product-aimed-at-the-dealmakers-among-us/) |
| MLex | from €2,209/yr | [LexisNexis](https://www.lexisnexis.com/de-at/produkte/mlex) |
| Politico Pro | ~$3,000/seat; EU from €7,000 | [A Media Operator](https://www.amediaoperator.com/news/politico-pro/) |
| Regology, Saidot | ~$1,700/user/mo, ~€1,000/mo | aggregator listings, *unverified* |
| Wikimedia Enterprise | free tier; paid by request, snapshot or contract | [Wikimedia Enterprise](https://enterprise.wikimedia.com/pricing/) |
| OpenSanctions | open data; commercial licence, with exemptions for journalists and NGOs | [OpenSanctions](https://www.opensanctions.org/docs/commercial/exemption/) |

$29/month for Pro sits between a paid newsletter and Axios Pro. That is right for an individual, and too cheap
and too narrow for a team; see §3.3 line R5.

### 3.3 Plan by stage

Revenue ranges are estimates for a solo-run site of this size; none rests on published conversion data.

| Stage | # | Line | Price | Range (estimate) | Credibility risk | Guardrail |
|---|---|---|---|---|---|---|
| 0–3 mo | R0 | Publish the independence and funding pages (C3) | — | — | lowers it | Before any money is taken |
| 0–3 mo | R1 | **Switch on Pro** after Dodo verification (debt #21) | $29/mo, $290/yr | $15k–90k ARR at 50–300 subscribers | Low | Pro buys speed and convenience only: every record, source, deadline and the weekly digest stay free |
| 0–3 mo | R2 | GitHub Sponsors and/or Open Collective | any | $1k–10k/yr | Low | Funders page lists every amount over $1k. GitHub takes 0% on personal sponsorships; Open Source Collective takes 10% ([docs](https://docs.opencollective.com/help/fiscal-hosts/fiscal-host-fees)) |
| 0–3 mo | R3 | Grants: NLnet NGI Zero Commons (€5k–50k, open-source output; next call after the June 2026 one), Humanity AI, Mozilla Democracy x AI, Patrick J. McGovern Foundation, EU Digital Europe | — | one or two awards of €20k–150k | Low | Unrestricted funding preferred; funders do not review output (the [Our World in Data](https://ourworldindata.org/funding) practice) |
| 3–12 mo | R4 | **Commercial API and data service**: free key (rate-limited), Builder $99–299/mo (higher limits, change webhooks, bulk NDJSON snapshots, paid MCP tier), Enterprise/OEM $5k–25k/yr flat for GRC vendors and RAG builders (SLA, support, attribution terms) | see left | $25k–250k/yr at 5–20 licences | Medium | The same data at every tier; licensees listed publicly; customers never influence coverage |
| 3–12 mo | R5 | **Team plan**: Pro for 3–10 seats, shared watchlists, a team obligations register, SSO later | $990–2,900/yr | depends on R1 uptake | Low | As R1 |
| 3–12 mo | R6 | One labelled sponsor slot in the weekly digest | $150–750/issue under 5k subscribers | $5k–30k/yr | Medium | No sponsor placed beside coverage of its own regulation or product; no sponsor from a vendor that competes with Certifyi or with Certifyi itself |
| 3–12 mo | R7 | AI governance job board | $99–250/post ([benchmarks](https://www.ai-governance-jobs.com/employers/)) | $5k–30k/yr | Low | Visually and structurally separate from records |
| 3–12 mo | R8 | Workshops and briefings (EU AI Act readiness, Colorado, state-law watch) | $2k–10k/session | varies | Medium | Uses only public records; clients get no influence over coverage |
| 12+ mo | R9 | Self-paced courses (AIGP prep and similar) with CPE credits | $200–600 | needs scale | Low–medium | No claim of IAPP endorsement |
| 12+ mo | R10 | Feed licences to LLM and legal-tech companies | $10k–100k/yr | small, niche | Medium | Non-exclusive; every licensee disclosed |
| Avoid | — | Vendor-sponsored reports, affiliate links to GRC tools, vendor rankings | — | — | High | Made worse by the Certifyi association |

R4 is the line that fits the product best: the API, OpenAPI document, MCP server and webhooks already exist,
and the `api.requests_per_day` entitlement was deliberately removed until it had a reader (debt #22). Adding
API keys with metered limits is the natural next entitlement.

## 4. New features

### 4.1 Context

The Digital Omnibus on AI (Reg. (EU) 2026/1744) is in force: Annex III high-risk duties move to 2 December 2027,
Annex I to 2 August 2028, Article 50 stays at 2 August 2026, and new Article 5 prohibitions on non-consensual
intimate imagery and CSAM apply from 2 December 2026 ([Gibson Dunn](https://www.gibsondunn.com/eu-ai-act-omnibus-agreement-postponed-high-risk-deadlines-and-other-key-changes/)).
With the main dates settled, reader questions shift from *what does the law say* to *who is enforcing it, who
is being sued, and what guidance and standards are still to come*. None of the enterprise competitors checked
(Credo AI, Trustible, Holistic AI, IAPP) shipped an open dataset on those questions in 2026.

### 4.2 Shortlist, ranked

Each item goes through the existing pipeline (YAML + JSON Schema + importer + read model + API + exports +
`llms.txt` + sitemap + MCP), as every phase in the roadmap did.

| # | Feature | Persona | Why now | Open data source | Effort | Tier |
|---|---|---|---|---|---|---|
| F1 | **Enforcement and fines tracker**: `/enforcement` hub over the existing `enforcement_events` field, extended with regulator, respondent, amount, legal basis, outcome and appeal status. | Compliance, lawyers, journalists | GPAI fining powers have applied since 2 August 2026; national DPAs are acting on AI under the GDPR; no AI Act action is public yet, so a tracker started now can become the reference ([Cross-Border Data Forum](https://www.crossborderdataforum.org/tag/ai/)) | Regulator press releases; Commission content CC BY 4.0 | M | Free list; alerts in Pro |
| F2 | **EU AI Act implementation tracker**: every delegated and implementing act, guideline, code of practice and AI Board output, with due date, actual date and status. | Compliance, policymakers | The Article 6 high-risk guidelines missed their 2 February 2026 deadline (draft May 2026); the Article 50 labelling code of practice was finalised 10 June 2026 ([Jones Day](https://www.jonesday.com/en/insights/2026/06/european-commission-publishes-final-code-of-practice-on-marking-and-labelling-aigenerated-content)) | EUR-Lex, Commission pages, CC BY 4.0 | S–M | Free |
| F3 | **US state AI bills at bill level**: status, sponsors, versions, synced. | Policymakers, lobbyists, journalists | 109 state AI laws enacted by 1 July 2026 under federal pre-emption pressure ([Tech Policy Press](https://techpolicy.press/where-state-ai-legislation-stands-half-way-into-2026)) | Open States / Plural bulk data (public domain, [Plural](https://open.pluralpolicy.com/data/)); LegiScan CC BY 4.0, free tier 30k queries/month | M | Free |
| F4 | **Version redlines**: diff between versions of a bill or a consolidated act. `PolicyVersion` already exists. | Lawyers, policymakers | The Omnibus amended the AI Act; Colorado repealed and replaced SB 24-205 | As F3; EUR-Lex consolidated texts | M | Latest diff free; history and export Pro |
| F5 | **AI litigation tracker**: copyright, privacy, bias, chatbot harm and pre-emption suits, linked to the laws and incidents on record. | Lawyers, researchers, journalists | 100+ US AI copyright suits by April 2026; DOJ AI Litigation Task Force formed 9 January 2026 ([CBS](https://cbsnews.com/news/doj-creates-task-force-to-challenge-state-ai-regulations)) | CourtListener/RECAP API ([free.law](https://free.law/2026/05/07/api-included-in-memberships/)); court records public; check CourtListener's terms for its own content | M | Free; docket alerts Pro |
| F6 | **Consultation calendar** with comment deadlines, in the existing `.ics` feeds and alerts. | Policy teams, NGOs | The Article 6 guidelines consultation drew public submissions in mid-2026 | Regulations.gov API (public domain); gov.uk (OGL v3); Have Your Say (no API, curated) | S–M | Free |
| F7 | **Harmonised standards tracker**: CEN-CENELEC JTC 21 and ISO/IEC SC 42 work items, stage, expected and actual OJ citation. Metadata only. | Compliance and engineering | JTC 21 missed its 2025 target; no harmonised AI standard was cited in the OJ by April 2026 ([ICTRecht](https://www.ictrecht.nl/en/blog/ai-standards-crucial-for-ai-act-compliance)) | ISO and CEN public stage metadata; standard texts are copyrighted and stay out | S | Free |
| F8 | **Member-state implementation and sandboxes**: market-surveillance authority, penalty law and sandbox status for each of the 27 member states. | Policymakers, companies entering the EU | Authority designation is uneven; the FLI national-plans overview was last refreshed in 2024 ([FLI](https://artificialintelligenceact.eu/ai-act-implementation/)) | National gazettes, curated | M | Free |
| F9 | **Ask the records**: question answering over the corpus that answers only from records and cites each one, refuses when the records are silent, and is tested against `QuestionBank`. | All | The Commission's AI Act Service Desk checker is light on decision support; nobody offers this across jurisdictions | The site's own corpus | M–L | Free with a daily cap; Pro uncapped |
| F10 | **Scored gap assessment** built on the applicability check and controls, exported as a shareable PDF report (dompdf is already a dependency). | SMEs, consultants | The new deadlines give teams a planning window; Credo AI and Trustible sell this inside platforms | Own data | M | One free score; saved and repeated assessments Pro |
| F11 | **Agentic AI controls**: Singapore IMDA's agentic framework (January 2026, updated 20 May 2026) mapped onto the existing controls. | Compliance, security | IMDA's update covers multi-agent and third-party agents ([Baker McKenzie](https://www.bakermckenzie.com/en/insight/publications/2026/06/singapore-imda-updates-model-ai-governance-framework-for-agentic-ai)) | Framework text; check IMDA terms | S | Free |
| F12 | **GRC exports**: obligations register in OneTrust, Vanta and ServiceNow import formats, over the existing register export. | Enterprise GRC | Teams want the duties inside the tools they already use | Own data | M–L | Pro / Enterprise |

Not now: an EU high-risk database mirror (Article 71 registration waits on December 2027); a browser extension or
Teams bot (Slack and webhooks already exist); a standalone policy-diffusion network (better as a section of the
quarterly report).

F1, F2 and F7 together answer the post-Omnibus question better than any free source found, and they reuse the
change log, deadlines and alerts. Build them first.

Facts named above that could not be confirmed from a primary source, and must not enter `data/` until a reviewer
reads one: the Garante's fine on Character.AI and its amount; any DOJ suit against a state law; Colorado
SB 26-189's effective date; the Article 6 consultation's closing date; member-state designation counts.

## 5. UX

See §5.1 for the audit findings, which come from running the site locally at 1440 px and 390 px.

## 6. Dodo Payments: review

### 6.1 Verdict

The integration is sound and better built than most. The work left before launch is commercial, not technical.

What is right:

- **Merchant of record.** Dodo is the seller of record and handles VAT, GST and sales tax in 190+ countries,
  chargebacks and invoices. For a solo founder selling to EU and US businesses from outside the Stripe-supported
  countries, that removes the tax registrations that would otherwise make selling impractical.
- **Access only from verified webhooks.** Standard Webhooks signatures with a 5-minute tolerance
  (`WebhookVerifier`); the return URL never grants anything.
- **Idempotency handled correctly on PostgreSQL.** The event insert runs in its own savepoint
  (`WebhookProcessor::handle`). A retry of an event that previously *errored* is re-applied rather than
  acknowledged as a duplicate, so a cancellation that hit a deadlock is not lost.
- **Out-of-order safety.** Events older than the last applied one are recorded `stale`; the on-hold grace clock
  starts at the first failure and does not restart on later webhooks.
- **No phantom entitlements.** Products not in `config/billing.php` are mirrored but grant nothing; promised
  features without code were removed (debt #22).
- **Operator tooling.** One-click provisioning, a product price check and a "can we sell right now?" probe.

### 6.2 Findings

| # | Finding | Effect | Fix | Effort |
|---|---|---|---|---|
| D1 | `payment.*`, `refund.*` and `dispute.*` events are stored as `ignored`. | A refunded or charged-back customer keeps Pro until the subscription itself changes state. Revenue in Admin → Billing cannot be reconciled from local data. | Apply `refund.succeeded` and `dispute.opened`/`dispute.lost` to the subscription (revoke, or flag for review); record `payment.succeeded` amounts for an MRR figure. | S |
| D2 | Turning billing on silently removes features from free accounts. While selling is off, `Entitlements::value()` grants every signed-in account the most permissive tier, so all of them hold `saved.server` (watches) and `alerts.daily`; the moment the switch flips, their watches stop alerting. | Existing users lose a feature they were given, at launch, which is the moment the site is most visible. | Grandfather accounts created before launch (an expiry date on the free entitlement, announced by email), or keep a small free watch quota (for example three watches) and gate only volume, daily email and the Slack and webhook channels. | S |
| D3 | Webhook processing runs inline (debt #23). | Acceptable at launch volume; a retry storm is absorbed only by the 120/min throttle. | As recorded: queue after the row is written, once `queue:work` runs in production. | S |
| D4 | One plan for one person. | Teams, the buyers with budget, have nothing to buy; nothing serves API users. | R4 and R5 in §3.3. Dodo supports usage-based billing ($1 per million events) and licence keys, which fit metered API tiers. | M |
| D5 | Effective fees are higher than the headline. | Dodo lists 4% + $0.40 for US cards, +1.5% for international cards, and more for PayPal and BNPL; one independent review estimates 6–7% effective for a global SaaS ([review](https://fungies.io/dodo-payments-review-2026)). On $29 that is roughly $1.60–2.30 per charge. | Promote the annual plan (one fixed fee instead of twelve); check the live pricing page before modelling margins. | — |
| D6 | No trial. `trial_days` is 0. | For a $29 alerting product, the value shows only after a change lands. | A 14-day trial on the monthly plan, which the config already supports, with webhook-driven access as today. | S |

None of D1–D6 blocks a test-mode launch. D1 and D2 should be fixed before checkout goes on in live mode.

## 7. Order of work

| Order | Item | Why first |
|---|---|---|
| 1 | C2 (close the Omnibus review), C3 (independence and funding pages) | No code; protects everything after |
| 2 | D1, D2, then Dodo verification and Pro on (R1) | The revenue path that is already built |
| 3 | C4 (DOIs, `CITATION.cff`), C7 (correction statistics), Bing Webmaster and `INDEXNOW_KEY` | Small, permanent credibility gains |
| 4 | F1, F2, F7 (enforcement, AI Act implementation, standards) | The post-Omnibus questions, on existing machinery |
| 5 | C1 (second verifier, agreement statistics), R3 (grants) | Grants fund reviewers; reviewers make C5 possible |
| 6 | UX items U1–U5 (§5) | — |
| 7 | R4 (API tiers), R5 (team plan), F10 (gap assessment) | Revenue that needs the trust above |
| 8 | C5 (data paper), C6 (academic partner), F3–F5, F9 | Larger, slower |

## 8. Measures

- Records with two independent sign-offs; kappa per field; median days from correction to fix.
- Citations: DOI resolutions (Zenodo), Google Scholar mentions, Wikipedia references, answer-engine referrals
  (the `ai_referral` event already exists).
- Revenue: paid subscribers, MRR, annual share, churn, API licences; grant income; share of revenue from any
  one source (keep it under a third).
- Feature uptake: enforcement and implementation pages' impressions and clicks against the October 2026 baseline in `SEO_OPERATIONS.md`.
