# From tracker to governance intelligence: research and build plan

Date: 2026-09-22. Author: maintainer. Status: research note, not a commitment.

This note tests the "LAW → OBLIGATION → RISK → CONTROL → EVIDENCE → ASSURANCE"
strategy against the code and data in this repository and against what exists
elsewhere, and turns the parts that survive into a build order. Every number
below was read from the corpus on the date above; every claim about a third
party carries a link.

## 1. What the corpus can and cannot support today

| Asset | Claimed on the site | Actual in `data/` after `policy:import` | Note |
|---|---|---|---|
| Jurisdictions | 212 | 212, of which 121 have at least one published instrument and pass `isIndexable()` | 91 jurisdiction pages are directory entries only |
| Policy instruments | 186 | 186, of which 42 are binding | Strategies and guidance dominate |
| Obligations | 57 | 57 (38 binding), 20 categories | 19 of the 57 come from the EU AI Act; 30 instruments carry none |
| Framework mappings | 35 ISO 42001, 42 NIST, 2 ISO 27001 | 79 mappings on 54 obligations | Almost every obligation is mapped, but there are only 57 obligations to map |
| Evidence examples | "evidence examples" on obligation pages | 65 evidence artifacts on 54 obligations; free-text titles, `artifact_type` optional | No controlled vocabulary, no owner, no frequency, no link to a control |
| Applicability rules | applicability check tool | 57 | One per obligation |
| Controls | none | none | The word does not exist in the schema |
| Risks linked to obligations | none | none | Risk domains are linked to policies by use-case overlap at render time only (`PolicyController::show`) |
| Framework registry | 4 | `iso_42001`, `nist_ai_rmf`, `iso_27001`, `oecd_ai_principles` (`config/frameworks.php`, enum in `data/schema/policy.schema.json`) | Adding a framework is a config entry plus an enum value |
| Editorial pages | guides, landings, comparisons | 4 guides, 9 landings, 4 curated comparisons (`config/content.php`) | Two of the four guides are already crosswalk explainers |

Two conclusions follow.

**The chain is the right model and the schema is one hop away from it.** An
obligation already carries applicability, actors, sectors, use cases, evidence
examples and framework references. The missing link is a `control` record
between obligation and evidence, with the risk as a property of the control's
purpose rather than a fifth table. Everything the strategy calls Level 2 and
Level 3 is a change to `policy.schema.json`, `PolicyImporter` and three views,
not a new product.

**Fifty-seven obligations is the real ceiling.** Every downstream artefact
(controls, evidence, "one control, many frameworks") is a projection of the
obligation set. The EU AI Act carries 19; Colorado, Singapore and the NIST RMF
carry 4 each; 30 published instruments carry none. Before any framework hub is
written, the obligation corpus needs to reach roughly 150 to 200 records across
the binding instruments (EU AI Act to 40 or more, Colorado, Texas, Utah, Illinois,
NYC LL144, Korea's Framework Act, Japan's AI Promotion Act, Vietnam, Kazakhstan,
the Council of Europe convention, Quebec Law 25, Ontario, Canada AIDA as
proposed). That is data entry against official texts by a reviewer, and it is
the slowest step, so it starts first.

## 2. What exists elsewhere

