<?php

namespace App\Services\Templates\Definitions;

use App\Models\PolicyInstrument;
use App\Services\Templates\Definition;
use App\Services\Templates\Records;
use Illuminate\Support\Collection;

/**
 * Templates for one law or one kind of organisation: the South Korean AI Basic Act,
 * Texas TRAIGA, US state law, public-sector use and synthetic-content labelling. Each
 * draws its duty rows from the records, so a file states only what a record states,
 * and a record still pending review is marked as such in the duties sheet.
 */
final class Jurisdictional
{
    public static function make(string $slug, array $meta): ?Definition
    {
        return match ($slug) {
            'south-korea-ai-basic-act-checklist' => new class($slug, $meta) extends Definition
            {
                private function duties(): Collection
                {
                    return Records::obligations(['policies' => [Jurisdictional::koreaSlug()]]);
                }

                public function sheets(): array
                {
                    return [Jurisdictional::checklistSheet($this->duties(), 'Korean AI Basic Act checklist', 'One row per recorded duty of the Framework Act. Copy the rows for each product or service offered in Korea.'),
                        [
                            'name' => 'Systems in scope',
                            'editable_rows' => 60,
                            'columns' => [
                                self::col('system', 'Product or service', 26),
                                self::select('generative', 'Uses generative AI', Jurisdictional::YES_NO, 14),
                                self::select('high_impact', 'Possibly high-impact AI', Jurisdictional::YES_NO, 16, 'Areas the Act lists as high-impact; confirm against the official text'),
                                self::select('threshold', 'Above the compute threshold', Jurisdictional::YES_NO, 16),
                                self::select('foreign', 'Operator outside Korea', Jurisdictional::YES_NO, 14),
                                self::col('representative', 'Domestic representative', 24),
                                self::col('notes', 'Notes', 34),
                            ],
                            'rows' => [],
                            'note' => 'Decide, per product, which of the Act\'s duty groups apply: notice and labelling (generative and high-impact AI), safety measures (above the compute threshold), the high-impact duties, and a domestic representative for foreign operators.',
                        ],
                        $this->dutiesSheet('Duties on record', $this->duties()),
                    ];
                }

                public function blocks(): array
                {
                    return [
                        $this->heading('South Korea AI Basic Act: compliance checklist'),
                        $this->p('The Framework Act on the Development of Artificial Intelligence applies to AI business operators whose products or services reach Korea, including operators established abroad. This document follows its recorded duties one by one; the workbook holds the checklist per product.'),
                        $this->heading('Scope', 2),
                        $this->placeholder('Products and services offered in Korea, and whether each uses generative AI, may be high-impact, or exceeds the compute threshold'),
                        $this->heading('Duties, one by one'),
                        ...$this->dutySections($this->duties(), 'How we meet this duty in Korea, and where the evidence is kept'),
                    ];
                }
            },

            'texas-traiga-compliance-checklist' => new class($slug, $meta) extends Definition
            {
                private function duties(): Collection
                {
                    return Records::obligations(['policies' => [Jurisdictional::texasSlug()]]);
                }

                public function sheets(): array
                {
                    return [
                        Jurisdictional::checklistSheet($this->duties(), 'TRAIGA checklist', 'One row per recorded duty of the Texas Responsible AI Governance Act. Several apply only to government entities or health-care providers; mark those not applicable with the reason.'),
                        [
                            'name' => 'Disclosure log',
                            'editable_rows' => 100,
                            'columns' => [self::col('system', 'AI system', 24), self::select('setting', 'Setting', ['Government agency service', 'Health-care service', 'Other consumer service'], 22), self::col('text', 'Disclosure text or link', 40, 'url'), self::col('shown', 'Where and when it is shown', 30), self::col('date', 'In place since', 13, 'date')],
                            'rows' => [],
                            'note' => 'Where a recorded duty requires telling people that AI is used, record the wording and where it appears.',
                        ],
                        $this->dutiesSheet('Duties on record', $this->duties()),
                    ];
                }

                public function blocks(): array
                {
                    return [
                        $this->heading('Texas Responsible AI Governance Act (TRAIGA): compliance checklist'),
                        $this->p('TRAIGA sets prohibitions for developers and deployers (manipulation towards harm, unlawful discrimination, child sexual abuse material and unlawful deepfakes) and disclosure duties for government agencies and health-care providers. Each recorded duty is set out below with room to record how it is met.'),
                        $this->heading('Intended-use statement', 2),
                        $this->placeholder('For each AI system: what it is for, who uses it, and the safeguards against the prohibited uses'),
                        $this->heading('Duties, one by one'),
                        ...$this->dutySections($this->duties(), 'How we meet this duty, and the evidence that shows it'),
                    ];
                }
            },

            'us-state-ai-law-matrix' => new class($slug, $meta) extends Definition
            {
                public function sheets(): array
                {
                    $rows = Jurisdictional::usStatePolicies()->map(fn (PolicyInstrument $p) => [
                        'state' => $p->jurisdiction->name,
                        'instrument' => $p->short_title ?: $p->title,
                        'status' => $p->statusEnum()->label(),
                        'binding' => $p->is_binding ? 'Binding' : 'Non-binding',
                        'applies' => ($p->applies_from ?? $p->in_force_on)?->toDateString(),
                        'duties' => $p->obligations->count(),
                        'review' => $p->review_status === 'verified' ? 'Verified' : 'Not yet verified',
                        'applies_to_us' => null, 'owner' => null, 'notes' => null,
                        'url' => $p->url(),
                    ])->all();

                    return [
                        [
                            'name' => 'US AI laws',
                            'columns' => [
                                self::col('state', 'Jurisdiction', 20), self::col('instrument', 'Instrument', 34), self::col('status', 'Status', 18), self::col('binding', 'Legal force', 12),
                                self::col('applies', 'Applies from', 13), self::col('duties', 'Recorded duties', 12, 'number'), self::col('review', 'Record', 16),
                                self::select('applies_to_us', 'Applies to us', ['Yes', 'No', 'To check'], 14), self::col('owner', 'Owner', 16), self::col('notes', 'Notes', 30), self::col('url', 'Record', 40, 'url'),
                            ],
                            'rows' => $rows,
                            'rules' => [['column' => 'applies_to_us', 'op' => 'equal', 'value' => 'Yes', 'fill' => 'FFF3CD']],
                            'note' => 'Every US federal and state AI instrument on record, with its status and date as recorded. "Not yet verified" means a reviewer has not yet confirmed the record against the official source; check it before relying on the row.',
                        ],
                        $this->dutiesSheet('Recorded duties', Records::obligations(['policies' => Jurisdictional::usStatePolicies()->pluck('slug')->all()])),
                        $this->deadlinesSheet(Jurisdictional::usStatePolicies()->pluck('slug')->all(), 'US deadlines'),
                    ];
                }

                public function blocks(): array
                {
                    $policies = Jurisdictional::usStatePolicies();

                    return [
                        $this->heading('US AI laws by state: applicability matrix'),
                        $this->p('There is no general federal AI statute on record; AI duties in the United States come from state laws, city rules and federal agency policy. This outline goes with the workbook, which lists every US instrument on record with its status, date and duties.'),
                        $this->table(['Jurisdiction', 'Instrument', 'Status', 'Applies from'], $policies->map(fn ($p) => [$p->jurisdiction->name, $p->short_title ?: $p->title, $p->statusEnum()->label(), (string) (($p->applies_from ?? $p->in_force_on)?->format('j M Y') ?? '')])->all()),
                        $this->heading('Our position', 2),
                        $this->placeholder('For each instrument marked "Applies to us": the products, customers or employees it reaches, the owner, and the plan'),
                        $this->note('Dates are as recorded on the day this file was built. Several state laws were amended, delayed or replaced in 2025 and 2026; the record for each says so, and the official text decides.'),
                    ];
                }
            },

            'public-sector-ai-use-case-inventory' => new class($slug, $meta) extends Definition
            {
                public function sheets(): array
                {
                    return [
                        [
                            'name' => 'Use-case inventory',
                            'editable_rows' => 200,
                            'columns' => [
                                self::col('id', 'Use case ID', 12), self::col('agency', 'Agency or unit', 22), self::col('name', 'Use case', 30), self::col('purpose', 'Purpose and benefit', 34),
                                self::select('stage', 'Stage', ['Idea', 'Pilot', 'In use', 'Retired'], 12),
                                self::select('rights', 'Affects rights or safety', ['Yes', 'No', 'To assess'], 16),
                                self::select('source', 'Developed by', ['In-house', 'Vendor', 'Open-source model', 'Other agency'], 16),
                                self::col('vendor', 'Vendor and product', 24), self::col('data', 'Data used', 26),
                                self::select('assessment', 'Impact assessment done', ['Yes', 'No', 'Not required'], 16),
                                self::col('oversight', 'Human oversight', 26), self::col('published', 'Published in public inventory', 14, 'date'), self::col('owner', 'Accountable official', 20),
                            ],
                            'rows' => [],
                            'rules' => [['column' => 'rights', 'op' => 'equal', 'value' => 'Yes', 'fill' => 'FFF3CD']],
                            'note' => 'One row per AI use case, in the shape public-sector inventory rules ask for. Rows that affect rights or safety get the minimum risk practices and an impact assessment before use.',
                        ],
                        $this->dutiesSheet('Public-sector duties', Records::obligations(['actors' => ['public_authority']]), 'Every recorded duty that names public authorities, across jurisdictions.'),
                    ];
                }

                public function blocks(): array
                {
                    return [
                        $this->heading('Public-sector AI use-case inventory and procedure'),
                        $this->p('Governments increasingly require agencies to keep and publish an inventory of their AI use cases and to apply extra safeguards where a use affects people\'s rights or safety. This procedure and the workbook keep that inventory; the duties below are those on record that name public authorities.'),
                        $this->heading('What counts as a use case', 2), $this->placeholder('The definition this agency uses, and what is excluded (for example research or general office software)'),
                        $this->heading('Rights- and safety-affecting uses', 2), $this->placeholder('How a use case is classified, who decides, and the practices applied before and during use'),
                        $this->heading('Publication', 2), $this->placeholder('What is published, where, and how often the public inventory is updated'),
                        $this->heading('Duties on record'),
                        ...$this->dutySections(Records::obligations(['controls' => ['public-sector-ai-use-case-register']]), 'How the inventory meets this duty'),
                    ];
                }
            },

            'synthetic-content-labelling-plan' => new class($slug, $meta) extends Definition
            {
                private function duties(): Collection
                {
                    return Jurisdictional::labellingDuties();
                }

                public function sheets(): array
                {
                    return [
                        [
                            'name' => 'Labelling plan',
                            'editable_rows' => 60,
                            'columns' => [
                                self::col('feature', 'Product feature', 26),
                                self::select('content', 'Content generated', ['Text', 'Image', 'Audio', 'Video', 'Mixed'], 12),
                                self::select('visible', 'Visible label', ['Yes', 'No', 'Not required'], 13),
                                self::select('machine', 'Machine-readable marking', ['Metadata', 'Watermark', 'Both', 'None'], 16),
                                self::col('standard', 'Standard or technique used', 26), self::col('wording', 'Label wording', 30),
                                self::col('markets', 'Markets', 18), self::col('tested', 'Detection tested on', 13, 'date'), self::col('owner', 'Owner', 16),
                            ],
                            'rows' => [],
                            'rules' => [['column' => 'machine', 'op' => 'equal', 'value' => 'None', 'fill' => 'F8D7DA']],
                            'note' => 'One row per feature that produces synthetic text, images, audio or video. Visible labels tell people; machine-readable marks let platforms and tools detect the content later.',
                        ],
                        $this->dutiesSheet('Labelling duties', $this->duties(), 'Recorded duties to label, mark or disclose AI-generated content, across jurisdictions.'),
                    ];
                }

                public function blocks(): array
                {
                    return [
                        $this->heading('Synthetic content labelling and provenance plan'),
                        $this->p('Several laws on record require AI-generated content to be marked so machines can detect it, labelled so people can see it, or both, with stricter rules for realistic depictions of real people. This plan records, per product feature, how the organisation meets them with one design.'),
                        $this->heading('Design principles', 2), $this->placeholder('Which marking technique and standard is used, where visible labels appear, and how both survive editing and re-upload'),
                        $this->heading('Exceptions', 2), $this->placeholder('Assistive editing, artistic or satirical content and other cases where the recorded duties allow a lighter label, and who decides'),
                        $this->heading('Duties, one by one'),
                        ...$this->dutySections($this->duties(), 'How this product meets the duty'),
                    ];
                }
            },

            default => null,
        };
    }

