<?php

namespace App\Services\Templates\Definitions;

use App\Services\Templates\Definition;
use App\Services\Templates\Records;
use Illuminate\Support\Collection;

/**
 * Compliance work packages: the templates a team picks up for one specific duty set.
 * EU AI Act deployer and GPAI provider packs, use-case intake, conformity assessment,
 * the AI supplement to a DPIA, explanation and appeal, employment bias audits,
 * substantial modification, frontier safety frameworks and a regulatory horizon scan.
 *
 * Each draws its duty rows from the records (by instrument, actor, category or the
 * control that serves them), so the file names the law it serves and is rebuilt
 * when that law's record changes. Questions and headings are generic; nothing in
 * them states a legal fact the records do not hold.
 */
final class Compliance
{
    private const STATUS = ['Not started', 'In progress', 'Done', 'Not applicable'];

    private const YES_NO = ['Yes', 'No', 'Unsure'];

    public static function make(string $slug, array $meta): ?Definition
    {
        return match ($slug) {
            'high-risk-deployer-compliance-pack' => new class($slug, $meta) extends Definition
            {
                private function duties(): Collection
                {
                    return Records::obligations(['policies' => ['eu-ai-act'], 'actors' => ['deployer']]);
                }

                public function sheets(): array
                {
                    return [
                        [
                            'name' => 'Deployer checklist',
                            'editable_rows' => 10,
                            'columns' => [
                                self::col('system', 'AI system', 24), self::col('duty', 'Duty', 46), self::col('ref', 'Reference', 14),
                                self::select('status', 'Status', Compliance::status(), 14), self::col('owner', 'Owner', 18),
                                self::col('evidence', 'Evidence link', 34, 'url'), self::col('due', 'Applies from', 13), self::col('url', 'Record', 40, 'url'),
                            ],
                            'rows' => $this->duties()->map(fn ($o) => ['system' => null, 'duty' => $o->title, 'ref' => $o->source_reference, 'status' => null, 'owner' => null, 'evidence' => null, 'due' => $o->applies_from?->toDateString(), 'url' => $o->url()])->all(),
                            'rules' => [['column' => 'status', 'op' => 'equal', 'value' => 'Not started', 'fill' => 'F8D7DA'], ['column' => 'status', 'op' => 'equal', 'value' => 'Done', 'fill' => 'D4EDDA']],
                            'note' => 'Copy the block of rows once per high-risk AI system you deploy. Each row is a recorded EU AI Act duty for deployers, with its reference and record.',
                        ],
                        [
                            'name' => 'Notices log',
                            'editable_rows' => 100,
                            'columns' => [
                                self::col('system', 'AI system', 24), self::select('audience', 'Who was told', ['Workers and their representatives', 'Affected persons', 'Users of the system'], 26),
                                self::col('channel', 'How (notice, letter, intranet, form)', 30), self::col('date', 'Date', 12, 'date'), self::col('text', 'Notice text or link', 40, 'url'), self::col('owner', 'Sent by', 18),
                            ],
                            'rows' => [],
                            'note' => 'A record of every notice given about a high-risk system in use: to workers before it is used at work, and to people it makes or supports decisions about.',
                        ],
                        $this->dutiesSheet('Deployer duties', $this->duties(), 'Every recorded EU AI Act duty for deployers, with its source reference, evidence examples and framework mappings.'),
                        $this->deadlinesSheet(['eu-ai-act']),
                    ];
                }

                public function blocks(): array
                {
                    return array_values(array_filter([
                        $this->heading('EU AI Act: high-risk deployer compliance pack'),
                        $this->p('What an organisation using a high-risk AI system has to do, one section per recorded deployer duty, each with room to record how it is met. Use one copy per system.'),
                        $this->dateCaveat('eu-ai-act'),
                        $this->heading('The system', 2),
                        $this->placeholder('System name, provider, version and the intended purpose stated in the provider\'s instructions for use'),
                        $this->placeholder('Where and for what it is used here, and the people it affects'),
                        $this->placeholder('Who is assigned human oversight, and their training and authority'),
                        $this->heading('Notice to workers (template)', 2),
                        $this->placeholder('[Organisation] will use [system] from [date] for [purpose]. It is a high-risk AI system under the EU AI Act. It will [describe its effect on work]. [Name, role] oversees it. Questions and concerns: [contact].'),
                        $this->heading('Notice to affected persons (template)', 2),
                        $this->placeholder('A decision about you [was made / is supported] by [system], an AI system. [What it does and the role it plays in the decision.] You may ask for an explanation of its role in the decision by contacting [contact].'),
                        $this->heading('Duties, one by one'),
                        ...$this->dutySections($this->duties(), 'How we meet this duty for this system, and where the evidence is kept'),
                    ]));
                }
            },

            'ai-use-case-intake-triage' => new class($slug, $meta) extends Definition
            {
                public function sheets(): array
                {
                    $prohibited = Records::obligations(['categories' => ['prohibited_practice']]);

                    return [
                        [
                            'name' => 'Intake',
                            'editable_rows' => 150,
                            'columns' => [
                                self::col('id', 'Request ID', 12), self::col('date', 'Date', 12, 'date'), self::col('requester', 'Requested by', 18), self::col('use_case', 'Use case', 34),
                                self::select('build', 'Build or buy', ['Build', 'Buy', 'Buy and adapt', 'Use a public service'], 16),
                                self::select('people', 'Makes or supports decisions about people', Compliance::yesNo(), 16),
                                self::select('personal', 'Uses personal data', Compliance::yesNo(), 12),
                                self::select('genai', 'Generates content', Compliance::yesNo(), 12),
                                self::col('jurisdictions', 'Where it will be used', 22),
                                self::select('screen', 'Prohibited-use screen', ['Passed', 'Failed', 'Referred to legal'], 18),
                                self::select('route', 'Route', ['Standard review', 'Enhanced review (possible high-risk)', 'Transparency review', 'Rejected'], 26),
                                self::col('decision', 'Decision and date', 22), self::col('inventory', 'Inventory entry', 18),
                            ],
                            'rows' => [],
                            'rules' => [['column' => 'screen', 'op' => 'equal', 'value' => 'Failed', 'fill' => 'F8D7DA']],
                            'note' => 'One row per proposed AI use, before any work starts. A failed prohibited-use screen ends the request; a possible high-risk use goes to enhanced review.',
                        ],
                        [
                            'name' => 'Prohibited-use screen',
                            'columns' => [self::col('practice', 'Practice on record', 50), self::col('ref', 'Reference', 14), self::col('where', 'Instrument', 26), self::select('applies', 'Could this use involve it?', Compliance::yesNo(), 18), self::col('reason', 'Reasoning', 40), self::col('url', 'Record', 40, 'url')],
                            'rows' => $prohibited->map(fn ($o) => ['practice' => $o->title, 'ref' => $o->source_reference, 'where' => $o->policyInstrument->short_title ?: $o->policyInstrument->title, 'applies' => null, 'reason' => null, 'url' => $o->url()])->all(),
                            'rules' => [['column' => 'applies', 'op' => 'equal', 'value' => 'Yes', 'fill' => 'F8D7DA']],
                            'note' => 'Every prohibited practice on record. Answer each for the use case; any "Yes" stops the request until legal has reviewed it.',
                        ],
                        $this->dutiesSheet('Risk duties', Records::obligations(['categories' => ['risk_management', 'impact_assessment']]), 'The recorded risk-management and impact-assessment duties an approved use case may trigger.'),
                    ];
                }

                public function blocks(): array
                {
                    return [
                        $this->heading('AI use-case intake and triage procedure'),
                        $this->p('Every proposed use of AI is registered and screened before work starts, so that prohibited uses are stopped early, likely high-risk uses get the review they need, and the AI inventory stays complete.'),
                        $this->heading('1. Register the request', 2), $this->placeholder('Who may submit a request, the form or channel, and the information required'),
                        $this->heading('2. Screen for prohibited practices', 2), $this->p('The workbook lists every prohibited practice on record. A "yes" or "unsure" to any of them stops the request until legal has reviewed it.'),
                        $this->heading('3. Classify and route', 2), $this->placeholder('The questions that decide the route: decisions about people, sensitive areas of use, personal data, generated content, jurisdictions'),
                        $this->table(['Route', 'When', 'Who reviews'], [['Standard review', 'Low-impact internal use', '[role]'], ['Enhanced review', 'Possible high-risk use or decisions about people', '[role]'], ['Transparency review', 'Chatbots and generated content', '[role]'], ['Rejected', 'Failed prohibited-use screen', '[role]']]),
                        $this->heading('4. Record the decision', 2), $this->placeholder('Who decides, how the decision is recorded, and how approved uses enter the AI inventory'),
                        $this->heading('Prohibited practices on record'),
                        ...$this->dutySections(Records::obligations(['categories' => ['prohibited_practice']]), 'How the intake screen detects this practice'),
                    ];
                }
            },

            'gpai-model-provider-compliance-kit' => new class($slug, $meta) extends Definition
            {
                private function duties(): Collection
                {
                    return Records::obligations(['actors' => ['gpai_provider']]);
                }

                public function sheets(): array
                {
                    return [
                        [
                            'name' => 'Provider checklist',
                            'columns' => [self::col('duty', 'Duty', 48), self::col('where', 'Instrument', 26), self::col('ref', 'Reference', 14), self::select('systemic', 'Systemic-risk models only', ['Yes', 'No'], 14), self::select('status', 'Status', Compliance::status(), 14), self::col('owner', 'Owner', 18), self::col('evidence', 'Evidence link', 34, 'url'), self::col('url', 'Record', 40, 'url')],
                            'rows' => $this->duties()->map(fn ($o) => ['duty' => $o->title, 'where' => $o->policyInstrument->short_title ?: $o->policyInstrument->title, 'ref' => $o->source_reference, 'systemic' => null, 'status' => null, 'owner' => null, 'evidence' => null, 'url' => $o->url()])->all(),
                            'rules' => [['column' => 'status', 'op' => 'equal', 'value' => 'Not started', 'fill' => 'F8D7DA'], ['column' => 'status', 'op' => 'equal', 'value' => 'Done', 'fill' => 'D4EDDA']],
                            'note' => 'Every recorded duty of general-purpose AI model providers. Mark the ones that apply only to models with systemic risk from each record.',
                        ],
                        [
                            'name' => 'Downstream information',
                            'editable_rows' => 40,
                            'columns' => [self::col('item', 'Information item', 40), self::col('content', 'What we provide', 50), self::col('where', 'Where downstream providers find it', 34, 'url'), self::col('updated', 'Last updated', 13, 'date')],
                            'rows' => array_map(fn ($i) => ['item' => $i, 'content' => null, 'where' => null, 'updated' => null], ['Model name, version and release date', 'Tasks the model is intended for, and uses it should not be put to', 'Acceptable use policy', 'Architecture and number of parameters', 'Input and output modalities and formats', 'Licence', 'Technical means needed to integrate it', 'Known limitations and evaluation results', 'Training data: types, provenance and curation', 'Contact for questions']),
                            'note' => 'What downstream providers building on the model need in order to understand it and meet their own duties. Keep it current with each release.',
                        ],
                        [
                            'name' => 'Training content summary',
                            'editable_rows' => 40,
                            'columns' => [self::col('source', 'Data source or category', 34), self::select('kind', 'Kind', ['Public web data', 'Licensed data', 'User data', 'Synthetic data', 'Other'], 18), self::col('period', 'Collection period', 16), self::col('share', 'Approximate share', 14), self::col('optout', 'Rights reservations respected and how', 40), self::col('notes', 'Notes', 30)],
                            'rows' => [],
                            'note' => 'A working sheet for the public summary of training content and for the copyright policy. Use the official template where an authority publishes one.',
                        ],
                        $this->dutiesSheet('GPAI provider duties', $this->duties()),
                    ];
                }

                public function blocks(): array
                {
                    return array_values(array_filter([
                        $this->heading('General-purpose AI model provider compliance kit'),
                        $this->p('The documents a provider of a general-purpose AI model keeps: technical documentation for authorities, information for downstream providers, a copyright policy, a public summary of training content and, for models with systemic risk, evaluation, risk mitigation, incident reporting and cybersecurity.'),
                        $this->dateCaveat('eu-ai-act'),
                        $this->heading('Copyright policy', 2), $this->placeholder('How the organisation identifies and respects reservations of rights when collecting training data, and who is responsible'),
                        $this->heading('Systemic-risk assessment', 2), $this->placeholder('Whether the model meets the threshold for systemic risk, how that was assessed, and the evaluations and adversarial tests run'),
                        $this->heading('Serious incidents', 2), $this->placeholder('How serious incidents are detected, recorded and reported, and to whom'),
                        $this->heading('Duties on record'),
                        ...$this->dutySections($this->duties(), 'How we meet this duty, and where the evidence is kept'),
                    ]));
                }
            },

            'eu-ai-act-conformity-assessment-and-qms' => new class($slug, $meta) extends Definition
            {
                private function duties(): Collection
                {
                    return Records::obligations(['policies' => ['eu-ai-act'], 'categories' => ['conformity_assessment', 'quality_management', 'technical_documentation', 'record_keeping', 'risk_management', 'data_governance', 'accuracy_robustness_security', 'human_oversight', 'post_market_monitoring']]);
                }

                public function sheets(): array
                {
                    return [
                        [
                            'name' => 'Requirement to evidence',
                            'columns' => [self::col('category', 'Area', 22), self::col('duty', 'Requirement', 46), self::col('ref', 'Reference', 14), self::col('evidence', 'Evidence in the technical file', 40, 'url'), self::select('status', 'Status', Compliance::status(), 14), self::col('reviewer', 'Checked by', 16), self::col('url', 'Record', 40, 'url')],
                            'rows' => $this->duties()->map(fn ($o) => ['category' => Records::categoryName($o->category), 'duty' => $o->title, 'ref' => $o->source_reference, 'evidence' => null, 'status' => null, 'reviewer' => null, 'url' => $o->url()])->all(),
                            'rules' => [['column' => 'status', 'op' => 'equal', 'value' => 'Not started', 'fill' => 'F8D7DA'], ['column' => 'status', 'op' => 'equal', 'value' => 'Done', 'fill' => 'D4EDDA']],
                            'note' => 'Every recorded EU AI Act requirement a conformity assessment checks, mapped to the evidence that shows it is met.',
                        ],
                        [
                            'name' => 'QMS elements',
                            'columns' => [self::col('element', 'Quality management element', 50), self::col('document', 'Document or procedure', 34, 'url'), self::col('owner', 'Owner', 18), self::col('approved', 'Approved', 12, 'date'), self::select('status', 'Status', Compliance::status(), 14)],
                            'rows' => array_map(fn ($e) => ['element' => $e, 'document' => null, 'owner' => null, 'approved' => null, 'status' => null], ['Strategy for regulatory compliance, including change management', 'Design, design control and design verification', 'Development, quality control and quality assurance', 'Examination, test and validation procedures', 'Technical specifications and standards applied', 'Data management', 'Risk management system', 'Post-market monitoring system', 'Serious incident reporting', 'Communication with authorities and other parties', 'Record keeping', 'Resource management, including supply chain', 'Accountability framework']),
                            'note' => 'The elements a provider\'s quality management system documents. Link each to the procedure that implements it.',
                        ],
                        [
                            'name' => 'Re-assessment triggers',
                            'editable_rows' => 60,
                            'columns' => [self::col('date', 'Date', 12, 'date'), self::col('change', 'Change made', 40), self::select('substantial', 'Substantial modification?', Compliance::yesNo(), 16), self::col('reason', 'Reasoning', 40), self::select('reassess', 'New assessment needed', Compliance::yesNo(), 16), self::col('decided', 'Decided by', 16)],
                            'rows' => [],
                            'rules' => [['column' => 'reassess', 'op' => 'equal', 'value' => 'Yes', 'fill' => 'FFF3CD']],
                            'note' => 'Every change to the system after its assessment, with the decision on whether it needs a new one.',
                        ],
                        $this->dutiesSheet('Requirements on record', $this->duties()),
                    ];
                }

                public function blocks(): array
                {
                    return array_values(array_filter([
                        $this->heading('EU AI Act conformity assessment and quality management'),
                        $this->p('A working file for a provider preparing a high-risk AI system for conformity assessment: the requirements to evidence, the quality management system behind them, and the draft declaration of conformity.'),
                        $this->dateCaveat('eu-ai-act'),
                        $this->heading('Assessment route', 2), $this->placeholder('Internal control or assessment with a notified body, and why; the notified body\'s name and number where one is used'),
                        $this->heading('Harmonised standards and specifications applied', 2), $this->placeholder('List the standards or common specifications applied, in full or in part'),
                        $this->heading('Draft EU declaration of conformity', 2),
                        $this->list(['AI system name, type and any other unambiguous reference', 'Name and address of the provider (and authorised representative)', 'Statement that the declaration is issued under the sole responsibility of the provider', 'Statement that the system conforms to the regulation and any other relevant EU law', 'Standards or specifications applied', 'Notified body, procedure and certificate, where applicable', 'Place and date of issue, name and function of the signatory, signature']),
                        $this->placeholder('Complete each item above against the recorded requirement and the official text'),
                        $this->heading('Requirements, one by one'),
                        ...$this->dutySections($this->duties(), 'Where the technical file shows this requirement is met'),
                    ]));
                }
            },

            'ai-dpia-supplement' => new class($slug, $meta) extends Definition
            {
                private function duties(): Collection
                {
                    return Records::obligations(['categories' => ['privacy_data_protection', 'impact_assessment']]);
                }

                public function sheets(): array
                {
                    return [
                        [
                            'name' => 'AI questions',
                            'columns' => [self::col('area', 'Area', 20), self::col('question', 'Question to add to the DPIA', 60), self::col('answer', 'Answer', 50), self::select('risk', 'Risk', ['Low', 'Medium', 'High'], 10), self::col('mitigation', 'Mitigation', 40)],
                            'rows' => array_map(fn ($q) => ['area' => $q[0], 'question' => $q[1], 'answer' => null, 'risk' => null, 'mitigation' => null], Compliance::dpiaQuestions()),
                            'rules' => [['column' => 'risk', 'op' => 'equal', 'value' => 'High', 'fill' => 'F8D7DA']],
                            'note' => 'Questions specific to AI processing, to answer alongside the standard data protection impact assessment.',
                        ],
                        $this->dutiesSheet('Privacy and impact duties', $this->duties(), 'The recorded privacy and impact-assessment duties for AI systems.'),
                    ];
                }

                public function blocks(): array
                {
                    $blocks = [
                        $this->heading('AI supplement to a data protection impact assessment'),
                        $this->p('A data protection impact assessment written for ordinary processing misses what is specific to AI: inferences, training on personal data, accuracy across groups, automated decisions and model behaviour. These sections are added to the DPIA, not substituted for it.'),
                    ];
                    foreach (collect(Compliance::dpiaQuestions())->groupBy(0) as $area => $qs) {
                        $blocks[] = $this->heading($area, 2);
                        foreach ($qs as $q) {
                            $blocks[] = $this->placeholder($q[1]);
                        }
                    }
                    $blocks[] = $this->heading('Duties on record');
                    array_push($blocks, ...$this->dutySections($this->duties(), 'How this assessment addresses the duty'));

                    return $blocks;
                }
            },

            'adverse-decision-explanation-and-appeal-kit' => new class($slug, $meta) extends Definition
            {
                private function duties(): Collection
                {
                    return Records::obligations(['controls' => ['decision-explanation-and-appeal-route']]);
                }

                public function sheets(): array
                {
                    return [
                        [
                            'name' => 'Requests log',
                            'editable_rows' => 200,
                            'columns' => [
                                self::col('ref', 'Reference', 12), self::col('received', 'Received', 12, 'date'), self::col('system', 'AI system', 20),
                                self::select('type', 'Request', ['Explanation', 'Human review', 'Correction of data', 'Appeal'], 18),
                                self::col('due', 'Reply due', 12, 'date'), self::col('replied', 'Replied', 12, 'date'),
                                self::formula('days', 'Days taken', '=IF(F{r}="","",F{r}-B{r})', 12),
                                self::select('outcome', 'Outcome', ['Upheld', 'Changed', 'Withdrawn', 'Open'], 14), self::col('reviewer', 'Human reviewer', 18), self::col('notes', 'Notes', 30),
                            ],
                            'rows' => [],
                            'rules' => [['column' => 'outcome', 'op' => 'equal', 'value' => 'Open', 'fill' => 'FFF3CD']],
                            'note' => 'Every request from a person about an AI-assisted decision, with the time taken to answer it. Set the reply-due date from the rule that applies to you.',
                        ],
                        $this->dutiesSheet('Explanation and appeal duties', $this->duties(), 'The recorded duties served by an explanation and appeal route, across every jurisdiction on record.'),
                    ];
                }

                public function blocks(): array
                {
                    return [
                        $this->heading('Adverse decision explanation and appeal kit'),
                        $this->p('Several laws on record give people a right to know that AI was used in a decision about them, to get an explanation, to correct their data and to have a human look again. This kit holds the letters and the procedure; the workbook logs each request.'),
                        $this->heading('Adverse decision notice (template)', 2),
                        $this->placeholder('We have decided [decision]. [System], an automated tool, [made / supported] this decision. The main reasons were [principal reasons, the data used and its source]. You can ask us to explain it further, to correct information about you, or to have a person review the decision, by [contact and deadline].'),
                        $this->heading('Explanation reply (template)', 2),
                        $this->placeholder('The role the system played; the main factors and how they affected the outcome; the data about you it used and where it came from; what would have changed the result; how to appeal'),
                        $this->heading('Human review procedure', 2),
                        $this->placeholder('Who reviews (independent of the original decision), what they look at, the deadline, and how the outcome is recorded and communicated'),
                        $this->heading('Duties on record'),
                        ...$this->dutySections($this->duties(), 'How this route meets the duty'),
                    ];
                }
            },

            'employment-ai-bias-audit-kit' => new class($slug, $meta) extends Definition
            {
                private function duties(): Collection
                {
                    return Records::obligations(['policies' => ['us-new-york-city-local-law-144-automated-employment-decision-tools', 'us-illinois-hb-3773-ai-in-employment', 'us-colorado-automated-decision-making-technology-act']]);
                }

                public function sheets(): array
                {
                    return [
                        [
                            'name' => 'Impact ratios',
                            'editable_rows' => 30,
                            'columns' => [
                                self::col('category', 'Category (e.g. sex, race or ethnicity, intersectional)', 34),
                                self::col('applicants', 'Applicants assessed', 14, 'number'), self::col('selected', 'Selected or scored above cut-off', 16, 'number'),
                                self::formula('rate', 'Selection rate', '=IF(B{r}>0,C{r}/B{r},"")', 14),
                                self::formula('ratio', 'Impact ratio', '=IFERROR(D{r}/MAX(D:D),"")', 14, 'This category\'s selection rate divided by the highest rate in the table'),
                                self::col('notes', 'Notes', 34),
                            ],
                            'rows' => [],
                            'rules' => [['column' => 'ratio', 'op' => 'lessThan', 'value' => 0.8, 'fill' => 'FFF3CD']],
                            'note' => 'One row per category. The impact ratio compares each category\'s selection rate with the highest; values under 0.8 (the four-fifths rule of thumb) are highlighted for review, which is a prompt to look closer, not a legal threshold.',
                        ],
                        [
                            'name' => 'Audit and notices',
                            'editable_rows' => 40,
                            'columns' => [self::col('tool', 'Tool', 22), self::col('auditor', 'Independent auditor', 22), self::col('audit_date', 'Audit date', 12, 'date'), self::col('published', 'Summary published at', 30, 'url'), self::col('notice_date', 'Candidate notice from', 14, 'date'), self::col('alternative', 'Alternative process offered', 28), self::col('next', 'Next audit due', 13, 'date')],
                            'rows' => [],
                            'note' => 'One row per automated employment decision tool in use.',
                        ],
                        $this->dutiesSheet('Employment AI duties', $this->duties(), 'The recorded duties for AI in hiring and employment: New York City, Illinois and Colorado.'),
                    ];
                }

                public function blocks(): array
                {
                    return [
                        $this->heading('Employment AI bias audit kit'),
                        $this->p('For employers and employment agencies using automated tools to screen, rank or score candidates and employees. The workbook calculates selection rates and impact ratios from your own numbers; the sections below hold the notices and the audit summary.'),
                        $this->heading('Candidate notice (template)', 2),
                        $this->placeholder('[Employer] uses an automated employment decision tool to assess applicants for [role]. It evaluates [job qualifications and characteristics]. You may request an alternative selection process or accommodation by [contact]. Information about the data collected and our data retention policy is available at [link].'),
                        $this->heading('Audit summary for publication', 2),
                        $this->placeholder('Date of the most recent audit, the source and explanation of the data used, the number of individuals assessed, selection or scoring rates and impact ratios by category, and the date the tool was first used'),
                        $this->heading('Duties on record'),
                        ...$this->dutySections($this->duties(), 'How we meet this duty'),
                    ];
                }
            },

            'ai-substantial-modification-change-log' => new class($slug, $meta) extends Definition
            {
                private function duties(): Collection
                {
                    return Records::obligations(['controls' => ['model-release-and-change-management-gate']]);
                }

                public function sheets(): array
                {
                    return [
                        [
                            'name' => 'Change log',
                            'editable_rows' => 300,
                            'columns' => [
                                self::col('date', 'Date', 12, 'date'), self::col('system', 'AI system', 20), self::col('version', 'New version', 12), self::col('change', 'What changed', 40),
                                self::select('purpose', 'Changes the intended purpose', Compliance::yesNo(), 14),
                                self::select('performance', 'Affects accuracy, robustness or safety', Compliance::yesNo(), 16),
                                self::select('data', 'New training data or retraining', Compliance::yesNo(), 14),
                                self::select('foreseen', 'Foreseen in the original assessment', Compliance::yesNo(), 16),
                                self::formula('flag', 'Possible substantial modification', '=IF(OR(E{r}="Yes",AND(F{r}="Yes",H{r}="No")),"Review","")', 18),
                                self::col('decision', 'Decision and reasoning', 36), self::col('approved', 'Approved by', 16),
                            ],
                            'rows' => [],
                            'rules' => [['column' => 'flag', 'op' => 'equal', 'value' => 'Review', 'fill' => 'FFF3CD']],
                            'note' => 'Every release of an AI system after it is in use. A change of intended purpose, or an unforeseen change to performance, is flagged for a decision on whether the system needs a new assessment, or whether the organisation has become its provider.',
                        ],
                        $this->dutiesSheet('Change-related duties', $this->duties(), 'The recorded duties served by a release and change-management gate.'),
                    ];
                }

                public function blocks(): array
                {
                    return [
                        $this->heading('AI change management and substantial modification procedure'),
                        $this->p('Changes to an AI system can change who is responsible for it and whether it needs to be assessed again. This procedure decides, for every release, whether a change is substantial, and records why.'),
                        $this->heading('What counts as a release', 2), $this->placeholder('Model updates, retraining, new data sources, changes of purpose or users, and configuration changes that alter behaviour'),
                        $this->heading('The test', 2), $this->list(['Does the change alter the intended purpose?', 'Does it affect compliance with a requirement (accuracy, robustness, oversight, data)?', 'Was it foreseen and documented in the original assessment?', 'If it was made by someone other than the provider, does it make them the provider?']),
                        $this->heading('Approval', 2), $this->placeholder('Who approves a release, and who decides a possible substantial modification'),
                        $this->heading('Duties on record'),
                        ...$this->dutySections($this->duties(), 'How the change gate meets this duty'),
                    ];
                }
            },

            'frontier-ai-safety-framework' => new class($slug, $meta) extends Definition
            {
                private function duties(): Collection
                {
                    return Records::obligations(['controls' => ['frontier-model-safety-framework']]);
                }

                public function sheets(): array
                {
                    return [
                        [
                            'name' => 'Risk thresholds',
                            'editable_rows' => 30,
                            'columns' => [self::col('risk', 'Catastrophic or severe risk area', 30), self::col('threshold', 'Capability threshold', 40), self::col('evaluation', 'Evaluation used', 34), self::col('result', 'Latest result', 24), self::col('mitigation', 'Mitigation required at threshold', 40), self::col('date', 'Assessed', 12, 'date')],
                            'rows' => array_map(fn ($r) => ['risk' => $r, 'threshold' => null, 'evaluation' => null, 'result' => null, 'mitigation' => null, 'date' => null], ['Chemical, biological, radiological or nuclear uplift', 'Cyber offence', 'Loss of control or autonomous replication', 'Large-scale manipulation or deception']),
                            'note' => 'The risk areas the framework covers, the capability level that triggers extra safeguards, and how each is tested.',
                        ],
                        [
                            'name' => 'Safety incidents',
                            'editable_rows' => 100,
                            'columns' => [self::col('id', 'ID', 10), self::col('date', 'Discovered', 12, 'date'), self::col('model', 'Model', 18), self::col('what', 'What happened', 44), self::select('critical', 'Critical safety incident', Compliance::yesNo(), 14), self::col('reported', 'Reported to authority on', 16, 'date'), self::col('actions', 'Actions taken', 36)],
                            'rows' => [],
                            'rules' => [['column' => 'critical', 'op' => 'equal', 'value' => 'Yes', 'fill' => 'F8D7DA']],
                            'note' => 'Every safety incident with a frontier model, and whether and when it was reported.',
                        ],
                        $this->dutiesSheet('Frontier model duties', $this->duties(), 'The recorded duties served by a frontier model safety framework, across every jurisdiction on record.'),
                    ];
                }

                public function blocks(): array
                {
                    return [
                        $this->heading('Frontier AI safety framework and transparency report'),
                        $this->p('An outline for the published safety framework of a developer of frontier models, and for the transparency report that accompanies a new model. Laws on record ask large developers to publish both; the sections follow the questions they ask.'),
                        $this->heading('Framework', 2),
                        $this->placeholder('How the developer identifies and assesses catastrophic risk, the capability thresholds it uses, and the evaluations behind them'),
                        $this->placeholder('The mitigations applied at each threshold, and how their effectiveness is checked'),
                        $this->placeholder('Third-party assessment, cybersecurity of model weights, and internal governance'),
                        $this->placeholder('How critical safety incidents are identified and responded to'),
                        $this->heading('Transparency report for a new model', 2),
                        $this->placeholder('Release date, languages and modalities, intended uses and restrictions; summary of catastrophic-risk assessments and their results; third-party involvement'),
                        $this->heading('Whistleblower channel', 2), $this->placeholder('How employees can report concerns internally and to authorities, and how they are protected'),
                        $this->heading('Duties on record'),
                        ...$this->dutySections($this->duties(), 'Where the framework or report meets this duty'),
                    ];
                }
            },

            'ai-regulatory-horizon-scan' => new class($slug, $meta) extends Definition
            {
                public function sheets(): array
                {
                    return [
                        [
                            'name' => 'Changes (12 months)',
                            'columns' => [
                                self::col('date', 'Date', 12), self::col('jurisdiction', 'Jurisdiction', 18), self::col('title', 'Change', 48), self::col('instrument', 'Instrument', 26),
                                self::col('impact', 'Impact on record', 12), self::col('what', 'What changed', 50), self::col('practical', 'In practice', 50), self::col('verified', 'Review', 14),
                                self::select('relevance', 'Relevant to us', ['High', 'Medium', 'Low', 'None'], 14), self::col('owner', 'Owner', 16), self::col('action', 'Our action', 34), self::col('url', 'Record', 40, 'url'),
                            ],
                            'rows' => Records::changeRows(12),
                            'rules' => [['column' => 'relevance', 'op' => 'equal', 'value' => 'High', 'fill' => 'F8D7DA']],
                            'note' => 'Every AI policy change recorded in the last twelve months, newest first. Rate each for your organisation and assign an owner; the file is rebuilt when new changes are recorded.',
                        ],
                        $this->deadlinesSheet(null, 'Upcoming deadlines'),
                    ];
                }

                public function blocks(): array
                {
                    $rows = Records::changeRows(3);

                    return [
                        $this->heading('AI regulatory horizon scan'),
                        $this->p('A quarterly briefing built from the change log: what changed in AI law and guidance, what it means in practice, and what this organisation will do about it. The workbook carries twelve months of changes and every recorded deadline.'),
                        $this->heading('Summary for leadership', 2), $this->placeholder('The three changes that matter most to us this quarter, and the decisions needed'),
                        $this->heading('Changes in the last three months', 2),
                        $rows === [] ? $this->p('No changes recorded in the last three months.') : $this->table(['Date', 'Jurisdiction', 'Change', 'Impact'], array_map(fn ($r) => [$r['date'], (string) $r['jurisdiction'], $r['title'], $r['impact']], array_slice($rows, 0, 40))),
                        $this->heading('Our response', 2), $this->placeholder('For each relevant change: owner, action and due date'),
                        $this->note('Changes marked "Not yet verified" have not been confirmed against the official source by a reviewer; open the record and its source before acting on one.'),
                    ];
                }
            },

            default => null,
        };
    }

