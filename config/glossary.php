<?php

/*
|--------------------------------------------------------------------------
| AI governance glossary (/glossary)
|--------------------------------------------------------------------------
|
| Plain-language explanations of the terms the records use, each naming the
| instrument it comes from and linking to the records that apply it. These are
| paraphrases for orientation, not the legal text: the page says so, and every
| entry links the official source for the binding wording. Dates are left out
| on purpose; they move (the 2026 Omnibus moved several) and the policy pages
| carry them with their sources.
|
| The vocabulary in data/taxonomies/terms.yaml (actors, system types, risk
| categories) is added to the page from the database, so a term defined there
| is not repeated here.
|
| 'see' entries are [label, route name, route parameters]. Each term also has a page
| at /glossary/{key}; 'match' lists extra phrases that count as the records using the
| term (the term without its parenthetical, and its abbreviation, always count).
|
*/

$euAiAct = ['EU AI Act (Regulation (EU) 2024/1689)', 'https://eur-lex.europa.eu/eli/reg/2024/1689/oj'];

return [
    'terms' => [
        'ai-system' => [
            'term' => 'AI system',
            'definition' => 'A machine-based system that operates with some autonomy, may adapt after it is deployed, and infers from the input it receives how to generate outputs such as predictions, content, recommendations or decisions that can influence physical or virtual environments. This is the EU AI Act\'s definition, which follows the OECD\'s; whether software is an "AI system" decides whether the Act applies to it at all.',
            'source' => $euAiAct,
            'see' => [['EU AI Act', 'policies.show', 'eu-ai-act'], ['AI system inventory control', 'controls.show', 'ai-system-inventory']],
        ],
        'general-purpose-ai-model' => [
            'term' => 'General-purpose AI model (GPAI)',
            'definition' => 'An AI model, typically trained on a large amount of data with self-supervision at scale, that shows significant generality, can perform a wide range of distinct tasks and can be built into many downstream systems. Models used only for research or prototyping before release are excluded. Providers of these models have their own duties under the EU AI Act, separate from the duties attached to AI systems.',
            'source' => $euAiAct,
            'see' => [['Duties of AI model providers', 'obligations.index', ['actor' => 'gpai_provider']]],
        ],
        'systemic-risk' => [
            'term' => 'Systemic risk (general-purpose AI)',
            'definition' => 'Under the EU AI Act, a general-purpose AI model has systemic risk when it has high-impact capabilities, which is presumed when the cumulative compute used to train it exceeds 10²⁵ floating-point operations, or when the Commission designates it. Such models carry extra duties: model evaluation including adversarial testing, assessing and mitigating systemic risks, reporting serious incidents, and cybersecurity protection.',
            'source' => $euAiAct,
            'see' => [['Frontier model safety framework control', 'controls.show', 'frontier-model-safety-framework'], ['Red-team testing control', 'controls.show', 'genai-red-team-testing']],
        ],
        'high-risk-ai-system' => [
            'term' => 'High-risk AI system',
            'match' => ['high-risk AI'],
            'definition' => 'In the EU AI Act, an AI system that is a safety component of (or is itself) a product covered by the EU product-safety laws listed in Annex I and needing third-party conformity assessment, or one used in an area listed in Annex III, such as biometrics, critical infrastructure, education, employment, access to essential services, law enforcement, migration and the administration of justice. A provider may document that an Annex III system is not high-risk when it does not pose a significant risk of harm.',
            'source' => $euAiAct,
            'see' => [['Risk management duties', 'obligations.index', ['category' => 'risk_management']], ['EU AI Act role and risk classifier', 'templates.show', 'eu-ai-act-role-risk-classifier']],
        ],
        'prohibited-ai-practices' => [
            'term' => 'Prohibited AI practices',
            'match' => ['prohibited practice', 'prohibited AI practice'],
            'definition' => 'Uses of AI the EU AI Act bans outright, including manipulative or deceptive techniques that cause significant harm, exploiting vulnerabilities, social scoring, predicting crime from profiling alone, untargeted scraping of facial images, emotion recognition in workplaces and schools (with narrow exceptions), biometric categorisation by sensitive traits, and most real-time remote biometric identification by police in public spaces.',
            'source' => $euAiAct,
            'see' => [['Prohibited practice duties', 'obligations.index', ['category' => 'prohibited_practice']], ['Prohibited-use screening control', 'controls.show', 'prohibited-use-screening-gate']],
        ],
        'intended-purpose' => [
            'term' => 'Intended purpose',
            'definition' => 'The use a provider intends an AI system for, including the context and conditions of use, as stated in its instructions, marketing and technical documentation. Classification as high-risk, and most provider duties, are assessed against the intended purpose; using a system outside it can shift provider duties onto the user.',
            'source' => $euAiAct,
        ],
        'substantial-modification' => [
            'term' => 'Substantial modification',
            'definition' => 'A change to an AI system after it is placed on the market that the provider\'s original conformity assessment did not foresee, and that affects its compliance or changes its intended purpose. Under the EU AI Act, whoever makes a substantial modification to a high-risk system can become its provider, with the provider\'s duties.',
            'source' => $euAiAct,
            'see' => [['Release and change management control', 'controls.show', 'model-release-and-change-management-gate']],
        ],
        'conformity-assessment' => [
            'term' => 'Conformity assessment',
            'definition' => 'The process of showing that a high-risk AI system meets the EU AI Act\'s requirements before it is placed on the market or put into service, either by the provider\'s own internal check or with a notified body. It ends with an EU declaration of conformity and CE marking, and for most Annex III systems a registration in the EU database.',
            'source' => $euAiAct,
            'see' => [['Conformity assessment duties', 'obligations.index', ['category' => 'conformity_assessment']], ['Conformity assessment and registration control', 'controls.show', 'conformity-assessment-and-registration']],
        ],
        'notified-body' => [
            'term' => 'Notified body',
            'definition' => 'An independent conformity assessment body designated by an EU member state to assess high-risk AI systems where the EU AI Act requires a third party, for example certain biometric systems.',
            'source' => $euAiAct,
        ],
        'quality-management-system' => [
            'term' => 'Quality management system (QMS)',
            'definition' => 'The documented policies, procedures and instructions a provider of a high-risk AI system keeps to ensure compliance across the system\'s life: design, testing, data management, risk management, post-market monitoring, incident reporting and accountability. Required of providers by the EU AI Act.',
            'source' => $euAiAct,
            'see' => [['Quality management duties', 'obligations.index', ['category' => 'quality_management']], ['Quality management system control', 'controls.show', 'ai-quality-management-system']],
        ],
        'post-market-monitoring' => [
            'term' => 'Post-market monitoring',
            'definition' => 'The activities a provider carries out to collect and review data on how its AI systems perform once in use, so that it can spot the need for corrective or preventive action. High-risk providers must have a documented post-market monitoring plan.',
            'source' => $euAiAct,
            'see' => [['Post-market monitoring duties', 'obligations.index', ['category' => 'post_market_monitoring']], ['Post-market monitoring plan template', 'templates.show', 'ai-post-market-monitoring-plan']],
        ],
        'serious-incident' => [
            'term' => 'Serious incident',
            'definition' => 'An incident or malfunction of an AI system that directly or indirectly leads to death or serious harm to a person\'s health, a serious and irreversible disruption of critical infrastructure, an infringement of obligations that protect fundamental rights, or serious harm to property or the environment. Providers of high-risk systems must report serious incidents to the market surveillance authority.',
            'source' => $euAiAct,
            'see' => [['Incident duties', 'obligations.index', ['category' => 'incident_handling']], ['AI incident response playbook', 'templates.show', 'ai-incident-response-playbook']],
        ],
        'human-oversight' => [
            'term' => 'Human oversight',
            'definition' => 'Design and operating measures that let people effectively oversee an AI system while it is in use: understand its capabilities and limits, watch for automation bias, interpret its output, and decide not to use it, override it or stop it. Required for high-risk systems under the EU AI Act and a common expectation in other frameworks.',
            'source' => $euAiAct,
            'see' => [['Human oversight duties', 'obligations.index', ['category' => 'human_oversight']], ['Human oversight procedure template', 'templates.show', 'human-oversight-procedure']],
        ],
        'fundamental-rights-impact-assessment' => [
            'term' => 'Fundamental rights impact assessment (FRIA)',
            'definition' => 'An assessment, before first use, of how a high-risk AI system could affect the people it is used on: who is affected, the specific risks of harm, the human oversight in place and what happens if the risks materialise. The EU AI Act requires it of public bodies, private bodies providing public services, and deployers of systems for credit scoring and for life and health insurance pricing.',
            'source' => $euAiAct,
            'see' => [['Impact assessment duties', 'obligations.index', ['category' => 'impact_assessment']], ['FRIA template', 'templates.show', 'fundamental-rights-impact-assessment']],
        ],
        'ai-literacy' => [
            'term' => 'AI literacy',
            'definition' => 'The skills, knowledge and understanding that let providers, deployers and affected people use AI systems in an informed way and be aware of their opportunities, risks and possible harm. The EU AI Act asks providers and deployers to take measures to ensure a sufficient level of AI literacy among the staff who deal with AI systems.',
            'source' => $euAiAct,
            'see' => [['AI literacy duties', 'obligations.index', ['category' => 'ai_literacy']], ['AI literacy training plan', 'templates.show', 'ai-literacy-training-plan']],
        ],
        'deep-fake' => [
            'term' => 'Deep fake',
            'match' => ['deepfake', 'deep-fake'],
            'definition' => 'AI-generated or AI-manipulated image, audio or video content that resembles existing people, objects, places or events and would falsely appear authentic. The EU AI Act requires deployers to disclose that such content is artificial, with lighter rules for evidently artistic or satirical work.',
            'source' => $euAiAct,
            'see' => [['Transparency duties', 'obligations.index', ['category' => 'transparency']], ['Synthetic content labelling control', 'controls.show', 'synthetic-content-labelling-and-provenance']],
        ],
        'regulatory-sandbox' => [
            'term' => 'AI regulatory sandbox',
            'match' => ['regulatory sandbox'],
            'definition' => 'A controlled framework run by a competent authority in which providers can develop, train, validate and test innovative AI systems for a limited time under regulatory supervision. The EU AI Act requires each member state to set up at least one.',
            'source' => $euAiAct,
        ],
        'ai-management-system' => [
            'term' => 'AI management system (ISO/IEC 42001)',
            'match' => ['ISO 42001', 'ISO/IEC 42001'],
            'definition' => 'The policies, objectives and processes an organisation puts in place to develop, provide or use AI systems responsibly, structured like other ISO management systems (plan, do, check, act). ISO/IEC 42001 sets the requirements for one and can be certified by an accredited body.',
            'source' => ['ISO/IEC 42001:2023', 'https://www.iso.org/standard/81230.html'],
            'see' => [['ISO/IEC 42001 record', 'policies.show', 'iso-iec-42001-2023-ai-management-system'], ['ISO 42001 gap assessment', 'templates.show', 'iso-42001-gap-assessment']],
        ],
        'ai-rmf' => [
            'term' => 'NIST AI Risk Management Framework (AI RMF)',
            'match' => ['NIST AI RMF', 'AI Risk Management Framework'],
            'definition' => 'A voluntary framework from the US National Institute of Standards and Technology for managing the risks of AI systems, organised in four functions: Govern (culture and accountability), Map (context and risks), Measure (analysis and tracking) and Manage (prioritising and acting on risks).',
            'source' => ['NIST AI 100-1', 'https://www.nist.gov/itl/ai-risk-management-framework'],
            'see' => [['NIST AI RMF record', 'policies.show', 'us-nist-ai-rmf'], ['NIST, EU and ISO crosswalk', 'templates.show', 'nist-eu-iso-crosswalk']],
        ],
        'bias-audit' => [
            'term' => 'Bias audit (automated employment decision tools)',
            'match' => ['bias audit', 'automated employment decision tool'],
            'definition' => 'Under New York City Local Law 144, an impartial evaluation by an independent auditor of an automated employment decision tool, reporting selection or scoring rates and impact ratios by sex and race or ethnicity. Employers must have one done within a year before using the tool, publish a summary, and notify candidates.',
            'source' => ['NYC Department of Consumer and Worker Protection', 'https://www.nyc.gov/site/dca/about/automated-employment-decision-tools.page'],
            'see' => [['Local Law 144 record', 'policies.show', 'us-new-york-city-local-law-144-automated-employment-decision-tools']],
        ],
        'red-teaming' => [
            'term' => 'Red teaming (AI)',
            'match' => ['red-teaming', 'red team', 'red-team'],
            'definition' => 'Structured adversarial testing in which testers try to make an AI system fail: produce harmful output, leak data, ignore its instructions or be misused. It is one of the evaluations expected of the most capable general-purpose models and a common control for generative AI.',
            'source' => $euAiAct,
            'see' => [['Red-team testing control', 'controls.show', 'genai-red-team-testing'], ['AI red team test plan', 'templates.show', 'ai-red-team-test-plan']],
        ],
        'model-card' => [
            'term' => 'Model card',
            'definition' => 'A short document published with a machine-learning model that states its intended use, the data it was evaluated on, its performance across groups and conditions, and its known limitations. The format comes from Mitchell et al. (2019) and now meets part of the documentation duties several laws place on model providers.',
            'source' => ['Mitchell et al., Model Cards for Model Reporting (2019)', 'https://arxiv.org/abs/1810.03993'],
            'see' => [['Technical documentation control', 'controls.show', 'technical-documentation-and-model-cards'], ['AI model card template', 'templates.show', 'ai-model-card']],
        ],
    ],
];
