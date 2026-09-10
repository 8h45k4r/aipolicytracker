<?php

namespace App\Enums;

enum ImpactLevel: string
{
    case Urgent = 'urgent';
    case High = 'high';
    case Routine = 'routine';

    public function label(): string
    {
        return ucfirst($this->value);
    }

    public static function values(): array
    {
        return array_map(fn (self $s) => $s->value, self::cases());
    }
}
