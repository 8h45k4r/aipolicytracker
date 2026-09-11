<?php

namespace App\Enums;

enum InstrumentType: string
{
    case Act = 'act';
    case Regulation = 'regulation';
    case Directive = 'directive';
    case Bill = 'bill';
    case ExecutiveOrder = 'executive_order';
    case Rules = 'rules';
    case Strategy = 'strategy';
    case Policy = 'policy';
    case Framework = 'framework';
    case Guidance = 'guidance';
    case Standard = 'standard';
    case CodeOfPractice = 'code_of_practice';
    case Consultation = 'consultation';
    case ProcurementRule = 'procurement_rule';
    case EnforcementDecision = 'enforcement_decision';

    public function label(): string
    {
        return match ($this) {
            self::Act => 'Act / statute',
            self::Regulation => 'Regulation',
            self::Directive => 'Directive',
            self::Bill => 'Bill',
            self::ExecutiveOrder => 'Executive order',
            self::Rules => 'Rules / secondary legislation',
            self::Strategy => 'National strategy',
            self::Policy => 'Policy',
            self::Framework => 'Framework',
            self::Guidance => 'Guidance',
            self::Standard => 'Standard',
            self::CodeOfPractice => 'Code of practice',
            self::Consultation => 'Consultation',
            self::ProcurementRule => 'Procurement rule',
            self::EnforcementDecision => 'Enforcement decision',
        };
    }

    public static function values(): array
    {
        return array_map(fn (self $s) => $s->value, self::cases());
    }
}
