<?php

namespace App\Services\Templates\Definitions;

use App\Services\Templates\Definition;
use App\Services\Templates\Records;

/** Kits and crosswalks: the EU AI Act classifier, Article 50, Colorado notices, the three-framework crosswalk. */
final class Kits
{
    public static function make(string $slug, array $meta): ?Definition
    {
        return match ($slug) {
            'eu-ai-act-role-risk-classifier' => new class($slug, $meta) extends Definition
            {
                public function sheets(): array
                {
                    $act = Records::policy('eu-ai-act');
                    $deadlines = $act ? $act->deadlines->sortBy('due_on') : collect();
                    $find = function (string $needle) use ($deadlines): ?string {
                        $d = $deadlines->first(fn ($d) => stripos((string) $d->title, $needle) !== false);

                        return $d?->due_on?->toDateString();
                    };
                    // The date each tier's duties apply, read from the dated milestones on the record.
                    $tiers = [
                        ['tier' => 'Prohibited', 'applies_from' => $find('Prohibited'), 'basis' => 'Prohibited practices milestone'],
                        ['tier' => 'High-risk', 'applies_from' => $find('Annex III'), 'basis' => 'Annex III high-risk milestone'],
                        ['tier' => 'High-risk (Annex I product)', 'applies_from' => $find('Annex I'), 'basis' => 'Annex I safety-component milestone'],
                        ['tier' => 'GPAI', 'applies_from' => $find('General-purpose'), 'basis' => 'General-purpose AI milestone'],
                        ['tier' => 'GPAI with systemic risk', 'applies_from' => $find('General-purpose'), 'basis' => 'General-purpose AI milestone'],
                        ['tier' => 'Minimal or limited risk', 'applies_from' => $find('Article 50'), 'basis' => 'Article 50 transparency milestone'],
                    ];
                    $areas = $act ? $act->termsOf('use_case')->pluck('name')->all() : [];
                    $yesNo = ['Yes', 'No'];

                    return [
                        [
                            'name' => 'Classifier',
                            'editable_rows' => 200,
                            'columns' => [
                                self::col('system', 'System', 28),
                                self::select('role', 'Your role', Records::actorNames(), 22, 'Provider, deployer, importer, distributor or authorised representative.'),
                                self::select('area', 'Annex III area (use case)', ['None', ...$areas], 30, 'The use-case areas the record lists for the Act; pick None if no listed area applies.'),
                                self::select('prohibited', 'A prohibited practice?', $yesNo, 14),
                                self::select('safety', 'Safety component of a regulated product (Annex I)?', $yesNo, 16),
                                self::select('gpai', 'A general-purpose AI model?', $yesNo, 14),
                                self::select('systemic', 'With systemic risk?', $yesNo, 12),
                                self::formula('tier', 'Risk tier', '=IF(A{r}="","",IF(D{r}="Yes","Prohibited",IF(E{r}="Yes","High-risk (Annex I product)",IF(AND(C{r}<>"",C{r}<>"None"),"High-risk",IF(G{r}="Yes","GPAI with systemic risk",IF(F{r}="Yes","GPAI","Minimal or limited risk"))))))', 24),
                                self::formula('applies_from', 'Duties apply from', '=IF(H{r}="","",IFERROR(VLOOKUP(H{r},Tiers!$A$2:$B$7,2,FALSE),"see Dates sheet"))', 16, 'Looked up from the Tiers sheet, which is read from the dated milestones on the EU AI Act record.'),
                                self::col('notes', 'Notes', 40),
                            ],
                            'rows' => [],
                            'rules' => [['column' => 'tier', 'op' => 'equal', 'value' => 'Prohibited', 'fill' => 'F8D7DA'], ['column' => 'tier', 'op' => 'equal', 'value' => 'High-risk', 'fill' => 'FFF3CD'], ['column' => 'tier', 'op' => 'equal', 'value' => 'High-risk (Annex I product)', 'fill' => 'FFF3CD']],
                            'note' => 'A first-pass classification from six questions. The Act\'s definitions have exceptions the sheet cannot ask about; confirm each high-risk or prohibited result against the record and the official text.',
                        ],
                        ['name' => 'Tiers', 'columns' => [self::col('tier', 'Tier', 28), self::col('applies_from', 'Duties apply from', 16, 'date'), self::col('basis', 'Milestone on the record', 40)], 'rows' => $tiers, 'note' => $act?->status_note ? 'The record notes: '.$act->status_note : null],
                        $this->deadlinesSheet(['eu-ai-act'], 'Dates'),
                        $this->dutiesSheet('Duties by role', Records::obligations(['policies' => ['eu-ai-act']])),
                    ];
                }
            },

            'article-50-transparency-kit' => new class($slug, $meta) extends Definition
            {
                private function duties()
                {
                    return Records::obligations(['policies' => ['eu-ai-act'], 'categories' => ['transparency']])->filter(fn ($o) => stripos((string) $o->source_reference, 'Article 50') !== false || stripos((string) $o->source_reference, 'Art. 50') !== false || stripos((string) $o->title, 'Article 50') !== false)->values();
                }

                public function sheets(): array
                {
                    $duties = $this->duties();
                    $rows = $duties->map(fn ($o) => ['duty' => $o->title, 'reference' => $o->source_reference, 'applies_from' => $o->applies_from?->toDateString(), 'owner' => null, 'status' => null, 'evidence' => null, 'watermark' => null, 'url' => $o->url()])->all();

                    return [
                        ['name' => 'Checklist', 'columns' => [self::col('duty', 'Article 50 duty', 60), self::col('reference', 'Reference', 16), self::col('applies_from', 'Applies from', 13, 'date'), self::col('owner', 'Owner', 18), self::select('status', 'Status', ['Not started', 'In progress', 'Done', 'Not applicable'], 14), self::col('evidence', 'Evidence', 40, 'url'), self::select('watermark', 'Machine-readable marking in place', ['Yes', 'No', 'Not applicable'], 16), self::col('url', 'Record', 44, 'url')], 'rows' => $rows, 'note' => 'Dates as recorded; see the record for any note that they may be superseded.'],
                        $this->dutiesSheet('Transparency duties', Records::obligations(['policies' => ['eu-ai-act'], 'categories' => ['transparency']])),
                    ];
                }

                public function blocks(): array
                {
                    $duties = $this->duties();
                    $blocks = [
                        $this->heading('What Article 50 requires'),
                        $this->p('Article 50 of the EU AI Act sets transparency duties for AI systems that interact with people, generate synthetic content, recognise emotions or categorise people biometrically, and generate deepfakes. Each duty on record is below, with a notice text to adapt.'),
                        $this->dateCaveat('eu-ai-act') ?? $this->note('Dates as recorded on '.now()->format('j F Y').'.'),
                    ];
                    $notices = [
                        'interact' => ['match' => ['interact', 'chatbot', 'natural person'], 'title' => 'Notice for systems that interact with people', 'text' => 'You are interacting with an AI system. [PLACEHOLDER: name of the system and the organisation responsible]. [PLACEHOLDER: how to reach a person, if the service offers one].'],
                        'synthetic' => ['match' => ['synthetic', 'mark', 'machine-readable'], 'title' => 'Marking of synthetic content', 'text' => 'This [image / audio / video / text] was generated or manipulated by an AI system. [PLACEHOLDER: the machine-readable marking or watermark applied, and the standard followed].'],
                        'emotion' => ['match' => ['emotion', 'biometric'], 'title' => 'Notice for emotion recognition or biometric categorisation', 'text' => 'This service uses an AI system that [recognises emotions / categorises people by biometric data]. [PLACEHOLDER: purpose, the data processed, the lawful basis, and how to object].'],
                        'deepfake' => ['match' => ['deep fake', 'deepfake', 'artificially generated'], 'title' => 'Disclosure for deepfakes', 'text' => 'This content has been artificially generated or manipulated. [PLACEHOLDER: what was altered, and by whom].'],
                    ];
                    foreach ($duties as $o) {
                        $blocks[] = $this->heading($o->title, 2);
                        $blocks[] = $this->p(trim((string) $o->summary));
                        foreach ($notices as $n) {
                            foreach ($n['match'] as $needle) {
                                if (stripos($o->title.' '.$o->summary, $needle) !== false) {
                                    $blocks[] = $this->heading($n['title'], 3);
                                    $blocks[] = $this->p($n['text']);
                                    break 2;
                                }
                            }
                        }
                        $blocks[] = $this->placeholder('Where this notice appears in your product, and who owns it');
                        $blocks[] = ['type' => 'cite', 'text' => Records::dutyLine($o), 'url' => $o->url()];
                    }
                    if ($duties->isEmpty()) {
                        $blocks[] = $this->note('No Article 50 duty is recorded yet; the checklist will fill as the record is expanded.');
                    }

                    return $blocks;
                }
            },

            'colorado-ai-act-notices' => new class($slug, $meta) extends Definition
            {
                public function sheets(): array
                {
                    $duties = Records::obligations(['policies' => ['us-colorado-ai-act']]);
                    $rows = $duties->map(fn ($o) => ['duty' => $o->title, 'reference' => $o->source_reference, 'actors' => $o->termsOf('actor')->pluck('name')->implode(', '), 'applies_from' => $o->applies_from?->toDateString(), 'owner' => null, 'status' => null, 'evidence' => null, 'url' => $o->url()])->all();

                    return [
                        ['name' => 'Checklist', 'columns' => [self::col('duty', 'Duty', 60), self::col('reference', 'Section', 20), self::col('actors', 'Who it binds', 22), self::col('applies_from', 'Applies from', 13, 'date'), self::col('owner', 'Owner', 18), self::select('status', 'Status', ['Not started', 'In progress', 'Done', 'Not applicable'], 14), self::col('evidence', 'Evidence', 40, 'url'), self::col('url', 'Record', 44, 'url')], 'rows' => $rows],
                        $this->deadlinesSheet(['us-colorado-ai-act'], 'Dates'),
                    ];
                }

                public function blocks(): array
                {
                    $act = Records::policy('us-colorado-ai-act');
                    $duties = Records::obligations(['policies' => ['us-colorado-ai-act']]);
                    $noticeDuties = $duties->filter(fn ($o) => preg_match('/notif|notice|disclos|explain|statement/i', $o->title.' '.$o->summary))->values();
                    $blocks = [
                        $this->heading('The notices the Colorado AI Act requires'),
                        $this->p('The Act places duties on developers and deployers of high-risk AI systems to tell consumers, each other and the Attorney General certain things. Each notification duty on record is below with a notice text to adapt; the checklist sheet tracks them all.'),
                        $act ? $this->note('The record\'s status: '.$act->statusEnum()->label().($act->applies_from ? ', applying from '.$act->applies_from->format('j F Y') : '').'.'.($act->status_note ? ' The record notes: '.$act->status_note : '')) : $this->note('The Colorado record is not published in this dataset.'),
                        $this->note('The 2026 legislative session\'s changes to the statute (SB 26-189) are not yet recorded in this dataset. Until a reviewer adds them, the duties and dates below are those of SB 24-205 as amended by SB 25B-004, and every notice must be checked against the enacted text before use.'),
                    ];
                    $templates = [
                        'consumer' => ['match' => ['consumer', 'notify consumers'], 'title' => 'Consumer notice of use of a high-risk AI system', 'text' => '[PLACEHOLDER: organisation] uses an artificial intelligence system to [PLACEHOLDER: make, or be a substantial factor in, a decision about you concerning …]. The purpose of the system is [PLACEHOLDER]. [PLACEHOLDER: how to contact us; your right to opt out of profiling where the law provides one].'],
                        'adverse' => ['match' => ['adverse', 'explain', 'consequential'], 'title' => 'Explanation of an adverse consequential decision', 'text' => 'We made a decision that was adverse to you: [PLACEHOLDER: decision]. An AI system was a substantial factor. The principal reasons were [PLACEHOLDER]; the system considered [PLACEHOLDER: the type and source of data]. You may correct any personal data we used and appeal for human review: [PLACEHOLDER: how].'],
                        'developer' => ['match' => ['developer', 'document', 'disclose known'], 'title' => 'Developer disclosure to deployers', 'text' => 'This statement describes [PLACEHOLDER: system]: its intended uses [PLACEHOLDER], known or reasonably foreseeable risks of algorithmic discrimination [PLACEHOLDER], the data used to train it [PLACEHOLDER], its limitations [PLACEHOLDER], and the measures taken to mitigate discrimination [PLACEHOLDER].'],
                        'ag' => ['match' => ['attorney general'], 'title' => 'Notice to the Attorney General', 'text' => 'On [PLACEHOLDER: date] [PLACEHOLDER: organisation] discovered that [PLACEHOLDER: system] has caused or is reasonably likely to have caused algorithmic discrimination: [PLACEHOLDER: description]. Measures taken: [PLACEHOLDER].'],
                    ];
                    foreach ($noticeDuties as $o) {
                        $blocks[] = $this->heading($o->title, 2);
                        $blocks[] = $this->p(trim((string) $o->summary));
                        foreach ($templates as $t) {
                            foreach ($t['match'] as $needle) {
                                if (stripos($o->title.' '.$o->summary, $needle) !== false) {
                                    $blocks[] = $this->heading($t['title'], 3);
                                    $blocks[] = $this->p($t['text']);
                                    break 2;
                                }
                            }
                        }
                        $blocks[] = ['type' => 'cite', 'text' => Records::dutyLine($o), 'url' => $o->url()];
                    }
                    $blocks[] = $this->heading('Every recorded duty under the Act');
                    array_push($blocks, ...$this->dutySections($duties, 'Whether this applies to you, and how it is met'));

                    return $blocks;
                }
            },

            'nist-eu-iso-crosswalk' => new class($slug, $meta) extends Definition
            {
                public function sheets(): array
                {
                    $rows = [];
                    $coverage = [];
                    foreach (Records::obligations() as $o) {
                        $iso = $o->frameworkMappings->where('framework', Records::frameworkKey('iso-42001'));
                        $nist = $o->frameworkMappings->where('framework', Records::frameworkKey('nist-ai-rmf'));
                        if ($iso->isEmpty() && $nist->isEmpty()) {
                            continue;
                        }
                        $instrument = $o->policyInstrument->short_title ?: $o->policyInstrument->title;
                        $rows[] = [
                            'duty' => $o->title, 'instrument' => $instrument, 'jurisdiction' => $o->policyInstrument->jurisdiction->name, 'reference' => $o->source_reference,
                            'nist' => $nist->pluck('reference')->implode('; '), 'nist_conf' => $nist->pluck('confidence_level')->unique()->implode('/'),
                            'iso' => $iso->pluck('reference')->implode('; '), 'iso_conf' => $iso->pluck('confidence_level')->unique()->implode('/'),
                            'note' => $o->frameworkMappings->pluck('note')->filter()->unique()->implode(' | '), 'url' => $o->url(),
                        ];
                        $coverage[$instrument]['instrument'] = $instrument;
                        $coverage[$instrument]['jurisdiction'] = $o->policyInstrument->jurisdiction->name;
                        $coverage[$instrument]['duties'] = ($coverage[$instrument]['duties'] ?? 0) + 1;
                        $coverage[$instrument]['nist'] = ($coverage[$instrument]['nist'] ?? 0) + ($nist->isNotEmpty() ? 1 : 0);
                        $coverage[$instrument]['iso'] = ($coverage[$instrument]['iso'] ?? 0) + ($iso->isNotEmpty() ? 1 : 0);
                    }

                    return [
                        ['name' => 'Crosswalk', 'columns' => [self::col('duty', 'Legal duty', 56), self::col('instrument', 'Instrument', 26), self::col('jurisdiction', 'Jurisdiction', 18), self::col('reference', 'Source reference', 20), self::col('nist', 'NIST AI RMF', 20), self::col('nist_conf', 'Confidence', 11), self::col('iso', 'ISO/IEC 42001', 20), self::col('iso_conf', 'Confidence', 11), self::col('note', 'Mapping note', 50), self::col('url', 'Record', 44, 'url')], 'rows' => $rows, 'note' => 'Editorial crosswalks with a stated confidence; they cite clause numbers only and reproduce no standard text.'],
                        ['name' => 'Coverage', 'columns' => [self::col('instrument', 'Instrument', 30), self::col('jurisdiction', 'Jurisdiction', 18), self::col('duties', 'Mapped duties', 12, 'number'), self::col('nist', 'With NIST reference', 12, 'number'), self::col('iso', 'With ISO reference', 12, 'number')], 'rows' => array_values($coverage)],
                        ['name' => 'ISO references', 'columns' => [self::col('reference', 'ISO/IEC 42001 reference', 22), self::col('duties', 'Duties', 8, 'number'), self::col('controls', 'Controls', 8, 'number'), self::col('duty_titles', 'Duties', 70), self::col('control_titles', 'Controls', 50)], 'rows' => Records::frameworkReferences('iso-42001')],
                        ['name' => 'NIST references', 'columns' => [self::col('reference', 'NIST AI RMF reference', 22), self::col('duties', 'Duties', 8, 'number'), self::col('controls', 'Controls', 8, 'number'), self::col('duty_titles', 'Duties', 70), self::col('control_titles', 'Controls', 50)], 'rows' => Records::frameworkReferences('nist-ai-rmf')],
                    ];
                }
            },

            default => null,
        };
    }
}