    public const YES_NO = ['Yes', 'No', 'Unsure'];

    public static function koreaSlug(): string
    {
        return (string) PolicyInstrument::where('slug', 'like', 'south-korea-framework-act-on-the-development-of-artificial-intelligence%')->value('slug');
    }

    public static function texasSlug(): string
    {
        return (string) PolicyInstrument::where('slug', 'like', 'us-texas-responsible-ai-governance-act%')->value('slug');
    }

    /** @return Collection<int, PolicyInstrument> */
    public static function usStatePolicies(): Collection
    {
        return PolicyInstrument::published()->with(['jurisdiction', 'obligations' => fn ($q) => $q->published()])
            ->whereHas('jurisdiction', fn ($q) => $q->where('slug', 'like', 'us-%')->orWhere('slug', 'us'))
            ->whereNotIn('status', ['repealed', 'superseded', 'archived'])
            ->get()->sortBy(fn ($p) => [$p->jurisdiction->name, $p->title])->values();
    }

    /** Duties to label or mark synthetic content: those the labelling control serves, and transparency duties that name it. */
    public static function labellingDuties(): Collection
    {
        $byControl = Records::obligations(['controls' => ['synthetic-content-labelling-and-provenance']]);
        $byText = Records::obligations(['categories' => ['transparency']])
            ->filter(fn ($o) => preg_match('/\b(label|labell|mark|watermark|synthetic|deepfake|deep fake|generated)/i', $o->title.' '.$o->summary));

        return $byControl->merge($byText)->unique('id')->values();
    }

