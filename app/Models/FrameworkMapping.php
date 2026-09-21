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

    /** Short label for chips and table cells, falling back to the full name. */
    public function frameworkShort(): string
    {
        return config('frameworks.'.$this->framework.'.short', $this->frameworkName());
    }

    /**
     * Clause or function families named by this mapping's reference, used to group
     * crosswalk rows. References are written by reviewers as free text, so this reads
     * the structural tokens out of them ("Clauses 4-5 and 7" -> Clause 4, Clause 5, Clause 7)
     * and returns an empty list when a reference names no recognisable unit.
     *
     * @return list<string>
     */
    public function families(): array
    {
        $reference = (string) $this->reference;

        if ($this->framework === 'nist_ai_rmf') {
            preg_match_all('/\\b(GOVERN|MAP|MEASURE|MANAGE)\\b/', strtoupper($reference), $m);

            return array_values(array_unique($m[1]));
        }

        $families = [];
        if (preg_match('/\\bAnnex\\s+A\\b/i', $reference)) {
            $families[] = 'Annex A';
        }

        // A reference may name a run of clauses ("Clauses 4-5 and 7", "Clauses 6.1.2, 8.3").
        // Capture the whole run, then read each part of it so neither ranges nor the
        // items after a range are lost.
        if (preg_match_all('/\\bClauses?\\s+([0-9]+(?:\\.[0-9]+)*(?:\\s*[-\\x{2013}\\x{2014}]\\s*[0-9]+)?(?:(?:\\s*,\\s*|\\s+and\\s+)[0-9]+(?:\\.[0-9]+)*)*)/iu', $reference, $matches)) {
            foreach ($matches[1] as $run) {
                foreach (preg_split('/\\s*,\\s*|\\s+and\\s+/i', trim($run)) as $part) {
                    if (preg_match('/^\\s*([0-9]+)\\s*[-\\x{2013}\\x{2014}]\\s*([0-9]+)/u', $part, $range)) {
                        foreach (range((int) $range[1], (int) $range[2]) as $n) {
                            $families[] = 'Clause '.$n;
                        }

                        continue;
                    }
                    if (preg_match('/^([0-9]+)/', trim($part), $number)) {
                        $families[] = 'Clause '.$number[1];
                    }
                }
            }
        }

        return array_values(array_unique($families));
    }

    /** The framework's own name for a structural unit, for column headings. */
    public function unitLabel(): string
    {
        return config('frameworks.'.$this->framework.'.unit', 'Reference');
    }
}
