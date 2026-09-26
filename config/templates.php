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

    'licence' => 'CC BY 4.0. You may use, adapt and share this template, including commercially, with attribution to aipolicytracker.org.',

    'types' => ['register' => 'Register', 'assessment' => 'Assessment', 'policy' => 'Policy', 'procedure' => 'Procedure', 'checklist' => 'Checklist', 'crosswalk' => 'Crosswalk', 'kit' => 'Kit'],

    'frameworks' => ['eu-ai-act' => 'EU AI Act', 'iso-42001' => 'ISO/IEC 42001', 'nist-ai-rmf' => 'NIST AI RMF', 'colorado-ai-act' => 'Colorado AI Act'],

    'topics' => ['inventory' => 'Inventory', 'risk' => 'Risk', 'governance' => 'Governance', 'transparency' => 'Transparency', 'incidents' => 'Incidents', 'vendors' => 'Vendors', 'oversight' => 'Human oversight', 'workforce' => 'Workforce', 'evidence' => 'Evidence'],

    'items' => [

        'ai-system-inventory' => [
            'title' => 'AI System Inventory',
            'type' => 'register', 'formats' => ['xlsx', 'docx'],
            'topics' => ['inventory', 'governance', 'evidence'], 'frameworks' => ['eu-ai-act', 'iso-42001', 'nist-ai-rmf'],
            'short' => 'One row per AI system: purpose, role under the law, jurisdictions, risk tier, data, owner and review dates, with the recorded duties for each role on a reference sheet.',
            'inside' => ['Inventory sheet with dropdowns for lifecycle stage, legal role, jurisdiction and EU AI Act tier', 'Automatic next-review date and overdue flag', 'Reference sheet: every recorded duty by legal role, with its source reference and record link', 'Reference sheet: jurisdictions with binding AI law'],
            'covers' => ['categories' => ['governance_accountability', 'technical_documentation', 'record_keeping']],
            'legal_basis' => ['eu-ai-act', 'us-colorado-ai-act', 'iso-42001'],
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
            'legal_basis' => ['us-colorado-ai-act', 'eu-ai-act', 'us-nist-ai-rmf'],
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
            'legal_basis' => ['eu-ai-act', 'us-colorado-ai-act'],
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
            'title' => 'Colorado AI Act Notices',
            'type' => 'kit', 'formats' => ['docx', 'xlsx'],
            'topics' => ['transparency', 'evidence'], 'frameworks' => ['colorado-ai-act'],
            'short' => 'The notices the Colorado AI Act requires of developers and deployers of high-risk AI: consumer notice, adverse-decision explanation, developer disclosure, Attorney General notification, each built from the recorded duty.',
            'inside' => ['Document: a notice template for each notification duty on record, citing its section', 'Checklist sheet: each duty, applies-from date, owner, status', 'A dated caveat where the record says the statute may have been amended'],
            'covers' => ['policies' => ['us-colorado-ai-act']],
            'legal_basis' => ['us-colorado-ai-act'],
            'caveat' => 'colorado',
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
            'legal_basis' => ['eu-ai-act', 'us-colorado-ai-act'],
            'replaces' => 'global-ai-regulatory-applicability-matrix',
        ],
    ],
];
