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
                private const ACT = 'us-colorado-automated-decision-making-technology-act';

                public function sheets(): array
                {
                    $duties = Records::obligations(['policies' => [self::ACT]]);
                    $rows = $duties->map(fn ($o) => ['duty' => $o->title, 'reference' => $o->source_reference, 'actors' => $o->termsOf('actor')->pluck('name')->implode(', '), 'applies_from' => $o->applies_from?->toDateString(), 'owner' => null, 'status' => null, 'evidence' => null, 'url' => $o->url()])->all();

                    return [
                        ['name' => 'Checklist', 'columns' => [self::col('duty', 'Duty', 60), self::col('reference', 'Section', 20), self::col('actors', 'Who it binds', 22), self::col('applies_from', 'Applies from', 13, 'date'), self::col('owner', 'Owner', 18), self::select('status', 'Status', ['Not started', 'In progress', 'Done', 'Not applicable'], 14), self::col('evidence', 'Evidence', 40, 'url'), self::col('url', 'Record', 44, 'url')], 'rows' => $rows],
                        $this->deadlinesSheet([self::ACT], 'Dates'),
                    ];
                }

                public function blocks(): array
                {
                    $act = Records::policy(self::ACT);
                    $duties = Records::obligations(['policies' => [self::ACT]]);
                    $blocks = [
                        $this->heading('The notices Colorado\'s SB 26-189 requires'),
                        $this->p('SB 26-189 attaches duties when automated decision-making technology makes, or materially influences, a consequential decision about a Colorado consumer. Each recorded duty is below with a notice or procedure text to adapt; the checklist sheet tracks them all.'),
                        $act ? $this->note('The record\'s status: '.$act->statusEnum()->label().($act->applies_from ? ', applying from '.$act->applies_from->format('j F Y') : '').'.'.($act->status_note ? ' The record notes: '.$act->status_note : '')) : $this->note('The SB 26-189 record is not published in this dataset.'),
                        $this->note('SB 24-205, the earlier Colorado AI Act, was repealed and replaced by SB 26-189 before it took effect. Its risk-management programme, impact assessment and Attorney General notice duties do not apply; do not use notices written for it.'),
                    ];
                    $texts = [
                        'advance-notice' => ['Advance notice to the consumer', '[PLACEHOLDER: organisation] uses automated decision-making technology to help decide [PLACEHOLDER: the decision, for example your application for …]. The technology [PLACEHOLDER: what it does and the main information it uses]. A person [PLACEHOLDER: reviews / can review] the outcome. Questions: [PLACEHOLDER: contact].'],
                        'adverse-decision-disclosure' => ['Disclosure after an adverse decision', 'We have decided [PLACEHOLDER: the decision]. Automated decision-making technology materially influenced this decision. The principal reasons were [PLACEHOLDER]. You may ask for this decision to be reviewed by a person who can change it: [PLACEHOLDER: how and by when].'],
                        'human-review' => ['Human review procedure', 'A request for review is handled by [PLACEHOLDER: role], who has the authority and the information to change the outcome, within [PLACEHOLDER: days]. The reviewer records the request, the information considered and the result, and tells the consumer the outcome in writing.'],
                        'record-keeping' => ['Record-keeping procedure', 'For each consequential decision the technology influenced, [PLACEHOLDER: team] keeps the decision, the tool and version used, the principal inputs, the notices sent and any review, for three years from the decision, in [PLACEHOLDER: system].'],
                        'developer-documentation' => ['Developer documentation for deployers', 'This statement describes [PLACEHOLDER: technology]: its intended uses [PLACEHOLDER], the kinds of consequential decision it is designed to support [PLACEHOLDER], its known limitations [PLACEHOLDER], the data it uses [PLACEHOLDER], and what a deployer needs to give notices, explain adverse decisions and offer human review [PLACEHOLDER].'],
                    ];
                    foreach ($duties as $o) {
                        $blocks[] = $this->heading($o->title, 2);
                        $blocks[] = $this->p(trim((string) $o->summary));
                        foreach ($texts as $key => [$title, $text]) {
                            if (str_ends_with($o->slug, $key)) {
                                $blocks[] = $this->heading($title, 3);
                                $blocks[] = $this->p($text);
                            }
                        }
                        $blocks[] = ['type' => 'cite', 'text' => Records::dutyLine($o), 'url' => $o->url()];
                    }

                    return $blocks;
                }
            },

            'california-admt-compliance-kit' => new class($slug, $meta) extends Definition
            {
                private const REGS = 'us-california-cppa-admt-regulations';

                public function sheets(): array
                {
                    $duties = Records::obligations(['policies' => [self::REGS]]);
                    $rows = $duties->map(fn ($o) => ['duty' => $o->title, 'reference' => $o->source_reference, 'applies_from' => $o->applies_from?->toDateString(), 'evidence_expected' => $o->evidenceArtifacts->pluck('title')->implode('; '), 'owner' => null, 'status' => null, 'evidence' => null, 'url' => $o->url()])->all();
                    $decisions = ['Financial or lending services', 'Housing', 'Education enrollment or opportunities', 'Employment or contracting opportunities or compensation', 'Healthcare services'];

                    return [
                        ['name' => 'Checklist', 'columns' => [self::col('duty', 'Duty', 60), self::col('reference', 'Section', 24), self::col('applies_from', 'Applies from', 13, 'date'), self::col('evidence_expected', 'Evidence a reviewer expects', 44), self::col('owner', 'Owner', 18), self::select('status', 'Status', ['Not started', 'In progress', 'Done', 'Not applicable'], 14), self::col('evidence', 'Evidence', 40, 'url'), self::col('url', 'Record', 44, 'url')], 'rows' => $rows],
                        ['name' => 'ADMT uses', 'columns' => [
                            self::col('system', 'System or tool', 28), self::select('decision', 'Significant decision it makes', $decisions, 34),
                            self::select('replaces', 'Replaces or substantially replaces human decision-making?', ['Yes', 'No', 'Unsure'], 18, 'Under § 7001(e), substantially replacing means using the output without the human involvement the definition describes.'),
                            self::col('in_use_since', 'In use since', 13, 'date'), self::col('notice_where', 'Where the Pre-use Notice is shown', 30),
                            self::select('optout_basis', 'Opt-out offered, or exception relied on', ['Opt-out offered', 'Human appeal exception', 'Admission, acceptance or hiring exception', 'Work allocation or compensation exception'], 30),
                            self::col('optout_methods', 'Opt-out methods (at least two)', 30), self::col('reviewer', 'Human reviewer (if appeal exception)', 24), self::col('access_template', 'Access response template', 30, 'url'), self::col('owner', 'Owner', 18),
                        ], 'rows' => [], 'editable_rows' => 100, 'note' => 'One row per use of ADMT for a significant decision. Your assessment; this kit does not decide whether a use is in scope.'],
                        ['name' => 'Opt-out log', 'columns' => [
                            self::col('received', 'Request received', 13, 'date'), self::col('system', 'System', 24),
                            self::select('timing', 'Before or after processing began', ['Before (do not start)', 'After (stop processing)'], 22),
                            self::col('stopped', 'Processing stopped on', 13, 'date'),
                            self::formula('deadline', 'Stop by (15 business days)', '=IF(A{r}="","",WORKDAY(A{r},15))', 14, '§ 7221(n)(1): no later than 15 business days from receipt. Public holidays are not excluded; adjust if needed.') + ['date_format' => true],
                            self::formula('late', 'Late?', '=IF(OR(D{r}="",E{r}=""),"",IF(D{r}>E{r},"Late","On time"))', 10),
                            self::col('vendors_notified', 'Service providers notified on', 13, 'date'), self::formula('reask', 'Earliest date to ask consent again', '=IF(A{r}="","",EDATE(A{r},12))', 14, '§ 7221(k): wait at least 12 months.') + ['date_format' => true],
                        ], 'rows' => [], 'editable_rows' => 200, 'note' => 'Do not record more personal information here than you need to track the request.'],
                        ['name' => 'Risk assessments', 'columns' => [
                            self::col('activity', 'Processing activity', 34),
                            self::select('category', 'Why it needs an assessment (§ 7150(b))', ['Selling or sharing personal information', 'Processing sensitive personal information', 'ADMT for a significant decision', 'Automated inference of traits (§ 7150(b)(4))', 'Automated inference from presence in a sensitive location (§ 7150(b)(5))', 'Other listed activity'], 34),
                            self::col('started', 'Processing started', 13, 'date'), self::col('assessed', 'Assessment completed', 13, 'date'),
                            self::formula('due', 'Assessment due', '=IF(C{r}="","",IF(C{r}<DATE(2026,1,1),DATE(2027,12,31),C{r}))', 14, 'Before processing starts; by 31 December 2027 for processing begun before the regulations took effect (§ 7155(b)).') + ['date_format' => true],
                            self::formula('review', 'Review by (3 years)', '=IF(D{r}="","",EDATE(D{r},36))', 14, '§ 7155(a)(2).') + ['date_format' => true],
                            self::col('material_change', 'Material change on', 13, 'date'),
                            self::formula('update', 'Update by (45 days)', '=IF(G{r}="","",G{r}+45)', 14, '§ 7155(a)(3).') + ['date_format' => true],
                            self::formula('submit', 'Submit to the CPPA by', '=IF(D{r}="","",IF(YEAR(D{r})<=2027,DATE(2028,4,1),DATE(YEAR(D{r})+1,4,1)))', 14, '§ 7157(a).') + ['date_format' => true],
                            self::col('owner', 'Owner', 18),
                        ], 'rows' => [], 'editable_rows' => 200, 'note' => 'Keep every version for as long as the processing continues or five years after completion, whichever is later (§ 7155(c)).'],
                        $this->deadlinesSheet([self::REGS], 'Dates'),
                    ];
                }

                public function blocks(): array
                {
                    $regs = Records::policy(self::REGS);
                    $duties = Records::obligations(['policies' => [self::REGS]]);
                    $blocks = [
                        $this->heading('California automated decision-making technology (ADMT) and risk assessment kit'),
                        $this->p('The California Privacy Protection Agency\'s regulations under the CCPA set duties for businesses that use automated decision-making technology to make significant decisions about consumers, and for processing that needs a risk assessment. Each recorded duty is below with a text or procedure to adapt; the workbook tracks your uses, opt-out requests and risk assessments with the dates the regulations set.'),
                        $regs ? $this->note('The record\'s status: '.$regs->statusEnum()->label().($regs->applies_from ? ', ADMT duties applying from '.$regs->applies_from->format('j F Y') : '').'. '.$regs->verificationLabel().'.') : $this->note('The California ADMT record is not published in this dataset.'),
                        $this->note('A significant decision is one that results in the provision or denial of financial or lending services, housing, education enrollment or opportunities, employment or independent contracting opportunities or compensation, or healthcare services (§ 7001(ddd)). Whether a given use is in scope is your assessment; read the definitions in the official text.'),
                    ];
                    $texts = [
                        'pre-use-notice' => ['Pre-use Notice to adapt', 'We use automated decision-making technology to [PLACEHOLDER: the specific decision, for example decide whether to offer you an interview for the role you applied for]. You can opt out of this use: [PLACEHOLDER: link titled "Opt-out of Automated Decisionmaking Technology" and a second method]. [PLACEHOLDER, only if an exception applies: instead of an opt-out, you can appeal the decision to a person who can overturn it: how and where.] You can ask us how the technology was used about you: [PLACEHOLDER: how]. We will not retaliate against you for exercising your rights. How it works: [PLACEHOLDER: the categories of personal information that affect the output; what the output is and how we use it; any person\'s role; how we decide if you opt out].'],
                        'opt-out' => ['Opt-out handling procedure', 'Requests arrive through [PLACEHOLDER: the online form linked from the Pre-use Notice] and [PLACEHOLDER: a second method]. No verifiable request is required. A request received before processing starts means the technology is not used for that person. A request received after it starts is actioned within 15 business days: [PLACEHOLDER: team] stops the processing and tells [PLACEHOLDER: service providers and contractors]. The person can confirm their request was processed at [PLACEHOLDER]. We do not ask them to consent again for 12 months.'],
                        'access' => ['Access response template', 'You asked how we used automated decision-making technology about you. Purpose: [PLACEHOLDER: the specific purpose]. How it works: [PLACEHOLDER: the logic, enough to understand how your information produced the output, which may include the parameters and your output]. Outcome: [PLACEHOLDER: the decision and how the output was used, including whether it was the only factor and any person\'s role]. We will not retaliate against you for exercising your rights. Your other rights and how to use them: [PLACEHOLDER].'],
                        'risk-assessment' => ['Risk assessment procedure', 'Before starting any processing listed in § 7150(b), [PLACEHOLDER: owner] completes and documents a risk assessment covering what § 7152 requires. Processing that began before the regulations took effect is assessed by 31 December 2027. Each assessment is reviewed at least every three years and updated within 45 calendar days of a material change. Every version is kept for as long as the processing continues or five years after completion, whichever is later.'],
                        'risk-assessment-submission' => ['Annual submission procedure', 'Each year [PLACEHOLDER: owner] compiles, from the risk assessment register, the information § 7157(b) lists and submits it to the Agency by 1 April (by 1 April 2028 for assessments conducted in 2026 and 2027). The point of contact is [PLACEHOLDER: name, phone, email].'],
                    ];
                    foreach ($duties as $o) {
                        $blocks[] = $this->heading($o->title, 2);
                        $blocks[] = $this->p(trim((string) $o->summary));
                        foreach ($texts as $key => [$title, $text]) {
                            if (str_ends_with($o->slug, $key)) {
                                $blocks[] = $this->heading($title, 3);
                                $blocks[] = $this->p($text);
                            }
                        }
                        $blocks[] = ['type' => 'cite', 'text' => Records::dutyLine($o), 'url' => $o->url()];
                    }

                    return $blocks;
                }
            },

            'companion-chatbot-safety-kit' => new class($slug, $meta) extends Definition
            {
                /** Every companion-chatbot law on record; duties are drawn only from those that have them recorded. */
                private const LAWS = ['us-washington-eshb-2225-ai-companion-chatbots', 'us-new-york-ai-companion-safeguards', 'us-california-sb-243-companion-chatbots', 'us-california-sb-1119-companion-chatbots-child-safety', 'us-connecticut-sb-5-online-safety'];

                public function sheets(): array
                {
                    $duties = Records::obligations(['policies' => self::LAWS]);
                    $rows = $duties->map(fn ($o) => ['duty' => $o->title, 'law' => $o->policyInstrument->short_title ?: $o->policyInstrument->title, 'jurisdiction' => $o->policyInstrument->jurisdiction->name, 'reference' => $o->source_reference, 'applies_from' => $o->applies_from?->toDateString(), 'owner' => null, 'status' => null, 'evidence' => null, 'url' => $o->url()])->all();
                    $laws = collect(self::LAWS)->map(fn ($slug) => Records::policy($slug))->filter()->map(fn ($p) => [
                        'law' => $p->short_title ?: $p->title, 'jurisdiction' => $p->jurisdiction->name, 'status' => $p->statusEnum()->label(),
                        'applies_from' => $p->applies_from?->toDateString(), // As text: the writer leaves a numeric 0 blank, and a blank here would read as unknown.
                        'duties' => (string) $duties->where('policy_instrument_id', $p->id)->count(),
                        'review' => $p->verificationLabel(), 'url' => $p->url(), 'source' => $p->official_source_url,
                    ])->values()->all();

                    return [
                        ['name' => 'Checklist', 'columns' => [self::col('duty', 'Duty', 56), self::col('law', 'Law', 30), self::col('jurisdiction', 'Jurisdiction', 14), self::col('reference', 'Section', 22), self::col('applies_from', 'Applies from', 13, 'date'), self::col('owner', 'Owner', 18), self::select('status', 'Status', ['Not started', 'In progress', 'Done', 'Not applicable'], 14), self::col('evidence', 'Evidence', 40, 'url'), self::col('url', 'Record', 44, 'url')], 'rows' => $rows],
                        ['name' => 'Laws', 'columns' => [self::col('law', 'Law', 36), self::col('jurisdiction', 'Jurisdiction', 14), self::col('status', 'Status', 16), self::col('applies_from', 'Applies from', 13, 'date'), self::col('duties', 'Duties recorded', 10), self::col('review', 'Record review status', 34), self::col('url', 'Record', 44, 'url'), self::col('source', 'Official source', 44, 'url')], 'rows' => $laws, 'note' => 'A law with no duties recorded is listed so it is not forgotten: read its official text for what it requires.'],
                        ['name' => 'Crisis referral log', 'columns' => [
                            self::col('date', 'Date', 13, 'date'), self::col('product', 'Product or character', 24),
                            self::select('signal', 'What was detected', ['Suicidal ideation', 'Self-harm', 'Eating disorder', 'Other risk to the user'], 22),
                            self::select('referral', 'Referral made', ['988 Suicide and Crisis Lifeline', 'Crisis text line', 'Other crisis service', 'None (explain)'], 28),
                            self::col('notes', 'Notes (no more personal data than needed)', 44),
                        ], 'rows' => [], 'editable_rows' => 200, 'note' => 'Counts by year feed any publication duty recorded for the laws that apply to you. Keep only what you need.'],
                        ['name' => 'Disclosure schedule', 'columns' => [
                            self::col('product', 'Product or character', 26), self::select('audience', 'Users', ['Adults', 'Known minors', 'Directed to minors', 'Unknown'], 18),
                            self::col('start', 'Notice at the start (text shown)', 40), self::col('interval', 'Repeat notice every (hours)', 14, 'number'),
                            self::col('laws', 'Laws it must meet', 30), self::col('owner', 'Owner', 18),
                        ], 'rows' => [], 'editable_rows' => 50, 'note' => 'Set the repeat interval to the shortest any applicable recorded duty requires; the Checklist sheet cites each one.'],
                        $this->deadlinesSheet(self::LAWS, 'Dates'),
                    ];
                }

                public function blocks(): array
                {
                    $duties = Records::obligations(['policies' => self::LAWS]);
                    $missing = collect(self::LAWS)->map(fn ($slug) => Records::policy($slug))->filter()->filter(fn ($p) => $duties->where('policy_instrument_id', $p->id)->isEmpty());
                    $blocks = [
                        $this->heading('AI companion chatbot safety kit'),
                        $this->p('Several US states now regulate AI companions: chatbots that sustain a relationship with the user. The duties recorded so far are below, each with a text or procedure to adapt and a link to its record; the workbook tracks them, your disclosure schedule and a crisis referral log.'),
                        $this->note('Every duty here is pending review unless its record says otherwise: confirm it against the official text before relying on it.'),
                    ];
                    if ($missing->isNotEmpty()) {
                        $blocks[] = $this->note('Also on record, with no duties recorded yet, so not covered below: '.$missing->map(fn ($p) => ($p->short_title ?: $p->title).' ('.$p->jurisdiction->name.')')->implode('; ').'. Read their official texts.');
                    }
                    $texts = [
                        'ai-disclosure' => ['Not-human notice to adapt', 'You are chatting with [PLACEHOLDER: product], an AI. It is not a person. [PLACEHOLDER: shown at the start of each interaction and again at the interval your schedule sets.]'],
                        'ai-notification' => ['Not-human notice to adapt', 'Reminder: you are talking with an AI, not a human. [PLACEHOLDER: shown, or spoken, at the start of an interaction and again at the interval your schedule sets.]'],
                        'minor-disclosure' => ['Notice for minors', 'You are talking with an AI chatbot, not a person. [PLACEHOLDER: shown to users known to be minors, or on products directed to minors, at the start and at the interval the record states.]'],
                        'minor-content-and-engagement-safeguards' => ['Minor safeguards procedure', 'For users known to be minors, or on products directed to minors, [PLACEHOLDER: team] keeps sexually explicit content and suggestive dialogue switched off and reviews engagement features against the techniques the law lists, recording each review in [PLACEHOLDER].'],
                        'self-harm-protocol' => ['Crisis protocol to adapt', 'When a conversation shows signs of suicidal ideation or self-harm, [PLACEHOLDER: product] stops the current interaction pattern, gives the user [PLACEHOLDER: 988 Suicide and Crisis Lifeline, a crisis text line or another crisis service], and [PLACEHOLDER: escalation and review steps]. The protocol is reviewed by [PLACEHOLDER: role] every [PLACEHOLDER] months and each referral is logged in the Crisis referral log.'],
                        'protocol-publication' => ['Publication procedure', 'Each year [PLACEHOLDER: owner] publishes the crisis protocol and the number of crisis referrals made in the previous calendar year, counted from the Crisis referral log, at [PLACEHOLDER: website page] and in the app at [PLACEHOLDER].'],
                    ];
                    foreach ($duties as $o) {
                        $blocks[] = $this->heading($o->title, 2);
                        $blocks[] = $this->p(trim((string) $o->summary));
                        foreach ($texts as $key => [$title, $text]) {
                            if (str_ends_with($o->slug, $key)) {
                                $blocks[] = $this->heading($title, 3);
                                $blocks[] = $this->p($text);
                            }
                        }
                        $blocks[] = ['type' => 'cite', 'text' => Records::dutyLine($o), 'url' => $o->url()];
                    }

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
