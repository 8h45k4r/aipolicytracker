<?php

namespace App\Enums;

enum ConfidenceLevel: string
{
    case High = 'high';
    case Medium = 'medium';
    case Low = 'low';
    case Unavailable = 'unavailable';

    public function label(): string
    {
        return match ($this) {
            self::High => 'High: primary official source identified',
            self::Medium => 'Medium: official source identified, detail needs confirmation',
            self::Low => 'Low: official source not yet located or status unclear',
            self::Unavailable => 'Unavailable: fact could not be established from the source',
        };
    }

    public static function values(): array
    {
        return array_map(fn (self $s) => $s->value, self::cases());
    }
}
