<?php

namespace App\Enums;

enum PolicyStatus: string
{
    case Proposed = 'proposed';
    case UnderConsultation = 'under_consultation';
    case Adopted = 'adopted';
    case InForce = 'in_force';
    case PartiallyApplicable = 'partially_applicable';
    case Guidance = 'guidance';
    case VoluntaryStandard = 'voluntary_standard';
    case EnforcementAction = 'enforcement_action';
    case Superseded = 'superseded';
    case Repealed = 'repealed';
    case Archived = 'archived';

    public function label(): string
    {
        return match ($this) {
            self::Proposed => 'Proposed',
            self::UnderConsultation => 'Under consultation',
            self::Adopted => 'Adopted',
            self::InForce => 'In force',
            self::PartiallyApplicable => 'Partially applicable',
            self::Guidance => 'Guidance',
            self::VoluntaryStandard => 'Voluntary standard',
            self::EnforcementAction => 'Enforcement action',
            self::Superseded => 'Superseded',
            self::Repealed => 'Repealed',
            self::Archived => 'Archived',
        };
    }

    /** Short plain-language definition shown in the methodology and status tooltips. */
    public function definition(): string
    {
        return match ($this) {
            self::Proposed => 'A bill, draft, or proposal that has been published but not adopted.',
            self::UnderConsultation => 'An official consultation is open or was recently closed; no final text adopted.',
            self::Adopted => 'Formally adopted or signed but not yet applicable, or applicable only from a future date.',
            self::InForce => 'Legally in force and applicable to the persons it covers.',
            self::PartiallyApplicable => 'In force, with some provisions applying now and others from later dates.',
            self::Guidance => 'Official guidance, principles, or policy that is not itself binding law.',
            self::VoluntaryStandard => 'A voluntary standard or framework organisations can choose to adopt.',
            self::EnforcementAction => 'A decision, fine, or order issued by a regulator or court under an existing rule.',
            self::Superseded => 'Replaced by a newer instrument; kept for history.',
            self::Repealed => 'Formally repealed or revoked.',
            self::Archived => 'No longer maintained on this site; may be outdated.',
        };
    }

    /** Whether the status generally describes a binding legal instrument. */
    public function isBinding(): bool
    {
        return in_array($this, [self::Adopted, self::InForce, self::PartiallyApplicable, self::EnforcementAction], true);
    }

    /** Tailwind classes for the status badge. */
    public function badgeClass(): string
    {
        return match ($this) {
            self::InForce, self::PartiallyApplicable => 'bg-state-goodbg text-state-good ring-state-good/20',
            self::Adopted => 'bg-state-infobg text-state-info ring-state-info/20',
            self::Proposed, self::UnderConsultation => 'bg-state-warnbg text-state-warn ring-state-warn/20',
            self::Guidance, self::VoluntaryStandard => 'bg-state-neutralbg text-brand-body ring-brand-line',
            self::EnforcementAction => 'bg-state-badbg text-state-bad ring-state-bad/20',
            self::Superseded, self::Repealed, self::Archived => 'bg-state-neutralbg text-state-neutral ring-brand-line',
        };
    }

    public static function values(): array
    {
        return array_map(fn (self $s) => $s->value, self::cases());
    }
}
