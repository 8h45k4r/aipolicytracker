<?php

namespace App\Services\Templates\Definitions;

use App\Services\Templates\Definition;
use App\Services\Templates\Records;

/** Policies and procedures: acceptable use, governance and RACI, human oversight, incident response, vendor due diligence. */
final class PoliciesProcedures
{
    private const ROLES = ['Board', 'Executive sponsor', 'AI governance lead', 'Product owner', 'Engineering lead', 'Legal and compliance', 'Security', 'Data protection'];

    public static function make(string $slug, array $meta): ?Definition
    {
        return match ($slug) {
            'acceptable-use-policy' => new class($slug, $meta) extends Definition
            {
                public function blocks(): array
                {
                    $prohibited = Records::obligations(['categories' => ['prohibited_practice']]);
                    $literacy = Records::obligations(['categories' => ['ai_literacy']]);

                    return [
                        $this->heading('1. Purpose and scope'),
                        $this->p('This policy sets out how people in the organisation may use AI tools, what they may not do, and what they must disclose. It applies to every use of an AI system in the course of work, whether the system is built, bought or free.'),
                        $this->placeholder('Organisation name, the units and contractors covered, and the date this policy takes effect'),
                        $this->heading('2. Definitions'),
                        $this->list(['AI system: software that, for a given objective, produces outputs such as predictions, content, recommendations or decisions.', 'Approved tool: an AI system listed in the AI system inventory with an owner and a completed review.', 'Personal data and confidential information: as defined in the data protection and information security policies.']),
                        $this->heading('3. Permitted uses'),
                        $this->placeholder('The tools and uses approved, by role or unit, with reference to the inventory'),
                        $this->heading('4. Prohibited uses'),
                        $this->p('The following are prohibited outright. The first group are practices the law prohibits, each cited to the duty on record; the second are the organisation\'s own rules.'),
                        $this->list($prohibited->map(fn ($o) => $o->title.' ('.($o->policyInstrument->short_title ?: $o->policyInstrument->title).($o->source_reference ? ', '.$o->source_reference : '').')')->all()),
                        $this->placeholder('Organisation-specific prohibitions: e.g. entering confidential or personal data into unapproved tools; presenting AI output as human work where disclosure is required; automating a decision about a person without approval'),
                        $this->heading('5. Data and confidentiality'),
                        $this->placeholder('What data may be entered into which tools; retention; where outputs are stored'),
                        $this->heading('6. Disclosure'),
                        $this->placeholder('When AI-generated or AI-assisted content must be labelled, internally and externally, and how'),
                        $this->heading('7. Competence and training'),
                        $this->p($literacy->isNotEmpty() ? 'The organisation is required to ensure a sufficient level of AI literacy among the staff who operate or use AI systems: '.Records::dutyLine($literacy->first()).'.' : 'Staff who use AI tools receive training proportionate to the risk of the use.'),
                        $this->placeholder('Training required before use, by role, and how completion is recorded'),
                        $this->heading('8. Reporting and enforcement'),
                        $this->placeholder('How to report a concern or an incident, who investigates, and the consequences of breach'),
                        $this->heading('9. Review'),
                        $this->placeholder('Owner of this policy, review cycle, and the version history'),
                        $this->heading('Duties this policy serves'),
                        ...$this->dutySections($prohibited->take(6), 'How this policy prevents the practice'),
                    ];
                }
            },

            'ai-governance-policy-raci' => new class($slug, $meta) extends Definition
            {
                public function sheets(): array
                {
                    $columns = [self::col('control', 'Control', 44), self::col('kind', 'Kind', 12)];
                    foreach (PoliciesProcedures::roles() as $n => $role) {
                        $columns[] = self::select('r'.$n, $role, ['R', 'A', 'C', 'I', '—'], 12);
                    }
                    $columns[] = self::formula('accountable', 'Accountable count', '=COUNTIF(C{r}:J{r},"A")', 10, 'Exactly one A per control.');
                    $columns[] = self::col('url', 'Record', 44, 'url');
                    $rows = array_map(fn ($c) => ['control' => $c['control'], 'kind' => $c['kind'], 'url' => $c['url']], Records::controlRows());

                    return [
                        ['name' => 'RACI', 'columns' => $columns, 'rows' => $rows, 'rules' => [['column' => 'accountable', 'op' => 'notEqual', 'value' => 1, 'fill' => 'F8D7DA']], 'note' => 'R responsible, A accountable, C consulted, I informed. A control with no A, or two, turns red.'],
                        $this->controlsSheet(),
                        $this->dutiesSheet('Governance duties', Records::obligations(['categories' => ['governance_accountability', 'quality_management']])),
                    ];
                }

                public function blocks(): array
                {
                    $duties = Records::obligations(['categories' => ['governance_accountability', 'quality_management']]);

                    return [
                        $this->heading('1. Purpose'),
                        $this->p('This policy establishes how the organisation governs its use of AI: who is accountable, what principles apply, which controls are operated, and how the governance duties on record are met.'),
                        $this->placeholder('Organisation, scope, effective date'),
                        $this->heading('2. Principles'),
                        $this->placeholder('The principles the organisation commits to (lawfulness, safety, transparency, fairness, accountability, human oversight), in its own words'),
                        $this->heading('3. Roles and accountabilities'),
                        $this->p('The RACI sheet assigns every recorded control to the roles below. Exactly one role is accountable for each control.'),
                        $this->table(['Role', 'Accountability'], array_map(fn ($r) => [$r, ''], PoliciesProcedures::roles())),
                        $this->placeholder('Name the holders of each role and how they report'),
                        $this->heading('4. The controls the organisation operates'),
                        $this->table(['Control', 'Purpose', 'Owner', 'Frequency'], array_map(fn ($c) => [$c['control'], $c['purpose'], $c['owner'] ?? '', $c['frequency'] ?? ''], Records::controlRows())),
                        $this->heading('5. Governance duties on record and how each is met'),
                        ...$this->dutySections($duties, 'The control, process or document by which this duty is met, and who owns it'),
                        $this->heading('6. Review'),
                        $this->placeholder('Review cycle, triggers for an out-of-cycle review, and version history'),
                    ];
                }
            },

            'human-oversight-procedure' => new class($slug, $meta) extends Definition
            {
                public function sheets(): array
                {
                    return [[
                        'name' => 'Override log',
                        'editable_rows' => 300,
                        'columns' => [self::col('log_id', 'Log ID', 10), self::col('date', 'Date', 13, 'date'), self::col('system', 'System', 24), self::col('decision', 'Output or decision overridden', 44), self::select('action', 'Action taken', ['Overridden', 'Suspended', 'Escalated', 'Confirmed after review'], 22), self::col('reason', 'Reason', 44), self::col('overseer', 'Overseer', 18), self::col('reviewer', 'Reviewed by', 18), self::select('reported', 'Reported as incident', ['Yes', 'No'], 12)],
                        'rows' => [],
                    ], $this->dutiesSheet('Oversight duties', Records::obligations(['categories' => ['human_oversight']]))];
                }

                public function blocks(): array
                {
                    return [
                        $this->heading('1. Purpose'),
                        $this->p('This procedure sets out who oversees each AI system, what they must be able to see and do, when they intervene, and how an intervention is recorded. It is written against every recorded oversight duty, each cited below.'),
                        $this->heading('2. Roles and competence'),
                        $this->placeholder('The overseers for each system, their competence and training, and who they escalate to'),
                        $this->heading('3. What the overseer must be able to do'),
                        $this->list(['Understand the system\'s capabilities and limitations, and monitor its operation for anomalies.', 'Remain aware of automation bias: the tendency to over-rely on the output.', 'Interpret the output correctly, with the tools and information to do so.', 'Decide not to use the system, or to disregard, override or reverse its output.', 'Intervene in, interrupt or stop the system through a control designed for that purpose.']),
                        $this->placeholder('For each system: the interface or control that provides each capability above'),
                        $this->heading('4. When to intervene'),
                        $this->placeholder('Triggers: confidence thresholds, anomaly alerts, complaints, categories of decision that always need a human'),
                        $this->heading('5. Recording an intervention'),
                        $this->p('Every override, suspension or escalation is entered in the override log sheet with the reason and the reviewer. Entries that meet the incident threshold are reported through the incident playbook.'),
                        $this->heading('6. Duties this procedure serves'),
                        ...$this->dutySections(Records::obligations(['categories' => ['human_oversight']]), 'How this procedure meets the duty for the systems in scope'),
                    ];
                }
            },

            'ai-incident-response-playbook' => new class($slug, $meta) extends Definition
            {
                public function sheets(): array
                {
                    $domains = array_map(fn ($d) => $d['id'].'. '.$d['name'], Records::mitDomains());
                    $regulators = Records::regulatorRows();

                    return [
                        [
                            'name' => 'Incident log',
                            'editable_rows' => 300,
                            'columns' => [
                                self::col('incident_id', 'Incident ID', 10), self::col('detected', 'Detected on', 13, 'date'), self::col('system', 'System', 24),
                                self::select('severity', 'Severity', ['Critical', 'High', 'Medium', 'Low'], 12), self::select('domain', 'Harm domain (MIT)', $domains, 30),
                                self::col('summary', 'What happened', 50), self::col('affected', 'Who was affected', 30),
                                self::select('reportable', 'Reportable to a regulator', ['Yes', 'No', 'Assessing'], 14), self::col('regulator', 'Regulator', 26),
                                self::formula('deadline', 'Reporting deadline', '=IF(OR(B{r}="",H{r}<>"Yes"),"",B{r}+15)', 13, 'Fifteen days from detection is the EU AI Act default for serious incidents; adjust to the duty that applies (see the Playbook and the duties sheet).'),
                                self::formula('days_left', 'Days to deadline', '=IF(J{r}="","",J{r}-TODAY())', 10),
                                self::select('status', 'Status', ['Open', 'Contained', 'Reported', 'Closed'], 12), self::col('owner', 'Owner', 18), self::col('lessons', 'Lessons and follow-up', 44),
                            ],
                            'rows' => [],
                            'rules' => [['column' => 'days_left', 'op' => 'lessThan', 'value' => 3, 'fill' => 'F8D7DA'], ['column' => 'severity', 'op' => 'equal', 'value' => 'Critical', 'fill' => 'F8D7DA']],
                        ],
                        ['name' => 'Regulators', 'columns' => [self::col('jurisdiction', 'Jurisdiction', 22), self::col('regulator', 'Regulator', 40), self::col('role', 'Role', 40), self::col('url', 'Website', 44, 'url'), self::col('record', 'Record', 44, 'url')], 'rows' => $regulators],
                        $this->dutiesSheet('Incident duties', Records::obligations(['categories' => ['incident_handling', 'post_market_monitoring']])),
                    ];
                }

                public function blocks(): array
                {
                    $duties = Records::obligations(['categories' => ['incident_handling', 'post_market_monitoring']]);

                    return [
                        $this->heading('1. What counts as an incident'),
                        $this->p('An AI incident is an event where an AI system\'s output or behaviour caused, or nearly caused, harm to a person, an organisation or the environment, or a failure that would breach a recorded duty. Severity is set on the scale below.'),
                        $this->table(['Severity', 'Definition', 'Response'], [['Critical', 'Serious harm to a person, a breach of a prohibited practice, or a regulator-reportable event', 'Stop the system; convene the incident team within 1 hour; assess reporting duties same day'], ['High', 'Harm or a near miss affecting several people, or a material control failure', 'Contain within 24 hours; assess reporting duties'], ['Medium', 'Limited harm, contained, no reporting duty', 'Fix within the sprint; log'], ['Low', 'No harm; a defect or a complaint', 'Log; review at the next cycle']]),
                        $this->heading('2. Roles'),
                        $this->placeholder('Incident lead, system owner, legal, communications, and who decides on regulator notification'),
                        $this->heading('3. The playbook'),
                        $this->heading('Detect', 2), $this->list(['Monitoring alerts, user reports, complaints, overseer overrides, vendor notices.', 'Open a log entry within the hour of detection; the reporting clock may already be running.']),
                        $this->heading('Contain', 2), $this->list(['Suspend or roll back the system if the severity warrants it.', 'Preserve logs, inputs and outputs; do not delete.']),
                        $this->heading('Assess and report', 2), $this->p('Decide whether a recorded reporting duty applies, using the duties below, and notify the regulator within the deadline the duty sets. The log computes the deadline from the detection date.'),
                        $this->heading('Recover and learn', 2), $this->list(['Restore with the fix verified.', 'Record lessons; update the risk register and the controls that failed.']),
                        $this->heading('4. Reporting duties on record'),
                        ...$this->dutySections($duties, 'Whether this duty applies to the systems in scope, the regulator, and the deadline'),
                    ];
                }
            },

            'ai-vendor-due-diligence-questionnaire' => new class($slug, $meta) extends Definition
            {
                public function sheets(): array
                {
                    $questions = [
                        ['Governance', 'Do you maintain an AI governance policy with named accountable roles?', 'Policy document; org chart'],
                        ['Governance', 'Is your AI management system certified or assessed against ISO/IEC 42001 or an equivalent?', 'Certificate or assessment report'],
                        ['Governance', 'Which AI laws and regulations do you consider yourself subject to, and in what role?', 'Written statement'],
                        ['Data', 'What data was used to train, tune and evaluate the model, and what rights do you hold to it?', 'Data provenance summary'],
                        ['Data', 'Do you process our data to train or improve models? Can we opt out contractually?', 'Contract terms'],
                        ['Data', 'Where is data stored and processed, and which sub-processors are involved?', 'Sub-processor list'],
                        ['Model', 'What are the model\'s intended purposes and known limitations?', 'Model card or documentation'],
                        ['Model', 'How is the model evaluated for accuracy, robustness and bias, and how often?', 'Evaluation results'],
                        ['Model', 'How are changes to the model communicated to customers, and with what notice?', 'Change policy'],
                        ['Security', 'How are prompt injection, data exfiltration and model abuse mitigated?', 'Security documentation; test results'],
                        ['Security', 'Are you certified against ISO/IEC 27001 or SOC 2, and is AI infrastructure in scope?', 'Certificate; scope statement'],
                        ['Transparency', 'Can outputs be labelled as AI-generated, and are synthetic media watermarked?', 'Feature documentation'],
                        ['Transparency', 'Do you provide the information a deployer needs to meet its own transparency and oversight duties?', 'Instructions for use'],
                        ['Incidents', 'How do you detect and notify customers of incidents, and within what time?', 'Incident policy; SLA'],
                        ['Incidents', 'Have you had a reportable AI incident in the last three years?', 'Written statement'],
                        ['Insurance and indemnity', 'What cyber and technology liability cover do you carry, and does it exclude AI-related claims?', 'Certificate of insurance; policy exclusions'],
                        ['Insurance and indemnity', 'Do you indemnify customers for intellectual-property and copyright claims arising from model outputs?', 'Contract terms'],
                        ['Insurance and indemnity', 'Do you indemnify for regulatory fines or claims caused by your breach of an AI law?', 'Contract terms'],
                        ['Insurance and indemnity', 'Are your obligations flowed down to sub-processors, with audit rights?', 'Sub-processor terms'],
                        ['Insurance and indemnity', 'Do we have audit and assessment rights, and access to evidence on request?', 'Contract terms'],
                    ];
                    $rows = array_map(fn ($q, $n) => ['n' => $n + 1, 'section' => $q[0], 'question' => $q[1], 'evidence' => $q[2], 'response' => null, 'notes' => null], $questions, array_keys($questions));

                    return [
                        [
                            'name' => 'Questionnaire',
                            'columns' => [self::col('n', '#', 6, 'number'), self::col('section', 'Section', 22), self::col('question', 'Question', 70), self::col('evidence', 'Evidence requested', 34), self::select('response', 'Response', ['Yes', 'Partial', 'No', 'Not applicable'], 14), self::formula('score', 'Score', '=IF(E{r}="Yes",2,IF(E{r}="Partial",1,IF(E{r}="No",0,"")))', 8), self::col('notes', 'Vendor notes / our assessment', 44)],
                            'rows' => $rows,
                            'rules' => [['column' => 'response', 'op' => 'equal', 'value' => 'No', 'fill' => 'F8D7DA'], ['column' => 'response', 'op' => 'equal', 'value' => 'Partial', 'fill' => 'FFF3CD']],
                            'note' => 'Total score: sum the Score column; the maximum is twice the number of applicable questions.',
                        ],
                        $this->dutiesSheet('Vendor-governance duties', Records::obligations(['categories' => ['vendor_governance']])),
                    ];
                }

                public function blocks(): array
                {
                    return [
                        $this->heading('How to use this questionnaire'),
                        $this->p('Send the questionnaire sheet to the vendor; ask for the evidence, not only the answer. Score the responses and record your assessment. The duties sheet lists what the law expects you to have from a vendor, so a "No" can be weighed against a recorded requirement.'),
                        $this->heading('Insurance and indemnity'),
                        $this->p('The last section asks what protection you have if the system fails: whether the vendor\'s cover excludes AI claims, whether outputs are indemnified for intellectual-property claims, whether regulatory exposure is covered, and whether the same terms reach sub-processors. These questions decide who pays when something goes wrong; ask them before the contract is signed.'),
                        $this->heading('Duties this questionnaire serves'),
                        ...$this->dutySections(Records::obligations(['categories' => ['vendor_governance']]), 'Which questions, and which contract terms, meet this duty'),
                    ];
                }
            },

            default => null,
        };
    }

    /** @return list<string> */
    public static function roles(): array
    {
        return self::ROLES;
    }
}
