<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * An enquiry about a team plan, a commercial licence or custom coverage, made through
 * the form on /pricing or /open-data. Read in Admin → Audience → Licence enquiries.
 */
class LicenceEnquiry extends Model
{
    /** The tiers a reader can ask about; Open needs no enquiry. */
    public const TIERS = [
        'commercial' => 'Commercial licence',
        'team' => 'Team',
        'builder' => 'Builder',
        'unsure' => 'Not sure yet',
    ];

    /** The pages the form is on, which are also where it returns to. */
    public const SOURCES = ['pricing' => 'pricing', 'open-data' => 'open-data'];

    protected $guarded = [];

    protected function casts(): array
    {
        return ['notified_at' => 'datetime'];
    }

    public function tierLabel(): string
    {
        return self::TIERS[$this->tier] ?? $this->tier;
    }
}
