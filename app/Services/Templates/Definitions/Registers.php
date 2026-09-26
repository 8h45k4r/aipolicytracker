<?php

namespace App\Services\Templates\Definitions;

use App\Services\Templates\Definition;
use App\Services\Templates\Records;

/**
 * The registers: inventory, risk register, agent registry, applicability
 * matrix, technical documentation. Each is an anonymous Definition so the
 * group stays one autoloadable file.
 */
final class Registers
{
    public static function make(string $slug, array $meta): ?Definition
    {
        return match ($slug) {
            'ai-system-inventory' => new class($slug, $meta) extends Definition
            {
                public function sheets(): array
                {
                    $yesNo = ['Yes', 'No', 'Unknown'];

                    return [
                        [
                            'name' => 'Inventory',
                            'editable_rows' => 200,
                            'columns' => [
                                self::col('system_id', 'System ID', 12),
                                self::col('name', 'System name', 28),
                                self::col('owner', 'Business owner', 20),
                                self::col('purpose', 'Intended purpose', 44),
                                self::select('lifecycle', 'Lifecycle stage', ['Idea', 'Pilot', 'Production', 'Retired'], 14),
                                self::select('role', 'Your role under the law', Records::actorNames(), 22, 'The obligations differ by role; see the Duties sheet.'),
                                self::select('jurisdiction', 'Primary jurisdiction', Records::jurisdictionNames(), 22, 'Jurisdictions with binding AI law on record.'),
                                self::select('tier', 'EU AI Act tier', ['Prohibited', 'High-risk', 'Limited (transparency)', 'Minimal', 'GPAI', 'GPAI with systemic risk', 'Not applicable'], 22),
                                self::select('personal_data', 'Personal data', $yesNo, 12),
                                self::select('automated_decisions', 'Automated decisions about people', $yesNo, 14),
                                self::col('vendor', 'Vendor', 18),
                                self::col('model', 'Model / provider', 20),
                                self::col('data', 'Data sources', 30),
                                self::col('last_review', 'Last review', 13, 'date'),
                                self::formula('next_review', 'Next review', '=IF(N{r}="","",N{r}+90)', 13, 'Ninety days after the last review; change the interval in this formula to match your policy.'),
                                self::formula('overdue', 'Review status', '=IF(O{r}="","",IF(O{r}<TODAY(),"Overdue","OK"))', 12),
                                self::col('evidence', 'Evidence link', 30, 'url'),
                                self::col('notes', 'Notes', 40),
                            ],
                            'rows' => [],
                            'rules' => [['column' => 'overdue', 'op' => 'equal', 'value' => 'Overdue', 'fill' => 'F8D7DA']],
                            'note' => 'One row per AI system, including third-party tools with AI features. Decide the role before the tier: obligations follow the role.',
                        ],
                        $this->dutiesSheet('Duties by role', Records::obligations(), 'Every recorded duty, with the roles it binds. Filter the "Who it binds" column by the role you chose in the inventory.'),
                        [
                            'name' => 'Jurisdictions',
                            'columns' => [self::col('jurisdiction', 'Jurisdiction', 22), self::col('instrument', 'Binding instrument', 40), self::col('status', 'Status', 16), self::col('applies_from', 'Applies from', 13, 'date'), self::col('url', 'Record', 44, 'url')],
                            'rows' => Records::bindingInstruments()->map(fn ($p) => ['jurisdiction' => $p->jurisdiction->name, 'instrument' => $p->short_title ?: $p->title, 'status' => $p->statusEnum()->label(), 'applies_from' => $p->applies_from?->toDateString(), 'url' => $p->url()])->all(),
                        ],
                    ];
                }

                public function blocks(): array
                {
                    $duties = Records::obligations(['categories' => ['governance_accountability', 'technical_documentation', 'record_keeping']]);

                    return [
                        $this->heading('Why an inventory'),
                        $this->p('Every recorded framework starts from the same question: which AI systems do you have, what are they for, and what is your role in each. The inventory answers it once, and the duties below are the reasons it has to be kept.'),
                        $this->heading('Fields and what to record'),
                        $this->table(['Field', 'What to record'], [
                            ['System name and purpose', 'What the system does and the decision or output it produces, in plain words.'],
                            ['Your role', 'Provider, deployer, importer, distributor or authorised representative. The duties differ by role.'],
                            ['Jurisdiction and tier', 'Where the system is used and, for the EU AI Act, its risk tier.'],
                            ['Data', 'Whether it processes personal data and whether it makes or supports decisions about people.'],
                            ['Review dates', 'When it was last reviewed; the next review is computed.'],
                            ['Evidence', 'A link to the documentation, assessment or approval, not a copy.'],
                        ]),
                        $this->heading('Duties that require a record of your systems'),
                        ...$this->dutySections($duties->take(12), 'Which of your systems this duty applies to, and where the inventory shows it'),
                    ];
                }
            },

            'ai-risk-register' => new class($slug, $meta) extends Definition
            {
                public function sheets(): array
                {
                    $domains = Records::mitDomains();
                    $domainNames = array_map(fn ($d) => $d['id'].'. '.$d['name'], $domains);
                    $subNames = [];
                    $taxonomy = [];
                    foreach ($domains as $d) {
                        foreach ($d['subdomains'] ?? [] as $s) {
                            $subNames[] = $s['id'].' '.$s['name'];
                            $taxonomy[] = ['domain' => $d['id'].'. '.$d['name'], 'subdomain' => $s['id'].' '.$s['name'], 'description' => $s['description'] ?? null, 'use_cases' => implode(', ', $d['use_cases'] ?? [])];
                        }
                    }
                    $scale = ['1', '2', '3', '4', '5'];

                    return [
                        [
                            'name' => 'Register',
                            'editable_rows' => 300,
                            'columns' => [
                                self::col('risk_id', 'Risk ID', 10),
                                self::col('system', 'System', 24),
                                self::select('domain', 'Risk domain (MIT)', $domainNames, 30),
                                self::select('subdomain', 'Subdomain (MIT)', $subNames, 40),
                                self::col('description', 'Risk description', 50),
                                self::col('cause', 'Cause or trigger', 36),
                                self::select('likelihood', 'Likelihood (1–5)', $scale, 10),
                                self::select('impact', 'Impact (1–5)', $scale, 10),
                                self::formula('inherent', 'Inherent score', '=IF(OR(G{r}="",H{r}=""),"",G{r}*H{r})', 10),
                                self::select('control', 'Control applied', array_column(Records::controlRows(), 'control'), 40, 'From the Controls sheet: the recorded controls and the duties each satisfies.'),
                                self::select('residual_likelihood', 'Residual likelihood', $scale, 10),
                                self::select('residual_impact', 'Residual impact', $scale, 10),
                                self::formula('residual', 'Residual score', '=IF(OR(K{r}="",L{r}=""),"",K{r}*L{r})', 10),
                                self::col('owner', 'Owner', 18),
                                self::select('status', 'Status', ['Open', 'Mitigating', 'Accepted', 'Closed'], 12),
                                self::col('review', 'Review date', 13, 'date'),
                            ],
                            'rows' => [],
                            'rules' => [...self::scoreRules('inherent'), ...self::scoreRules('residual')],
                            'note' => 'Red at 15 and above, amber from 8, green below. An all-green register is a warning sign for a reviewer.',
                        ],
                        ['name' => 'MIT taxonomy', 'columns' => [self::col('domain', 'Domain', 34), self::col('subdomain', 'Subdomain', 48), self::col('description', 'Definition', 80), self::col('use_cases', 'Use cases (site taxonomy)', 30)], 'rows' => $taxonomy],
                        $this->controlsSheet(),
                        $this->dutiesSheet('Risk duties', Records::obligations(['categories' => ['risk_management', 'safety_testing']])),
                    ];
                }
            },

            'ai-agent-registry' => new class($slug, $meta) extends Definition
            {
                private const ACTIONS = ['Read internal data', 'Write or change data', 'Send messages or email', 'Execute code', 'Make payments or commit funds', 'Browse the internet', 'Call other agents or tools', 'Act on behalf of a named person'];

                public function sheets(): array
                {
                    $permission = ['Allowed', 'With human approval', 'Denied'];
                    $matrixColumns = [self::col('agent', 'Agent', 26)];
                    foreach (self::ACTIONS as $n => $action) {
                        $matrixColumns[] = self::select('a'.$n, $action, $permission, 18);
                    }
                    $matrixColumns[] = self::formula('autonomous', 'Actions allowed alone', '=COUNTIF(B{r}:I{r},"Allowed")', 12);

                    return [
                        [
                            'name' => 'Registry',
                            'editable_rows' => 100,
                            'columns' => [
                                self::col('agent_id', 'Agent ID', 10),
                                self::col('name', 'Agent name', 24),
                                self::col('purpose', 'Purpose and scope', 44),
                                self::col('model', 'Model(s)', 22),
                                self::col('tools', 'Tools and integrations', 36),
                                self::col('credentials', 'Credentials it holds', 30),
                                self::select('autonomy', 'Autonomy level', ['Suggests only', 'Acts with approval', 'Acts alone within limits', 'Acts alone'], 22),
                                self::col('limits', 'Hard limits (spend, scope, rate)', 36),
                                self::col('owner', 'Accountable owner', 20),
                                self::select('kill_switch', 'Kill switch tested', ['Yes', 'No'], 12),
                                self::select('logging', 'Actions logged', ['Every action', 'Summaries', 'None'], 14),
                                self::col('last_review', 'Last review', 13, 'date'),
                                self::col('notes', 'Notes', 36),
                            ],
                            'rows' => [],
                        ],
                        ['name' => 'Permission matrix', 'editable_rows' => 100, 'columns' => $matrixColumns, 'rows' => [], 'rules' => [['column' => 'autonomous', 'op' => 'greaterThanOrEqual', 'value' => 4, 'fill' => 'F8D7DA']], 'note' => 'One row per agent. Four or more actions allowed alone turns the count red: that is an agent that needs the oversight procedure, not a note.'],
                        $this->dutiesSheet('Oversight duties', Records::obligations(['categories' => ['human_oversight', 'transparency', 'record_keeping']])),
                    ];
                }

                public function blocks(): array
                {
                    return [
                        $this->heading('Agents are systems that act'),
                        $this->p('An agent holds tools, credentials and some autonomy. The recorded duties on oversight, transparency and record-keeping were written for AI systems; they apply with more force to one that can send, spend or execute without a person in the loop. This registry records what each agent may do, and the matrix records what it may do alone.'),
                        $this->heading('Permission levels'),
                        $this->table(['Level', 'Meaning'], [['Allowed', 'The agent may do this without a person; every action is logged.'], ['With human approval', 'The agent proposes; a named person approves before it acts.'], ['Denied', 'The agent cannot do this; the capability is not connected.']]),
                        $this->heading('Duties that apply'),
                        ...$this->dutySections(Records::obligations(['categories' => ['human_oversight']])->take(8), 'How this agent meets the duty, and who is the human in the loop'),
                    ];
                }
            },

            'global-ai-regulatory-applicability-matrix' => new class($slug, $meta) extends Definition
            {
                public function sheets(): array
                {
                    $rows = Records::bindingInstruments()->map(fn ($p) => [
                        'jurisdiction' => $p->jurisdiction->name,
                        'instrument' => $p->short_title ?: $p->title,
                        'type' => $p->typeEnum()->label(),
                        'status' => $p->statusEnum()->label(),
                        'applies_from' => $p->applies_from?->toDateString(),
                        'actors' => $p->termsOf('actor')->pluck('name')->implode(', '),
                        'use_cases' => $p->termsOf('use_case')->pluck('name')->implode(', '),
                        'sectors' => $p->termsOf('sector')->pluck('name')->implode(', '),
                        'duties' => $p->obligations()->published()->count(),
                        'applies' => null, 'owner' => null, 'notes' => null,
                        'source' => $p->official_source_url,
                        'url' => $p->url(),
                    ])->all();

                    return [
                        [
                            'name' => 'Matrix',
                            'columns' => [
                                self::col('jurisdiction', 'Jurisdiction', 20), self::col('instrument', 'Instrument', 40), self::col('type', 'Type', 16), self::col('status', 'Status', 16),
                                self::col('applies_from', 'Applies from', 13, 'date'), self::col('actors', 'Who it binds', 26), self::col('use_cases', 'Use cases', 30), self::col('sectors', 'Sectors', 24),
                                self::col('duties', 'Duties recorded', 10, 'number'),
                                self::select('applies', 'Applies to us', ['Yes', 'Possibly', 'No', 'Not assessed'], 14), self::col('owner', 'Owner', 18), self::col('notes', 'Notes', 36),
                                self::col('source', 'Official source', 44, 'url'), self::col('url', 'Record', 44, 'url'),
                            ],
                            'rows' => $rows,
                            'rules' => [['column' => 'applies', 'op' => 'equal', 'value' => 'Yes', 'fill' => 'D4EDDA'], ['column' => 'applies', 'op' => 'equal', 'value' => 'Possibly', 'fill' => 'FFF3CD']],
                        ],
                        $this->deadlinesSheet(Records::bindingInstruments()->pluck('slug')->all()),
                    ];
                }
            },

            'ai-system-technical-documentation' => new class($slug, $meta) extends Definition
            {
                public function sheets(): array
                {
                    return [
                        [
                            'name' => 'Document register',
                            'editable_rows' => 100,
                            'columns' => [self::col('doc_id', 'Doc ID', 10), self::col('title', 'Document', 40), self::select('section', 'Section of the technical file', ['General description', 'Development process', 'Data and data governance', 'Monitoring and control', 'Risk management', 'Changes over the lifecycle', 'Standards applied', 'Performance and testing', 'Post-market monitoring'], 30), self::col('version', 'Version', 10), self::col('owner', 'Owner', 18), self::col('last_review', 'Last review', 13, 'date'), self::formula('next_review', 'Next review', '=IF(F{r}="","",F{r}+365)', 13), self::col('location', 'Location / link', 40, 'url')],
                            'rows' => [],
                        ],
                        $this->dutiesSheet('Documentation duties', Records::obligations(['categories' => ['technical_documentation', 'record_keeping', 'accuracy_robustness_security']])),
                    ];
                }

                public function blocks(): array
                {
                    $duties = Records::obligations(['categories' => ['technical_documentation', 'record_keeping', 'accuracy_robustness_security']]);
                    $blocks = [
                        $this->heading('Purpose of the technical file'),
                        $this->p('A technical file is the evidence that a system was built and is run as the rules require. The sections below follow what the recorded documentation duties ask for; each ends with the duty it serves, cited.'),
                    ];
                    foreach ([
                        'General description' => ['Intended purpose, provider, version, how it is placed on the market or put into service', 'Hardware and software it runs on, and interactions with other systems', 'Instructions for use and the user interface'],
                        'Development process' => ['Methods and steps; use of pre-trained systems or third-party tools', 'Design specifications, architecture and computational resources', 'Choices about the trade-offs made'],
                        'Data and data governance' => ['Training, validation and test datasets: provenance, scope, main characteristics', 'Labelling, cleaning and enrichment; representativeness and bias examination', 'Personal data and the legal basis for it'],
                        'Monitoring and control' => ['Human oversight measures and the interface for them', 'Logging and traceability', 'Accuracy, robustness and cybersecurity measures and their metrics'],
                        'Risk management' => ['The risk management system and its outputs', 'Known and foreseeable risks, and residual risks'],
                        'Changes over the lifecycle' => ['Version history and what each change affected', 'Re-assessment triggers'],
                        'Standards applied' => ['Harmonised standards and common specifications used, or the alternatives adopted'],
                        'Post-market monitoring' => ['The monitoring plan and how incidents are captured'],
                    ] as $section => $items) {
                        $blocks[] = $this->heading($section);
                        $blocks[] = $this->list($items);
                        $blocks[] = $this->placeholder('Complete this section for the system, and link the evidence in the register sheet');
                    }
                    $blocks[] = $this->heading('Duties this file serves');
                    array_push($blocks, ...$this->dutySections($duties->take(10), 'Where in the file this duty is met'));

                    return $blocks;
                }
            },

            default => null,
        };
    }
}