    /** A per-duty checklist sheet: duty, reference, instrument, status, owner, evidence, record. */
    public static function checklistSheet(Collection $duties, string $name, string $note): array
    {
        return [
            'name' => $name,
            'columns' => [
                ['key' => 'duty', 'label' => 'Duty', 'width' => 46, 'type' => 'text'],
                ['key' => 'ref', 'label' => 'Reference', 'width' => 18, 'type' => 'text'],
                ['key' => 'review', 'label' => 'Record', 'width' => 16, 'type' => 'text'],
                ['key' => 'status', 'label' => 'Status', 'width' => 14, 'type' => 'select', 'options' => ['Not started', 'In progress', 'Done', 'Not applicable'], 'note' => null],
                ['key' => 'owner', 'label' => 'Owner', 'width' => 18, 'type' => 'text'],
                ['key' => 'evidence', 'label' => 'Evidence link', 'width' => 34, 'type' => 'url'],
                ['key' => 'url', 'label' => 'Record link', 'width' => 40, 'type' => 'url'],
            ],
            'rows' => $duties->map(fn ($o) => ['duty' => $o->title, 'ref' => $o->source_reference, 'review' => $o->review_status === 'verified' ? 'Verified' : 'Not yet verified', 'status' => null, 'owner' => null, 'evidence' => null, 'url' => $o->url()])->all(),
            'rules' => [['column' => 'status', 'op' => 'equal', 'value' => 'Not started', 'fill' => 'F8D7DA'], ['column' => 'status', 'op' => 'equal', 'value' => 'Done', 'fill' => 'D4EDDA']],
            'note' => $note,
        ];
    }
}
