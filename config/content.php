<?php

/*
|--------------------------------------------------------------------------
| Editorial content for landing pages, guides and curated comparisons
|--------------------------------------------------------------------------
|
| Editorial text is original and deliberately caveated. Every page pulls live
| records (policies, obligations, deadlines, changes) at render time, so the
| editorial part is context and orientation, not a substitute for the data.
| Keep claims here general; put dated, sourced facts in data/ records instead.
|
*/

return [
    // Policy milestones overlaid on the AI-incident timeline (date, label, policy slug or null).
    'risk_milestones' => [
        ['2019-05-22', 'OECD AI Principles', 'oecd-recommendation-on-artificial-intelligence'],
        ['2021-04-21', 'EU AI Act proposed', 'eu-ai-act'],
        ['2021-11-23', 'UNESCO AI ethics recommendation', 'unesco-recommendation-on-the-ethics-of-ai'],
        ['2023-01-26', 'NIST AI RMF 1.0', 'us-nist-ai-rmf'],
        ['2023-11-01', 'Bletchley Declaration', 'bletchley-declaration-ai-safety-summit-2023'],
        ['2024-05-17', 'Council of Europe AI Convention', 'council-of-europe-framework-convention-on-ai'],
        ['2024-08-01', 'EU AI Act in force', 'eu-ai-act'],
        ['2025-02-02', 'EU prohibited practices apply', 'eu-ai-act'],
        ['2025-09-25', 'Italy Law 132/2025', 'italy-law-132-2025-ai'],
        ['2026-01-01', 'Texas TRAIGA in force', 'us-texas-responsible-ai-governance-act-traiga'],
        ['2026-08-02', 'EU high-risk obligations apply', 'eu-ai-act'],
    ],

    /*
    | Audience pages: one instrument-independent cut of the corpus per role, sector or
    | use case, generated from the taxonomy terms every obligation already carries. The
    | editorial part is orientation; the duties, controls, evidence and dates are live.
    | A page is indexable only when the cut returns at least five duties.
    */
    'audiences' => [
        'providers' => [
            'taxonomy' => 'actor', 'term' => 'provider',
            'h1' => 'AI regulation for providers and developers',
            'title' => 'AI rules for providers and developers: duties, controls, evidence',
            'description' => 'Every recorded duty that binds an organisation that develops an AI system or places it on a market, across jurisdictions, with the controls that meet it and the evidence a reviewer expects.',
            'answer' => 'A provider is the organisation that develops an AI system, or has one developed, and puts it on a market or into service under its own name. Most binding AI law lands here first: risk management, data governance, technical documentation, logging, accuracy and security, conformity assessment and post-market monitoring are provider duties before they are anyone else\'s.',
            'sections' => [
                ['heading' => 'What is different about being a provider?', 'body' => 'The provider carries the design-time duties and the paper trail. A deployer can rely on the provider\'s instructions and documentation; the provider has to have produced them. That makes the technical file, the risk management record and the quality management system the centre of gravity, and it makes a change to the system a regulatory event rather than an engineering one.'],
                ['heading' => 'Where the same control does double duty', 'body' => 'A risk assessment written once to the structure a management standard expects satisfies or supports the risk-management duty in several jurisdictions at once. The controls listed below are counted by the number of duties each one serves, so the ones worth building first are at the top.'],
            ],
            'faq' => [
                ['question' => 'Am I a provider if I fine-tune or rebrand someone else\'s model?', 'answer' => 'Often yes. Several instruments treat a substantial modification, or putting a system on the market under your own name, as becoming the provider. Check the definition in the instrument that applies to you; the duty pages cite the article.'],
                ['question' => 'Does this page tell me which duties apply to my product?', 'answer' => 'No. It lists every recorded duty that binds providers anywhere. Use the applicability check for a screening of your own situation, then open the official source.'],
            ],
        ],
        'deployers' => [
            'taxonomy' => 'actor', 'term' => 'deployer',
            'h1' => 'AI regulation for deployers and user organisations',
            'title' => 'AI rules for deployers: duties, controls and evidence by jurisdiction',
            'description' => 'Every recorded duty on organisations that use AI systems under their own authority, with the controls that meet it, the evidence to keep and the dates that apply.',
            'answer' => 'A deployer uses an AI system under its own authority in the course of its business. Deployer duties are lighter than provider duties but they are the ones most organisations actually have: follow the instructions, keep humans in a position to oversee, retain logs, tell people when AI is used in decisions about them, and in some jurisdictions assess the impact before use.',
            'sections' => [
                ['heading' => 'What deployers get wrong', 'body' => 'Treating a purchased system as the vendor\'s problem. The vendor\'s documentation is an input to the deployer\'s own oversight, notice and logging duties, not a substitute for them, and a deployer who materially changes a system can become its provider.'],
                ['heading' => 'The short list', 'body' => 'An inventory of the systems in use, a named owner for each, the vendor documentation on file, a human oversight arrangement that can actually intervene, notices to affected people, and logs kept for the period the instrument names. The controls below cover that list and say which duty each one serves.'],
            ],
            'faq' => [
                ['question' => 'Do deployer duties apply to internal tools?', 'answer' => 'Usually yes when the tool makes or supports decisions about people, such as hiring, credit or access to services. Purely personal, non-professional use is typically excluded. The duty pages cite the scope article.'],
                ['question' => 'What evidence should a deployer keep?', 'answer' => 'The system register entry, the vendor\'s instructions and documentation, the oversight arrangement, the notices shown to people, the logs, and any impact assessment the instrument requires. The controls below list each item.'],
            ],
        ],
        'general-purpose-ai-providers' => [
            'taxonomy' => 'actor', 'term' => 'gpai_provider',
            'h1' => 'AI regulation for general-purpose and foundation model providers',
            'title' => 'Rules for foundation model providers: duties, controls, evidence',
            'description' => 'Recorded duties on providers of general-purpose AI models, including systemic-risk tiers, with the controls, documentation and evidence they call for.',
            'answer' => 'Providers of general-purpose AI models face a distinct set of duties: technical documentation for downstream integrators, a copyright policy and training-data summary, and for the most capable models, evaluation, adversarial testing, incident reporting and cybersecurity. These duties sit with the model provider even when the model is used inside someone else\'s system.',
            'sections' => [
                ['heading' => 'Model duties versus system duties', 'body' => 'A model is not a system. The model provider documents the model and its training; the system provider who builds on it documents the system. Both chains of documentation have to exist and reference each other, which is why downstream documentation is a control of its own below.'],
                ['heading' => 'Systemic-risk tiers', 'body' => 'Where an instrument defines a higher tier by capability or compute, the duties step up to evaluation, red-teaming, incident reporting and security. The controls for those duties are listed with the risk areas they address, so the evaluation report and the incident record can be planned together.'],
            ],
            'faq' => [
                ['question' => 'Are open-weight models exempt?', 'answer' => 'Partly, in some instruments: openly released models can be relieved of some documentation duties but not of copyright or, where it applies, systemic-risk duties. Check the exemption clause cited on the duty page.'],
                ['question' => 'Which evidence matters most?', 'answer' => 'The model documentation for downstream users, the training-data summary and copyright policy, and for higher tiers the evaluation and adversarial-testing reports and the incident log.'],
            ],
        ],
        'public-sector' => [
            'taxonomy' => 'actor', 'term' => 'public_authority',
            'h1' => 'AI regulation for public bodies and government',
            'title' => 'AI rules for public bodies: duties, controls, procurement evidence',
            'description' => 'Recorded duties on public authorities that procure, deploy or oversee AI, across jurisdictions, with the controls that meet them and the transparency evidence citizens and auditors expect.',
            'answer' => 'Public bodies are deployers with extra duties: impact assessment before use, public registers of systems, procurement rules that pass duties to suppliers, and explanations to people affected by automated decisions. Several jurisdictions regulate government use of AI before, or instead of, private use.',
            'sections' => [
                ['heading' => 'Procurement as the control point', 'body' => 'For a public body most AI arrives through a contract. The contractual allocation of duties, the supplier\'s documentation and the right to audit are where governance is won or lost, which is why contractual controls carry as much weight below as technical ones.'],
                ['heading' => 'Transparency to the public', 'body' => 'Registers, notices and explanations are duties in their own right, and they are the evidence an auditor or a court will ask for first.'],
            ],
            'faq' => [
                ['question' => 'Do government AI rules apply to contractors?', 'answer' => 'Frequently, through the contract or through procurement rules that require suppliers to meet the same standard. The duty pages say which instrument and article.'],
                ['question' => 'What should a public body publish?', 'answer' => 'At minimum an inventory or register entry per system and a notice where a decision about a person is automated; several instruments require an impact assessment to be published or available on request.'],
            ],
        ],
        'hr-and-recruitment' => [
            'taxonomy' => 'use_case', 'term' => 'hiring_and_hr',
            'h1' => 'AI in hiring and employment: the rules that apply',
            'title' => 'AI in hiring and HR: duties, controls and evidence by jurisdiction',
            'description' => 'Every recorded duty on AI used in recruitment, screening, promotion and workforce management, across jurisdictions, with the controls that meet it and the evidence to keep.',
            'answer' => 'Hiring and employment is the use case regulated most often and most specifically: bias audits, notices to candidates, impact assessments, human review of decisions and record keeping appear in city, state, national and supranational rules. An organisation that uses AI anywhere in the employee lifecycle is a deployer in most of them.',
            'sections' => [
                ['heading' => 'What the rules have in common', 'body' => 'Tell the candidate, test for disparate impact, keep a human able to change the outcome, and keep the records that show you did. The wording differs; the controls do not, which is why one bias-testing control below serves duties in several jurisdictions.'],
                ['heading' => 'Where they differ', 'body' => 'Who must run the audit, how often, whether it is published, and whether a candidate can opt out or request an alternative process. Those details are on each duty page with the article cited.'],
            ],
            'faq' => [
                ['question' => 'Does using an off-the-shelf screening tool make my company responsible?', 'answer' => 'In most instruments yes: the employer is the deployer and carries the notice, oversight and record-keeping duties, whatever the vendor promised. Some rules place the audit duty on the vendor, others on the employer.'],
                ['question' => 'What evidence do these duties produce?', 'answer' => 'A bias or impact assessment, the notice text shown to candidates, the human-review procedure, the system register entry and the decision logs. The controls below list each one.'],
            ],
        ],
        'healthcare' => [
            'taxonomy' => 'sector', 'term' => 'healthcare',
            'h1' => 'AI regulation in healthcare and life sciences',
            'title' => 'AI rules for healthcare: duties, controls and evidence by jurisdiction',
            'description' => 'Recorded AI duties that reach hospitals, clinicians, device makers and health data holders, across jurisdictions, with the controls that meet them and the clinical and data-governance evidence they call for.',
            'answer' => 'Healthcare AI sits under two regimes at once: medical-device and safety law for the product, and AI and data-protection law for the decision. A diagnostic or triage system is usually high-risk under AI law and a regulated device under health law, so its technical file, clinical evaluation and post-market surveillance have to satisfy both.',
            'sections' => [
                ['heading' => 'One technical file, two regulators', 'body' => 'The documentation duties overlap heavily. The controls below are counted by the duties they serve so that the technical documentation and post-market monitoring work can be planned once and cited twice.'],
                ['heading' => 'Data governance is the hard part', 'body' => 'Training data provenance, representativeness across patient groups and lawful basis for health data are where healthcare AI programmes stall. The dataset documentation and privacy controls carry those duties.'],
            ],
            'faq' => [
                ['question' => 'Is clinical decision support high-risk?', 'answer' => 'Under several instruments, systems that inform or take decisions about access to health services or that act as safety components of medical devices are high-risk. Check the annex or schedule the duty page cites.'],
                ['question' => 'Which evidence overlaps with device regulation?', 'answer' => 'The technical file, clinical or performance evaluation, risk management file, and post-market surveillance records. Keep one set that references both regimes.'],
            ],
        ],
        'financial-services' => [
            'taxonomy' => 'sector', 'term' => 'financial_services',
            'h1' => 'AI regulation in financial services, credit and insurance',
            'title' => 'AI rules for financial services: duties, controls, model evidence',
            'description' => 'Recorded AI duties that reach lenders, insurers, payment firms and their vendors, with the controls that meet them and the model-governance evidence supervisors expect.',
            'answer' => 'Financial firms already run model risk management, so most AI duties land on existing controls: model inventories, validation, monitoring, explanations of adverse decisions and vendor oversight. What changes is scope and evidence: credit scoring and insurance pricing are high-risk uses in several instruments, and the explanation given to a consumer is a duty rather than a courtesy.',
            'sections' => [
                ['heading' => 'Extend model risk management, do not duplicate it', 'body' => 'The controls below map onto validation, monitoring and vendor-management practice a supervised firm has already. The value is in the mapping: knowing which duty a validation report already evidences, and which one still needs a fairness test or a consumer notice.'],
                ['heading' => 'Explanations and adverse action', 'body' => 'Several regimes require a specific, reviewable reason for an automated adverse decision. That is a transparency control with a data-governance dependency: the features have to be explainable to be explained.'],
            ],
            'faq' => [
                ['question' => 'Is credit scoring high-risk?', 'answer' => 'Under the instruments that use a risk tiering, creditworthiness assessment of natural persons is typically listed as high-risk, with fraud detection often excluded. The duty pages cite the entry.'],
                ['question' => 'What evidence do supervisors ask for?', 'answer' => 'The model inventory entry, the validation report, monitoring records, the fairness assessment, the consumer notice and the vendor assessment. The controls below list them.'],
            ],
        ],
        'education' => [
            'taxonomy' => 'sector', 'term' => 'education',
            'h1' => 'AI regulation in education and training',
            'title' => 'AI rules for education: duties, controls and evidence',
            'description' => 'Recorded AI duties that reach schools, universities, edtech vendors and examination bodies, with the controls that meet them and the evidence to keep.',
            'answer' => 'Education is named as a high-risk area in several instruments: admissions, assessment, proctoring and the allocation of learning are decisions about people, often minors. Institutions are deployers; edtech vendors are providers; the duties on both are listed here with the controls that meet them.',
            'sections' => [
                ['heading' => 'Minors raise the bar', 'body' => 'Data-protection law and AI law both treat children as needing stronger safeguards. Notices have to be understood by the people receiving them, oversight has to be real, and emotion recognition and some biometric uses are prohibited outright in some jurisdictions.'],
                ['heading' => 'Procurement again', 'body' => 'Most educational AI is bought, so the contract and the vendor\'s documentation are the institution\'s first evidence. The contractual and vendor controls below carry that.'],
            ],
            'faq' => [
                ['question' => 'Is automated proctoring regulated?', 'answer' => 'Under several instruments, monitoring students during tests is a high-risk use, and emotion recognition in education is prohibited in at least one. Check the duty pages for the article.'],
                ['question' => 'What should an institution keep?', 'answer' => 'A register of the systems in use, the vendor documentation, the notices given to students and parents, the human-review procedure for assessments, and the impact assessment where required.'],
            ],
        ],
        'generative-ai' => [
            'taxonomy' => 'use_case', 'term' => 'generative_ai',
            'h1' => 'Generative AI and foundation models: the rules that apply',
            'title' => 'Generative AI rules: duties, controls and evidence by jurisdiction',
            'description' => 'Recorded duties on generative AI systems and the models behind them, from content labelling and disclosure to training-data transparency and safety testing, with the controls that meet them.',
            'answer' => 'Generative AI attracts three families of duty: transparency (tell people they are interacting with AI, label synthetic content), model governance (document the model, publish a training-data summary, respect copyright) and safety (evaluate, red-team, report incidents, secure the system). Several jurisdictions have rules specific to synthetic media even where they have no general AI law.',
            'sections' => [
                ['heading' => 'Labelling is a technical control', 'body' => 'A duty to mark synthetic content is met by provenance and watermarking measures, a disclosure notice and a procedure for exceptions. The synthetic-content control below serves that duty wherever it appears.'],
                ['heading' => 'Testing is the new documentation', 'body' => 'For higher-capability models the evidence that matters is the evaluation and adversarial-testing record, tied to the risk areas it covers. The controls list those risk areas with the incidents recorded against them.'],
            ],
            'faq' => [
                ['question' => 'Do chatbot disclosure duties apply to internal tools?', 'answer' => 'Usually they apply where a natural person interacts with the system without it being obvious; an internal tool used by staff who know it is AI is often out of scope. The duty page cites the wording.'],
                ['question' => 'Which evidence do these duties call for?', 'answer' => 'The disclosure notice, the content-labelling mechanism, the model documentation and training-data summary, the copyright policy, and the evaluation and red-team reports.'],
            ],
        ],
        'biometrics' => [
            'taxonomy' => 'use_case', 'term' => 'biometrics',
            'h1' => 'Biometric and facial recognition AI: the rules that apply',
            'title' => 'Biometric AI rules: prohibitions, duties, controls and evidence',
            'description' => 'Recorded duties and prohibitions on biometric identification, categorisation and emotion recognition, across jurisdictions, with the controls that meet them.',
            'answer' => 'Biometric AI is where prohibitions live. Real-time remote identification in public spaces, biometric categorisation by sensitive traits and emotion recognition in workplaces and schools are banned or tightly conditioned in several jurisdictions; where a biometric use is allowed it is almost always high-risk, with the full set of provider and deployer duties on top of data-protection rules for biometric data.',
            'sections' => [
                ['heading' => 'Screen for prohibited uses first', 'body' => 'The prohibited-use gate is a control of its own below: an inventory question answered before any risk assessment, because a prohibited use has no compliant configuration.'],
                ['heading' => 'Then the high-risk set', 'body' => 'Accuracy across demographic groups, human verification of matches, logging and a data-protection impact assessment are the duties that follow. The controls are listed with the incident history of the risk areas they address.'],
            ],
            'faq' => [
                ['question' => 'Is verification (one-to-one) treated like identification (one-to-many)?', 'answer' => 'Generally no: one-to-one verification such as unlocking a device is often excluded from the strictest categories, while one-to-many identification is the prohibited or high-risk case. The duty page cites the definition.'],
                ['question' => 'What evidence does a permitted biometric use need?', 'answer' => 'The prohibited-use screening record, the impact assessment, accuracy testing by group, the human verification procedure, the notice, and the logs.'],
            ],
        ],
    ],

    'landings' => [
        'eu-ai-act' => [
            'h1' => 'EU AI Act: what it is, who it covers and what to do',
            'title' => 'EU AI Act explained: scope, deadlines and what to do',
            'description' => 'Plain-language explainer of the EU AI Act with live records: who it covers, phased application dates, key obligations, latest changes and official sources.',
            'jurisdictions' => ['eu'],
            'policy' => 'eu-ai-act',
            'answer' => 'The EU AI Act (Regulation (EU) 2024/1689) is the European Union\'s binding, risk-based law for AI systems and general-purpose AI models. It entered into force on 1 August 2024 and applies in phases: prohibited practices and AI-literacy duties from 2 February 2025, general-purpose AI model duties from 2 August 2025, and most remaining obligations, including high-risk requirements, from 2 August 2026 under the adopted text, with product-embedded high-risk AI following in 2027. Amendments proposed in late 2025 may shift some high-risk dates; check the official sources linked on this page.',
            'sections' => [
                ['heading' => 'Who does the EU AI Act apply to?', 'body' => 'It applies by role rather than by company size or location: providers who place AI systems or models on the EU market, deployers established in the EU that use AI under their authority, importers and distributors, and non-EU providers and deployers whose system output is used in the EU. Public bodies are deployers with extra duties. The same organisation can be a provider for one system and a deployer for another, so map roles system by system.'],
                ['heading' => 'How does the risk-based approach work?', 'body' => 'The Act bans a short list of practices, treats a defined set of uses as high-risk (safety components of regulated products and the Annex III areas such as employment, education, credit, essential services, law enforcement and migration), adds transparency duties for chatbots, emotion recognition and synthetic content, and leaves most other AI under general law. General-purpose AI models have their own chapter, with heavier duties for models with systemic risk.'],
                ['heading' => 'What should a team do first?', 'body' => 'Build an inventory, assign roles, screen for prohibited practices (already applicable), classify against Annex I and Annex III, and then plan the high-risk workstreams: risk management, data governance, technical documentation, logging, transparency to deployers, human oversight, accuracy and security, quality management, conformity assessment and registration, post-market monitoring and incident reporting. Deployers focus on instructions, oversight, logs, notices and, where required, fundamental-rights impact assessments.'],
            ],
            'faq' => [
                ['question' => 'Is the EU AI Act in force?', 'answer' => 'Yes. It entered into force on 1 August 2024. Its obligations apply in phases from February 2025 to August 2027, subject to any adopted amendments.'],
                ['question' => 'Does the EU AI Act apply to startups outside the EU?', 'answer' => 'It can, if you place an AI system or general-purpose model on the EU market or your system\'s output is used in the EU. Check Article 2 and your role for each product.'],
            ],
        ],
        'ai-regulation-india' => [
            'h1' => 'AI regulation in India',
            'title' => 'AI regulation in India: DPDP Act, IT Rules and governance guidelines',
            'description' => 'How AI is governed in India today: the Digital Personal Data Protection Act 2023 and 2025 Rules, IT Act and intermediary rules, IndiaAI Mission and the 2025 AI Governance Guidelines, with official sources.',
            'jurisdictions' => ['india'],
            'policy' => 'india-dpdp-act',
            'answer' => 'India has no dedicated AI statute. AI is governed through the Digital Personal Data Protection Act 2023 and its 2025 Rules, the Information Technology Act 2000 and intermediary rules, sector regulators, and non-binding policy such as the IndiaAI Mission and the India AI Governance Guidelines released by MeitY in November 2025. The stated direction favours enabling innovation with principle-based governance and enforcement through existing law.',
            'sections' => [
                ['heading' => 'What is binding today?', 'body' => 'The DPDP Act sets consent, notice, security, breach-notification and rights obligations for digital personal data, which covers most AI training and inference data about Indian residents. The IT Act and the 2021 intermediary rules apply to platforms hosting AI-generated or synthetic content. Sector regulators such as the RBI, SEBI and IRDAI regulate automated decisions in lending, markets and insurance under their own rules.'],
                ['heading' => 'What is guidance?', 'body' => 'The India AI Governance Guidelines describe principles, an institutional framework and voluntary commitments; NITI Aayog\'s Responsible AI papers and MeitY advisories add expectations without creating statutory duties. Treat them as signals of regulatory direction and of what a future law might contain.'],
                ['heading' => 'Practical steps for teams operating in India', 'body' => 'Map personal data flows into your AI systems and establish a lawful basis; prepare breach and rights processes for the DPDP commencement dates; if you run a platform, review synthetic-media duties; and align governance with the 2025 guidelines to be ready for voluntary commitments and sector expectations.'],
            ],
            'faq' => [
                ['question' => 'Does India have an AI Act?', 'answer' => 'No. India relies on the DPDP Act, the IT Act and sector regulation, plus non-binding AI governance guidelines.'],
            ],
        ],
        'ai-policy-nepal' => [
            'h1' => 'AI policy in Nepal',
            'title' => 'AI policy in Nepal: National AI Policy, privacy law and what applies',
            'description' => 'Nepal\'s AI governance landscape: the 2025 National AI Policy, the 2024 AI concept paper, the Individual Privacy Act 2075 and digital governance rules, with source certainty clearly marked.',
            'jurisdictions' => ['nepal'],
            'policy' => 'nepal-national-ai-policy',
            'answer' => 'Nepal has a National AI Policy approved by the Council of Ministers in 2025 and a 2024 concept paper from the Ministry of Communication and Information Technology, but no AI-specific legislation. Binding obligations for AI come from existing law, chiefly the Individual Privacy Act 2075 (2018), the Electronic Transactions Act 2063 (2008) and sector rules. Official English texts and stable document links are limited, so every record for Nepal on this site is marked with its source certainty and awaits human verification.',
            'sections' => [
                ['heading' => 'What the National AI Policy does', 'body' => 'It sets government direction on AI infrastructure, skills, ethics, data governance and institutions, and signals that implementing legislation and standards will follow. It does not itself impose obligations on private organisations.'],
                ['heading' => 'What applies to AI systems now', 'body' => 'The Individual Privacy Act requires consent for collecting and using personal information and restricts disclosure; the Electronic Transactions Act governs electronic records and cyber offences; regulators such as Nepal Rastra Bank and the Nepal Telecommunications Authority set sector rules. AI systems that process personal data of people in Nepal must respect these limits.'],
                ['heading' => 'How to use this page', 'body' => 'Use the records here to orient your planning, then verify every claim against MoCIT and Nepal Law Commission publications. Dates in official documents use the Bikram Sambat calendar; conversions here are approximate until verified.'],
            ],
            'faq' => [
                ['question' => 'Is there an AI law in Nepal?', 'answer' => 'No. Nepal has a national AI policy (2025) and applies existing privacy, electronic-transaction and cyber-security law to AI.'],
            ],
        ],
        'ai-governance-singapore' => [
            'h1' => 'AI governance in Singapore',
            'title' => 'AI governance in Singapore: Model Framework, AI Verify and PDPC guidance',
            'description' => 'Singapore\'s voluntary-framework approach to AI: the Model AI Governance Framework, the generative-AI framework, AI Verify, PDPC advisory guidelines and the National AI Strategy 2.0, with official sources.',
            'jurisdictions' => ['singapore'],
            'policy' => 'singapore-model-ai-governance-framework',
            'answer' => 'Singapore governs AI through voluntary frameworks, testing tools and sector guidance rather than an AI statute. The Model AI Governance Framework (2020) and its 2024 generative-AI companion describe expected practices; AI Verify offers a testing framework and toolkit; the PDPC\'s 2024 advisory guidelines explain how the Personal Data Protection Act applies to AI recommendation and decision systems. Binding duties come from the PDPA and sector regulation.',
            'sections' => [
                ['heading' => 'Why the frameworks matter even though they are voluntary', 'body' => 'Regulators, government buyers and enterprise customers in Singapore reference the Model Framework and AI Verify as the expected baseline, and the PDPC uses the frameworks to interpret binding PDPA obligations. Aligning with them is the practical route to demonstrating responsible AI in the market.'],
                ['heading' => 'What is binding', 'body' => 'The PDPA governs personal data in AI, including the consent obligation and its business-improvement and research exceptions, and the notification obligation. Financial institutions follow MAS requirements; online platforms follow the online-safety framework.'],
            ],
            'faq' => [
                ['question' => 'Does Singapore have an AI Act?', 'answer' => 'No. Singapore uses voluntary frameworks and existing law, with the PDPA as the main binding constraint for AI using personal data.'],
            ],
        ],
        'ai-regulation-australia' => [
            'h1' => 'AI regulation in Australia',
            'title' => 'AI regulation in Australia: voluntary standard, guardrails and existing law',
            'description' => 'Australia\'s AI regulatory position: the Voluntary AI Safety Standard, the 2024 mandatory-guardrails proposal, the government AI policy and Privacy Act changes, with official sources and status.',
            'jurisdictions' => ['australia'],
            'policy' => 'australia-voluntary-ai-safety-standard',
            'answer' => 'Australia has no AI-specific statute. The Voluntary AI Safety Standard (September 2024) sets ten guardrails aligned with ISO/IEC 42001 and the NIST AI RMF, a proposals paper consulted on mandatory guardrails for high-risk AI, and the December 2025 National AI Plan signalled reliance on strengthening existing laws rather than a standalone AI Act. Federal agencies follow a binding policy for responsible use of AI in government, and the Privacy Act, consumer law and anti-discrimination law apply to AI.',
            'sections' => [
                ['heading' => 'What to build against', 'body' => 'The ten voluntary guardrails are the practical baseline: accountability, risk management, data governance, testing and monitoring, human control, user transparency, contestability, supply-chain transparency, record keeping and stakeholder engagement. They were designed to match the proposed mandatory guardrails, so adopting them prepares you for whichever regulatory option is chosen.'],
                ['heading' => 'What is binding', 'body' => 'The Privacy Act 1988 (with 2024 amendments introducing automated-decision transparency provisions that commence later), the Australian Consumer Law, anti-discrimination Acts and sector regulators. Government suppliers must support agency obligations under the DTA policy.'],
            ],
            'faq' => [
                ['question' => 'Will Australia pass an AI Act?', 'answer' => 'As of the last check, the government indicated it would strengthen existing laws rather than introduce a standalone AI Act. Check the official sources on this page for the current position.'],
            ],
        ],
        'ai-regulation-uk' => [
            'h1' => 'AI regulation in the UK',
            'title' => 'AI regulation in the UK: principles, regulators and what is binding',
            'description' => 'The UK\'s regulator-led approach to AI: the 2023 white paper principles, the 2024 government response, ICO guidance, the AI Security Institute and the laws that already bind AI, with official sources.',
            'jurisdictions' => ['uk'],
            'policy' => 'uk-ai-regulation-white-paper',
            'answer' => 'The United Kingdom has not enacted a cross-sector AI law. Existing regulators apply five principles (safety, transparency, fairness, accountability, contestability) within their remits, as set out in the 2023 white paper and confirmed in February 2024. Binding obligations therefore come from UK GDPR and the Data Protection Act 2018, the Equality Act 2010, consumer and product law, the Online Safety Act 2023 and sector rules. The government has signalled future legislation for the most powerful models, but no bill had been introduced at the last check.',
            'sections' => [
                ['heading' => 'Which regulator matters for you', 'body' => 'The ICO for any AI using personal data, the FCA for financial services, the CMA for competition and consumer issues including foundation models, Ofcom for online services, the MHRA for medical devices and the EHRC for discrimination. Each has published AI guidance or strategies in response to the white paper.'],
                ['heading' => 'UK versus EU', 'body' => 'UK-based companies selling into the EU should assume the EU AI Act sets the higher bar and design once for both. The UK framework adds few AI-specific duties beyond data protection, but regulators can enforce existing law against AI harms today.'],
            ],
            'faq' => [
                ['question' => 'Is there a UK AI Act?', 'answer' => 'No. The UK uses a principles-based, regulator-led framework with binding duties coming from existing laws.'],
            ],
        ],
        'ai-regulation-usa' => [
            'h1' => 'AI regulation in the United States',
            'title' => 'AI regulation in the USA: federal policy, NIST AI RMF and state laws',
            'description' => 'How AI is regulated in the United States: executive orders and OMB memoranda, the voluntary NIST AI RMF, agency enforcement under existing law, and binding state statutes such as Colorado SB 24-205 and California SB 53.',
            'jurisdictions' => ['us', 'us-colorado', 'us-california'],
            'policy' => 'us-nist-ai-rmf',
            'answer' => 'There is no comprehensive federal AI statute in the United States. Federal policy comes from executive orders (Executive Order 14179 of January 2025), OMB memoranda that bind federal agencies, the voluntary NIST AI Risk Management Framework, and enforcement of existing consumer-protection, civil-rights and financial laws. Binding AI-specific rules for businesses come mainly from states, including Colorado\'s algorithmic-discrimination law and California\'s frontier-model transparency act.',
            'sections' => [
                ['heading' => 'Federal layer', 'body' => 'Executive orders set policy direction for agencies and shape procurement; OMB memoranda M-25-21 and M-25-22 require agency governance, inventories, high-impact AI practices and acquisition rules; NIST provides the AI RMF and its Generative AI Profile as the shared vocabulary. Agencies such as the FTC, EEOC and CFPB apply existing law to AI.'],
                ['heading' => 'State layer', 'body' => 'States create most binding duties for private organisations. Colorado imposes reasonable-care duties on developers and deployers of high-risk AI; California requires frontier-model safety frameworks and regulates automated decision-making under the CCPA; Texas, Utah and others have targeted statutes. Effective dates have moved after enactment, so verify each record\'s official source.'],
                ['heading' => 'What to do', 'body' => 'Map where your users and employees are; adopt the NIST AI RMF as your governance backbone (state laws and federal buyers recognise it); and treat anti-discrimination, consumer-protection and privacy law as applying to AI today.'],
            ],
            'faq' => [
                ['question' => 'Is the NIST AI RMF mandatory in the US?', 'answer' => 'No, it is voluntary, but state laws and federal procurement reference it as a recognised framework.'],
            ],
        ],
        'ai-governance-uae' => [
            'h1' => 'AI governance in the UAE',
            'title' => 'AI governance in the UAE: strategy, ethics principles and data protection',
            'description' => 'The United Arab Emirates\' approach to AI: the National AI Strategy 2031, AI ethics principles and charter, the federal Personal Data Protection Law and free-zone regimes, with official sources.',
            'jurisdictions' => ['uae'],
            'policy' => 'uae-national-ai-strategy-2031',
            'answer' => 'The UAE governs AI through national strategy and principles rather than a dedicated AI statute. The National Strategy for AI 2031, the AI Ethics Principles and Guidelines and the 2024 UAE AI Charter set expectations, while the Federal Decree-Law No. 45 of 2021 on Personal Data Protection and the DIFC and ADGM data-protection regimes provide binding rules for personal data, including rights around automated decisions.',
            'sections' => [
                ['heading' => 'What is binding', 'body' => 'The federal PDPL applies outside the financial free zones and includes a right to object to solely automated decisions and a duty to assess the impact of high-risk processing using new technologies. The DIFC\'s Regulation 10 adds specific duties for autonomous and semi-autonomous systems in the DIFC.'],
                ['heading' => 'Working with UAE government', 'body' => 'Government entities are the primary audience of the strategy and charter. Vendors should reference the AI Ethics Principles and the AI Charter in proposals and expect procurement to reflect them.'],
            ],
            'faq' => [
                ['question' => 'Does the UAE have an AI law?', 'answer' => 'Not a dedicated federal AI statute as of the last check. AI is governed by strategy and principles, with binding personal-data rules at federal and free-zone level.'],
            ],
        ],
        'ai-regulation-south-asia' => [
            'h1' => 'AI regulation in South Asia',
            'title' => 'AI regulation in South Asia: India, Nepal and the regional picture',
            'description' => 'Overview of AI governance across South Asia, starting with India\'s DPDP Act and AI governance guidelines and Nepal\'s National AI Policy, with official sources and clear status labels.',
            'jurisdictions' => ['india', 'nepal'],
            'policy' => null,
            'answer' => 'South Asian governments are building AI governance on data-protection and digital-governance law rather than dedicated AI statutes. India combines the Digital Personal Data Protection Act 2023 with the IndiaAI Mission and non-binding AI governance guidelines; Nepal adopted a National AI Policy in 2025 and applies its Individual Privacy Act to AI. Coverage of other South Asian jurisdictions will be added as source-backed records are verified.',
            'sections' => [
                ['heading' => 'Common patterns', 'body' => 'Across the region, personal-data law is the first binding constraint on AI, national AI strategies emphasise capacity and inclusion, and regulators signal principle-based expectations before legislating. Organisations operating across South Asia can build one governance programme around data protection, transparency and human oversight and then localise notices and lawful bases.'],
                ['heading' => 'Coverage note', 'body' => 'This page currently covers India and Nepal. Records for Bangladesh, Sri Lanka, Pakistan, Bhutan and the Maldives will be published only once an official source has been identified and reviewed; contributions are welcome.'],
            ],
            'faq' => [],
        ],
    ],

    'guides' => [
        'ai-startup-eu-ai-act-readiness' => [
            'h1' => 'EU AI Act readiness for AI startups: the 90-day path from inventory to evidence',
            'title' => 'EU AI Act readiness for startups: a 90-day path',
            'description' => 'The EU AI Act already applies to prohibited practices, AI literacy, general-purpose models and, since 2 August 2026, Article 50 transparency; after the 2026 Digital Omnibus, the high-risk obligations apply from 2 December 2027 (Annex III) and 2 August 2028 (Annex I products). This guide turns those dates into a 90-day path: decide your role, screen banned uses, classify risk, plan the high-risk workstreams and build the evidence pack customers and regulators will ask for, with every step linked to the recorded obligation.',
            'summary' => 'Most startups are providers without knowing it. In 90 days: role, prohibited-use screen, risk classification, high-risk workstreams and an evidence pack, each step linked to the recorded obligation and its deadline.',
            'policies' => ['eu-ai-act'],
            'obligations' => ['eu-ai-act-prohibited-practices', 'eu-ai-act-ai-literacy', 'eu-ai-act-risk-management-system', 'eu-ai-act-technical-documentation', 'eu-ai-act-transparency-article-50', 'eu-ai-act-gpai-provider-obligations'],
            'steps' => [
                ['title' => 'Inventory and roles', 'body' => 'List every AI system and model you build, buy or embed. For each, decide whether you are the provider, deployer, importer, distributor or a downstream integrator of a general-purpose model, and in which markets. Most startups are providers of their product and deployers of the tools they use internally.'],
                ['title' => 'Screen prohibited practices now', 'body' => 'Article 5 bans have applied since 2 February 2025. Run a documented screen of every system against the prohibited list and keep the record; this is the cheapest and most urgent control.'],
                ['title' => 'Classify risk', 'body' => 'Check whether your system is a safety component of an Annex I product or falls in an Annex III area (employment, education, credit, essential services, law enforcement, migration, justice, biometrics, critical infrastructure). If it does, assess whether the Article 6(3) derogation applies and document the reasoning.'],
                ['title' => 'Plan the high-risk workstreams', 'body' => 'For high-risk systems, plan risk management, data governance, technical documentation, logging, instructions for deployers, human oversight, accuracy and security, a quality management system, conformity assessment and registration, post-market monitoring and incident reporting. Sequence them so documentation is produced as a by-product of engineering, not afterwards.'],
                ['title' => 'Handle transparency and general-purpose AI', 'body' => 'If you ship chatbots or generate synthetic content, design the Article 50 disclosures and provenance marking into the product. If you provide a general-purpose model, prepare technical documentation, a copyright policy and the training-content summary, and consider the Code of Practice.'],
                ['title' => 'Evidence and review', 'body' => 'Keep evidence in a structured file mapped to articles. Have counsel review classifications and dates. Regulation (EU) 2026/1744 (the Digital Omnibus) moved the high-risk dates to 2 December 2027 and 2 August 2028; its record is pending review, so confirm against the Official Journal before you commit launch dates.'],
            ],
            'faq' => [
                ['question' => 'Do small startups get any relief under the EU AI Act?', 'answer' => 'The Act includes measures for SMEs such as simplified technical documentation forms, priority access to regulatory sandboxes and lower fine caps, but the substantive obligations for high-risk systems still apply.'],
            ],
        ],
        'ai-governance-for-startups' => [
            'h1' => 'AI governance for startups: the four artefacts every buyer and regulator asks for',
            'title' => 'AI governance for startups: the four artefacts',
            'description' => 'Enterprise buyers, insurers and regulators ask small AI teams for the same four things: an AI system inventory, a risk register, a written policy and an incident process. This guide shows how to produce them in weeks, not quarters, using obligations recorded across the EU, US, UK, Singapore and Australia and the free templates on this site.',
            'summary' => 'Skip the year-long programme. Build the four artefacts procurement, insurers and regulators actually request (inventory, risk register, policy, incident process) with free templates and recorded obligations.',
            'policies' => ['eu-ai-act', 'us-nist-ai-rmf', 'singapore-model-ai-governance-framework', 'australia-voluntary-ai-safety-standard', 'us-colorado-automated-decision-making-technology-act'],
            'obligations' => ['eu-ai-act-risk-management-system', 'us-nist-ai-rmf-govern', 'singapore-mgf-human-involvement', 'australia-vaiss-accountability-and-risk-management', 'us-colorado-admt-human-review'],
            'steps' => [
                ['title' => 'Own it', 'body' => 'Name one accountable owner for AI governance and give them a short written policy: what you build, what you will not build, and how decisions are recorded.'],
                ['title' => 'Inventory and classify', 'body' => 'Maintain a register of AI systems with purpose, users, jurisdictions, data types and a risk tier. Most frameworks and laws start here.'],
                ['title' => 'Document data and models', 'body' => 'For each system keep a data sheet (sources, licences, consent basis, known gaps and bias checks) and a model card (intended use, limitations, evaluation results). These satisfy the documentation core of the EU AI Act, the NIST AI RMF, Singapore\'s framework and the Australian standard.'],
                ['title' => 'Design oversight and recourse', 'body' => 'Decide where a human reviews or can override outputs, how users are told they are dealing with AI, and how an affected person can contest a decision. The EU AI Act and Colorado SB 26-189 (from 1 January 2027, record pending review) make these explicit duties for consequential decisions.'],
                ['title' => 'Prepare for incidents', 'body' => 'Extend your security incident process to AI harms: define severity, who reports, to whom and within what time, and keep a log.'],
                ['title' => 'Evidence once, reuse everywhere', 'body' => 'Store evidence against obligations, not against laws. One risk assessment or impact assessment template can serve the EU AI Act, UK GDPR DPIAs and ISO/IEC 42001 with small adaptations.'],
            ],
            'faq' => [],
        ],
        'iso-42001-vs-eu-ai-act' => [
            'h1' => 'ISO/IEC 42001 vs the EU AI Act: what certification proves and what it does not',
            'title' => 'ISO/IEC 42001 vs EU AI Act: clause-by-obligation crosswalk',
            'description' => 'Certification against ISO/IEC 42001 is not a presumption of conformity with the EU AI Act. This original crosswalk maps each recorded EU AI Act obligation to the management-system clauses that help meet it, marks the gaps the standard cannot close (conformity assessment, registration, transparency duties) and tells CISOs and compliance leads how to use the two together.',
            'summary' => 'Certification is not conformity. An obligation-by-clause crosswalk that shows where ISO/IEC 42001 helps with the EU AI Act, where it stops, and how CISOs should use both.',
            'policies' => ['eu-ai-act'],
            'framework' => 'iso_42001',
            'framework_jurisdiction' => 'eu',
            'steps' => [
                ['title' => 'Different kinds of instrument', 'body' => 'The EU AI Act is binding law with obligations that attach to specific AI systems by risk category. ISO/IEC 42001 is a voluntary, certifiable management-system standard describing how an organisation governs AI across its portfolio. Certification demonstrates a functioning management system; it is not a presumption of conformity with the AI Act, and it does not replace conformity assessment or registration for high-risk systems.'],
                ['title' => 'Where they overlap', 'body' => 'Both expect leadership accountability, a risk process, impact assessment, competence and awareness, documented information, operational controls over data and development, monitoring, incident handling and continual improvement. An organisation running ISO/IEC 42001 will already produce much of the evidence the AI Act\'s quality-management, risk-management and post-market monitoring articles require.'],
                ['title' => 'Where the law goes further', 'body' => 'The AI Act prescribes system-level outputs: Annex IV technical documentation, logging capability, instructions for use, specific human-oversight design features, conformity assessment, CE marking, EU database registration, serious-incident time limits and, for general-purpose models, training-content summaries and copyright policies. Treat these as controls inside the management system rather than assuming the standard covers them.'],
                ['title' => 'How to use the crosswalk below', 'body' => 'Each mapping is an original editorial judgement with a confidence level; it references clause numbers only and reproduces no standard text. Use it to organise evidence, then validate against the standard and legal advice.'],
            ],
            'faq' => [
                ['question' => 'Does ISO/IEC 42001 certification mean EU AI Act compliance?', 'answer' => 'No. Certification shows you operate an AI management system. EU AI Act compliance is assessed obligation by obligation for each AI system, including conformity assessment for high-risk systems.'],
            ],
        ],
        'nist-ai-rmf-vs-eu-ai-act' => [
            'h1' => 'NIST AI RMF vs the EU AI Act: turning a voluntary framework into legal evidence',
            'title' => 'NIST AI RMF vs EU AI Act: functions mapped to duties',
            'description' => 'US teams built on the NIST AI Risk Management Framework face binding EU AI Act obligations the moment their systems reach EU users. This crosswalk maps Govern, Map, Measure and Manage to the recorded obligations, shows which framework outputs count as evidence and which legal duties the framework never mentions.',
            'summary' => 'Built on NIST AI RMF and selling into Europe? See which Govern, Map, Measure and Manage outputs count as EU AI Act evidence, and which binding duties the framework never covers.',
            'policies' => ['eu-ai-act', 'us-nist-ai-rmf'],
            'framework' => 'nist_ai_rmf',
            'framework_jurisdiction' => 'eu',
            'steps' => [
                ['title' => 'A framework and a law', 'body' => 'The NIST AI RMF is voluntary guidance organised into Govern, Map, Measure and Manage, with a Generative AI Profile. The EU AI Act is binding legislation with duties attached to roles and risk categories. Many organisations use the RMF as their practice catalogue and the AI Act as the requirement set.'],
                ['title' => 'Shared concepts', 'body' => 'Both centre on lifecycle risk management, context and impact mapping, measurement of validity, safety, security, bias and explainability, documentation, monitoring and incident response. The RMF\'s trustworthiness characteristics line up with the AI Act\'s Articles 9 to 15 requirements for high-risk systems.'],
                ['title' => 'Gaps to close', 'body' => 'The RMF does not classify systems as prohibited or high-risk, does not require conformity assessment, registration, CE marking or fixed incident reporting deadlines, and has no general-purpose model chapter. Teams using the RMF for EU compliance need to add those legal outputs explicitly.'],
            ],
            'faq' => [
                ['question' => 'Can I use the NIST AI RMF to comply with the EU AI Act?', 'answer' => 'It is a good foundation for the risk-management and governance duties, but you must add the Act\'s specific outputs such as technical documentation, conformity assessment and registration.'],
            ],
        ],
        'eu-ai-act-digital-omnibus-new-deadlines' => [
            'h1' => 'EU AI Act after the Digital Omnibus: the new 2026 to 2028 deadlines',
            'title' => 'EU AI Act Digital Omnibus: new 2026-2028 deadlines',
            'description' => 'Regulation (EU) 2026/1744 moved the EU AI Act high-risk duties to 2 December 2027 (Annex III) and 2 August 2028 (Annex I products), added two prohibitions from 2 December 2026 and left Article 50 transparency at 2 August 2026. What applies now, what moved, and how to re-plan.',
            'summary' => 'What the Digital Omnibus changed: high-risk duties move to December 2027 and August 2028, two new bans from December 2026, and Article 50 transparency still applies from August 2026.',
            'policies' => ['eu-ai-act'],
            'obligations' => ['eu-ai-act-prohibited-practices', 'eu-ai-act-ai-literacy', 'eu-ai-act-gpai-provider-obligations', 'eu-ai-act-transparency-article-50', 'eu-ai-act-art-50-2-synthetic-content-marking', 'eu-ai-act-risk-management-system', 'eu-ai-act-conformity-assessment-registration', 'eu-ai-act-deployer-obligations'],
            'steps' => [
                ['title' => 'What Regulation (EU) 2026/1744 is', 'body' => 'The Digital Omnibus on AI amends the AI Act\'s application dates in Article 113. It was published in the Official Journal on 24 July 2026 and entered into force on 27 July 2026. The dates on this page are recorded from concordant secondary reporting and the records are still pending review against the Official Journal text, so confirm each date there before you rely on it.'],
                ['title' => 'What already applies', 'body' => 'The prohibited practices in Article 5 and the AI literacy duty in Article 4 have applied since 2 February 2025. General-purpose AI model duties, governance and penalties have applied since 2 August 2025; models placed on the market before that date have until 2 August 2027. General application of the Regulation began on 2 August 2026.'],
                ['title' => 'Article 50 transparency did not move', 'body' => 'Chatbot disclosure, machine-readable marking of synthetic content, emotion-recognition notices and deepfake disclosure under Article 50 apply from 2 August 2026. If your product talks to people or generates content, these are live now; the Omnibus deferral is for high-risk duties only.'],
                ['title' => 'Two new prohibitions from 2 December 2026', 'body' => 'The Omnibus adds two Article 5 prohibitions, on generating non-consensual intimate imagery and child sexual abuse material, applying from 2 December 2026. Add both to your prohibited-use screen.'],
                ['title' => 'High-risk duties: 2 December 2027 and 2 August 2028', 'body' => 'The Chapter III obligations for stand-alone high-risk systems listed in Annex III (employment, education, credit, essential services, law enforcement, migration, justice, biometrics, critical infrastructure) now apply from 2 December 2027. For AI that is a safety component of a product covered by the Annex I product-safety laws, they apply from 2 August 2028. That covers risk management, data governance, technical documentation, logging, human oversight, accuracy and security, the quality management system, conformity assessment, registration, and the deployer duties in Article 26.'],
                ['title' => 'How to re-plan', 'body' => 'Keep the Article 50 and prohibited-practice work on today\'s footing. Re-sequence high-risk work so documentation and testing are produced as the system is built, aiming to finish conformity assessment before December 2027. Use the extra time for the slow items: data governance evidence, the quality management system and supplier agreements under Article 25(4).'],
            ],
            'faq' => [
                ['question' => 'Was the EU AI Act delayed?', 'answer' => 'Partly. Regulation (EU) 2026/1744 moved the high-risk obligations to 2 December 2027 (Annex III systems) and 2 August 2028 (AI in Annex I products). The prohibitions, AI literacy, general-purpose AI duties and Article 50 transparency were not delayed. The record is pending review against the Official Journal.'],
                ['question' => 'When do the EU AI Act high-risk rules apply now?', 'answer' => 'From 2 December 2027 for stand-alone high-risk systems listed in Annex III, and from 2 August 2028 for AI embedded in products covered by the Annex I product-safety laws, according to the record of Regulation (EU) 2026/1744.'],
                ['question' => 'Did the Article 50 transparency duties move?', 'answer' => 'No. According to the record, chatbot disclosure, synthetic-content marking and deepfake labelling under Article 50 apply from 2 August 2026.'],
                ['question' => 'What new prohibitions did the Omnibus add?', 'answer' => 'Two prohibitions in Article 5, on AI generation of non-consensual intimate imagery and of child sexual abuse material, applying from 2 December 2026.'],
                ['question' => 'Do the general-purpose AI model duties still apply?', 'answer' => 'Yes. They have applied since 2 August 2025, with models already on the market before that date given until 2 August 2027.'],
            ],
        ],
        'colorado-ai-act-repeal-sb-26-189' => [
            'h1' => 'Colorado AI Act repealed: what SB 26-189 requires from 2027',
            'title' => 'Colorado AI Act repealed: what SB 26-189 requires',
            'description' => 'Colorado repealed its AI Act (SB 24-205) on 14 May 2026, before it took effect, and replaced it with SB 26-189 on automated decision-making technology, applying from 1 January 2027: advance notice, adverse-decision disclosure, human review, three-year records and developer documentation.',
            'summary' => 'Colorado\'s 2024 AI Act never took effect. SB 26-189 replaces it from 1 January 2027 with narrower duties: notice, adverse-decision disclosure, human review, records and developer documentation.',
            'policies' => ['us-colorado-automated-decision-making-technology-act', 'us-colorado-ai-act'],
            'obligations' => ['us-colorado-admt-advance-notice', 'us-colorado-admt-adverse-decision-disclosure', 'us-colorado-admt-human-review', 'us-colorado-admt-record-keeping', 'us-colorado-admt-developer-documentation'],
            'steps' => [
                ['title' => 'What happened on 14 May 2026', 'body' => 'The Governor signed SB 26-189, which repeals and re-enacts the Colorado AI Act (SB 24-205) before its delayed 30 June 2026 effective date. SB 24-205 never took effect. Both records are pending review: they were recorded from secondary reporting, so confirm the details against the enrolled bill.'],
                ['title' => 'What was dropped', 'body' => 'The replacement drops SB 24-205\'s duty of reasonable care, the impact assessments, the risk-management programme and the Attorney General notification. If you were building to those requirements, stop and re-scope to the new duties.'],
                ['title' => 'Who is covered', 'body' => 'SB 26-189 attaches duties when automated decision-making technology materially influences a consequential decision about a Colorado consumer, in areas such as employment, lending, housing, education, healthcare, insurance and government services.'],
                ['title' => 'Before the decision: notice', 'body' => 'Deployers must notify consumers before the technology influences a consequential decision about them.'],
                ['title' => 'After an adverse decision: disclosure and human review', 'body' => 'After an adverse consequential decision, disclose that the technology was used and the principal reasons, and offer meaningful human review of the decision.'],
                ['title' => 'Records and developer documentation', 'body' => 'Keep records of consequential decisions influenced by the technology for three years. Developers must give deployers documentation of the technology so that deployers can meet their own duties.'],
                ['title' => 'Plan for 1 January 2027', 'body' => 'Inventory the decisions your systems influence in Colorado, draft the notices and adverse-decision letters, design the human-review route, and agree documentation terms with your vendors before the law applies.'],
            ],
            'faq' => [
                ['question' => 'Is the Colorado AI Act repealed?', 'answer' => 'Yes, according to the record: SB 26-189, signed on 14 May 2026, repeals and re-enacts the Colorado AI Act (SB 24-205) before it took effect. The record is pending review against the enrolled bill.'],
                ['question' => 'When does Colorado SB 26-189 apply?', 'answer' => 'From 1 January 2027, according to the record.'],
                ['question' => 'Do I still need a Colorado AI impact assessment?', 'answer' => 'Not under SB 26-189 as recorded: the replacement law drops the impact assessments, the risk-management programme and the duty of care that SB 24-205 would have imposed.'],
                ['question' => 'What counts as a consequential decision?', 'answer' => 'A decision with a material effect on a consumer in areas such as employment, lending, housing, education, healthcare, insurance and government services. Check the definition in the enrolled bill for your case.'],
            ],
        ],
        'us-state-ai-laws-2026' => [
            'h1' => 'US state AI laws in force in 2026, and what starts in 2027',
            'title' => 'US state AI laws in 2026 and 2027: what applies',
            'description' => 'There is no general federal AI statute. AI duties in the US come from state laws such as Texas TRAIGA, Illinois HB 3773 and Utah\'s AI Policy Act, California SB 53, NYC Local Law 144, and from 2027 Colorado SB 26-189 and New York\'s RAISE Act. Status, dates and duties, as recorded.',
            'summary' => 'No general federal AI law: the duties come from the states. Texas, Illinois, Utah, California and NYC rules already apply; Colorado and New York\'s RAISE Act follow in 2027.',
            'policies' => ['us-texas-responsible-ai-governance-act-traiga', 'us-illinois-hb-3773-ai-in-employment', 'us-utah-artificial-intelligence-policy-act', 'us-california-sb-53', 'us-new-york-city-local-law-144-automated-employment-decision-tools', 'us-colorado-automated-decision-making-technology-act', 'us-new-york-raise-act', 'us-omb-m-25-21'],
            'obligations' => ['us-texas-responsible-ai-governance-act-traiga-unlawful-discrimination-prohibition', 'us-texas-responsible-ai-governance-act-traiga-health-care-ai-disclosure', 'us-california-sb-53-frontier-ai-framework', 'us-new-york-city-local-law-144-bias-audit', 'us-colorado-admt-advance-notice'],
            'steps' => [
                ['title' => 'The federal position', 'body' => 'There is no general federal AI statute on record. Federal policy comes from executive action and agency guidance; OMB memorandum M-25-21 binds federal agencies\' own use of AI, and the NIST AI RMF is voluntary. Private-sector duties come from the states.'],
                ['title' => 'Texas: TRAIGA', 'body' => 'The Texas Responsible AI Governance Act applies from 1 January 2026. It prohibits developing or deploying AI to manipulate people towards harm, to discriminate unlawfully against a protected class, or to produce child sexual abuse material or unlawful deepfakes, and requires government agencies and health-care providers to disclose AI use. The Attorney General enforces it after a 60-day cure period.'],
                ['title' => 'Illinois and Utah', 'body' => 'Illinois HB 3773 amends the Human Rights Act to address AI in employment decisions, effective 1 January 2026; Illinois\'s AI Video Interview Act has applied since 2020. Utah\'s Artificial Intelligence Policy Act has applied since 1 May 2024: a business using generative AI with consumers must disclose it when asked (as amended in 2025, also when the interaction is high-risk), and regulated occupations such as health and legal services must disclose it up front.'],
                ['title' => 'California and New York City', 'body' => 'California SB 53 has required large frontier developers to publish a frontier AI framework and report critical safety incidents since 1 January 2026, with a set of further California laws signed in September 2026 (recorded, pending review). New York City Local Law 144 has required bias audits and candidate notices for automated employment decision tools since July 2023.'],
                ['title' => 'Coming in 2027', 'body' => 'Colorado SB 26-189 applies from 1 January 2027, replacing the repealed Colorado AI Act (record pending review). New York\'s RAISE Act on frontier model safety also applies from 1 January 2027; its record is verified but at low confidence, so check the text.'],
                ['title' => 'A multi-state baseline', 'body' => 'Most of these laws ask for the same few things: know which systems make or influence decisions about people, tell people when AI is used, test for discrimination, keep records, and offer a human route. Building those once covers most of the state duties; the applicability matrix template lists every instrument on record.'],
            ],
            'faq' => [
                ['question' => 'Which US states have AI laws in effect?', 'answer' => 'On record: Texas (TRAIGA, from 1 January 2026), Illinois (HB 3773 from 1 January 2026, and the AI Video Interview Act), Utah (since 1 May 2024), California (SB 53 since 1 January 2026), Tennessee (the ELVIS Act) and New York City (Local Law 144). Colorado and New York State follow on 1 January 2027.'],
                ['question' => 'Is there a federal AI law in the US?', 'answer' => 'There is no general federal AI statute on record. Federal agencies are bound by OMB guidance for their own use of AI; the NIST AI RMF is voluntary.'],
                ['question' => 'What changes on 1 January 2027?', 'answer' => 'Colorado SB 26-189 (automated decision-making technology) and New York\'s RAISE Act (frontier model safety) apply, according to the records, and several California laws signed in September 2026 take effect.'],
                ['question' => 'Does Texas TRAIGA apply to private companies?', 'answer' => 'Yes, in part. Its prohibitions on manipulation, unlawful discrimination and sexual-content misuse apply to developers and deployers generally; its disclosure duties fall on government agencies and health-care providers.'],
            ],
        ],
        'ai-hiring-compliance-laws' => [
            'h1' => 'AI in hiring: NYC Local Law 144, Illinois, Colorado and the EU AI Act',
            'title' => 'AI in hiring laws: NYC LL144, Illinois, EU AI Act',
            'description' => 'Using AI to screen, rank or score candidates triggers bias audits and notices in New York City, anti-discrimination and notice duties in Illinois, job-posting disclosure in Ontario, Colorado\'s automated-decision duties from 2027, and high-risk duties under the EU AI Act. A practical checklist.',
            'summary' => 'AI hiring tools carry the most specific rules on record: NYC bias audits and notices, Illinois, Ontario job postings, Colorado from 2027 and the EU AI Act high-risk regime.',
            'policies' => ['us-new-york-city-local-law-144-automated-employment-decision-tools', 'us-illinois-hb-3773-ai-in-employment', 'ca-ontario-working-for-workers-four-act-ai-in-job-postings', 'us-colorado-automated-decision-making-technology-act', 'eu-ai-act'],
            'obligations' => ['us-new-york-city-local-law-144-bias-audit', 'us-new-york-city-local-law-144-publish-audit-summary', 'us-new-york-city-local-law-144-candidate-notice', 'us-new-york-city-local-law-144-alternative-process-request', 'us-new-york-city-local-law-144-data-policy-disclosure', 'eu-ai-act-art-26-7-worker-information', 'us-colorado-admt-advance-notice'],
            'steps' => [
                ['title' => 'Inventory the tools', 'body' => 'List every tool that screens, ranks, scores or recommends candidates or employees, who supplies it, and where the candidates are. The rules attach to where the job or the person is, not where the employer is.'],
                ['title' => 'New York City: bias audit and notice', 'body' => 'Under Local Law 144, enforced since 5 July 2023, an employer or agency using an automated employment decision tool for a NYC job must have an independent bias audit within the year before use, publish a summary of the results, notify candidates beforehand, let them ask for an alternative process or accommodation, and disclose the data collected and its retention policy.'],
                ['title' => 'Illinois', 'body' => 'Illinois HB 3773, effective 1 January 2026, makes it a civil-rights violation to use AI in recruitment, hiring, promotion, discipline or discharge in a way that discriminates on a protected basis, or to use zip codes as a proxy; employers must notify employees when AI is used for such decisions. The AI Video Interview Act has applied to AI analysis of video interviews since 2020.'],
                ['title' => 'Ontario job postings', 'body' => 'Ontario requires employers with 25 or more employees to state in every publicly advertised job posting whether AI is used to screen, assess or select applicants, from 1 January 2026.'],
                ['title' => 'Colorado from 2027', 'body' => 'Employment is a consequential-decision area under Colorado SB 26-189, applying from 1 January 2027 (record pending review): advance notice, adverse-decision disclosure with the principal reasons, human review, and three-year records.'],
                ['title' => 'EU AI Act', 'body' => 'AI used in employment and worker management is among the high-risk uses listed in Annex III. With the Omnibus, those duties apply from 2 December 2027 (record pending review). Employers must also inform workers and their representatives before using a high-risk system at work.'],
                ['title' => 'One evidence pack', 'body' => 'Keep, per tool: the vendor\'s documentation, a selection-rate and impact-ratio analysis, the candidate notice, the alternative-process route, the human-review procedure and the decision records. The employment bias audit kit template holds all of these.'],
            ],
            'faq' => [
                ['question' => 'Do I need a bias audit for an AI hiring tool?', 'answer' => 'For jobs in New York City, yes: Local Law 144 requires an independent bias audit within the year before use, and a published summary. Other laws on record ask for notice, non-discrimination and records rather than a formal audit.'],
                ['question' => 'Must I tell candidates that AI is used?', 'answer' => 'Under several laws on record, yes: NYC Local Law 144 (before use), Illinois HB 3773, Ontario job postings and, from 2027, Colorado SB 26-189. The EU AI Act requires informing workers and their representatives.'],
                ['question' => 'Does Local Law 144 apply outside New York City?', 'answer' => 'It applies to the use of automated employment decision tools for jobs and candidates in New York City, wherever the employer is based.'],
                ['question' => 'Is AI recruiting software high-risk under the EU AI Act?', 'answer' => 'Yes. Employment uses are listed in Annex III, so the high-risk duties apply, from 2 December 2027 after the Omnibus according to the record.'],
            ],
        ],
        'california-ai-laws-2026' => [
            'h1' => 'California AI laws in 2026: SB 53, companion chatbots, SB 1050 and AI audits',
            'title' => 'California AI laws 2026: SB 53, chatbots, SB 1050',
            'description' => 'California\'s AI rules as recorded: SB 53 frontier AI transparency in force since 1 January 2026, the September 2026 companion-chatbot child-safety package, SB 1050 synthetic-performer ad disclosure from 2027, the SB 813 and AB 1405 auditor framework, and the September executive order.',
            'summary' => 'SB 53 has applied since January 2026. September 2026 added companion-chatbot safeguards, ad disclosure for synthetic performers, an AI auditor registry and an executive order on frontier oversight.',
            'policies' => ['us-california-sb-53'],
            'obligations' => ['us-california-sb-53-frontier-ai-framework', 'us-california-sb-53-critical-safety-incident-reporting', 'us-california-sb-53-transparency-report', 'us-california-sb-53-catastrophic-risk-assessment-summaries', 'us-california-sb-53-whistleblower-protections'],
            'steps' => [
                ['title' => 'SB 53: frontier AI transparency', 'body' => 'The Transparency in Frontier Artificial Intelligence Act has applied since 1 January 2026. Large frontier developers must publish a frontier AI framework, publish a transparency report before deploying a new frontier model, send periodic summaries of catastrophic-risk assessments to the state, report critical safety incidents to the Office of Emergency Services, and protect employees who report catastrophic-risk concerns.'],
                ['title' => 'Companion chatbots and children', 'body' => 'On 10 September 2026 the Governor signed a package of child-safety bills that require risk assessments before new companion-chatbot rollouts, add penalties for harm to children and restrict toys that include a companion chatbot. Most provisions take effect on 1 January 2027 and the companion-chatbot safeguards on 1 July 2027. This is recorded from secondary reporting and pending review; check each enrolled bill.'],
                ['title' => 'SB 1050: synthetic performers in advertising', 'body' => 'From 1 January 2027, an advertisement that prominently includes a synthetic performer (a human-like digital voice, figure or representation made at least partly with generative AI and not depicting an identifiable person) needs a clear and conspicuous disclosure. Pending review against the chaptered text.'],
                ['title' => 'SB 813 and AB 1405: AI auditors', 'body' => 'SB 813 has the Government Operations Agency set criteria by 1 January 2028 for designating qualified AI auditors; AB 1405 creates an AI Auditor Registry by 1 January 2029, after which covered AI audits may be offered only by registered auditors. Pending review.'],
                ['title' => 'The September 2026 executive order', 'body' => 'An executive order of 18 September 2026 asks a working group to recommend measures including whether frontier developers should maintain a shutdown capability. It creates no obligations itself. Pending review.'],
                ['title' => 'Timeline', 'body' => 'January 2026: SB 53. January 2027: most of the child-safety package and SB 1050. July 2027: companion-chatbot safeguards. January 2028: auditor criteria. January 2029: auditor registry.'],
            ],
            'faq' => [
                ['question' => 'What does California SB 53 require?', 'answer' => 'Large frontier AI developers must publish a frontier AI framework and transparency reports, send catastrophic-risk assessment summaries to the state, report critical safety incidents to the Office of Emergency Services, and protect whistleblowers. It has applied since 1 January 2026.'],
                ['question' => 'When do California\'s companion-chatbot rules apply?', 'answer' => 'According to the record (pending review), most provisions of the September 2026 child-safety package take effect on 1 January 2027 and the companion-chatbot safeguards on 1 July 2027.'],
                ['question' => 'Do AI-generated performers in ads need a disclosure in California?', 'answer' => 'Yes, from 1 January 2027 under SB 1050 as recorded: an ad that prominently includes a synthetic performer needs a clear and conspicuous disclosure.'],
                ['question' => 'Who can perform AI audits in California?', 'answer' => 'Under AB 1405 as recorded, once the AI Auditor Registry opens (by 1 January 2029), covered AI audits may be offered only by registered auditors meeting independence and integrity standards.'],
            ],
        ],
        'ai-content-labelling-disclosure-laws' => [
            'h1' => 'AI disclosure and labelling laws: EU, China, South Korea and the US',
            'title' => 'AI labelling and disclosure laws: EU, China, Korea, US',
            'description' => 'When must you tell people they are dealing with AI, and how must AI-generated content be marked? The EU AI Act Article 50, China\'s labelling measures, South Korea\'s AI Basic Act, Utah and Texas disclosure rules and California SB 1050, compared, with one design that meets them.',
            'summary' => 'Chatbot disclosure, machine-readable marking and deepfake labels are now law in the EU, China and South Korea, with US state rules on top. What each asks, and one design that meets them all.',
            'policies' => ['eu-ai-act', 'china-measures-for-labeling-artificial-intelligence-generated-and-synthetic-content', 'south-korea-framework-act-on-the-development-of-artificial-intelligence-and-establishment-of-a-foundation-for', 'us-utah-artificial-intelligence-policy-act', 'us-texas-responsible-ai-governance-act-traiga'],
            'obligations' => ['eu-ai-act-transparency-article-50', 'eu-ai-act-art-50-2-synthetic-content-marking', 'eu-ai-act-art-50-3-emotion-recognition-notice', 'eu-ai-act-art-50-4-deepfake-and-public-interest-text-disclosure', 'south-korea-ai-basic-act-art-31-advance-notice-of-high-impact-and-generative-ai', 'south-korea-ai-basic-act-art-31-generative-output-labelling-and-deepfake-notice', 'us-texas-responsible-ai-governance-act-traiga-health-care-ai-disclosure'],
            'steps' => [
                ['title' => 'Tell people they are talking to AI', 'body' => 'Under EU AI Act Article 50, people must be told when they interact with an AI system unless it is obvious, from 2 August 2026. South Korea\'s AI Basic Act requires advance notice that a product or service runs on generative or high-impact AI. Utah requires disclosure of generative AI to consumers on request or in high-risk interactions, and up front in regulated occupations; Texas requires it from government agencies and health-care providers.'],
                ['title' => 'Mark synthetic content for machines', 'body' => 'EU Article 50(2) requires providers of systems that generate synthetic audio, images, video or text to mark the output in a machine-readable, detectable way, as far as technically feasible. China\'s labelling measures, in force since 1 September 2025, require implicit labels in file metadata alongside explicit ones.'],
                ['title' => 'Label deepfakes for people', 'body' => 'EU Article 50(4) requires deployers to disclose deepfakes and AI-generated text published on matters of public interest, with lighter rules for evidently artistic or satirical work. South Korea requires generative output to be labelled and realistic synthetic media to be clearly flagged. China requires visible labels on synthetic content.'],
                ['title' => 'Advertising and performers', 'body' => 'California SB 1050 requires a clear disclosure on ads that prominently include a synthetic performer, from 1 January 2027 (record pending review).'],
                ['title' => 'One design for all of them', 'body' => 'Use one provenance standard for machine-readable marking (metadata plus a watermark where content types allow), one visible label pattern per content type, a disclosure at the start of every AI conversation, and a register of the features that generate content. The synthetic content labelling plan template is built for this.'],
            ],
            'faq' => [
                ['question' => 'Do I have to tell users they are talking to an AI?', 'answer' => 'In the EU, yes, under Article 50 of the AI Act from 2 August 2026, unless it is obvious. South Korea requires advance notice for generative and high-impact AI; Utah requires disclosure on request or in high-risk interactions, and Texas in government and health-care settings.'],
                ['question' => 'Do AI-generated images need a watermark?', 'answer' => 'The EU requires machine-readable marking of synthetic output (Article 50(2)), and China requires implicit labels in metadata plus explicit labels. A watermark is one technique; the laws on record ask for detectable marking rather than naming a single method.'],
                ['question' => 'What does China require for AI-generated content?', 'answer' => 'Its labelling measures, in force since 1 September 2025, require explicit labels that people can see and implicit labels embedded in file metadata for AI-generated and synthetic content.'],
                ['question' => 'When did EU Article 50 start to apply?', 'answer' => 'On 2 August 2026. Regulation (EU) 2026/1744 did not move it, according to the record.'],
            ],
        ],
        'frontier-ai-laws-sb-53-raise-act' => [
            'h1' => 'Frontier AI laws: California SB 53, New York\'s RAISE Act and EU systemic-risk duties',
            'title' => 'Frontier AI laws: California SB 53 vs New York RAISE',
            'description' => 'Developers of the most capable AI models face published safety frameworks, transparency reports, incident reporting and whistleblower protection under California SB 53 (since January 2026), New York\'s RAISE Act (from January 2027) and the EU AI Act duties for systemic-risk models.',
            'summary' => 'SB 53, the RAISE Act and the EU systemic-risk duties ask frontier developers for the same core set: a published safety framework, incident reporting, transparency and protection for whistleblowers.',
            'policies' => ['us-california-sb-53', 'us-new-york-raise-act', 'eu-ai-act'],
            'obligations' => ['us-california-sb-53-frontier-ai-framework', 'us-california-sb-53-critical-safety-incident-reporting', 'us-california-sb-53-transparency-report', 'us-california-sb-53-whistleblower-protections', 'eu-ai-act-art-52-systemic-risk-notification', 'eu-ai-act-art-55-systemic-risk-incident-reporting', 'eu-ai-act-art-55-systemic-risk-cybersecurity'],
            'steps' => [
                ['title' => 'Who is a frontier developer', 'body' => 'Each law sets its own thresholds, by training compute and, for some duties, revenue. In the EU, a general-purpose model is presumed to have systemic risk when its cumulative training compute exceeds 10^25 floating-point operations, and the provider must notify the Commission within two weeks of meeting the threshold.'],
                ['title' => 'A published safety framework', 'body' => 'California SB 53 requires large frontier developers to publish a frontier AI framework describing how they assess and mitigate catastrophic risk. New York\'s RAISE Act requires a safety and security protocol. The EU asks systemic-risk providers to assess and mitigate systemic risks, including through adversarial testing.'],
                ['title' => 'Transparency', 'body' => 'Under SB 53, frontier developers publish a transparency report before deploying a new frontier model, and large developers send periodic summaries of catastrophic-risk assessments to the state.'],
                ['title' => 'Incident reporting', 'body' => 'SB 53 requires critical safety incidents to be reported to the California Office of Emergency Services; RAISE requires reporting to New York within a set period; the EU requires systemic-risk providers to track and report serious incidents to the AI Office.'],
                ['title' => 'Whistleblowers and security', 'body' => 'SB 53 protects employees who report catastrophic-risk concerns. The EU requires systemic-risk providers to secure the model and its infrastructure.'],
                ['title' => 'Dates and confidence', 'body' => 'SB 53 has applied since 1 January 2026 (record verified). The RAISE Act applies from 1 January 2027; its record is verified but at low confidence, so check the text. The EU general-purpose AI duties have applied since 2 August 2025. California\'s September 2026 executive order on a shutdown capability creates no obligations yet.'],
            ],
            'faq' => [
                ['question' => 'Does California SB 53 apply to my company?', 'answer' => 'Only if you develop frontier AI models above its compute threshold; several duties apply only to large frontier developers above a revenue threshold. Check the definitions in the statute.'],
                ['question' => 'When does New York\'s RAISE Act take effect?', 'answer' => 'On 1 January 2027, according to the record, which is verified at low confidence.'],
                ['question' => 'Where are frontier AI incidents reported?', 'answer' => 'In California, to the Office of Emergency Services under SB 53; in New York, to the state under the RAISE Act; in the EU, systemic-risk model providers report serious incidents to the AI Office.'],
                ['question' => 'What is the EU threshold for systemic-risk models?', 'answer' => 'A general-purpose model is presumed to have systemic risk when its cumulative training compute exceeds 10^25 floating-point operations, or when the Commission designates it.'],
            ],
        ],
        'south-korea-ai-basic-act-compliance' => [
            'h1' => 'South Korea\'s AI Basic Act: what foreign companies must do',
            'title' => 'South Korea AI Basic Act: compliance for foreign firms',
            'description' => 'South Korea\'s Framework Act on AI has applied since 22 January 2026, including to foreign operators. Advance notice and labelling for generative and high-impact AI, safety measures above a compute threshold, high-impact duties, impact assessment and a domestic representative, duty by duty.',
            'summary' => 'In force since January 2026 and reaching foreign operators: notice and labelling, safety measures above a compute threshold, high-impact AI duties, impact assessment and a Korean representative.',
            'policies' => ['south-korea-framework-act-on-the-development-of-artificial-intelligence-and-establishment-of-a-foundation-for'],
            'obligations' => ['south-korea-ai-basic-act-art-31-advance-notice-of-high-impact-and-generative-ai', 'south-korea-ai-basic-act-art-31-generative-output-labelling-and-deepfake-notice', 'south-korea-ai-basic-act-art-32-safety-measures-for-high-performance-ai', 'south-korea-ai-basic-act-art-34-high-impact-ai-risk-management-plan', 'south-korea-ai-basic-act-art-34-high-impact-ai-explanation-measures', 'south-korea-ai-basic-act-art-34-high-impact-ai-human-oversight', 'south-korea-ai-basic-act-art-34-high-impact-ai-user-protection-and-documentation', 'south-korea-ai-basic-act-art-35-high-impact-ai-impact-assessment', 'south-korea-ai-basic-act-art-36-domestic-representative'],
            'steps' => [
                ['title' => 'In force and extraterritorial', 'body' => 'The Framework Act on the Development of Artificial Intelligence and Establishment of a Foundation for Trust was promulgated on 21 January 2025 and has applied since 22 January 2026. It applies to AI business operators whose products and services reach Korea, including operators established abroad.'],
                ['title' => 'Notice and labelling (Article 31)', 'body' => 'Notify users in advance that a product or service runs on high-impact or generative AI; label generative AI output; and clearly flag realistic synthetic media.'],
                ['title' => 'Safety above the compute threshold (Article 32)', 'body' => 'Operators of AI systems above the compute threshold set by decree must run lifecycle risk management and report the safety results.'],
                ['title' => 'High-impact AI (Article 34)', 'body' => 'Operators of high-impact AI must establish and operate a risk management plan, be able to explain outputs and their main criteria, ensure human management and supervision, and prepare user-protection measures and keep records of their safety and trust measures.'],
                ['title' => 'Impact assessment (Article 35)', 'body' => 'Operators of high-impact AI should assess its impact on fundamental rights before use.'],
                ['title' => 'Domestic representative (Article 36)', 'body' => 'Foreign AI business operators above the thresholds set by decree must designate a domestic representative in Korea.'],
                ['title' => 'Reusing EU AI Act work', 'body' => 'Much of the evidence overlaps with the EU AI Act: Article 50 disclosures and marking for Article 31, the high-risk risk management, oversight and documentation for Article 34, and the fundamental rights impact assessment for Article 35. The Korean duties have their own thresholds and definitions, so map each one rather than assuming equivalence.'],
            ],
            'faq' => [
                ['question' => 'Does the Korean AI Basic Act apply to foreign companies?', 'answer' => 'Yes. It applies to AI business operators whose products and services reach Korea, and foreign operators above set thresholds must designate a domestic representative (Article 36).'],
                ['question' => 'When did South Korea\'s AI Basic Act take effect?', 'answer' => 'On 22 January 2026, a year after it was promulgated on 21 January 2025.'],
                ['question' => 'What is high-impact AI under the Korean act?', 'answer' => 'AI that may significantly affect life, safety or fundamental rights in areas the Act lists. Operators of high-impact AI carry the Article 34 duties and should run an impact assessment under Article 35. Check the Act and its decree for the list.'],
                ['question' => 'How does it compare with the EU AI Act?', 'answer' => 'Both require disclosure and labelling of AI, extra duties for higher-risk uses and an impact assessment. The Korean act has its own definitions and thresholds and a domestic-representative duty for foreign operators, so map each duty rather than assuming equivalence.'],
            ],
        ],
    ],

    'comparisons' => [
        'eu-vs-india-ai-regulation' => [
            'short' => 'EU vs India',
            'jurisdictions' => ['eu', 'india'],
            'title' => 'EU vs India AI regulation: side-by-side comparison',
            'description' => 'Compare the EU AI Act with India\'s DPDP Act, IT Rules and AI governance guidelines: status, binding rules, high-risk and generative AI, transparency, impact assessment, data governance, oversight, public sector, dates and official sources.',
            'intro' => 'The European Union has a binding, risk-based AI law with phased deadlines; India relies on data-protection and IT law plus non-binding governance guidelines. For a company serving both markets, the EU AI Act usually sets the design bar while India\'s DPDP Act sets the data bar.',
            'faq' => [
                ['question' => 'Which is stricter, the EU or India, on AI?', 'answer' => 'The EU has binding AI-specific obligations with fines up to 7 % of turnover. India has no AI-specific law; its binding constraints come from the DPDP Act and sector rules, with AI governance guidance that is voluntary.'],
            ],
        ],
        'eu-vs-uk-ai-regulation' => [
            'short' => 'EU vs UK',
            'jurisdictions' => ['eu', 'uk'],
            'title' => 'EU vs UK AI regulation: side-by-side comparison',
            'description' => 'Compare the EU AI Act with the UK\'s principles-based, regulator-led framework: binding legislation, high-risk rules, transparency, impact assessment, data governance, oversight, public-sector duties, dates and official sources.',
            'intro' => 'The EU legislated; the UK delegated to existing regulators under five principles. UK GDPR and the Equality Act do much of the binding work in the UK, while the EU AI Act adds system-level duties on top of the GDPR.',
            'faq' => [],
        ],
        'eu-vs-us-ai-regulation' => [
            'short' => 'EU vs US',
            'jurisdictions' => ['eu', 'us', 'us-colorado'],
            'title' => 'EU vs US AI regulation: federal, state and EU AI Act compared',
            'description' => 'Compare the EU AI Act with US federal policy (executive orders, OMB memoranda, NIST AI RMF) and Colorado\'s state AI law across status, binding rules, high-risk and generative AI, transparency, oversight, dates and sources.',
            'intro' => 'The EU has one binding law applied across 27 Member States; the United States has federal policy that binds agencies, a voluntary NIST framework, and a growing set of state statutes. Colorado is included because it is the closest US analogue to the EU\'s high-risk approach.',
            'faq' => [],
        ],
        'singapore-vs-australia-ai-governance' => [
            'short' => 'Singapore vs Australia',
            'jurisdictions' => ['singapore', 'australia'],
            'title' => 'Singapore vs Australia AI governance compared',
            'description' => 'Compare Singapore\'s Model AI Governance Framework and AI Verify with Australia\'s Voluntary AI Safety Standard and mandatory-guardrails proposal: status, binding rules, transparency, oversight, public sector, dates and sources.',
            'intro' => 'Both countries lead with voluntary frameworks and existing law. Singapore emphasises testing and assurance tooling; Australia\'s guardrails were written to become mandatory if the government chose to legislate.',
            'faq' => [],
        ],
    ],
];