**Crosswalks are commodity content.** NIST publishes official crosswalks from
the AI RMF to ISO/IEC 23894, the OECD recommendation and the (then proposed)
EU AI Act, and lists more in its Trustworthy and Responsible AI Center
([NIST](https://www.nist.gov/itl/ai-risk-management-framework/crosswalks-nist-artificial-intelligence-risk-management-framework)).
Consultancies and vendors publish NIST-to-ISO 42001-to-EU AI Act tables freely
([RSI Security](https://blog.rsisecurity.com/nist-ai-risk-management-framework-iso-42001-crosswalk/),
[Legalithm](https://www.legalithm.com/en/blog/nist-ai-rmf-iso-42001-eu-ai-act-framework-crosswalk),
[AccuroAI](https://accuroai.co/blog/unified-ai-compliance-crosswalk-nist-iso-eu-ai-act),
[EU AI Compass](https://euaicompass.com/iso-42001-nist-ai-rmf-eu-ai-act-mapping.html)).
A framework-versus-framework page on its own will not rank and will not be cited
over these. What none of them offer is the crosswalk **as data**: one row per
legal duty, with a source article, an applicability rule, a confidence level, a
named reviewer, a JSON and Markdown form, an open licence and an API. That is
the part this repository already does and should say louder.

**A control catalogue with framework mappings already exists, and it is big.**
The Cloud Security Alliance's AI Controls Matrix v1.1 has 247 control objectives
in 18 domains, mapped to ISO 42001 (59 percent "no gap"), ISO 27001, BSI AIC4,
NIST AI RMF and the EU AI Act
([CSA announcement](https://cloudsecurityalliance.org/blog/2026/07/14/ai-controls-matrix-v1-1-strengthening-the-foundation-for-trustworthy-ai),
[AICM to ISO 42001 mapping](https://cloudsecurityalliance.org/artifacts/aicm-to-iso-42001-mapping),
[CSA on NIST and CSA control frameworks](https://cloudsecurityalliance.org/blog/2025/09/03/a-look-at-the-new-ai-control-frameworks-from-nist-and-csa)).
Writing a competing 200-control catalogue is not the moat. Being the open,
source-backed **legal** side of that matrix is: AICM maps controls to the
frameworks, this site maps statutes to duties. The join between the two is the
product. Check the AICM licence before importing a single control id; where it
permits, a mapping table `obligation → AICM control id` is a smaller and more
defensible job than an original catalogue.

**Regulatory-intelligence products are positioning on exactly the same words.**
AIGI describes itself as "enterprise AI governance regulatory intelligence" that
turns primary-source law, enforcement and policy signals into traceable
intelligence with the source chain attached, explicitly not a GRC system of
record ([AIGI](https://www.aigovbrief.com/)). The GRC and observability
platforms (OneTrust, Credo AI, Holistic AI, Vanta, Drata, Arthur, Fiddler) sell
inventories, assessments, continuous monitoring and evidence collection, and all
of them cite NIST AI RMF, ISO 42001 and the EU AI Act
([Speakeasy round-up](https://www.speakeasy.com/blog/best-ai-governance-platforms-2026),
[Arthur](https://www.arthur.ai/column/best-ai-governance-platforms-2026),
[Gartner Peer Insights](https://www.gartner.com/reviews/market/ai-governance-platforms)).
The tracker category itself has a meta-catalogue of more than sixty trackers,
which names local, African, Latin American and South-East Asian coverage as the
gaps ([AI Policy Tracker Tracker](https://ai-policy-tracker-tracker.vercel.app/)).
This repository already covers those regions; that is worth stating on the
About page and in the `Organization.areaServed` data, which now lists them.

**The standards the strategy names are real and current.** ISO/IEC 42005:2025
(AI system impact assessment, guidance, May 2025), ISO/IEC 42006:2025
(requirements for bodies certifying against 42001) and ISO/IEC 23894:2023 (AI
risk management guidance) are all published
([ISO 42005](https://www.iso.org/standard/42005), [ISO 42006](https://www.iso.org/standard/42006),
[ANSI on 23894](https://blog.ansi.org/ansi/iso-iec-23894-2023-ai-risk-management/)).
OWASP's list is now "OWASP GenAI LLM Top 10 2026" and ships its own mappings to
NIST, MITRE ATLAS and CWE ([OWASP](https://genai.owasp.org/resource/owasp-genai-llm-top-10-2026/));
MITRE ATLAS carries mitigation identifiers (M00xx) that can be cited by id
([ATLAS versus OWASP](https://www.redfoxsec.com/blog/mitre-atlas-vs-owasp-llm-top-10-which-framework-should-you-use-in-2026)).
ISO texts are copyrighted and this project's rule of citing clause numbers only
stands; OWASP and ATLAS are openly licensed and their identifiers can be
reproduced.

## 3. What survives, what changes

Kept from the strategy, in this order of leverage:

1. **The chain as the data model.** Obligation → control → evidence type, with
   risk categories and framework references as attributes. Already half built.
2. **One control, many requirements.** The only page a compliance lead cannot
   get from a vendor blog: for one control, every duty it supports, in every
   jurisdiction, with confidence and source. It falls out of (1) for free.
3. **Framework pages as an index, not an essay.** Duties, jurisdictions,
   controls, evidence types, risk domains, incidents, related instruments; each
   number a link. The current page shows two of those seven.
4. **Role and sector cuts of existing records** ("EU AI Act for HR teams")
   generated from the actor, sector and use-case taxonomy already on every
   obligation. Editorial framing, data-driven body, one template.
5. **The governance card** at the foot of every editorial page. This is the
   `<x-site.cite>` block plus applicability, status, frameworks and actions;
   most of it is already fields on the record.
6. **A stricter type vocabulary** for what is called a framework. The instrument
   type enum already separates act, regulation, strategy, guidance, standard,
   code of practice and consultation; the `frameworks` registry needs a `kind`
   field (management standard, risk framework, control catalogue, threat model,
   principles, assessment method) so OECD and ISO 42001 stop sharing a badge.

Changed:

- **Framework hubs are not the first build.** They are the third, after
  obligations reach critical mass and the control layer exists, because a hub
  whose numbers read "4 duties, 0 controls, 0 evidence types" advertises the
  gap. Two of the four existing guides are already ISO 42001 and NIST
  crosswalk explainers; extend those rather than adding fifteen thin pages.
- **Fifteen crosswalk pages become three real ones plus a generator.** EU AI
  Act, Colorado and Korea against ISO 42001, NIST AI RMF and (where the duty is
  a security duty) ISO 27001, generated from mappings the way `/frameworks/{a}/{b}`
  already is. OWASP and ATLAS join as `kind: threat model` entries whose
  mapping target is a control, not a duty.
- **AIRIS stays a dataset, not a pillar of prose.** The strongest move is a
  typed link from a control to the MIT risk subdomain it mitigates, so the
  incident count on a framework page is a real query. That needs `risk_domains`
  on the control record and nothing else.
- **Fifty flagship pages is the wrong unit.** The unit is records that render
  as pages. Ten role guides written by hand go stale; one template over 57
  (then 200) obligations filtered by actor does not.

Dropped:

- Separate "Level 1" reference articles for each framework. The registry entry
  plus the official link already is the reference layer; padding it is what the
  strategy itself warns against.
- G7 Hiroshima, UNESCO and similar as framework entries. They are instruments
  and belong in `data/policies/international/` as records of type `guidance`
  or `principles`, which is where the site can say honestly that nothing is
  mapped to them.

## 4. Build order

Each step ships behind the existing gates and is independently useful.

**Step 0 (data, continuous): obligations to ~150.** Reviewer time against the
official texts named in section 1. Every new obligation must carry
`applicability`, `evidence_examples` and at least one mapping, which the
completeness policy can enforce by raising the threshold in `config/completeness.php`.

**Step 1 (schema): the control layer.**
- `data/controls/*.yaml`: `slug`, `title`, `kind` (policy, process, technical,
  contractual), `purpose`, `risk_domains[]` (MIT ids), `evidence_types[]`
  (from a controlled list in `data/taxonomies/terms.yaml`: risk register,
  impact assessment, dataset documentation, evaluation report, approval record,
  incident log, contract clause, training record, monitoring dashboard),
  `owner_role`, `frequency`, `framework_references[]` (same shape as today's
  mappings, enum extended with `owasp_llm_top10`, `mitre_atlas`, `iso_23894`,
  `iso_42005`, `csa_aicm` once the licence is confirmed).
- On the obligation: `controls[]` of `{control, relationship: satisfies|supports, note, confidence_level}`.
- Importer, validator, `PolicyDataRepository`, serializer, CSV and NDJSON
  exports, OpenAPI, `llms-full.txt`, one migration. Tests in the style of
  `tests/Feature/FrameworkCrosswalkTest.php`.

**Step 2 (pages): controls as first-class records.**
- `/controls` index and `/controls/{slug}` ("one control, many requirements"):
  every obligation that cites it grouped by jurisdiction, the evidence it
  produces, the risk subdomains and live incident counts it addresses, the
  framework references. Dataset and `.md` surfaces like every other record.
- Obligation page gains "Controls that satisfy this" and a proper evidence table
  (type, owner, frequency) in place of the free-text list.
- Framework page gains the five missing counters and their lists.

**Step 3 (editorial, generated): role, sector and use-case cuts.**
One template, `config/content.php` entries of the form
`{instrument, actor|sector|use_case, intro, faq}`; the body is the filtered
obligation set with its controls and evidence. Title pattern
"{Instrument} for {audience}: duties, controls and evidence". Indexable only when
the filter returns at least five obligations, on the same rule landing pages use.

**Step 4 (registry): the framework vocabulary and the security frameworks.**
`kind` on every registry entry; OWASP GenAI LLM Top 10 2026 and MITRE ATLAS
added as threat models whose references land on controls; ISO 23894 and 42005
added as assessment methods. The existing two crosswalk guides are extended to
three-way comparisons with a "what can be reused" table computed from the data.

**Step 5 (positioning): words that match the data.** Once step 2 is live the
tagline can move from "Track AI policy. Build compliant AI." to "AI governance
intelligence, from regulation to evidence" without overclaiming. Until then
the current line is the honest one.

## 5. Measures

- Obligations published; share with a control; share with a named reviewer.
- Controls published; median number of obligations per control (the reuse
  claim in numbers).
- Framework pages: each counter non-zero for ISO 42001, NIST AI RMF and the EU
  AI Act before the hubs are promoted.
- Search: impressions on queries containing "control", "evidence" or "for
  {role}" against the existing baseline in `SEO_OPERATIONS.md`.
- Answer engines: citations of `/controls/` and `/obligations/` URLs in the
  referrer log, against the current `/policies/` baseline.

## Sources

- NIST, crosswalks to the AI RMF: https://www.nist.gov/itl/ai-risk-management-framework/crosswalks-nist-artificial-intelligence-risk-management-framework
- CSA, AI Controls Matrix v1.1: https://cloudsecurityalliance.org/blog/2026/07/14/ai-controls-matrix-v1-1-strengthening-the-foundation-for-trustworthy-ai and https://cloudsecurityalliance.org/artifacts/aicm-to-iso-42001-mapping
- AIGI: https://www.aigovbrief.com/
- AI Policy Tracker Tracker: https://ai-policy-tracker-tracker.vercel.app/
- Platform round-ups: https://www.speakeasy.com/blog/best-ai-governance-platforms-2026 , https://www.arthur.ai/column/best-ai-governance-platforms-2026 , https://www.gartner.com/reviews/market/ai-governance-platforms
- Published crosswalks: https://blog.rsisecurity.com/nist-ai-risk-management-framework-iso-42001-crosswalk/ , https://www.legalithm.com/en/blog/nist-ai-rmf-iso-42001-eu-ai-act-framework-crosswalk , https://accuroai.co/blog/unified-ai-compliance-crosswalk-nist-iso-eu-ai-act , https://euaicompass.com/iso-42001-nist-ai-rmf-eu-ai-act-mapping.html
- Standards: https://www.iso.org/standard/42005 , https://www.iso.org/standard/42006 , https://blog.ansi.org/ansi/iso-iec-23894-2023-ai-risk-management/
- OWASP GenAI LLM Top 10 2026: https://genai.owasp.org/resource/owasp-genai-llm-top-10-2026/
- MITRE ATLAS and OWASP compared: https://www.redfoxsec.com/blog/mitre-atlas-vs-owasp-llm-top-10-which-framework-should-you-use-in-2026

## 6. Mandate after the first build (2026-09-22)

The control layer, audience pages, framework registry and comparison shipped the same day this note was written, and the maintainer's review changed the recommendation: the centre of gravity has moved from tracker to governance intelligence, so the next bottleneck is depth and authority of the data, not surface area.

Standing instruction for whoever works on this next: **do not redesign the UI; do not add generic framework articles; expand the graph.**

| Priority | Work | Gate |
|---|---|---|
| P0 | Obligations from 57 to about 150, EU AI Act first as the reference implementation | Every record: official source, exact article, jurisdiction, status, actor, applicability, effective date, the duty in plain words, at least one control, evidence, framework references where justified, risk relationship where applicable |
| P0 | Make each of the 26 controls a knowledge object: regulations, duties, frameworks, risks, incidents, evidence with owner and frequency, implementation steps | A control page must be useful to a CISO, an auditor and an engineer at once |
| P1 | CSA AI Controls Matrix, only as a mapping on existing controls, once the licence is confirmed | The question is "how does one control map across the governance ecosystems", never "do we support AICM" |
| P1 | Comparison methodology | The page must never read as a leaderboard: purpose and normative status before any count |
| P1 | Audience pages | Keep the taxonomy tight; a thin cut stays unindexed |
| P2 | Framework articles as entry points to the graph | Only after the data is deep enough that the article can link into it |
| P2 | Further UI | The current UI is sufficient |

Relationship semantics stay visible everywhere a control meets a duty: `satisfies` (the control, operated properly, does the work the article asks for) and `supports` (it contributes; the duty needs more). Neither says a control satisfies a law; the official text decides.

Positioning approved: "AI governance intelligence, from regulation to evidence." as the product line; "Track regulations. Map obligations. Operationalise controls. Prove compliance." beneath it; "AI policy, verified at the source." kept as the trust line.

KPIs to track from here are graph KPIs first: verified obligations, primary-source coverage, controls, control–obligation links, framework, evidence, risk and incident mappings, jurisdictions covered, freshness. Product KPIs second: control and obligation views, crosswalk use, API calls, exports, saved records, repeat visits. Traffic alone is not the measure.