    /** @return list<string> */
    public static function status(): array
    {
        return self::STATUS;
    }

    /** @return list<string> */
    public static function yesNo(): array
    {
        return self::YES_NO;
    }

    /** @return list<array{0: string, 1: string}> */
    public static function dpiaQuestions(): array
    {
        return [
            ['Purpose and necessity', 'What decision or output does the AI system produce, and why is AI necessary for it?'],
            ['Purpose and necessity', 'Could the same purpose be met with less personal data or without AI?'],
            ['Data', 'Which personal data trains, tunes or grounds the model, and what is its lawful basis?'],
            ['Data', 'Does the system infer new personal data (for example characteristics, preferences or risk scores)?'],
            ['Data', 'Is special-category or children\'s data used or inferred?'],
            ['Data', 'Can personal data be extracted from the model or its outputs?'],
            ['Accuracy and fairness', 'How accurate is the system, and how does accuracy differ across groups?'],
            ['Accuracy and fairness', 'What bias testing was done, with what result?'],
            ['Automated decisions', 'Does the system make decisions with legal or similarly significant effects without meaningful human involvement?'],
            ['Automated decisions', 'How can a person get an explanation, contest a decision and obtain human review?'],
            ['Transparency', 'How are people told that AI is used, and what they are told?'],
            ['Rights', 'How are access, rectification, erasure and objection handled when data is in a model?'],
            ['Security', 'What protects against model attacks such as prompt injection, data poisoning and model inversion?'],
            ['Suppliers', 'Which providers process the data, where, and may they use it to train their own models?'],
            ['Monitoring', 'How is the system monitored after launch, and what triggers a review of this assessment?'],
        ];
    }
}
