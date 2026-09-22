<?php

// Questions readers actually arrive with, answered on the page they land on, keyed by route
// name. Rendered visibly by <x-site.faq> and emitted as FAQPage by Seo::withFaq() — schema
// without the matching visible text on the page is against Google's own guidance, so the two
// always ship together.
//
// Answers may use the :license token, substituted by App\Support\Faq. Calling config() from
// inside a config file looks harmless and is not: once config:cache runs, the value resolves
// to null and the sentence ships with a hole in it.
//
// Answers state policy rather than counts. A sentence like "212 jurisdictions" goes stale
// silently; "every jurisdiction recorded here" does not, and the page itself already shows
// the live figure.
return [
    'policies.index' => [
        ['question' => 'What counts as an AI policy instrument here?', 'answer' => 'Anything a government or standards body has actually issued: acts, regulations, executive orders, rules, national strategies, frameworks, guidance, codes of practice, voluntary standards and open consultations. Each record states which of those it is, whether it binds anyone, and links the official source it was read from.'],
        ['question' => 'Are these records legally verified?', 'answer' => 'A record is marked verified only after a named reviewer has opened its official source and confirmed each field. Anything that has not been through that carries "pending review" on the record itself, and the data-health document reports the current totals. Nothing here is legal advice.'],
        ['question' => 'How current is the information?', 'answer' => 'Every record carries the date its source document was published and the date it was last checked. A record not re-checked within the freshness window set by the verification policy is flagged as stale rather than quietly presented as current.'],
        ['question' => 'Can I reuse this data?', 'answer' => 'Yes. The records are published under :license with attribution, and are available as CSV, newline-delimited JSON and a read-only REST API that needs no key. Cite the record URL, the date accessed and the official source alongside it.'],
    ],

    'obligations.index' => [
        ['question' => 'What is an obligation on this site?', 'answer' => 'A single practical requirement pulled out of an instrument and stated on its own: keep a risk management system, log incidents, document training data, provide human oversight, and so on. Each one cites the article or section it comes from so you can check it against the source.'],
        ['question' => 'Does a voluntary obligation have legal force?', 'answer' => 'No, and every obligation is labelled either a legal requirement or voluntary guidance. Voluntary items still matter in practice, because procurement questionnaires and auditors ask about them, but only the binding ones carry legal consequence.'],
        ['question' => 'How do I find the obligations that apply to my organisation?', 'answer' => 'Filter by jurisdiction, category, actor, sector or use case. The applicability check asks a short set of questions and returns the duties that may reach you. It is an educational screen, not a legal determination, and it says so.'],
        ['question' => 'Why do some instruments have no obligations listed?', 'answer' => 'Because nobody has broken them out yet. Most instruments are recorded at summary level first; obligations are added jurisdiction by jurisdiction. The coverage and open-gaps pages publish exactly what is missing rather than hiding it.'],
    ],

    'jurisdictions.index' => [
        ['question' => 'Does every jurisdiction listed here have an AI law?', 'answer' => 'No, and that is deliberate. Many are recorded with no AI-specific instrument at all, because "nothing binding yet" is a real and useful answer. Each jurisdiction states its regulatory status in words rather than implying a law exists.'],
        ['question' => 'What is the difference between a country, a region and a state here?', 'answer' => 'They are all jurisdictions, and each record says which kind it is. Supranational bodies such as the EU, the Council of Europe and ASEAN sit alongside countries, and sub-national records such as US states or Canadian provinces are kept separate from their federal government because their rules differ.'],
        ['question' => 'How do I compare two jurisdictions?', 'answer' => 'The comparison tool takes two to four jurisdictions and puts them side by side on regulatory status, binding legislation, high-risk and generative-AI rules, transparency, impact assessment, oversight, public-sector rules and dates, with every cell drawn from the same published records.'],
    ],

    'compare.index' => [
        ['question' => 'How many jurisdictions can I compare at once?', 'answer' => 'Between two and four. Beyond four the table stops being readable on a normal screen, which defeats the point of comparing at all.'],
        ['question' => 'What does an empty cell mean?', 'answer' => 'That nothing is recorded for that jurisdiction on that row — not that the jurisdiction has no rule. An empty cell is a gap in this platform, and the coverage and open-gaps pages track those in public.'],
        ['question' => 'Where do the comparison rows come from?', 'answer' => 'Every cell is derived from the same published, source-linked records the rest of the site uses. Nothing in a comparison is written specially for it, so a correction to a record corrects the comparison too.'],
    ],

    'frameworks.index' => [
        ['question' => 'What is a law-to-standard crosswalk?', 'answer' => 'A row-by-row mapping from a legal duty to the clause or function of a standard that asks for overlapping work. It tells you where evidence you already produce for an audit may be reusable against a statute, and where it is not.'],
        ['question' => 'Does ISO/IEC 42001 certification make an organisation EU AI Act compliant?', 'answer' => 'No. ISO/IEC 42001 is a management system standard and carries no legal force in any jurisdiction. Certification evidences a practice, not conformity with a statute. A crosswalk shows the overlap so evidence can be reused; it never transfers the legal obligation.'],
        ['question' => 'Which frameworks are covered?', 'answer' => 'ISO/IEC 42001 and the NIST AI Risk Management Framework carry most of the mappings, with a small number against ISO/IEC 27001 where an AI duty is really a security duty. Each framework page states how many duties are mapped to it and across how many jurisdictions.'],
        ['question' => 'How were the mappings decided?', 'answer' => 'They are editorial judgements recorded against one obligation at a time, each with a confidence level showing how direct the correspondence is. They cite clause numbers only and reproduce no text from any standard, which remains the publisher\'s copyright.'],
    ],

    'changes.index' => [
        ['question' => 'What counts as a change?', 'answer' => 'A dated development in the life of an instrument: adoption, entry into force, an amendment, a withdrawal, a gazette notice, the opening or closing of a consultation, or guidance being issued under it. Each entry says what changed and what it means in practice, and links the official source.'],
        ['question' => 'How far back does the change log go?', 'answer' => 'To 2017, which is where the earliest recorded national AI strategies sit. Entries are grouped by year, and the whole log is available as an RSS feed and through the API.'],
        ['question' => 'Will I be told when something changes?', 'answer' => 'Follow a jurisdiction or an instrument and you will be alerted when a change is recorded against it. There is also a digest you can subscribe to. Both are free.'],
    ],

    'calendar' => [
        ['question' => 'What is on this calendar?', 'answer' => 'Compliance dates recorded against instruments and obligations: dates an instrument applies from, staged application deadlines, review points and consultation closing dates. Each carries its own source reference.'],
        ['question' => 'Why are some dates approximate?', 'answer' => 'Because the source says so. A date is recorded with its precision — exact day, month, year, or to be determined — rather than inventing a specific day the official text does not give. Nothing is estimated.'],
        ['question' => 'Can I get these dates in my own calendar?', 'answer' => 'Yes. The page publishes an iCalendar feed you can subscribe to in any calendar application, and there is a per-jurisdiction feed if you only want one.'],
    ],

    'risk.risks' => [
        ['question' => 'Where do these risk entries come from?', 'answer' => 'The MIT AI Risk Repository, which extracts risks from dozens of published frameworks, taxonomies and papers and codes each one by domain and subdomain, and by a causal taxonomy of entity, intent and timing. This is a browseable copy, attributed and openly licensed, not original research.'],
        ['question' => 'What do entity, intent and timing mean?', 'answer' => 'They are the causal coding. Entity is whether a human or the AI system is the cause; intent is whether the harm was intentional or not; timing is whether it arises before or after deployment. Together they let you separate misuse from malfunction.'],
        ['question' => 'Can I export the results?', 'answer' => 'Yes, any filtered set exports as CSV or JSON, and every export carries the upstream source, licence and citation with it, because attribution is a condition of the licence rather than a courtesy.'],
        ['question' => 'What is the MIT AI Risk Repository?', 'answer' => 'A living database of AI risks extracted from published frameworks, taxonomies and papers, classified by a causal taxonomy of entity, intent and timing and by a domain taxonomy of seven domains and 24 subdomains. It is published by the MIT AI Risk Initiative under CC BY 4.0.'],
    ],

    'risk.incidents.browse' => [
        ['question' => 'What is recorded in each incident?', 'answer' => 'What happened, when, who was involved as developer or deployer, who was harmed, the sectors and countries affected, and the domain and subdomain the harm falls under. Incidents are mirrored from the AI Incident Database and each links back to its source record.'],
        ['question' => 'Does an incident mean wrongdoing was proven?', 'answer' => 'No. These are reported incidents, not findings of liability. Entities named in a report are named as reported, allegations are marked as alleged, and nothing here is a legal determination.'],
        ['question' => 'How current is the incident data?', 'answer' => 'It syncs directly from the AI Incident Database several times a day, so a new incident usually appears within hours. Each record carries the date it was last synced, and the snapshot date is shown on the page.'],
        ['question' => 'Does this page include the full incident reports?', 'answer' => 'No. The report texts are outside the licence this data is shared under, so only the metadata and the classifications appear here. Every row links to the original incident record, which carries the reports themselves.'],
    ],

    'risk.frameworks' => [
        ['question' => 'What are these frameworks?', 'answer' => 'The published documents the MIT AI Risk Repository synthesised: academic papers, taxonomies, government and industry frameworks. Each is listed with the number of risk entries extracted from it, so you can see which sources the taxonomy leans on.'],
        ['question' => 'Are these the same as the compliance frameworks on this site?', 'answer' => 'No, and the distinction matters. These are sources of risk research. The crosswalk pages deal with ISO/IEC 42001 and the NIST AI RMF, which are control frameworks an organisation is audited against.'],
    ],

    'tools.applicability' => [
        ['question' => 'Is this a legal assessment?', 'answer' => 'No. It is an educational screen that matches what you tell it against recorded obligations and shows which ones may reach you. It cannot see your systems, your contracts or your markets, and it is not advice.'],
        ['question' => 'What happens to my answers?', 'answer' => 'Nothing is required to run the screen. If you sign in you can save a profile so the screen can be re-run when records change; otherwise the answers are not stored.'],
        ['question' => 'Why does it say "may apply" rather than "applies"?', 'answer' => 'Because applicability turns on facts the screen does not have, and because most records here have not yet been confirmed by a named reviewer. Overstating certainty would be the most harmful thing this tool could do.'],
    ],

    'guides.index' => [
        ['question' => 'What is the difference between a guide and a policy record?', 'answer' => 'A record states what an instrument says, field by field, with its official source. A guide is written explanation on top of those records: what the rules mean together, in what order to deal with them, and what evidence to keep. Every guide links the records it draws on so you can check it.'],
        ['question' => 'Are the templates really free?', 'answer' => 'Yes. Every template downloads without payment; you give an email address so the file can be sent and so you can be told when it is revised. There is no paid tier on this platform.'],
        ['question' => 'Can I use these templates inside my company?', 'answer' => 'Yes, that is what they are for. They are provided under an open licence for internal use. They are informational resources, not legal advice, and completing one does not make an organisation compliant with any law or standard.'],
        ['question' => 'Which frameworks do the guides cover?', 'answer' => 'Mostly the EU AI Act, ISO/IEC 42001 and the NIST AI Risk Management Framework, because those are what most organisations are asked about. The crosswalk pages carry the clause-level mappings; the guides explain how to use them together.'],
    ],

    'open-data' => [
        ['question' => 'Do I need an API key?', 'answer' => 'No. The REST API is read-only, public and rate limited, and needs no registration. The same records are available as bulk CSV and newline-delimited JSON, and every record is also served as a single Markdown context file with its provenance.'],
        ['question' => 'What licence applies, and what must I include?', 'answer' => 'The records are published under :license. Attribution is a condition: cite the record URL and the date accessed, and cite the linked official source alongside it. Mirrored third-party datasets carry their own licence and citation in every response and export.'],
        ['question' => 'How should an assistant or agent use this?', 'answer' => 'Start at llms.txt, which lists the canonical pages, the exports, the schemas and the data-health document. Read the health document before quoting any record as settled, because it reports how many records are unverified or past their re-check date.'],
    ],
];
