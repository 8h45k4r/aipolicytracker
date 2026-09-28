<?php

/*
|--------------------------------------------------------------------------
| The templates library
|--------------------------------------------------------------------------
|
| Every template here is generated from the read model (obligations, controls,
| deadlines, frameworks, risks) by App\Services\Templates. Nothing in a file is
| written by hand: the catalogue says what each template is, which duties it
| covers and which instruments it rests on; the builder says how the records
| become sheets and pages. When the records change, the files are rebuilt with
| a new version and a changelog (templates:build, daily).
|
| `covers` decides which obligation pages link to the template: by obligation
| category, by instrument, or by framework. `legal_basis` lists the instruments
| the template's content is drawn from, shown as links on the template page.
| `replaces` is the slug of the free tool the template supersedes; that address
| redirects here.
|
| Everything in this file must stay serializable (config:cache).
|
*/

return [

    'disclaimer' => 'This template is generated from the records on aipolicytracker.org. It is an informational resource, not legal advice, and completing it does not make an organisation compliant with any law or standard. Every row that cites a duty links to the record it came from; check the official source before relying on it.',

    // Downloads are requested through a form (name, work email, company, Turnstile)
    // and delivered as signed links by email. `require_work_email` refuses consumer
    // mailboxes; throwaway domains are refused regardless (App\Rules\NotDisposableEmail).
    'gate' => [
        'require_work_email' => (bool) env('TEMPLATE_REQUIRE_WORK_EMAIL', true),
        'per_email_per_day' => (int) env('TEMPLATE_REQUESTS_PER_EMAIL_PER_DAY', 10),
    ],

    'licence' => 'CC BY 4.0. You may use, adapt and share this template, including commercially, with attribution to aipolicytracker.org.',

    'types' => ['register' => 'Register', 'assessment' => 'Assessment', 'policy' => 'Policy', 'procedure' => 'Procedure', 'checklist' => 'Checklist', 'crosswalk' => 'Crosswalk', 'kit' => 'Kit'],

    'frameworks' => ['eu-ai-act' => 'EU AI Act', 'iso-42001' => 'ISO/IEC 42001', 'nist-ai-rmf' => 'NIST AI RMF', 'colorado-ai-act' => 'Colorado ADMT law (SB 26-189)'],

    'topics' => ['inventory' => 'Inventory', 'risk' => 'Risk', 'governance' => 'Governance', 'transparency' => 'Transparency', 'incidents' => 'Incidents', 'vendors' => 'Vendors', 'oversight' => 'Human oversight', 'workforce' => 'Workforce', 'evidence' => 'Evidence'],

    'items' => [

        'ai-system-inventory' => [
            'title' => 'AI System Inventory',
            'type' => 'register', 'formats' => ['xlsx', 'docx'],
            'topics' => ['inventory', 'governance', 'evidence'], 'frameworks' => ['eu-ai-act', 'iso-42001', 'nist-ai-rmf'],
            'short' => 'One row per AI system: purpose, role under the law, jurisdictions, risk tier, data, owner and review dates, with the recorded duties for each role on a reference sheet.',
            'inside' => ['Inventory sheet with dropdowns for lifecycle stage, legal role, jurisdiction and EU AI Act tier', 'Automatic next-review date and overdue flag', 'Reference sheet: every recorded duty by legal role, with its source reference and record link', 'Reference sheet: jurisdictions with binding AI law'],
            'covers' => ['categories' => ['governance_accountability', 'technical_documentation', 'record_keeping']],
            'legal_basis' => ['eu-ai-act', 'us-colorado-automated-decision-making-technology-act', 'iso-42001'],
            'replaces' => 'ai-system-inventory-template',
        ],

        'ai-risk-register' => [
            'title' => 'AI Risk Register (MIT AI Risk Repository taxonomy)',
            'type' => 'register', 'formats' => ['xlsx'],
            'topics' => ['risk', 'governance', 'evidence'], 'frameworks' => ['nist-ai-rmf', 'iso-42001', 'eu-ai-act'],
            'short' => 'A risk register whose domain and subdomain dropdowns are the MIT AI Risk Repository taxonomy, with inherent and residual scoring, control lookup and colour-coded thresholds.',
            'inside' => ['Register sheet: domain and subdomain dropdowns from the MIT taxonomy, likelihood × impact scoring with formulas, residual scoring after controls', 'Conditional formatting: red at 15 and above, amber at 8, green below', 'Controls sheet: every recorded control, what it does and which duties it satisfies', 'Taxonomy sheet: all 7 domains and 24 subdomains with their definitions'],
            'covers' => ['categories' => ['risk_management', 'safety_testing']],
            'legal_basis' => ['eu-ai-act', 'us-nist-ai-rmf', 'iso-42001'],
            'replaces' => 'ai-risk-register-template',
        ],

        'eu-ai-act-role-risk-classifier' => [
            'title' => 'EU AI Act Role and Risk Classifier',
            'type' => 'checklist', 'formats' => ['xlsx'],
            'topics' => ['inventory', 'risk', 'governance'], 'frameworks' => ['eu-ai-act'],
            'short' => 'Answer six questions per system and the sheet returns the role, the risk tier and the date its duties apply, looked up from the dated milestones on the EU AI Act record.',
            'inside' => ['Classifier sheet: role, Annex III area, prohibited practice, safety component, GPAI and systemic-risk questions as dropdowns; tier and application date as formulas', 'Dates sheet: every dated milestone on the EU AI Act record, with status and confidence', 'Duties sheet: every recorded EU AI Act duty by role, with article reference'],
            'covers' => ['policies' => ['eu-ai-act']],
            'legal_basis' => ['eu-ai-act'],
            'replaces' => 'eu-ai-act-readiness-checklist',
            'caveat' => 'dates',
        ],

        'fundamental-rights-impact-assessment' => [
            'title' => 'Fundamental Rights Impact Assessment (FRIA)',
            'type' => 'assessment', 'formats' => ['docx', 'xlsx'],
            'topics' => ['risk', 'oversight', 'evidence'], 'frameworks' => ['eu-ai-act'],
            'short' => 'The assessment Article 27 of the EU AI Act requires of deployers of high-risk AI: one section per element the article names, with placeholders, plus a register to track completed assessments.',
            'inside' => ['Document: a section for each element of the assessment, with guidance drawn from the recorded duty and a placeholder to complete', 'Register sheet: one row per assessment with status, owner, date and link to evidence', 'The duty this rests on, cited by article'],
            'covers' => ['categories' => ['impact_assessment']],
            'legal_basis' => ['eu-ai-act'],
        ],

        'ai-impact-assessment' => [
            'title' => 'AI Impact Assessment',
            'type' => 'assessment', 'formats' => ['docx', 'xlsx'],
            'topics' => ['risk', 'governance', 'evidence'], 'frameworks' => ['nist-ai-rmf', 'iso-42001', 'colorado-ai-act'],
            'short' => 'A general impact assessment for any AI system: purpose, affected people, harms by risk domain, mitigations and the decision, with the impact-assessment duties recorded across jurisdictions as the checklist.',
            'inside' => ['Document: purpose, scope, affected groups, harm analysis by MIT risk domain, mitigations, residual risk, decision and sign-off', 'Duties sheet: every recorded impact-assessment duty, by jurisdiction and instrument', 'Harm areas sheet: the 24 MIT subdomains as prompts'],
            'covers' => ['categories' => ['impact_assessment', 'risk_management']],
            'legal_basis' => ['us-colorado-automated-decision-making-technology-act', 'eu-ai-act', 'us-nist-ai-rmf'],
            'replaces' => 'ai-impact-assessment-template',
        ],

        'acceptable-use-policy' => [
            'title' => 'AI Acceptable Use Policy',
            'type' => 'policy', 'formats' => ['docx'],
            'topics' => ['governance', 'transparency'], 'frameworks' => ['eu-ai-act', 'iso-42001'],
            'short' => 'A policy for staff use of AI tools: permitted and prohibited uses, data handling, disclosure and reporting, with the prohibited practices drawn from the law.',
            'inside' => ['Scope, roles and definitions', 'Permitted and prohibited uses, the latter drawn from every recorded prohibited-practice duty', 'Data, confidentiality and disclosure rules', 'Reporting, review and enforcement, with placeholders'],
            'covers' => ['categories' => ['prohibited_practice', 'ai_literacy', 'governance_accountability']],
            'legal_basis' => ['eu-ai-act'],
            'replaces' => 'ai-policy-statement-template',
        ],

        'ai-governance-policy-raci' => [
            'title' => 'AI Governance Policy and RACI',
            'type' => 'policy', 'formats' => ['docx', 'xlsx'],
            'topics' => ['governance', 'evidence'], 'frameworks' => ['iso-42001', 'nist-ai-rmf', 'eu-ai-act'],
            'short' => 'The governance policy an AI management system needs, built from the recorded governance duties, with a RACI matrix over every recorded control.',
            'inside' => ['Document: purpose, principles, roles, the governance duties on record and how each is met, review cycle', 'RACI sheet: every recorded control against eight roles, R/A/C/I dropdowns', 'Controls sheet: what each control is for and which duties it satisfies'],
            'covers' => ['categories' => ['governance_accountability', 'quality_management']],
            'legal_basis' => ['iso-42001', 'us-nist-ai-rmf', 'eu-ai-act'],
            'replaces' => 'ai-governance-30-day-starter-plan',
        ],

        'ai-vendor-due-diligence-questionnaire' => [
            'title' => 'AI Vendor Due-Diligence Questionnaire',
            'type' => 'checklist', 'formats' => ['xlsx', 'docx'],
            'topics' => ['vendors', 'governance', 'evidence'], 'frameworks' => ['eu-ai-act', 'iso-42001', 'nist-ai-rmf'],
            'short' => 'Questions to put to an AI vendor, grouped by governance, data, model, security, transparency, incidents, and insurance and indemnity, scored automatically.',
            'inside' => ['Questionnaire sheet: seven sections, response dropdowns, evidence requested, weighted score', 'Insurance and indemnity section: cover, AI exclusions, IP indemnity, sub-processor flow-down, audit rights', 'Duties sheet: the vendor-governance duties on record that the questions serve'],
            'covers' => ['categories' => ['vendor_governance']],
            'legal_basis' => ['eu-ai-act', 'iso-42001'],
            'replaces' => 'ai-vendor-due-diligence-questionnaire',
        ],

        'human-oversight-procedure' => [
            'title' => 'Human Oversight Procedure',
            'type' => 'procedure', 'formats' => ['docx', 'xlsx'],
            'topics' => ['oversight', 'governance'], 'frameworks' => ['eu-ai-act', 'colorado-ai-act'],
            'short' => 'A procedure for the humans who oversee an AI system: what they must be able to do, when they intervene, how an override is recorded, drawn from every recorded oversight duty.',
            'inside' => ['Document: roles, competence, the oversight measures each recorded duty requires, intervention and override, escalation', 'Override log sheet: date, system, decision overridden, reason, reviewer'],
            'covers' => ['categories' => ['human_oversight']],
            'legal_basis' => ['eu-ai-act', 'us-colorado-automated-decision-making-technology-act'],
        ],

        'ai-incident-response-playbook' => [
            'title' => 'AI Incident Response Playbook and Log',
            'type' => 'procedure', 'formats' => ['docx', 'xlsx'],
            'topics' => ['incidents', 'governance', 'evidence'], 'frameworks' => ['eu-ai-act', 'nist-ai-rmf'],
            'short' => 'Detect, contain, report and learn: a playbook built from the recorded incident-handling duties, with the reporting deadlines they set, and a log that computes them.',
            'inside' => ['Document: severity scale, roles, the playbook steps, the reporting duties on record with their deadlines', 'Incident log sheet: severity and harm-domain dropdowns, deadline computed from the date, status', 'Regulators sheet: who to notify, by jurisdiction, from the records'],
            'covers' => ['categories' => ['incident_handling', 'post_market_monitoring']],
            'legal_basis' => ['eu-ai-act', 'us-nist-ai-rmf'],
            'replaces' => 'ai-incident-response-checklist',
        ],

        'ai-agent-registry' => [
            'title' => 'AI Agent Registry and Permission Matrix',
            'type' => 'register', 'formats' => ['xlsx', 'docx'],
            'topics' => ['inventory', 'oversight', 'governance'], 'frameworks' => ['eu-ai-act', 'nist-ai-rmf'],
            'short' => 'A register for agents that act — with tools, credentials and autonomy — and a permission matrix stating what each may do alone, with approval, or never.',
            'inside' => ['Registry sheet: agent, purpose, model, tools, credentials, owner, kill switch, logging', 'Permission matrix sheet: agents against actions (read, write, send, execute, pay, browse, act for a user) with Allowed / With approval / Denied dropdowns', 'Duties sheet: the oversight and transparency duties on record that apply to autonomous systems'],
            'covers' => ['categories' => ['human_oversight', 'transparency', 'record_keeping']],
            'legal_basis' => ['eu-ai-act', 'us-nist-ai-rmf'],
        ],

        'article-50-transparency-kit' => [
            'title' => 'EU AI Act Article 50 Transparency Kit',
            'type' => 'kit', 'formats' => ['docx', 'xlsx'],
            'topics' => ['transparency', 'evidence'], 'frameworks' => ['eu-ai-act'],
            'short' => 'Notice texts and a checklist for the transparency duties of Article 50: chatbot disclosure, synthetic content marking, deepfake and emotion-recognition notices, with the application date from the record.',
            'inside' => ['Document: a notice template for each Article 50 duty, with the duty it serves cited', 'Checklist sheet: each duty, applies-from date, owner, status, evidence', 'The dates as recorded, with the record\'s own note where they may be superseded'],
            'covers' => ['categories' => ['transparency'], 'policies' => ['eu-ai-act']],
            'legal_basis' => ['eu-ai-act'],
            'caveat' => 'dates',
        ],

        'iso-42001-gap-assessment' => [
            'title' => 'ISO/IEC 42001 Gap Assessment and Statement of Applicability',
            'type' => 'assessment', 'formats' => ['xlsx'],
            'topics' => ['governance', 'evidence'], 'frameworks' => ['iso-42001'],
            'short' => 'Every ISO/IEC 42001 clause and control the records map to, with the legal duties behind each, applicability, implementation status and evidence, in the form a Statement of Applicability takes.',
            'inside' => ['Gap sheet: each mapped clause, the duties and controls mapped to it, applicable and implemented dropdowns, evidence, gap, owner', 'Statement of Applicability sheet, derived from the gap sheet by formula', 'Mappings sheet: every recorded crosswalk with its confidence'],
            'covers' => ['frameworks' => ['iso-42001']],
            'legal_basis' => ['iso-42001', 'eu-ai-act'],
        ],

        'nist-eu-iso-crosswalk' => [
            'title' => 'NIST AI RMF ↔ EU AI Act ↔ ISO/IEC 42001 Crosswalk',
            'type' => 'crosswalk', 'formats' => ['xlsx'],
            'topics' => ['governance', 'evidence'], 'frameworks' => ['nist-ai-rmf', 'eu-ai-act', 'iso-42001'],
            'short' => 'Every recorded legal duty against the NIST AI RMF and ISO/IEC 42001 references it maps to, with the confidence of each mapping, so what is done for one counts for the others.',
            'inside' => ['Crosswalk sheet: duty, instrument, jurisdiction, NIST reference, ISO reference, confidence, record link', 'Coverage sheet: mappings per instrument and framework', 'Editorial mappings with a stated confidence, not official crosswalks'],
            'covers' => ['frameworks' => ['nist-ai-rmf', 'iso-42001']],
            'legal_basis' => ['eu-ai-act', 'us-nist-ai-rmf', 'iso-42001'],
        ],

        'colorado-ai-act-notices' => [
            'title' => 'Colorado ADMT Law (SB 26-189) Notices',
            'type' => 'kit', 'formats' => ['docx', 'xlsx'],
            'topics' => ['transparency', 'evidence'], 'frameworks' => ['colorado-ai-act'],
            'short' => 'The notices Colorado\'s SB 26-189 requires when automated decision-making technology influences a consequential decision: advance notice, adverse-decision disclosure, human review, record keeping and developer documentation, built from the recorded duties.',
            'inside' => ['Document: a notice or procedure for each recorded duty, citing its record', 'Checklist sheet: each duty, applies-from date, owner, status', 'A dated caveat carrying the record\'s review status, and a note on the repealed SB 24-205'],
            'covers' => ['policies' => ['us-colorado-automated-decision-making-technology-act']],
            'legal_basis' => ['us-colorado-automated-decision-making-technology-act'],
            'caveat' => 'dates',
        ],

        'ai-workforce-impact-assessment' => [
            'title' => 'AI Workforce Impact Assessment',
            'type' => 'assessment', 'formats' => ['docx', 'xlsx'],
            'topics' => ['workforce', 'governance'], 'frameworks' => ['nist-ai-rmf'],
            'short' => 'An assessment of what an AI deployment does to jobs: roles affected, tasks automated, timeline, consultation, reskilling and disclosure, with a role inventory sheet.',
            'inside' => ['Document: scope, roles and tasks affected, displacement and augmentation analysis, consultation, reskilling plan, disclosure duties, metrics', 'Role inventory sheet: role, headcount, share of tasks automated, timeline, mitigation, owner', 'A note on disclosure laws not yet in this dataset'],
            'covers' => ['categories' => ['impact_assessment', 'public_sector_use']],
            'legal_basis' => ['us-nist-ai-rmf'],
            'caveat' => 'workforce',
        ],

        'ai-system-technical-documentation' => [
            'title' => 'AI System Technical Documentation',
            'type' => 'kit', 'formats' => ['docx', 'xlsx'],
            'topics' => ['evidence', 'inventory'], 'frameworks' => ['eu-ai-act', 'iso-42001'],
            'short' => 'The technical file a high-risk system needs: one section per element the recorded documentation duties name, with placeholders, plus a document register.',
            'inside' => ['Document: general description, development process, data, monitoring, risk management, changes, standards, with placeholders', 'Register sheet: documents, versions, owners, last review', 'Duties sheet: every recorded documentation and record-keeping duty'],
            'covers' => ['categories' => ['technical_documentation', 'record_keeping', 'accuracy_robustness_security']],
            'legal_basis' => ['eu-ai-act', 'iso-42001'],
            'replaces' => 'ai-system-technical-documentation-template',
        ],

        'global-ai-regulatory-applicability-matrix' => [
            'title' => 'Global AI Regulatory Applicability Matrix',
            'type' => 'crosswalk', 'formats' => ['xlsx'],
            'topics' => ['inventory', 'governance'], 'frameworks' => ['eu-ai-act', 'colorado-ai-act'],
            'short' => 'Every binding AI instrument on record, by jurisdiction, with status, application date, who it binds and the use cases it covers, as a matrix to mark which apply to you.',
            'inside' => ['Matrix sheet: jurisdiction, instrument, status, applies from, binding, actors, use cases, applies-to-us dropdown, owner', 'Deadlines sheet: every dated milestone on those instruments', 'Record links throughout'],
            'covers' => ['categories' => ['governance_accountability']],
            'legal_basis' => ['eu-ai-act', 'us-colorado-automated-decision-making-technology-act'],
            'replaces' => 'global-ai-regulatory-applicability-matrix',
        ],

        'ai-model-card' => [
            'title' => 'AI Model Card',
            'type' => 'kit', 'formats' => ['docx', 'xlsx'],
            'topics' => ['transparency', 'evidence'], 'frameworks' => ['eu-ai-act', 'iso-42001', 'nist-ai-rmf'],
            'short' => 'A model or system card: intended use, out-of-scope uses, data, evaluation results by group, risks, oversight and monitoring, with the documentation and transparency duties it helps evidence.',
            'inside' => ['Document: twenty questions in seven sections, each with space for the answer', 'Workbook: the same card as a fill-in sheet with evidence links', 'Documentation duties sheet: every recorded technical-documentation, transparency and record-keeping duty'],
            'covers' => ['categories' => ['technical_documentation', 'transparency', 'record_keeping']],
            'legal_basis' => ['eu-ai-act', 'us-nist-ai-rmf'],
        ],

        'ai-data-governance-register' => [
            'title' => 'AI Data Governance Register',
            'type' => 'register', 'formats' => ['xlsx', 'docx'],
            'topics' => ['governance', 'evidence'], 'frameworks' => ['eu-ai-act', 'iso-42001'],
            'short' => 'One row per dataset that trains, tests or feeds an AI system: provenance, personal data, lawful basis or licence, bias checks and retention, with a procedure and the data duties on record.',
            'inside' => ['Datasets register with dropdowns for use, personal data and bias checks', 'Procedure document: acceptance criteria, quality and bias checks, retention', 'Data duties sheet: data governance, privacy and copyright duties on record'],
            'covers' => ['categories' => ['data_governance', 'privacy_data_protection', 'copyright_training_data']],
            'legal_basis' => ['eu-ai-act'],
        ],

        'ai-audit-evidence-tracker' => [
            'title' => 'AI Audit Evidence Tracker',
            'type' => 'register', 'formats' => ['xlsx'],
            'topics' => ['evidence', 'governance'], 'frameworks' => ['iso-42001', 'nist-ai-rmf', 'eu-ai-act'],
            'short' => 'Every piece of evidence the recorded controls expect, one row each, with owner, status, location and review date, so an audit or certification starts from a complete list.',
            'inside' => ['Evidence tracker: control, evidence expected, kind, owner, status, location, last reviewed', 'Status colouring for missing and available evidence', 'Controls sheet: every control on record with its duties and framework references'],
            'covers' => ['frameworks' => ['iso-42001', 'nist-ai-rmf']],
            'legal_basis' => ['iso-42001', 'us-nist-ai-rmf'],
        ],

        'ai-literacy-training-plan' => [
            'title' => 'AI Literacy and Training Plan',
            'type' => 'procedure', 'formats' => ['xlsx', 'docx'],
            'topics' => ['workforce', 'governance'], 'frameworks' => ['eu-ai-act'],
            'short' => 'A role-based AI literacy plan: who needs which level of training, in what format, by when, with a coverage formula and the literacy and oversight duties on record.',
            'inside' => ['Training matrix with five starter roles, levels, formats and a coverage formula', 'Plan document: objectives, curriculum, records', 'Literacy duties sheet: AI literacy and human-oversight duties'],
            'covers' => ['categories' => ['ai_literacy', 'human_oversight']],
            'legal_basis' => ['eu-ai-act'],
        ],

        'ai-post-market-monitoring-plan' => [
            'title' => 'AI Post-Market Monitoring Plan',
            'type' => 'procedure', 'formats' => ['docx', 'xlsx'],
            'topics' => ['risk', 'incidents', 'evidence'], 'frameworks' => ['eu-ai-act', 'iso-42001'],
            'short' => 'How a deployed AI system is watched after release: metrics, thresholds, frequency, owners and escalation to incident response, with the monitoring and incident duties on record.',
            'inside' => ['Monitoring metrics sheet with six starter metrics and alert colouring', 'Plan document: systems covered, data collected, review and escalation', 'Monitoring duties sheet: post-market monitoring and incident duties'],
            'covers' => ['categories' => ['post_market_monitoring', 'incident_handling']],
            'legal_basis' => ['eu-ai-act'],
        ],

        'ai-contract-clauses' => [
            'title' => 'AI Contract Clause Library',
            'type' => 'policy', 'formats' => ['docx', 'xlsx'],
            'topics' => ['vendors', 'governance'], 'frameworks' => ['eu-ai-act'],
            'short' => 'Nine contract clauses for buying or supplying AI: documentation, data use, testing, incidents, changes, oversight, audit and the allocation of regulatory roles, each tied to the duty it allocates.',
            'inside' => ['Document: nine clauses with placeholders for counsel', 'Vendor duties sheet: the duties a supplier contract should allocate', 'Each clause cites the recorded duty behind it'],
            'covers' => ['categories' => ['vendor_governance']],
            'legal_basis' => ['eu-ai-act'],
        ],

        'ai-red-team-test-plan' => [
            'title' => 'AI Red-Team and Evaluation Test Plan',
            'type' => 'assessment', 'formats' => ['xlsx', 'docx'],
            'topics' => ['risk', 'evidence'], 'frameworks' => ['eu-ai-act', 'nist-ai-rmf'],
            'short' => 'A test plan and case log for adversarial and evaluation testing: accuracy, robustness, prompt injection, bias, harmful content, privacy leakage and misuse, with severity and retest tracking.',
            'inside' => ['Test cases sheet with area, scenario, expected and observed behaviour, outcome and severity', 'Plan document: scope, independence, method, exit criteria', 'Testing duties sheet: safety-testing and robustness duties on record'],
            'covers' => ['categories' => ['safety_testing', 'accuracy_robustness_security']],
            'legal_basis' => ['eu-ai-act', 'us-nist-ai-rmf'],
        ],

        'ai-board-reporting-pack' => [
            'title' => 'AI Governance Board Reporting Pack',
            'type' => 'kit', 'formats' => ['xlsx', 'docx'],
            'topics' => ['governance', 'risk'], 'frameworks' => ['iso-42001', 'nist-ai-rmf'],
            'short' => 'A quarterly board report on AI: a dashboard of eight measures, the regulatory deadlines ahead from the records, and a report outline ending in the decisions the board is asked to take.',
            'inside' => ['Dashboard sheet: eight measures with quarter-on-quarter trend', 'Upcoming deadlines sheet built from the recorded dates', 'Report document: summary, AI in use, risks and incidents, regulatory outlook, decisions'],
            'covers' => ['categories' => ['governance_accountability']],
            'legal_basis' => ['eu-ai-act', 'iso-42001'],
        ],
    ],
];
