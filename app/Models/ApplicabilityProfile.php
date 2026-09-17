<?php

namespace App\Models;

use App\Services\Applicability\ApplicabilityScreener;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A saved answer set from the applicability check. Daily alerts screen new
 * changes against it, so the reader is told which of their described systems a
 * change may touch. The profile holds answers, never conclusions.
 */
class ApplicabilityProfile extends Model
{
    public const MAX_PER_USER = 10;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['answers' => 'array', 'last_matched_at' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** True when another answer set would produce the same screen. */
    public function matchesAnswers(array $answers): bool
    {
        return $this->canonical($this->answers) === $this->canonical($answers);
    }

    /** Jurisdiction and instrument ids this profile watches (score above zero). */
    public function watchedIds(?ApplicabilityScreener $screener = null): array
    {
        return ($screener ?? app(ApplicabilityScreener::class))->watchedIds($this->answers);
    }

    /** Short human description, e.g. "European Union, Australia · provider · hiring". */
    public function summary(): string
    {
        $a = $this->answers;
        $places = Jurisdiction::whereIn('slug', $a['jurisdictions'] ?? [])->orderBy('name')->pluck('name')->all();
        $parts = [implode(', ', array_slice($places, 0, 3)).(count($places) > 3 ? ' +'.(count($places) - 3) : '')];
        foreach (['role', 'use_case', 'sector'] as $key) {
            if (! empty($a[$key])) {
                $parts[] = str_replace('_', ' ', (string) $a[$key]);
            }
        }

        return implode(' · ', array_filter($parts));
    }

    /** The tool-page URL that reproduces this screen. */
    public function url(): string
    {
        return route('tools.applicability', array_filter($this->answers, fn ($v) => $v !== null && $v !== []));
    }

    private function canonical(array $a): string
    {
        $sorted = [];
        foreach (['jurisdictions', 'role', 'use_case', 'sector', 'personal_data', 'domains', 'genai'] as $key) {
            $value = $a[$key] ?? null;
            if (is_array($value)) {
                sort($value);
            }
            $sorted[$key] = $value;
        }

        return json_encode($sorted);
    }
}
