<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FrameworkMapping extends Model
{
    protected $guarded = [];

    public const FRAMEWORKS = [
        'iso_42001' => 'ISO/IEC 42001:2023',
        'nist_ai_rmf' => 'NIST AI RMF 1.0',
        'iso_27001' => 'ISO/IEC 27001:2022',
        'oecd_ai_principles' => 'OECD AI Principles',
    ];

    protected function casts(): array
    {
        return ['is_original' => 'boolean'];
    }

    public function obligation(): BelongsTo
    {
        return $this->belongsTo(Obligation::class);
    }

    public function frameworkName(): string
    {
        return self::FRAMEWORKS[$this->framework] ?? $this->framework;
    }
}
