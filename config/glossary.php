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
        'ai-grc' => [
            'term' => 'AI GRC (governance, risk and compliance)',
            'group' => 'Governance, risk and compliance',
            'question' => 'What is AI GRC?',
            'answer' => 'AI GRC (governance, risk and compliance) is the integrated discipline of assigning accountability and policy for AI systems, identifying and treating the risks they create, and showing conformity with the laws, standards and contracts that apply to them, usually through an AI management system and an evidence trail.',
            'definition' => 'The organisational practice that joins three activities for AI: governance (owners, policies, approval and oversight across an AI system\'s life cycle), risk management (identifying, assessing and treating risks to people, the organisation and third parties) and compliance (meeting legal duties, such as those of the EU AI Act, and keeping the records that show it). ISO/IEC 42001 and the NIST AI RMF give it structure; it is a practice term, not one defined by a single law.',
            'why' => [
                'Regulators ask for evidence, not intentions: the EU AI Act requires providers of high-risk AI systems to operate a risk management system and a quality management system and to keep documentation showing both.',
                'One control set can answer several regimes: an inventory, risk assessment and oversight procedure mapped to ISO/IEC 42001 and the NIST AI RMF serves auditors, customers and regulators alike.',
                'Without an owner and an inventory entry for each AI system, no other duty (assessment, oversight, incident reporting) can be met reliably.',
            ],
            'related' => ['ai-risk', 'ai-management-system', 'ai-rmf'],
            'source' => null,
            'see' => [
                ['ISO 42001 gap assessment', 'templates.show', 'iso-42001-gap-assessment'],
                ['AI governance RACI', 'templates.show', 'ai-governance-policy-raci'],
                ['AI system inventory', 'templates.show', 'ai-system-inventory'],
            ],
            'match' => ['AI governance, risk and compliance'],
            'autolink' => false,
            'faq' => true,
        ],
        'ai-risk' => [
            'term' => 'AI risk',
            'group' => 'Governance, risk and compliance',
            'answer' => 'AI risk is the combination of how likely an AI system is to cause harm and how severe that harm would be, to people, organisations or society. The EU AI Act defines risk this way, and the NIST AI RMF uses the same probability-and-consequence composite.',
            'definition' => 'The EU AI Act defines risk as the combination of the probability of an occurrence of harm and the severity of that harm (Article 3(2)); the NIST AI Risk Management Framework describes it as a composite of an event\'s probability and the magnitude of its consequences. AI risks include harm to health, safety and fundamental rights, discrimination, security failures and misinformation, and they change as a system is retrained, repurposed or connected to new tools.',
            'why' => [
                'Risk level decides the legal regime: the EU AI Act prohibits some practices, regulates high-risk systems in detail and leaves most other systems to transparency duties.',
                'A documented, repeatable risk assessment is central to ISO/IEC 42001 and to the Map and Measure functions of the NIST AI RMF.',
                'Risk changes after deployment, so assessments need re-running when the data, the model or the use changes.',
            ],
            'related' => ['high-risk-ai-system', 'ai-impact-assessment', 'ai-rmf'],
            'source' => ['EU AI Act (Regulation (EU) 2024/1689), Article 3(2)', $euAiAct[1]],
            'see' => [
                ['AI risk register', 'templates.show', 'ai-risk-register'],
                ['AI risk assessment control', 'controls.show', 'ai-risk-assessment'],
                [
                    'AI risk and incidents',
                    'risk.index',
                    [],
                ],
            ],
            'autolink' => false,
            'faq' => true,
        ],
        'eu-ai-act' => [
            'term' => 'EU AI Act',
            'group' => 'Governance, risk and compliance',
            'question' => 'What is the EU AI Act?',
            'answer' => 'The EU AI Act (Regulation (EU) 2024/1689) is the European Union\'s horizontal law on artificial intelligence. It prohibits certain AI practices, sets requirements for high-risk AI systems, imposes transparency duties and regulates general-purpose AI models, with obligations applying in stages from February 2025.',
            'definition' => 'A regulation in force since 1 August 2024 that applies to providers, deployers, importers and distributors of AI systems placed on the EU market or whose output is used in the EU. It takes a risk-based approach: prohibited practices, high-risk systems (products covered by Annex I and uses listed in Annex III), transparency duties for certain systems, and separate duties for providers of general-purpose AI models. The dates of individual duties are on the record page, with their sources.',
            'why' => [
                'It reaches beyond the EU: it applies to providers placing AI systems on the EU market and to providers and deployers outside the EU where the system\'s output is used in the EU.',
                'Fines for prohibited practices reach EUR 35 million or 7% of total worldwide annual turnover, whichever is higher.',
                'Deployers have duties too, not only developers: human oversight, monitoring, keeping logs and, for some, a fundamental rights impact assessment.',
            ],
            'related' => ['high-risk-ai-system', 'prohibited-ai-practices', 'general-purpose-ai-model'],
            'source' => $euAiAct,
            'see' => [
                ['EU AI Act role and risk classifier', 'templates.show', 'eu-ai-act-role-risk-classifier'],
                ['High-risk deployer compliance pack', 'templates.show', 'high-risk-deployer-compliance-pack'],
            ],
            'canonical' => ['policies.show', 'eu-ai-act'],
            'autolink' => false,
            'faq' => true,
        ],
        'google-saif' => [
            'term' => 'Google Secure AI Framework (SAIF)',
            'group' => 'AI security',
            'question' => 'What is Google\'s Secure AI Framework (SAIF)?',
            'answer' => 'Google\'s Secure AI Framework (SAIF) is a conceptual framework, published by Google in June 2023, for securing AI systems. It sets out six core elements, from extending established security foundations to AI to placing AI system risks in the context of business processes.',
            'definition' => 'A voluntary industry framework, not a law or a standard. Its six core elements are: expand strong security foundations to the AI ecosystem; extend detection and response to bring AI into the organisation\'s threat universe; automate defences to keep pace with existing and new threats; harmonise platform-level controls for consistent security across the organisation; adapt controls to create faster feedback loops for AI deployment; and contextualise AI system risks in surrounding business processes. Google notes the elements are not meant to be applied in sequence.',
            'why' => [
                'It gives security teams a shared vocabulary for AI-specific threats that conventional application security programmes do not cover.',
                'It works alongside the OWASP Top 10 for LLM Applications (specific weaknesses) and ISO/IEC 42001 (the management system) as the security layer of an AI programme.',
                'It is voluntary: no regulator requires it, but it documents a secure-by-design approach in customer and vendor reviews.',
            ],
            'related' => ['owasp-llm-top-10', 'prompt-injection', 'data-poisoning'],
            'source' => ['Google, Secure AI Framework (SAIF)', 'https://safety.google/cybersecurity-advancements/saif/'],
            'see' => [
                ['AI red team test plan', 'templates.show', 'ai-red-team-test-plan'],
            ],
            'match' => ['SAIF', 'Secure AI Framework'],
        ],
        'owasp-llm-top-10' => [
            'term' => 'OWASP Top 10 for LLM Applications',
            'group' => 'AI security',
            'question' => 'What is the OWASP Top 10 for LLM Applications?',
            'answer' => 'The OWASP Top 10 for LLM Applications is the Open Worldwide Application Security Project\'s list of the ten most critical security risks in applications built on large language models. The 2025 edition runs from LLM01 Prompt Injection to LLM10 Unbounded Consumption.',
            'definition' => 'A community-maintained awareness list, first published in 2023 and revised for 2025. The 2025 entries are: prompt injection, sensitive information disclosure, supply chain, data and model poisoning, improper output handling, excessive agency, system prompt leakage, vector and embedding weaknesses, misinformation, and unbounded consumption. Each entry describes the weakness, example attack scenarios and mitigations.',
            'why' => [
                'It is the common checklist for LLM security reviews, penetration tests and vendor security questionnaires.',
                'Several entries correspond to legal duties: the EU AI Act requires high-risk AI systems to resist attempts to manipulate them, naming data poisoning, model poisoning and adversarial inputs (Article 15).',
                'It changes as attacks change: the 2025 edition added system prompt leakage and vector and embedding weaknesses.',
            ],
            'related' => ['prompt-injection', 'agentic-ai', 'google-saif'],
            'source' => ['OWASP Top 10 for LLM Applications 2025', 'https://owasp.org/www-project-top-10-for-large-language-model-applications/'],
            'see' => [
                ['AI red team test plan', 'templates.show', 'ai-red-team-test-plan'],
            ],
            'match' => ['OWASP'],
        ],
        'prompt-injection' => [
            'term' => 'Prompt injection',
            'group' => 'AI security',
            'answer' => 'Prompt injection is an attack in which crafted input, typed by a user or hidden in content the model reads, overrides an LLM application\'s instructions, making it ignore its system prompt, disclose data or take unintended actions. OWASP ranks it first (LLM01) among LLM application risks.',
            'definition' => 'Direct prompt injection comes from the user\'s own input (often called jailbreaking); indirect prompt injection arrives through external content the model processes, such as web pages, documents, emails or tool results. Because instructions and data share one channel, filtering cannot fully prevent it; mitigations limit what a successful injection can do: least-privilege tools, separating untrusted content, validating outputs and human approval for consequential actions. Exposure of the system prompt itself is a separate OWASP entry (LLM07 System Prompt Leakage).',
            'why' => [
                'Agents that read email, browse the web or call tools turn a successful injection into real actions: data exfiltration, transactions or code changes.',
                'Resilience against manipulated inputs is a legal requirement for high-risk AI systems under the EU AI Act (Article 15) and a standard red-team test case.',
                'It cannot be patched once and closed: it needs design controls and recurring testing in the release process.',
            ],
            'related' => ['agentic-ai', 'red-teaming', 'owasp-llm-top-10'],
            'source' => ['OWASP Top 10 for LLM Applications 2025, LLM01 Prompt Injection', 'https://owasp.org/www-project-top-10-for-large-language-model-applications/'],
            'see' => [
                ['AI red team test plan', 'templates.show', 'ai-red-team-test-plan'],
                ['Red-team testing control', 'controls.show', 'genai-red-team-testing'],
            ],
            'match' => ['jailbreak', 'system prompt leakage'],
        ],
        'data-poisoning' => [
            'term' => 'Data poisoning',
            'group' => 'AI security',
            'answer' => 'Data poisoning is an attack that corrupts an AI model by inserting or altering its training or fine-tuning data, so that the model learns hidden behaviours, errors or backdoors. NIST classifies poisoning as a training-stage attack in its adversarial machine learning taxonomy (NIST AI 100-2).',
            'definition' => 'Poisoning can degrade a model across the board, cause targeted errors, or plant a backdoor that activates on a trigger. Model poisoning, its close relative, tampers with model weights or pre-trained components. Defences include data provenance and integrity checks, supplier controls for datasets and pre-trained models, anomaly detection on training data, and evaluation for unexpected behaviour before release.',
            'why' => [
                'The EU AI Act names data poisoning and model poisoning among the attacks high-risk AI systems must be protected against (Article 15).',
                'Poisoned open datasets or pre-trained models can arrive through the supply chain, so sourcing data and models is a security control, not only a quality one.',
                'A backdoor can pass normal accuracy testing, so detection depends on provenance records and targeted evaluation.',
            ],
            'related' => ['ai-lineage', 'aibom', 'owasp-llm-top-10'],
            'source' => ['NIST AI 100-2 E2025, Adversarial Machine Learning: A Taxonomy and Terminology of Attacks and Mitigations', 'https://doi.org/10.6028/NIST.AI.100-2e2025'],
            'see' => [
                ['Data governance and dataset documentation control', 'controls.show', 'data-governance-and-dataset-documentation'],
                ['AI data governance register', 'templates.show', 'ai-data-governance-register'],
            ],
            'match' => ['model poisoning'],
        ],
        'algorithmic-bias' => [
            'term' => 'Algorithmic bias',
            'group' => 'Governance, risk and compliance',
            'answer' => 'Algorithmic bias is systematic error in an AI system\'s outputs that produces unjustified differences in treatment or outcomes for people or groups, for example by sex, race, age or disability. It can come from the data, the model\'s design, or the context in which the system is used.',
            'definition' => 'NIST\'s guidance on managing bias in AI (SP 1270) stresses that bias arises not only in data and algorithms but also in the human and societal context in which systems are built and used, so testing a model\'s statistics alone is not enough. Many laws address the effect rather than the cause: unlawful discrimination is prohibited whether or not a system was designed to discriminate, and some regimes require bias audits or impact assessments before use.',
            'why' => [
                'Discriminatory outcomes create liability under existing anti-discrimination, consumer and employment law, regardless of AI-specific rules.',
                'The EU AI Act requires providers of high-risk systems to examine training, validation and testing data for possible biases and to take measures to detect, prevent and mitigate them (Article 10).',
                'Hiring tools face specific checks, such as New York City\'s independent bias audit for automated employment decision tools.',
            ],
            'related' => ['bias-audit', 'ai-impact-assessment', 'ai-explainability'],
            'source' => ['NIST SP 1270, Towards a Standard for Identifying and Managing Bias in Artificial Intelligence', 'https://doi.org/10.6028/NIST.SP.1270'],
            'see' => [
                ['Employment AI bias audit kit', 'templates.show', 'employment-ai-bias-audit-kit'],
            ],
            'match' => ['algorithmic discrimination', 'AI bias'],
        ],
        'ai-lineage' => [
            'term' => 'AI lineage (data and model provenance)',
            'group' => 'Governance, risk and compliance',
            'question' => 'What is AI lineage?',
            'answer' => 'AI lineage is the documented record of where an AI system\'s data and models came from and how they changed: sources, licences, collection methods, preprocessing, training runs, versions and deployments. It lets an organisation trace an output or a defect back to its inputs.',
            'definition' => 'Lineage covers datasets (origin, consent and licence, cleaning and labelling), models (base model, fine-tuning, weight versions) and the pipeline between them. It is the evidence behind data governance, copyright and privacy compliance, incident investigation and reproducibility. Content provenance, marking AI-generated outputs as such, is a related but separate practice.',
            'why' => [
                'The EU AI Act requires data governance for high-risk systems covering data collection processes and the origin of data (Article 10), and providers of general-purpose AI models must publish a summary of the content used for training (Article 53).',
                'Without lineage, a poisoned dataset, a licence problem or a biased source cannot be traced to the systems it affected.',
                'It makes model changes auditable, which is needed to decide whether a change is a substantial modification.',
            ],
            'related' => ['data-poisoning', 'aibom', 'substantial-modification'],
            'source' => ['EU AI Act (Regulation (EU) 2024/1689), Article 10', $euAiAct[1]],
            'see' => [
                ['Training data provenance and copyright register', 'controls.show', 'training-data-provenance-and-copyright-register'],
                ['AI data governance register', 'templates.show', 'ai-data-governance-register'],
            ],
            'match' => ['data lineage', 'data provenance', 'model provenance', 'training data provenance'],
        ],
        'ai-impact-assessment' => [
            'term' => 'AI impact assessment',
            'group' => 'Governance, risk and compliance',
            'question' => 'What is an AI impact assessment?',
            'answer' => 'An AI impact assessment is a structured evaluation, before deployment and whenever things change, of how an AI system and its foreseeable uses may affect individuals, groups and society, recording the impacts found and the measures taken to address them.',
            'definition' => 'ISO/IEC 42005:2025 gives guidance on carrying out AI system impact assessments and on fitting them into risk management and an AI management system; ISO/IEC 42001 requires one. Law sets specific versions: the EU AI Act\'s fundamental rights impact assessment for certain deployers of high-risk systems, data protection impact assessments under the GDPR, and algorithmic impact assessments in public-sector rules such as Canada\'s Directive on Automated Decision-Making.',
            'why' => [
                'It is a required step in ISO/IEC 42001 and, in specific forms, in law: the FRIA, the DPIA and public-sector algorithmic impact assessments.',
                'It surfaces harms a technical risk assessment misses: effects on rights, access to services and particular groups.',
                'Its record is the evidence a regulator, auditor or court asks for when a decision is challenged.',
            ],
            'related' => ['fundamental-rights-impact-assessment', 'ai-risk', 'algorithmic-bias'],
            'source' => ['ISO/IEC 42005:2025, AI system impact assessment', 'https://www.iso.org/standard/44545.html'],
            'see' => [
                ['AI impact assessment template', 'templates.show', 'ai-impact-assessment'],
                ['AI impact assessment control', 'controls.show', 'ai-impact-assessment'],
            ],
            'match' => ['algorithmic impact assessment', 'AI system impact assessment'],
        ],
        'aibom' => [
            'term' => 'AI bill of materials (AIBOM)',
            'group' => 'Governance, risk and compliance',
            'question' => 'What is an AI bill of materials (AIBOM)?',
            'answer' => 'An AI bill of materials (AIBOM) is a machine-readable inventory of the components of an AI system: models, datasets, software dependencies and services, with their versions, licences and provenance. It extends the software bill of materials (SBOM) to AI.',
            'definition' => 'Formats include CycloneDX\'s machine learning bill of materials (ML-BOM) and the AI and dataset profiles introduced in SPDX 3.0. An AIBOM lets buyers and security teams see which third-party models and datasets a system depends on, check licences, and find affected systems when a component turns out to be vulnerable, poisoned or withdrawn.',
            'why' => [
                'It turns AI supply-chain risk, an OWASP Top 10 entry (LLM03 Supply Chain), into something that can be checked and monitored.',
                'It answers the first question in an incident: which of our systems use the affected model or dataset?',
                'It feeds documentation duties, such as the technical documentation the EU AI Act requires for high-risk systems (Annex IV).',
            ],
            'related' => ['ai-lineage', 'data-poisoning', 'owasp-llm-top-10'],
            'source' => null,
            'see' => [
                ['AI system inventory', 'templates.show', 'ai-system-inventory'],
                ['Third-party AI due diligence control', 'controls.show', 'third-party-ai-due-diligence'],
            ],
            'match' => ['AI-BOM', 'ML-BOM'],
        ],
        'shadow-ai' => [
            'term' => 'Shadow AI',
            'group' => 'Governance, risk and compliance',
            'answer' => 'Shadow AI is the use of AI tools, models or features inside an organisation without the knowledge or approval of the people responsible for security, privacy and compliance, such as staff pasting data into public chatbots or AI features switched on in existing software.',
            'definition' => 'It is the AI form of shadow IT. Because many AI services are free, browser-based or built into software already licensed, use spreads before any policy exists. It is managed through an acceptable use policy, an inventory and intake process for AI use cases, approved alternatives, and technical controls on data leaving the organisation.',
            'why' => [
                'Unapproved use can disclose confidential or personal data to third parties, breaching contracts and data protection law.',
                'AI systems nobody knows about cannot be classified, assessed or overseen, so the legal duties attached to them go unmet.',
                'ISO/IEC 42001 and the NIST AI RMF both expect an organisation to know which AI systems it uses; shadow AI is what an inventory misses.',
            ],
            'related' => ['ai-grc', 'ai-literacy', 'aibom'],
            'source' => null,
            'see' => [
                ['AI acceptable use policy', 'templates.show', 'acceptable-use-policy'],
                ['AI use case intake and triage', 'templates.show', 'ai-use-case-intake-triage'],
                ['AI system inventory', 'templates.show', 'ai-system-inventory'],
            ],
            'match' => ['unsanctioned AI'],
        ],
        'ai-explainability' => [
            'term' => 'AI explainability',
            'group' => 'Governance, risk and compliance',
            'answer' => 'AI explainability is the ability of an AI system, or the people operating it, to give reasons for an output that are accurate and understandable to the person receiving them, and to recognise when the system is operating outside the conditions it was designed for.',
            'definition' => 'NIST\'s four principles of explainable AI (NISTIR 8312) are explanation, meaningful, explanation accuracy and knowledge limits. Explainability differs from transparency (disclosing that AI is used and how it works in general) and from interpretability (how far a model\'s internal workings can be understood). Laws usually require the result rather than a technique: reasons a person can understand and contest.',
            'why' => [
                'People affected by certain AI-assisted decisions have a right to an explanation: the EU AI Act gives one for decisions based on high-risk systems that significantly affect them (Article 86), alongside GDPR rights on automated decisions.',
                'Credit rules already require specific reasons for adverse decisions, such as adverse action notices in US consumer credit, whatever model produced them.',
                'Explanations are how errors and bias are found and how human oversight works in practice.',
            ],
            'related' => ['human-oversight', 'algorithmic-bias', 'model-card'],
            'source' => ['NISTIR 8312, Four Principles of Explainable Artificial Intelligence', 'https://nvlpubs.nist.gov/nistpubs/ir/2021/NIST.IR.8312.pdf'],
            'see' => [
                ['Adverse decision explanation and appeal kit', 'templates.show', 'adverse-decision-explanation-and-appeal-kit'],
                ['Decision explanation and appeal route control', 'controls.show', 'decision-explanation-and-appeal-route'],
            ],
            'match' => ['explainability', 'explainable AI', 'right to explanation', 'interpretability'],
        ],
        'ai-hallucination' => [
            'term' => 'AI hallucination (confabulation)',
            'group' => 'AI security',
            'question' => 'What is an AI hallucination?',
            'answer' => 'An AI hallucination is output from a generative AI system that is presented confidently but is false, fabricated or unsupported by its inputs, such as invented facts, citations or legal authorities. NIST\'s Generative AI Profile calls this risk confabulation.',
            'definition' => 'It follows from how language models generate text, predicting plausible continuations rather than retrieving verified facts, so it can be reduced but not eliminated. Mitigations include grounding answers in retrieved sources with citations, restricting use to tasks where errors are caught, human review of consequential outputs, and evaluating factual accuracy before release. OWASP lists the related risk as LLM09 Misinformation.',
            'why' => [
                'Courts have sanctioned lawyers for filing briefs that cited cases invented by AI.',
                'In regulated advice (legal, medical, financial) a confident wrong answer can be a compliance breach, not just an error.',
                'It is one of the twelve risks NIST\'s Generative AI Profile asks organisations to manage (NIST AI 600-1).',
            ],
            'related' => ['ai-explainability', 'human-oversight', 'red-teaming'],
            'source' => ['NIST AI 600-1, Artificial Intelligence Risk Management Framework: Generative Artificial Intelligence Profile', 'https://doi.org/10.6028/NIST.AI.600-1'],
            'see' => [
                ['Human oversight procedure', 'templates.show', 'human-oversight-procedure'],
            ],
            'match' => ['hallucination', 'confabulation'],
        ],
        'agentic-ai' => [
            'term' => 'Agentic AI',
            'group' => 'AI security',
            'answer' => 'Agentic AI is an AI system that pursues a goal by planning and taking actions with some autonomy, such as calling tools and APIs, browsing, writing and running code or sending messages, rather than only producing text for a person to act on.',
            'definition' => 'Agents combine a model with tools, memory and permissions. The main security risk is excessive agency (OWASP LLM06): an agent with more functionality, permissions or autonomy than its task needs can cause damage when its output is wrong, ambiguous or manipulated, for example through prompt injection. Controls include least-privilege tool access, authorisation enforced outside the model, rate limits, logging, and human approval for consequential actions.',
            'why' => [
                'An agent\'s mistake is an action, not a sentence: a payment, a deletion, an email or a code change.',
                'Where an agent is part of a high-risk system, EU AI Act duties on human oversight and logging govern how much authority it is given.',
                'Every tool and permission is attack surface, so an agent registry and a least-privilege review belong in the release process.',
            ],
            'related' => ['prompt-injection', 'human-oversight', 'owasp-llm-top-10'],
            'source' => ['OWASP Top 10 for LLM Applications 2025, LLM06 Excessive Agency', 'https://genai.owasp.org/llmrisk/llm062025-excessive-agency/'],
            'see' => [
                ['AI agent registry', 'templates.show', 'ai-agent-registry'],
            ],
            'match' => ['AI agent', 'autonomous agent', 'excessive agency'],
        ],
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
            'group' => 'Governance, risk and compliance',
            'question' => 'What is ISO/IEC 42001?',
            'answer' => 'ISO/IEC 42001 is the international standard for an AI management system: the policies, roles, risk and impact assessment processes, controls and continual improvement an organisation sets up to develop, provide or use AI responsibly. Published in December 2023, organisations can be certified against it.',
            'why' => [
                'Certification by an accredited body gives customers and regulators third-party evidence that AI governance is in place.',
                'Its Annex A controls (AI policy, impact assessment, data, life cycle, third parties) map onto EU AI Act and NIST AI RMF duties, so one programme can serve several regimes.',
                'It follows the same harmonised structure as ISO/IEC 27001, so it can be run alongside an existing information security management system.',
            ],
            'related' => ['ai-grc', 'ai-impact-assessment', 'ai-rmf'],
            'faq' => true,
            'match' => ['ISO 42001', 'ISO/IEC 42001'],
            'definition' => 'The policies, objectives and processes an organisation puts in place to develop, provide or use AI systems responsibly, structured like other ISO management systems (plan, do, check, act). ISO/IEC 42001 sets the requirements for one and can be certified by an accredited body.',
            'source' => ['ISO/IEC 42001:2023', 'https://www.iso.org/standard/81230.html'],
            'see' => [['ISO/IEC 42001 record', 'policies.show', 'iso-iec-42001-2023-ai-management-system'], ['ISO 42001 gap assessment', 'templates.show', 'iso-42001-gap-assessment']],
        ],
        'ai-rmf' => [
            'term' => 'NIST AI Risk Management Framework (AI RMF)',
            'group' => 'Governance, risk and compliance',
            'question' => 'What is the NIST AI RMF?',
            'answer' => 'The NIST AI Risk Management Framework (AI RMF 1.0) is a voluntary US framework, published in January 2023, for managing risks to people, organisations and society from AI. It organises the work into four functions: Govern, Map, Measure and Manage.',
            'why' => [
                'It is voluntary and sector-neutral, so it fits any organisation and can be adopted in steps.',
                'Its Generative AI Profile (NIST AI 600-1, July 2024) adds risks specific to generative AI, such as confabulation.',
                'NIST publishes crosswalks to other frameworks, including ISO/IEC 42001, so its outputs can be reused as evidence elsewhere.',
            ],
            'related' => ['ai-risk', 'ai-management-system', 'ai-hallucination'],
            'faq' => true,
            'match' => ['NIST AI RMF', 'AI Risk Management Framework'],
            'definition' => 'A voluntary framework from the US National Institute of Standards and Technology for managing the risks of AI systems, organised in four functions: Govern (culture and accountability), Map (context and risks), Measure (analysis and tracking) and Manage (prioritising and acting on risks).',
            'source' => ['NIST AI 100-1', 'https://www.nist.gov/itl/ai-risk-management-framework'],
            'see' => [['NIST AI RMF record', 'policies.show', 'us-nist-ai-rmf'], ['NIST, EU and ISO crosswalk', 'templates.show', 'nist-eu-iso-crosswalk']],
        ],
        'bias-audit' => [
            'term' => 'Bias audit (automated employment decision tools)',
            'group' => 'Governance, risk and compliance',
            'match' => ['bias audit', 'automated employment decision tool'],
            'definition' => 'Under New York City Local Law 144, an impartial evaluation by an independent auditor of an automated employment decision tool, reporting selection or scoring rates and impact ratios by sex and race or ethnicity. Employers must have one done within a year before using the tool, publish a summary, and notify candidates.',
            'source' => ['NYC Department of Consumer and Worker Protection', 'https://www.nyc.gov/site/dca/about/automated-employment-decision-tools.page'],
            'see' => [['Local Law 144 record', 'policies.show', 'us-new-york-city-local-law-144-automated-employment-decision-tools']],
        ],
        'red-teaming' => [
            'term' => 'Red teaming (AI)',
            'group' => 'AI security',
            'question' => 'What is AI red teaming?',
            'answer' => 'AI red teaming is structured adversarial testing in which testers try to make an AI system fail, by producing harmful output, leaking data, bypassing safeguards or taking unintended actions, so that weaknesses are found and fixed before attackers or users find them.',
            'why' => [
                'The EU AI Act requires providers of general-purpose AI models with systemic risk to carry out and document adversarial testing (Article 55).',
                'It is how prompt injection, data leakage and unsafe behaviour are found before release, and the OWASP Top 10 for LLM Applications gives it a checklist.',
                'Findings only count if they are fixed and re-tested, so red teaming belongs in the release process, not as a one-off exercise.',
            ],
            'related' => ['prompt-injection', 'owasp-llm-top-10', 'agentic-ai'],
            'match' => ['red-teaming', 'red team', 'red-team'],
            'definition' => 'Structured adversarial testing in which testers try to make an AI system fail: produce harmful output, leak data, ignore its instructions or be misused. It is one of the evaluations expected of the most capable general-purpose models and a common control for generative AI.',
            'source' => $euAiAct,
            'see' => [['Red-team testing control', 'controls.show', 'genai-red-team-testing'], ['AI red team test plan', 'templates.show', 'ai-red-team-test-plan']],
        ],
        'model-card' => [
            'term' => 'Model card',
            'group' => 'Governance, risk and compliance',
            'definition' => 'A short document published with a machine-learning model that states its intended use, the data it was evaluated on, its performance across groups and conditions, and its known limitations. The format comes from Mitchell et al. (2019) and now meets part of the documentation duties several laws place on model providers.',
            'source' => ['Mitchell et al., Model Cards for Model Reporting (2019)', 'https://arxiv.org/abs/1810.03993'],
            'see' => [['Technical documentation control', 'controls.show', 'technical-documentation-and-model-cards'], ['AI model card template', 'templates.show', 'ai-model-card']],
        ],
    ],
];
