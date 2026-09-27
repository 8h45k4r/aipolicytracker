<?php

namespace App\Services\Templates\Definitions;

use App\Services\Templates\Definition;
use App\Services\Templates\Records;

/**
 * Running an AI governance programme: the templates an organisation uses after the
 * inventory and the risk register exist. Model cards, data governance, audit
 * evidence, literacy, monitoring, contract clauses, red-team testing and board
 * reporting. Every duty row and section is drawn from the records, so each file
 * cites the law it serves and is rebuilt when that law's record changes.
 */
final class Programme
{
    public static function make(string $slug, array $meta): ?Definition
    {
        return match ($slug) {
            'ai-model-card' => new class($slug, $meta) extends Definition
            {
                public function sheets(): array
                {
                    return [
                        [
                            'name' => 'Model card',
                            'editable_rows' => 60,
                            'columns' => [self::col('section', 'Section', 28), self::col('field', 'Field', 34), self::col('value', 'Your answer', 60), self::col('evidence', 'Evidence link', 36, 'url')],
                            'rows' => array_map(fn ($r) => ['section' => $r[0], 'field' => $r[1], 'value' => null, 'evidence' => null], self::fields()),
                            'note' => 'One card per model or AI system. Keep it with the technical documentation and update it on every material change.',
                        ],
                        $this->dutiesSheet('Documentation duties', Records::obligations(['categories' => ['technical_documentation', 'transparency', 'record_keeping']]), 'The recorded duties a model card helps evidence: technical documentation, transparency to deployers and users, and record keeping.'),
                    ];
                }

                public function blocks(): array
                {
                    $blocks = [
                        $this->heading('AI model card'),
                        $this->p('A model card describes what a model or AI system is for, how it was built and evaluated, and where it should not be used. Regulators and customers ask for this information under several of the laws on record; the sections below follow the questions they ask.'),
                    ];
                    foreach (collect(self::fields())->groupBy(0) as $section => $fields) {
                        $blocks[] = $this->heading($section, 2);
                        foreach ($fields as $f) {
                            $blocks[] = $this->placeholder($f[1]);
                        }
                    }
                    $blocks[] = $this->heading('The duties this card helps evidence');
                    array_push($blocks, ...$this->dutySections(Records::obligations(['categories' => ['technical_documentation']]), 'Where this card, or the documentation it points to, meets the duty'));

                    return $blocks;
                }

                /** @return list<array{0: string, 1: string}> */
                private static function fields(): array
                {
                    return [
                        ['Overview', 'Model or system name and version'], ['Overview', 'Provider, and the team accountable for it'], ['Overview', 'Release date and status'],
                        ['Intended use', 'Intended purpose and users'], ['Intended use', 'Uses that are out of scope or prohibited'], ['Intended use', 'Decisions about people it may influence'],
                        ['Data', 'Training, validation and test data: sources and dates'], ['Data', 'Personal data and its lawful basis'], ['Data', 'Known gaps and representativeness limits'],
                        ['Performance', 'Evaluation method and metrics'], ['Performance', 'Results overall and by relevant group'], ['Performance', 'Accuracy, robustness and security testing done'],
                        ['Risk', 'Known and foreseeable risks'], ['Risk', 'Mitigations in place'], ['Risk', 'Residual risk accepted, and by whom'],
                        ['Oversight', 'Human oversight measures and who exercises them'], ['Oversight', 'How users are told they are dealing with AI'],
                        ['Operations', 'Logging and monitoring in production'], ['Operations', 'How incidents and complaints are reported'], ['Operations', 'Change history and next review date'],
                    ];
                }
            },

            'ai-data-governance-register' => new class($slug, $meta) extends Definition
            {
                public function sheets(): array
                {
                    $yesNo = ['Yes', 'No', 'Unknown'];

                    return [
                        [
                            'name' => 'Datasets',
                            'editable_rows' => 200,
                            'columns' => [
                                self::col('dataset', 'Dataset', 26), self::col('system', 'AI system(s) using it', 24), self::col('owner', 'Data owner', 18),
                                self::select('use', 'Used for', ['Training', 'Validation', 'Testing', 'Fine-tuning', 'Retrieval / grounding', 'Production input'], 18),
                                self::col('source', 'Source and provenance', 34), self::select('personal', 'Personal data', $yesNo, 12), self::select('special', 'Special-category data', $yesNo, 14),
                                self::col('basis', 'Lawful basis / licence', 24), self::select('bias_checked', 'Bias and representativeness checked', $yesNo, 16),
                                self::col('quality', 'Quality checks done', 30), self::col('retention', 'Retention', 14), self::col('last_review', 'Last review', 13, 'date'), self::col('evidence', 'Evidence link', 30, 'url'),
                            ],
                            'rows' => [],
                            'note' => 'One row per dataset that trains, tests or feeds an AI system, including licensed and scraped data.',
                        ],
                        $this->dutiesSheet('Data duties', Records::obligations(['categories' => ['data_governance', 'privacy_data_protection', 'copyright_training_data']]), 'The recorded duties on training data, personal data and copyright.'),
                    ];
                }

                public function blocks(): array
                {
                    $blocks = [
                        $this->heading('AI data governance procedure'),
                        $this->p('How this organisation decides which data may train, test or feed an AI system, how that data is checked, and how the decision is recorded. The register workbook holds one row per dataset.'),
                        $this->heading('Scope and roles', 2), $this->placeholder('Systems and datasets in scope; the data owner and the approver for each'),
                        $this->heading('Acceptance criteria', 2), $this->placeholder('Provenance, licence and lawful basis required before a dataset is used'),
                        $this->heading('Quality and bias checks', 2), $this->placeholder('The checks run, their thresholds, and who signs off the result'),
                        $this->heading('Retention and deletion', 2), $this->placeholder('How long each class of dataset is kept, and how it is deleted'),
                        $this->heading('The duties this procedure serves'),
                    ];
                    array_push($blocks, ...$this->dutySections(Records::obligations(['categories' => ['data_governance', 'copyright_training_data']]), 'How the procedure meets this duty'));

                    return $blocks;
                }
            },

            'ai-audit-evidence-tracker' => new class($slug, $meta) extends Definition
            {
                public function sheets(): array
                {
                    $rows = [];
                    foreach (Records::controls() as $c) {
                        foreach ($c->evidence as $e) {
                            $rows[] = ['control' => $c->title, 'evidence' => $e->title, 'type' => str_replace('_', ' ', (string) $e->evidence_type), 'owner' => null, 'status' => null, 'location' => null, 'reviewed' => null, 'url' => $c->url()];
                        }
                    }

                    return [
                        [
                            'name' => 'Evidence tracker',
                            'editable_rows' => 50,
                            'columns' => [
                                self::col('control', 'Control', 34), self::col('evidence', 'Evidence expected', 38), self::col('type', 'Kind', 16), self::col('owner', 'Owner', 18),
                                self::select('status', 'Status', ['Not started', 'In progress', 'Available', 'Not applicable'], 14),
                                self::col('location', 'Where it is kept', 30, 'url'), self::col('reviewed', 'Last reviewed', 13, 'date'), self::col('url', 'Control record', 44, 'url'),
                            ],
                            'rows' => $rows,
                            'rules' => [['column' => 'status', 'op' => 'equal', 'value' => 'Not started', 'fill' => 'F8D7DA'], ['column' => 'status', 'op' => 'equal', 'value' => 'Available', 'fill' => 'D4EDDA']],
                            'note' => 'Every piece of evidence the controls on record expect, one row each. Fill in owner, status and location as the evidence is gathered; an auditor starts here.',
                        ],
                        $this->controlsSheet('Controls'),
                    ];
                }
            },

            'ai-literacy-training-plan' => new class($slug, $meta) extends Definition
            {
                public function sheets(): array
                {
                    return [
                        [
                            'name' => 'Training matrix',
                            'editable_rows' => 60,
                            'columns' => [
                                self::col('role', 'Role or group', 26), self::col('systems', 'AI systems they use or oversee', 30),
                                self::select('level', 'Level needed', ['Awareness', 'Practitioner', 'Specialist', 'Oversight'], 14),
                                self::col('topics', 'Topics', 40), self::select('format', 'Format', ['E-learning', 'Workshop', 'On the job', 'External course'], 14),
                                self::col('people', 'People', 8, 'number'), self::col('completed', 'Completed', 10, 'number'),
                                self::formula('coverage', 'Coverage', '=IF(OR(F{r}="",F{r}=0),"",G{r}/F{r})', 11, 'Completed over people; format as a percentage.'),
                                self::col('due', 'Due by', 13, 'date'), self::col('evidence', 'Evidence link', 30, 'url'),
                            ],
                            'rows' => array_map(fn ($r) => ['role' => $r[0], 'systems' => null, 'level' => $r[1], 'topics' => $r[2], 'format' => null, 'people' => null, 'completed' => null, 'due' => null, 'evidence' => null], [
                                ['Board and executives', 'Oversight', 'Accountability, risk appetite, the obligations on record, incident escalation'],
                                ['AI system owners', 'Specialist', 'Classification, documentation, oversight design, monitoring'],
                                ['Staff using AI tools', 'Awareness', 'Acceptable use, limits, data handling, disclosure to customers'],
                                ['Human overseers', 'Practitioner', 'Automation bias, when to override, how to escalate'],
                                ['Procurement and legal', 'Practitioner', 'Vendor due diligence, contract clauses, allocation of duties'],
                            ]),
                            'note' => 'AI literacy duties apply to staff and others operating AI on the organisation\'s behalf. One row per role; the coverage column shows progress.',
                        ],
                        $this->dutiesSheet('Literacy duties', Records::obligations(['categories' => ['ai_literacy', 'human_oversight']])),
                    ];
                }

                public function blocks(): array
                {
                    $blocks = [
                        $this->heading('AI literacy and training plan'),
                        $this->p('Who needs to understand AI to what depth, how they are trained, and how completion is evidenced. The workbook holds the training matrix.'),
                        $this->heading('Objectives', 2), $this->placeholder('What each group must be able to do after training'),
                        $this->heading('Curriculum', 2), $this->placeholder('Modules per level, owner of each, and refresh interval'),
                        $this->heading('Records', 2), $this->placeholder('How completion is recorded and kept as evidence'),
                        $this->heading('The duties this plan serves'),
                    ];
                    array_push($blocks, ...$this->dutySections(Records::obligations(['categories' => ['ai_literacy']]), 'How the plan meets this duty'));

                    return $blocks;
                }
            },

            'ai-post-market-monitoring-plan' => new class($slug, $meta) extends Definition
            {
                public function sheets(): array
                {
                    return [
                        [
                            'name' => 'Monitoring metrics',
                            'editable_rows' => 60,
                            'columns' => [
                                self::col('system', 'AI system', 24), self::col('metric', 'Metric', 30), self::col('threshold', 'Alert threshold', 18), self::col('frequency', 'Checked', 14),
                                self::col('owner', 'Owner', 18), self::col('latest', 'Latest value', 14), self::select('state', 'State', ['Within threshold', 'Alert', 'Not measured'], 16), self::col('action', 'Action if breached', 34),
                            ],
                            'rows' => array_map(fn ($m) => ['system' => null, 'metric' => $m[0], 'threshold' => $m[1], 'frequency' => $m[2], 'owner' => null, 'latest' => null, 'state' => null, 'action' => null], [
                                ['Accuracy on a held-out production sample', '[PLACEHOLDER: % below baseline]', 'Monthly'], ['Performance gap between relevant groups', '[PLACEHOLDER: max gap]', 'Monthly'],
                                ['Input data drift', '[PLACEHOLDER: drift score]', 'Weekly'], ['Human override rate', '[PLACEHOLDER: % of decisions]', 'Weekly'],
                                ['Complaints and appeals', '[PLACEHOLDER: count]', 'Monthly'], ['Serious incidents', 'Any', 'Continuous'],
                            ]),
                            'rules' => [['column' => 'state', 'op' => 'equal', 'value' => 'Alert', 'fill' => 'F8D7DA']],
                        ],
                        $this->dutiesSheet('Monitoring duties', Records::obligations(['categories' => ['post_market_monitoring', 'incident_handling']])),
                    ];
                }

                public function blocks(): array
                {
                    $blocks = [
                        $this->heading('Post-market monitoring plan'),
                        $this->p('How a deployed AI system is watched after release: what is measured, how often, what counts as a problem, and what happens then. Serious incidents follow the incident response playbook.'),
                        $this->heading('Systems covered', 2), $this->placeholder('Each system, its risk tier and its owner'),
                        $this->heading('Data collected', 2), $this->placeholder('Logs, samples and feedback collected, and how long they are kept'),
                        $this->heading('Review and escalation', 2), $this->placeholder('Who reviews the metrics, how often, and when a breach becomes an incident'),
                        $this->heading('The duties this plan serves'),
                    ];
                    array_push($blocks, ...$this->dutySections(Records::obligations(['categories' => ['post_market_monitoring', 'incident_handling']]), 'How the plan meets this duty'));

                    return $blocks;
                }
            },

            'ai-contract-clauses' => new class($slug, $meta) extends Definition
            {
                public function sheets(): array
                {
                    return [$this->dutiesSheet('Vendor duties', Records::obligations(['categories' => ['vendor_governance', 'technical_documentation', 'incident_handling']]), 'Duties a contract with an AI supplier should allocate.')];
                }

                public function blocks(): array
                {
                    $clauses = [
                        ['Description of the AI system', 'The Supplier shall provide, and keep current, a description of the AI system\'s intended purpose, capabilities, limitations and known risks, [PLACEHOLDER: format and update interval].'],
                        ['Documentation and cooperation', 'The Supplier shall provide the technical documentation, instructions for use and information the Customer reasonably requires to meet its obligations under applicable AI law, and cooperate with any regulator\'s request [PLACEHOLDER: timescales].'],
                        ['Data use', 'The Supplier shall not use Customer data to train or improve any model except [PLACEHOLDER: permitted uses], and shall delete it on [PLACEHOLDER: trigger].'],
                        ['Testing and performance', 'The Supplier warrants that the AI system has been tested for accuracy, robustness and security as described in [PLACEHOLDER: schedule] and shall notify the Customer of any material degradation within [PLACEHOLDER] days.'],
                        ['Incidents', 'The Supplier shall notify the Customer of any serious incident or malfunction affecting the AI system within [PLACEHOLDER: hours] and provide the information needed for the Customer\'s own reporting.'],
                        ['Changes', 'The Supplier shall give [PLACEHOLDER] days\' notice of any substantial modification to the AI system or its underlying model.'],
                        ['Human oversight', 'The Supplier shall design the AI system so that the Customer can exercise the human oversight described in [PLACEHOLDER: schedule], including the ability to override or stop it.'],
                        ['Audit', 'The Customer may audit, or appoint an independent auditor to audit, the Supplier\'s compliance with this Agreement\'s AI provisions [PLACEHOLDER: frequency and notice].'],
                        ['Allocation of regulatory roles', 'The parties agree that for the purposes of applicable AI law the Supplier acts as [PLACEHOLDER: provider / developer] and the Customer as [PLACEHOLDER: deployer], and each shall meet the obligations of that role.'],
                    ];
                    $blocks = [
                        $this->heading('AI contract clause library'),
                        $this->p('Clauses for buying or supplying AI systems, each written to allocate a duty that appears in the law on record. They are starting points for counsel, not legal advice; adapt them to the governing law and the deal.'),
                    ];
                    foreach ($clauses as $i => [$title, $text]) {
                        $blocks[] = $this->heading(($i + 1).'. '.$title, 2);
                        $blocks[] = $this->p($text);
                    }
                    $blocks[] = $this->heading('The duties these clauses allocate');
                    array_push($blocks, ...$this->dutySections(Records::obligations(['categories' => ['vendor_governance']]), 'Which clause allocates this duty'));

                    return $blocks;
                }
            },

            'ai-red-team-test-plan' => new class($slug, $meta) extends Definition
            {
                public function sheets(): array
                {
                    return [
                        [
                            'name' => 'Test cases',
                            'editable_rows' => 150,
                            'columns' => [
                                self::col('id', 'Test ID', 10), self::select('area', 'Area', ['Accuracy', 'Robustness', 'Security / prompt injection', 'Bias and fairness', 'Harmful content', 'Privacy leakage', 'Misuse'], 20),
                                self::col('scenario', 'Scenario', 44), self::col('expected', 'Expected behaviour', 34), self::col('result', 'Observed', 34),
                                self::select('outcome', 'Outcome', ['Pass', 'Fail', 'Partial', 'Not run'], 12), self::select('severity', 'Severity if failed', ['Critical', 'High', 'Medium', 'Low'], 14),
                                self::col('fix', 'Remediation', 30), self::col('retest', 'Retested', 13, 'date'),
                            ],
                            'rows' => [],
                            'rules' => [['column' => 'outcome', 'op' => 'equal', 'value' => 'Fail', 'fill' => 'F8D7DA']],
                            'note' => 'One row per test case. Plan the cases before the run; record what was observed, not what was hoped for.',
                        ],
                        $this->dutiesSheet('Testing duties', Records::obligations(['categories' => ['safety_testing', 'accuracy_robustness_security']])),
                    ];
                }

                public function blocks(): array
                {
                    $blocks = [
                        $this->heading('AI red-team and evaluation test plan'),
                        $this->p('The plan for testing an AI system adversarially before release and after material change: scope, method, who tests, and how findings are fixed.'),
                        $this->heading('Scope', 2), $this->placeholder('System, version, and the risks in and out of scope'),
                        $this->heading('Team and independence', 2), $this->placeholder('Who tests, and how independent they are from the builders'),
                        $this->heading('Method', 2), $this->placeholder('Test areas, tools and datasets; how severity is rated'),
                        $this->heading('Exit criteria', 2), $this->placeholder('The results that allow release, and who signs off'),
                        $this->heading('The duties this plan serves'),
                    ];
                    array_push($blocks, ...$this->dutySections(Records::obligations(['categories' => ['safety_testing', 'accuracy_robustness_security']]), 'Which tests evidence this duty'));

                    return $blocks;
                }
            },

            'ai-board-reporting-pack' => new class($slug, $meta) extends Definition
            {
                public function sheets(): array
                {
                    return [
                        [
                            'name' => 'Dashboard',
                            'editable_rows' => 30,
                            'columns' => [self::col('measure', 'Measure', 40), self::col('current', 'This quarter', 14), self::col('previous', 'Last quarter', 14), self::select('trend', 'Trend', ['Improving', 'Stable', 'Worsening'], 14), self::col('comment', 'Comment', 44)],
                            'rows' => array_map(fn ($m) => ['measure' => $m, 'current' => null, 'previous' => null, 'trend' => null, 'comment' => null], [
                                'AI systems in the inventory', 'High-risk systems', 'Systems with a completed impact assessment', 'Open high and critical risks', 'Serious incidents this quarter',
                                'Staff trained (AI literacy coverage)', 'Vendor assessments overdue', 'Regulatory deadlines in the next 12 months',
                            ]),
                        ],
                        $this->deadlinesSheet(null, 'Upcoming deadlines'),
                        $this->dutiesSheet('Governance duties', Records::obligations(['categories' => ['governance_accountability']])),
                    ];
                }

                public function blocks(): array
                {
                    return [
                        $this->heading('AI governance report to the board'),
                        $this->p('A quarterly report on the organisation\'s use of AI: what is in use, what could go wrong, what the law requires next, and the decisions the board is asked to take.'),
                        $this->heading('1. Summary', 2), $this->placeholder('Three sentences: the position, the main change since last quarter, the decision needed'),
                        $this->heading('2. AI in use', 2), $this->placeholder('Inventory totals by risk tier, new and retired systems'),
                        $this->heading('3. Risks and incidents', 2), $this->placeholder('Top risks, their owners and trend; incidents and lessons'),
                        $this->heading('4. Regulatory outlook', 2), $this->placeholder('Deadlines in the next twelve months from the Upcoming deadlines sheet, and readiness for each'),
                        $this->heading('5. Decisions requested', 2), $this->placeholder('Each decision, the options and the recommendation'),
                    ];
                }
            },

            default => null,
        };
    }
}
