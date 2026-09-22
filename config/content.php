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
            'title' => 'EU AI Act readiness for AI startups (90-day path, dated obligations)',
            'description' => 'The EU AI Act already applies to prohibited practices and AI literacy, general-purpose model duties started in August 2025 and most high-risk obligations land in August 2026. This guide turns those dates into a 90-day path: decide your role, screen banned uses, classify risk, plan the high-risk workstreams and build the evidence pack customers and regulators will ask for, with every step linked to the recorded obligation.',
            'summary' => 'Most startups are providers without knowing it. In 90 days: role, prohibited-use screen, risk classification, high-risk workstreams and an evidence pack, each step linked to the recorded obligation and its deadline.',
            'policies' => ['eu-ai-act'],
            'obligations' => ['eu-ai-act-prohibited-practices', 'eu-ai-act-ai-literacy', 'eu-ai-act-risk-management-system', 'eu-ai-act-technical-documentation', 'eu-ai-act-transparency-article-50', 'eu-ai-act-gpai-provider-obligations'],
            'steps' => [
                ['title' => 'Inventory and roles', 'body' => 'List every AI system and model you build, buy or embed. For each, decide whether you are the provider, deployer, importer, distributor or a downstream integrator of a general-purpose model, and in which markets. Most startups are providers of their product and deployers of the tools they use internally.'],
                ['title' => 'Screen prohibited practices now', 'body' => 'Article 5 bans have applied since 2 February 2025. Run a documented screen of every system against the prohibited list and keep the record; this is the cheapest and most urgent control.'],
                ['title' => 'Classify risk', 'body' => 'Check whether your system is a safety component of an Annex I product or falls in an Annex III area (employment, education, credit, essential services, law enforcement, migration, justice, biometrics, critical infrastructure). If it does, assess whether the Article 6(3) derogation applies and document the reasoning.'],
                ['title' => 'Plan the high-risk workstreams', 'body' => 'For high-risk systems, plan risk management, data governance, technical documentation, logging, instructions for deployers, human oversight, accuracy and security, a quality management system, conformity assessment and registration, post-market monitoring and incident reporting. Sequence them so documentation is produced as a by-product of engineering, not afterwards.'],
                ['title' => 'Handle transparency and general-purpose AI', 'body' => 'If you ship chatbots or generate synthetic content, design the Article 50 disclosures and provenance marking into the product. If you provide a general-purpose model, prepare technical documentation, a copyright policy and the training-content summary, and consider the Code of Practice.'],
                ['title' => 'Evidence and review', 'body' => 'Keep evidence in a structured file mapped to articles. Have counsel review classifications and dates, and re-check the official sources for amendments such as the 2025 Digital Omnibus proposal before you commit launch dates.'],
            ],
            'faq' => [
                ['question' => 'Do small startups get any relief under the EU AI Act?', 'answer' => 'The Act includes measures for SMEs such as simplified technical documentation forms, priority access to regulatory sandboxes and lower fine caps, but the substantive obligations for high-risk systems still apply.'],
            ],
        ],
        'ai-governance-for-startups' => [
            'h1' => 'AI governance for startups: the four artefacts every buyer and regulator asks for',
            'title' => 'AI governance for startups: inventory, register, policy, incident process',
            'description' => 'Enterprise buyers, insurers and regulators ask small AI teams for the same four things: an AI system inventory, a risk register, a written policy and an incident process. This guide shows how to produce them in weeks, not quarters, using obligations recorded across the EU, US, UK, Singapore and Australia and the free templates on this site.',
            'summary' => 'Skip the year-long programme. Build the four artefacts procurement, insurers and regulators actually request (inventory, risk register, policy, incident process) with free templates and recorded obligations.',
            'policies' => ['eu-ai-act', 'us-nist-ai-rmf', 'singapore-model-ai-governance-framework', 'australia-voluntary-ai-safety-standard', 'us-colorado-ai-act'],
            'obligations' => ['eu-ai-act-risk-management-system', 'us-nist-ai-rmf-govern', 'singapore-mgf-human-involvement', 'australia-vaiss-accountability-and-risk-management', 'us-colorado-deployer-impact-assessment'],
            'steps' => [
                ['title' => 'Own it', 'body' => 'Name one accountable owner for AI governance and give them a short written policy: what you build, what you will not build, and how decisions are recorded.'],
                ['title' => 'Inventory and classify', 'body' => 'Maintain a register of AI systems with purpose, users, jurisdictions, data types and a risk tier. Most frameworks and laws start here.'],
                ['title' => 'Document data and models', 'body' => 'For each system keep a data sheet (sources, licences, consent basis, known gaps and bias checks) and a model card (intended use, limitations, evaluation results). These satisfy the documentation core of the EU AI Act, the NIST AI RMF, Singapore\'s framework and the Australian standard.'],
                ['title' => 'Design oversight and recourse', 'body' => 'Decide where a human reviews or can override outputs, how users are told they are dealing with AI, and how an affected person can contest a decision. Colorado and EU rules make these explicit duties for consequential decisions.'],
                ['title' => 'Prepare for incidents', 'body' => 'Extend your security incident process to AI harms: define severity, who reports, to whom and within what time, and keep a log.'],
                ['title' => 'Evidence once, reuse everywhere', 'body' => 'Store evidence against obligations, not against laws. One risk assessment or impact assessment template can serve the EU AI Act, Colorado, UK GDPR DPIAs and ISO/IEC 42001 with small adaptations.'],
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
            'title' => 'NIST AI RMF vs EU AI Act: Govern, Map, Measure, Manage mapped to obligations',
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
