<?php

namespace App\Services\Templates\Definitions;

use App\Services\Templates\Definition;
use App\Services\Templates\Records;

/** The assessments: FRIA, general impact, workforce impact, ISO/IEC 42001 gap and SoA. */
final class Assessments
{
    public static function make(string $slug, array $meta): ?Definition
    {
        return match ($slug) {
            'fundamental-rights-impact-assessment' => new class($slug, $meta) extends Definition
            {
                public function sheets(): array
                {
                    return [[
                        'name' => 'FRIA register',
                        'editable_rows' => 100,
                        'columns' => [self::col('fria_id', 'FRIA ID', 10), self::col('system', 'System', 28), self::col('deployer', 'Deployer / unit', 24), self::select('status', 'Status', ['Planned', 'In progress', 'Completed', 'Notified to authority', 'Superseded'], 18), self::col('completed', 'Completed on', 13, 'date'), self::col('owner', 'Owner', 18), self::select('notified', 'Market surveillance authority notified', ['Yes', 'No', 'Not required'], 16), self::col('evidence', 'Assessment document', 40, 'url'), self::col('review', 'Next review', 13, 'date')],
                        'rows' => [],
                    ], $this->dutiesSheet('Impact-assessment duties', Records::obligations(['categories' => ['impact_assessment']]))];
                }

                public function blocks(): array
                {
                    $duties = Records::obligations(['categories' => ['impact_assessment'], 'policies' => ['eu-ai-act']]);
                    $elements = [
                        'The deployer\'s processes in which the system will be used' => 'Describe the process, the decisions it feeds and the deployer\'s purpose.',
                        'Period and frequency of use' => 'How long the system is intended to be used and how often.',
                        'Categories of natural persons and groups likely to be affected' => 'Who is affected, including groups at particular risk.',
                        'Specific risks of harm to those persons and groups' => 'Taking into account the provider\'s instructions for use.',
                        'Human oversight measures' => 'What the deployer will implement, in line with the instructions for use.',
                        'Measures if the risks materialise' => 'Internal governance arrangements and complaint mechanisms.',
                    ];
                    $blocks = [
                        $this->heading('What this assessment is'),
                        $this->p('A fundamental rights impact assessment is what a deployer of a high-risk AI system completes before first use, covering the elements the EU AI Act names. The sections below follow those elements; the duty each rests on is cited at the end.'),
                        $this->heading('System and deployer'),
                        $this->placeholder('System name, version, provider, and the deployer unit completing this assessment'),
                        $this->placeholder('Date of assessment and the person accountable for it'),
                    ];
                    foreach ($elements as $h => $guide) {
                        $blocks[] = $this->heading($h, 2);
                        $blocks[] = $this->p($guide);
                        $blocks[] = $this->placeholder('Your assessment of this element');
                    }
                    $blocks[] = $this->heading('Decision and notification');
                    $blocks[] = $this->placeholder('Decision: proceed, proceed with measures, or do not deploy; and whether the market surveillance authority has been notified');
                    $blocks[] = $this->heading('Duties this assessment serves');
                    array_push($blocks, ...$this->dutySections($duties->isNotEmpty() ? $duties : Records::obligations(['categories' => ['impact_assessment']])->take(6), 'Where this assessment meets the duty'));

                    return $blocks;
                }
            },

            'ai-impact-assessment' => new class($slug, $meta) extends Definition
            {
                public function sheets(): array
                {
                    $areas = [];
                    foreach (Records::mitDomains() as $d) {
                        foreach ($d['subdomains'] ?? [] as $s) {
                            $areas[] = ['domain' => $d['name'], 'area' => $s['name'], 'definition' => $s['description'] ?? null, 'relevant' => null, 'analysis' => null, 'mitigation' => null];
                        }
                    }

                    return [
                        ['name' => 'Harm areas', 'columns' => [self::col('domain', 'Domain', 30), self::col('area', 'Harm area', 44), self::col('definition', 'Definition (MIT AI Risk Repository)', 70), self::select('relevant', 'Relevant to this system', ['Yes', 'No', 'Unsure'], 14), self::col('analysis', 'Analysis', 50), self::col('mitigation', 'Mitigation', 50)], 'rows' => $areas],
                        $this->dutiesSheet('Duties by jurisdiction', Records::obligations(['categories' => ['impact_assessment']])),
                    ];
                }

                public function blocks(): array
                {
                    return [
                        $this->heading('Scope'),
                        $this->placeholder('System, purpose, the decisions or outputs it produces, and the context of use'),
                        $this->heading('Affected people'),
                        $this->placeholder('Who is affected directly and indirectly, including groups at particular risk, and how they were consulted'),
                        $this->heading('Harm analysis'),
                        $this->p('Work through the harm areas sheet: mark each of the 24 areas relevant or not, and analyse the relevant ones. The areas are the MIT AI Risk Repository taxonomy, so the analysis can be compared with recorded incidents in each area.'),
                        $this->placeholder('Summary of the relevant harm areas and their severity and likelihood'),
                        $this->heading('Mitigations and residual risk'),
                        $this->placeholder('Measures adopted, who owns them, and the risk that remains'),
                        $this->heading('Decision'),
                        $this->placeholder('Proceed, proceed with conditions, or do not proceed; sign-off by the accountable person; review date'),
                        $this->heading('Duties this assessment serves'),
                        ...$this->dutySections(Records::obligations(['categories' => ['impact_assessment']])->take(8), 'Where this assessment meets the duty'),
                    ];
                }
            },

            'ai-workforce-impact-assessment' => new class($slug, $meta) extends Definition
            {
                public function sheets(): array
                {
                    return [[
                        'name' => 'Role inventory',
                        'editable_rows' => 200,
                        'columns' => [self::col('role', 'Role', 26), self::col('unit', 'Unit', 18), self::col('headcount', 'Headcount', 10, 'number'), self::col('tasks', 'Tasks affected', 40), self::col('share', 'Share of tasks automated (%)', 12, 'number'), self::select('effect', 'Expected effect', ['Augmented', 'Reshaped', 'Reduced', 'Eliminated', 'Created'], 16), self::col('timeline', 'Timeline', 14), self::select('consulted', 'Workers consulted', ['Yes', 'Planned', 'No'], 12), self::col('mitigation', 'Mitigation (reskilling, redeployment, notice)', 44), self::col('owner', 'Owner', 18), self::formula('people', 'People affected (est.)', '=IF(OR(C{r}="",E{r}=""),"",ROUND(C{r}*E{r}/100,0))', 12)],
                        'rows' => [],
                    ]];
                }

                public function blocks(): array
                {
                    return [
                        $this->heading('Scope'),
                        $this->placeholder('The deployment being assessed, the units it touches, and the period covered'),
                        $this->heading('Roles and tasks affected'),
                        $this->p('Complete the role inventory sheet first. This section summarises it: which roles change, by how much, and when.'),
                        $this->placeholder('Summary of the inventory: roles augmented, reshaped, reduced, eliminated, created'),
                        $this->heading('Displacement and augmentation analysis'),
                        $this->placeholder('For roles reduced or eliminated: numbers, timeline, and the basis for the estimate'),
                        $this->heading('Consultation'),
                        $this->placeholder('How affected workers and their representatives were consulted, and what changed as a result'),
                        $this->heading('Reskilling and redeployment plan'),
                        $this->placeholder('Programmes, budget, eligibility, timeline, and how take-up is measured'),
                        $this->heading('Disclosure and notice duties'),
                        $this->note('No worker-notice or AI-layoff disclosure requirement is yet recorded in this dataset; check the law where you operate. When such a record is added, this section will cite it.'),
                        $this->placeholder('Notice periods and disclosures that apply, and how they will be met'),
                        $this->heading('Metrics and review'),
                        $this->placeholder('What will be measured, how often, and who reviews it'),
                    ];
                }
            },

            'iso-42001-gap-assessment' => new class($slug, $meta) extends Definition
            {
                public function sheets(): array
                {
                    $refs = Records::frameworkReferences('iso-42001');
                    $gap = array_map(fn ($r) => $r + ['applicable' => null, 'justification' => null, 'implemented' => null, 'evidence' => null, 'gap' => null, 'owner' => null], $refs);
                    $soa = array_map(fn ($r) => ['reference' => $r['reference'], 'applicable' => null, 'implemented' => null, 'justification' => null], $refs);

                    return [
                        [
                            'name' => 'Gap assessment',
                            'columns' => [
                                self::col('reference', 'ISO/IEC 42001 reference', 22), self::col('duties', 'Duties mapped', 8, 'number'), self::col('controls', 'Controls mapped', 8, 'number'),
                                self::col('duty_titles', 'Legal duties behind it', 60), self::col('control_titles', 'Recorded controls', 44), self::col('confidence', 'Mapping confidence', 12),
                                self::select('applicable', 'Applicable', ['Yes', 'No'], 10), self::col('justification', 'Justification (if not applicable)', 36),
                                self::select('implemented', 'Implementation', ['Not started', 'Partial', 'Implemented', 'Verified'], 14), self::col('evidence', 'Evidence', 40, 'url'), self::col('gap', 'Gap and action', 44), self::col('owner', 'Owner', 18),
                            ],
                            'rows' => $gap,
                            'rules' => [['column' => 'implemented', 'op' => 'equal', 'value' => 'Not started', 'fill' => 'F8D7DA'], ['column' => 'implemented', 'op' => 'equal', 'value' => 'Partial', 'fill' => 'FFF3CD'], ['column' => 'implemented', 'op' => 'equal', 'value' => 'Verified', 'fill' => 'D4EDDA']],
                            'note' => 'The references are those the recorded duties and controls map to; the mappings are editorial, with a stated confidence, not the standard\'s text.',
                        ],
                        ['name' => 'Statement of Applicability', 'columns' => [self::col('reference', 'Reference', 22), self::formula('applicable', 'Applicable', '=IFERROR(VLOOKUP(A{r},\'Gap assessment\'!A:L,7,FALSE),"")', 12), self::formula('implemented', 'Implementation', '=IFERROR(VLOOKUP(A{r},\'Gap assessment\'!A:L,9,FALSE),"")', 14), self::formula('justification', 'Justification', '=IFERROR(VLOOKUP(A{r},\'Gap assessment\'!A:L,8,FALSE),"")', 40)], 'rows' => $soa, 'note' => 'Derived from the gap sheet by formula: complete that sheet and this one follows.'],
                        $this->controlsSheet(),
                    ];
                }
            },

            default => null,
        };
    }
}
