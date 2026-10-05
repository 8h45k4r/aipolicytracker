<?php

// Free self-assessments on Certifyi (self.getcertifyi.com), a related product, listed so a
// reader can check where they stand on the page where they read about a law, a framework
// or a template. Kept plain: one short line says where they run, nothing more.
//
// Each entry names where it belongs on this site: policy slugs, jurisdiction slugs,
// framework keys (config/frameworks.php), template slugs (config/templates.php), audience
// keys and guide keys (config/content.php). Summaries are this site's own wording of what
// each assessment covers. Counts and times are as published by Certifyi.
return [
    'provider' => [
        'name' => 'Certifyi',
        'url' => 'https://self.getcertifyi.com/',
        'assessment_url' => 'https://self.getcertifyi.com/assessments/',
        // Added to outbound links so Certifyi can tell where a visit came from.
        'utm' => 'utm_source=aipolicytracker&utm_medium=referral&utm_campaign=self-assessments',
        'disclosure' => 'They run on Certifyi, a related product.',
    ],

    'types' => [
        'regulation' => 'Regulation',
        'framework' => 'Standard or framework',
        'security' => 'Security',
        'governance' => 'Governance and risk',
        'privacy' => 'Privacy and fairness',
    ],

    'items' => [
        'eu-ai-act-readiness' => [
            'title' => 'EU AI Act Readiness Assessment', 'type' => 'regulation', 'region' => 'EU', 'questions' => 28, 'minutes' => 7, 'domains' => 7,
            'summary' => 'Whether you could evidence what the EU AI Act sets out for your role as provider or deployer: roles, prohibited practices, high-risk requirements, conformity, deployer duties, transparency and general-purpose AI.',
            'policies' => ['eu-ai-act'], 'jurisdictions' => ['eu'], 'templates' => ['high-risk-deployer-compliance-pack', 'eu-ai-act-conformity-assessment-and-qms', 'gpai-model-provider-compliance-kit'], 'audiences' => ['providers', 'deployers', 'general-purpose-ai-providers'], 'guides' => ['ai-startup-eu-ai-act-readiness', 'eu-ai-act-digital-omnibus-new-deadlines'],
        ],
        'ai-risk-classification' => [
            'title' => 'AI System Risk Classification Assessment', 'type' => 'regulation', 'region' => 'EU', 'questions' => 21, 'minutes' => 6, 'domains' => 6,
            'summary' => 'Which EU AI Act tier each AI system sits in, and whether you could defend the call: inventory, prohibited-practice screening, the high-risk routes, transparency-only systems and how decisions are revisited.',
            'policies' => ['eu-ai-act'], 'jurisdictions' => ['eu'], 'templates' => ['eu-ai-act-role-risk-classifier', 'ai-use-case-intake-triage'], 'audiences' => ['providers', 'deployers'], 'guides' => ['ai-startup-eu-ai-act-readiness'],
        ],
        'fundamental-rights-impact-assessment' => [
            'title' => 'Fundamental Rights Impact Assessment (FRIA)', 'type' => 'regulation', 'region' => 'EU', 'questions' => 21, 'minutes' => 6, 'domains' => 6,
            'summary' => 'For deployers of high-risk AI covered by the EU AI Act\'s fundamental rights impact assessment: applicability, the assessment itself, affected groups, human oversight, notification and keeping it current.',
            'policies' => ['eu-ai-act'], 'jurisdictions' => ['eu'], 'templates' => ['fundamental-rights-impact-assessment', 'high-risk-deployer-compliance-pack'], 'audiences' => ['deployers', 'public-sector'],
        ],
        'ai-literacy-readiness' => [
            'title' => 'AI Literacy Readiness Assessment', 'type' => 'governance', 'region' => 'EU', 'questions' => 21, 'minutes' => 6, 'domains' => 6,
            'summary' => 'How you support AI literacy among the people who build, run and use AI, as the EU AI Act asks of providers and deployers: scope, role-based training, content, delivery, records and behaviour change.',
            'policies' => ['eu-ai-act'], 'jurisdictions' => ['eu'], 'templates' => ['ai-literacy-training-plan'], 'audiences' => ['deployers', 'education'],
        ],
        'ai-transparency-disclosure' => [
            'title' => 'AI Transparency & Disclosure Assessment', 'type' => 'privacy', 'region' => 'EU', 'questions' => 20, 'minutes' => 5, 'domains' => 6,
            'summary' => 'Whether people know when they are dealing with AI, when content is synthetic, and why an AI-informed decision went against them: disclosure, labelling, explanations and documentation.',
            'policies' => ['eu-ai-act', 'china-measures-for-labeling-artificial-intelligence-generated-and-synthetic-content'], 'jurisdictions' => ['eu'], 'templates' => ['article-50-transparency-kit', 'synthetic-content-labelling-plan', 'adverse-decision-explanation-and-appeal-kit'], 'audiences' => ['generative-ai'], 'guides' => ['ai-content-labelling-disclosure-laws'],
        ],
        'ai-data-privacy' => [
            'title' => 'AI & Data Privacy Assessment', 'type' => 'privacy', 'region' => 'EU', 'questions' => 23, 'minutes' => 6, 'domains' => 6,
            'summary' => 'Whether you can justify, limit and explain each use of personal data to train or run AI: lawful basis, DPIAs, transparency, individuals\' rights, transfers and vendors. For DPOs and privacy leads.',
            'templates' => ['ai-dpia-supplement', 'ai-data-governance-register'], 'audiences' => ['biometrics'],
        ],
        'colorado-ai-act' => [
            'title' => 'Colorado AI Act Readiness Assessment', 'type' => 'regulation', 'region' => 'Colorado', 'questions' => 22, 'minutes' => 6, 'domains' => 6,
            'summary' => 'Colorado\'s law on automated decision-making technology in consequential decisions: scope, developer documentation, notices, adverse-outcome explanations, consumer rights and records.',
            'policies' => ['us-colorado-automated-decision-making-technology-act', 'us-colorado-ai-act'], 'jurisdictions' => ['us-colorado'], 'templates' => ['colorado-ai-act-notices'], 'guides' => ['colorado-ai-act-repeal-sb-26-189'],
        ],
        'us-state-ai-laws' => [
            'title' => 'US State AI Law Exposure Assessment', 'type' => 'regulation', 'region' => 'United States', 'questions' => 24, 'minutes' => 6, 'domains' => 6,
            'summary' => 'Your exposure to US state AI laws: state footprint, notices for automated decisions, bias audits, chatbot and synthetic media rules, consumer rights and how you track new laws.',
            'policies' => ['us-texas-responsible-ai-governance-act-traiga', 'us-california-sb-53', 'us-utah-artificial-intelligence-policy-act', 'us-illinois-hb-3773-ai-in-employment', 'us-new-york-raise-act'], 'jurisdictions' => ['us', 'us-california', 'us-texas', 'us-utah', 'us-illinois', 'us-new-york'], 'templates' => ['us-state-ai-law-matrix', 'texas-traiga-compliance-checklist'], 'guides' => ['us-state-ai-laws-2026', 'california-ai-laws-2026', 'frontier-ai-laws-sb-53-raise-act'],
        ],
        'apac-ai-regulation' => [
            'title' => 'Asia-Pacific AI Regulation Assessment', 'type' => 'regulation', 'region' => 'Asia-Pacific', 'questions' => 23, 'minutes' => 6, 'domains' => 6,
            'summary' => 'Which Asia-Pacific AI rules reach your services, from China\'s generative AI measures to Singapore\'s governance framework and Korea\'s AI Basic Act: applicability, the main national regimes and how you track change.',
            'policies' => ['china-interim-measures-for-the-management-of-generative-artificial-intelligence-services', 'china-measures-for-labeling-artificial-intelligence-generated-and-synthetic-content', 'singapore-model-ai-governance-framework', 'south-korea-framework-act-on-the-development-of-artificial-intelligence-and-establishment-of-a-foundation-for'], 'jurisdictions' => ['china', 'singapore', 'south-korea', 'japan', 'india', 'australia'], 'templates' => ['south-korea-ai-basic-act-checklist', 'global-ai-regulatory-applicability-matrix'], 'guides' => ['south-korea-ai-basic-act-compliance'],
        ],
        'ai-healthcare-compliance' => [
            'title' => 'AI in Healthcare Compliance Assessment', 'type' => 'regulation', 'region' => 'Global', 'questions' => 21, 'minutes' => 6, 'domains' => 6,
            'summary' => 'Clinical AI carries patient-safety, device-regulation and data-protection duties at once: intended use, the regulatory route, patient data, clinical validation, clinician oversight and post-market monitoring.',
            'audiences' => ['healthcare'], 'templates' => ['ai-post-market-monitoring-plan'],
        ],
        'iso-42001-readiness' => [
            'title' => 'ISO/IEC 42001 Readiness Assessment', 'type' => 'framework', 'region' => 'Global', 'questions' => 23, 'minutes' => 6, 'domains' => 7,
            'summary' => 'How close your AI management system is to an ISO/IEC 42001:2023 certification audit: seven domains following clauses 4 to 10 and the Annex A controls, checked the way an auditor samples evidence.',
            'frameworks' => ['iso_42001'], 'templates' => ['iso-42001-gap-assessment', 'nist-eu-iso-crosswalk'], 'guides' => ['iso-42001-vs-eu-ai-act'],
        ],
        'nist-ai-rmf' => [
            'title' => 'NIST AI Risk Management Framework Assessment', 'type' => 'framework', 'region' => 'Global', 'questions' => 22, 'minutes' => 6, 'domains' => 5,
            'summary' => 'How far your AI risk practice matches the outcomes in the NIST AI RMF: GOVERN, MAP, MEASURE and MANAGE, plus the Generative AI Profile.',
            'frameworks' => ['nist_ai_rmf'], 'policies' => ['us-nist-ai-rmf'], 'templates' => ['nist-eu-iso-crosswalk', 'ai-risk-register'], 'guides' => ['nist-ai-rmf-vs-eu-ai-act'],
        ],
        'ai-impact-assessment' => [
            'title' => 'AI System Impact Assessment (ISO/IEC 42005)', 'type' => 'framework', 'region' => 'Global', 'questions' => 21, 'minutes' => 6, 'domains' => 6,
            'summary' => 'How you assess what your AI systems do to individuals, groups and society, following the guidance in ISO/IEC 42005:2025: scoping, stakeholders, mitigation, records and review triggers.',
            'frameworks' => ['iso_42005'], 'templates' => ['ai-impact-assessment', 'ai-workforce-impact-assessment'],
        ],
        'soc-2-ai-companies' => [
            'title' => 'SOC 2 for AI Companies Assessment', 'type' => 'framework', 'region' => 'Global', 'questions' => 25, 'minutes' => 7, 'domains' => 7,
            'summary' => 'Getting an AI product ready for a SOC 2 examination: the AICPA Trust Services Criteria applied to training data, model changes, customer data segregation and output integrity. A self-assessment is not a SOC 2 report.',
            'audiences' => ['providers'], 'templates' => ['ai-audit-evidence-tracker'],
        ],
        'ai-security-controls' => [
            'title' => 'AI Security Controls Assessment', 'type' => 'security', 'region' => 'Global', 'questions' => 22, 'minutes' => 6, 'domains' => 6,
            'summary' => 'Securing AI end to end: threat modelling for machine learning, access to models and data, adversarial testing, secure deployment, monitoring and incident handling.',
            'frameworks' => ['mitre_atlas'], 'templates' => ['ai-red-team-test-plan'],
        ],
        'genai-llm-security' => [
            'title' => 'Generative AI & LLM Security Assessment', 'type' => 'security', 'region' => 'Global', 'questions' => 22, 'minutes' => 6, 'domains' => 6,
            'summary' => 'Prompt injection, data leakage, excessive agency and the rest of the OWASP Top 10 for LLM Applications, turned into checks you can evidence, from input handling to access control and red teaming.',
            'frameworks' => ['owasp_llm_top10'], 'audiences' => ['generative-ai'], 'templates' => ['ai-red-team-test-plan'],
        ],
        'ai-agent-risk' => [
            'title' => 'AI Agent & Autonomy Risk Assessment', 'type' => 'security', 'region' => 'Global', 'questions' => 23, 'minutes' => 6, 'domains' => 6,
            'summary' => 'AI agents that call tools, send email or change records need tighter limits than a chatbot: how you scope autonomy and permissions, how you stop them, and how you test and watch them.',
            'audiences' => ['generative-ai'], 'templates' => ['ai-agent-registry'],
        ],
        'ai-supply-chain-security' => [
            'title' => 'AI Supply Chain Security Assessment', 'type' => 'security', 'region' => 'Global', 'questions' => 21, 'minutes' => 6, 'domains' => 6,
            'summary' => 'The models, datasets, libraries and hosted services under your AI: provenance, integrity checks, vulnerability management and the MLOps pipeline.',
            'templates' => ['ai-vendor-due-diligence-questionnaire', 'ai-model-card'],
        ],
        'ai-incident-response' => [
            'title' => 'AI Incident Response Readiness Assessment', 'type' => 'security', 'region' => 'Global', 'questions' => 23, 'minutes' => 6, 'domains' => 6,
            'summary' => 'When a model misbehaves, who hears first and how fast it can be switched off: detection, triage, containment, investigation, notification and learning for AI incidents.',
            'templates' => ['ai-incident-response-playbook', 'ai-post-market-monitoring-plan'],
        ],
        'shadow-ai-discovery' => [
            'title' => 'Shadow AI Discovery Assessment', 'type' => 'security', 'region' => 'Global', 'questions' => 21, 'minutes' => 6, 'domains' => 6,
            'summary' => 'Whether you can see staff use of unapproved AI tools, offer sanctioned alternatives and stop sensitive data leaving, from telemetry to training.',
            'templates' => ['acceptable-use-policy', 'ai-system-inventory'],
        ],
        'ai-governance-maturity' => [
            'title' => 'AI Governance Maturity Assessment', 'type' => 'governance', 'region' => 'Global', 'questions' => 25, 'minutes' => 7, 'domains' => 7,
            'summary' => 'A framework-neutral view of how well AI is governed in practice: accountability, policy, inventory, and how risk, monitoring and training really work. For boards, CISOs and risk leads.',
            'templates' => ['ai-governance-policy-raci', 'ai-board-reporting-pack'], 'guides' => ['ai-governance-for-startups'],
        ],
        'ai-acceptable-use-policy' => [
            'title' => 'AI Policy & Acceptable Use Assessment', 'type' => 'governance', 'region' => 'Global', 'questions' => 23, 'minutes' => 6, 'domains' => 7,
            'summary' => 'Whether your AI policy works on a busy day: approved tools, the data that must never go into a prompt, training and enforcement.',
            'templates' => ['acceptable-use-policy', 'ai-governance-policy-raci'], 'guides' => ['ai-governance-for-startups'],
        ],
        'ai-inventory-readiness' => [
            'title' => 'AI Model & Data Inventory Readiness', 'type' => 'governance', 'region' => 'Global', 'questions' => 21, 'minutes' => 6, 'domains' => 6,
            'summary' => 'Whether your inventory of AI systems, models and datasets is complete, current, owned and tied into your risk and change processes.',
            'templates' => ['ai-system-inventory', 'public-sector-ai-use-case-inventory', 'ai-data-governance-register'], 'audiences' => ['public-sector'],
        ],
        'ai-adoption-readiness' => [
            'title' => 'AI Adoption Readiness Assessment', 'type' => 'governance', 'region' => 'Global', 'questions' => 24, 'minutes' => 6, 'domains' => 7,
            'summary' => 'Before AI moves from pilots to production: use cases, data, skills, governance, security and privacy, value measures and change.',
            'templates' => ['ai-use-case-intake-triage'], 'guides' => ['ai-governance-for-startups'],
        ],
        'ai-procurement-controls' => [
            'title' => 'AI Procurement Controls Assessment', 'type' => 'governance', 'region' => 'Global', 'questions' => 23, 'minutes' => 6, 'domains' => 6,
            'summary' => 'Controls on AI that arrives through purchases: intake, risk tiering, due diligence, contract terms, pilots, renewal and exit.',
            'templates' => ['ai-vendor-due-diligence-questionnaire', 'ai-contract-clauses'], 'audiences' => ['public-sector'],
        ],
        'ai-vendor-risk' => [
            'title' => 'Third-Party AI Vendor Risk Assessment', 'type' => 'governance', 'region' => 'Global', 'questions' => 24, 'minutes' => 6, 'domains' => 6,
            'summary' => 'Where AI sits inside the products you buy, what each vendor does with your data, and what your contracts, monitoring and exit plans cover.',
            'templates' => ['ai-vendor-due-diligence-questionnaire', 'ai-contract-clauses'], 'audiences' => ['deployers', 'financial-services'],
        ],
        'ai-model-risk-management' => [
            'title' => 'AI Model Risk Management Assessment', 'type' => 'governance', 'region' => 'United States', 'questions' => 25, 'minutes' => 7, 'domains' => 6,
            'summary' => 'Model risk from build to retirement: inventory and tiering, development standards, independent validation, monitoring, change control and vendor models. For model risk, validation and audit teams.',
            'audiences' => ['financial-services'], 'templates' => ['ai-model-card', 'ai-substantial-modification-change-log'],
        ],
        'ai-hiring-bias' => [
            'title' => 'AI in Hiring & Employment Bias Assessment', 'type' => 'privacy', 'region' => 'Global', 'questions' => 25, 'minutes' => 7, 'domains' => 6,
            'summary' => 'AI in recruiting, screening and promotion: tool inventory, bias audits, candidate notices, human review, vendor terms and records.',
            'policies' => ['us-new-york-city-local-law-144-automated-employment-decision-tools', 'us-illinois-hb-3773-ai-in-employment', 'us-illinois-artificial-intelligence-video-interview-act'], 'audiences' => ['hr-and-recruitment'], 'templates' => ['employment-ai-bias-audit-kit'], 'guides' => ['ai-hiring-compliance-laws'],
        ],
        'algorithmic-bias-fairness' => [
            'title' => 'Algorithmic Bias & Fairness Assessment', 'type' => 'privacy', 'region' => 'Global', 'questions' => 23, 'minutes' => 6, 'domains' => 6,
            'summary' => 'How you define fairness, test data and outcomes across groups, mitigate, monitor and govern algorithms that shape decisions about people.',
            'templates' => ['employment-ai-bias-audit-kit', 'adverse-decision-explanation-and-appeal-kit'], 'audiences' => ['financial-services'],
        ],
        'responsible-ai-principles' => [
            'title' => 'Responsible AI Principles Assessment', 'type' => 'privacy', 'region' => 'Global', 'questions' => 23, 'minutes' => 6, 'domains' => 6,
            'summary' => 'Whether published AI principles change how systems are approved, tested and run: accountability, fairness, transparency, privacy, safety and human oversight.',
            'frameworks' => ['oecd_ai_principles'], 'templates' => ['ai-governance-policy-raci'],
        ],
    ],
];
