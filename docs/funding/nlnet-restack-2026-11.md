# NLnet Restack application: draft

Status: **draft for the maintainer to rewrite, check and submit.** Not submitted.

Deadline: **3 November 2026, 12:00 CET** (Restack's first open call, opened 3 September 2026,
[NLnet announcement](https://nlnet.nl/news/2026/20260903-call.html)). The NGI Zero Commons Fund that the
strategy note named is closed: its thirteenth call, which closed on 1 June 2026, was its last
([NLnet](https://nlnet.nl/commonsfund/)). Restack is its successor for "operational internet commons" across
the stack. Open data is in scope, and applicants need no legal entity ([NLnet Restack](https://nlnet.nl/restack/),
[FAQ](https://nlnet.nl/restack/faq/)).

Check before submitting. nlnet.nl could not be opened from the environment this draft was written in, so the
following come from search results and NLnet's long-standing form, not from the live page:

1. **Grant size.** No per-project range is published in what could be read. Previous NLnet calls were €5,000–50,000
   for a first proposal. The amount below assumes that range still applies.
2. **Geography.** Restack is Horizon Europe cascade funding. Confirm that an applicant based in Nepal is eligible,
   and whether a European partner or fiscal host is needed.
3. **Form fields.** The headings below follow the NLnet proposal form as used in earlier calls. Match them to the
   live form.
4. **Generative AI disclosure.** NLnet asks applicants whether generative AI was used to write the proposal. This
   draft was prepared with an AI assistant, so either rewrite it in your own words or disclose it as the form asks.

---

## Proposal name

AIPolicyTracker: an open, verified dataset of AI law obligations and their enforcement

## Website / wiki

https://aipolicytracker.org · https://github.com/8h45k4r/aipolicytracker

## Abstract

*(NLnet's form limits this field; keep it to about 1,200 characters.)*

AI law is now operational. EU AI Act duties apply in stages to 2028, more than a hundred US state AI laws were enacted
by mid-2026, and governments across Asia, Africa and Latin America are adopting their own. The organisations that
must comply, and the researchers, journalists and regulators who follow them, depend on commercial trackers or
law-firm blogs that are closed, unversioned and thin outside the EU and US.

AIPolicyTracker is an open commons for this information. It covers 212 jurisdictions, 187 policy instruments,
117 obligations mapped to 26 controls and to ISO/IEC 42001 and the NIST AI RMF, and 1,663 recorded AI incidents.
Every record cites its official source and carries a verification state. Data is CC BY 4.0 and code is Apache-2.0.
It is served as YAML, JSON, CSV, NDJSON, an OpenAPI API, `llms.txt` and an MCP server.

This project makes the commons trustworthy enough to be cited and complete enough to be relied on:
- independent second-reviewer verification with published agreement statistics;
- open datasets for enforcement actions and the EU AI Act's implementing acts and standards;
- citable versioned releases (DOIs);
- broken-out obligations for under-covered jurisdictions.

## Have you been involved with projects or organisations relevant to this project before?

I founded and maintain AIPolicyTracker. I designed its data model (law → obligation → control → evidence), its
provenance and verification rules, the import pipeline that rebuilds the site from reviewable YAML on every deploy,
the public API and MCP server, and the generated template library. Every change goes through a written engineering
standard and five review gates (engineering, UX, documentation, compliance, security). The site has had two
security assessments (VAPT, September 2026).

*(Add your other relevant work, publications and affiliations here. Disclose the Certifyi association in this
answer, in the same words as the [funding page](https://aipolicytracker.org/funding).)*

## Requested amount

€40,000 *(adjust to the published range)*

## Explain what the requested budget will be used for

All work is published as it is done, under the existing licences (data CC BY 4.0, code Apache-2.0). The hourly
rate is a placeholder to set.

| # | Milestone | Deliverable anyone can check | Budget |
|---|---|---|---|
| 1 | **Independent verification** | A written double-coding protocol. Two paid reviewers independently re-verify a random 20% sample of records each quarter, against the official source. Cohen's kappa per field (status, dates, binding force, actors) published on `/methodology`. "Verified by A, checked by B" on each double-reviewed record. | €12,000 |
| 2 | **Enforcement dataset** | JSON Schema, importer, API endpoints, CSV and NDJSON exports and MCP tools for regulator enforcement actions on AI: regulator, respondent, legal basis, amount, outcome, appeal, source. Populated from official regulator publications. | €8,000 |
| 3 | **EU AI Act implementation and standards dataset** | Every delegated and implementing act, guideline, code of practice and harmonised-standard work item (CEN-CENELEC JTC 21, ISO/IEC SC 42), with due date, actual date and status. Metadata only; standard texts stay out because they are copyrighted. | €6,000 |
| 4 | **Citable releases** | Quarterly releases tagged in git and archived on Zenodo with a DOI each and a concept DOI; `CITATION.cff`; DOIs in the Dataset metadata and the "Cite this record" box; a data descriptor submitted as a preprint. | €4,000 |
| 5 | **Coverage outside the EU and US** | Obligations broken out, sourced and verified for at least ten binding instruments in under-covered jurisdictions (South and South-East Asia, Africa, Latin America). | €7,000 |
| 6 | **Documentation and reuse** | Reuser guide for the API, the bulk files and the MCP server; schema documentation; two worked examples (a researcher's analysis, an integration into an open-source GRC tool). | €3,000 |

Other funding: none. The maintainer pays for hosting. An optional paid tier for individual alert features may open;
it never restricts the data. Every funder above $1,000 a year is listed publicly at
https://aipolicytracker.org/funding.

## Compare your own project with existing or historical efforts

- **OECD.AI policy database.** Broad coverage of strategies, maintained with government input. It does not break
  laws into obligations, map them to controls and standards, or publish per-record provenance and verification.
- **IAPP Global AI Law and Policy Tracker.** A free, curated overview of about 25 jurisdictions. It is not openly
  licensed, has no API and keeps no record-level history.
- **Holistic AI Tracker, Fairly and similar.** Free trackers attached to commercial governance platforms. Their data
  is not openly licensed or downloadable in bulk.
- **CSET's AGORA.** An openly licensed, peer-reviewed dataset of AI legislation focused on the US. It is the closest
  model for rigour. AIPolicyTracker differs in global scope, obligation-level structure and the link from law to
  controls and evidence.
- **Law-firm and vendor crosswalks** (NIST AI RMF ↔ ISO/IEC 42001 ↔ EU AI Act). These are published as prose or
  PDFs, not as data with a source article and a confidence level on each row.

None of these publishes the chain from statute to duty to control to evidence as open, versioned, machine-readable
data with a named reviewer on each record. That chain is what this project hardens.

## What are significant technical challenges you expect to solve during the project?

1. **Measuring verification quality.** Legal fields are judgement calls: whether a duty is binding, who it applies
   to, which date governs. The protocol has to define what counts as agreement for each field type (dates, enums,
   free text) so that kappa means something, and the data model has to keep two independent decisions without one
   overwriting the other. `RecordVerification` already survives re-import; it needs a second decision and a
   disagreement-resolution record.
2. **Modelling enforcement across legal systems.** A fine, an order, a settlement and a court annulment are
   different events with different finality. The schema must represent appeals and reversals (for example a fine
   later annulled) without implying a final outcome.
3. **Tracking delegated acts and standards whose dates move.** The deadline engine already keeps "originally X, now
   Y" revisions. The same has to work for implementing acts and standards, so readers see which dates slipped.
4. **Stable identifiers across releases.** DOIs per release only help if record identifiers are stable and
   renamed records redirect. The import pipeline must guarantee that, and CI must test it.

## Describe the ecosystem of the project, and how you will engage with relevant actors and promote the outcomes

- **Users:** compliance teams, lawyers, researchers, journalists, civil society and regulators. They already reach
  the data through the website, a weekly digest, RSS, `.ics` calendars, embeddable widgets, the API and the MCP
  server.
- **Reviewers:** law and policy students and practitioners, recruited openly. The reviewer roster publishes each
  reviewer's interests.
- **Research and data commons:** releases on Zenodo; submission to the source lists of the OECD.AI observatory and
  the Stanford AI Index; Wikidata links for jurisdictions and instruments.
- **Open-source GRC and tooling:** a worked integration example and stable bulk files, so other commons projects can
  build on the data without asking permission.
- **Outreach:** each quarterly release is announced in the digest and in a short public changelog. A data
  descriptor is submitted for peer review.
