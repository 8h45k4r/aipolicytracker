<?php

namespace App\Services\Verification;

/**
 * Inter-reviewer agreement over records that carry an independent second check.
 *
 * Pure: it takes the `second_review` entries and returns numbers, with no database or
 * clock, so the arithmetic can be tested against hand-worked examples.
 *
 * For each field it reports percent agreement (the share of double-checked records on
 * which the second reviewer did not dispute the field). For categorical fields it also
 * reports Cohen's kappa, which discounts the agreement two reviewers would reach by
 * chance given how often each used each category:
 *
 *     kappa = (p_o - p_e) / (1 - p_e)
 *     p_o   = share of records on which the two values match
 *     p_e   = sum over categories k of (share of first reviewer's values that are k)
 *                                     x (share of second reviewer's values that are k)
 *
 * Kappa is undefined when p_e is 1, which happens when both reviewers used one and the
 * same category for every record (for example every record in the sample was verified
 * by both). It is then reported as null rather than as 1 or 0, either of which would
 * be a claim the data does not support.
 *
 * Below the minimum sample nothing per field is reported at all: on a handful of
 * records a kappa is noise, and publishing it would invite exactly the reading it
 * cannot bear.
 */
class AgreementStatistics
{
    public function __construct(private readonly int $minSample = 20) {}

    /**
     * @param  list<array>  $reviews  `second_review` entries of double-checked records
     * @return array{n: int, min_sample: int, sufficient: bool, records_fully_agreed: int, fields: ?array<string, array{label: string, categorical: bool, n: int, agreed: int, percent: float, kappa: ?float}>}
     */
    public function compute(array $reviews): array
    {
        $reviews = array_values(array_filter($reviews, 'is_array'));
        $n = count($reviews);
        $sufficient = $n >= $this->minSample && $n > 0;
        $fullyAgreed = count(array_filter($reviews, fn ($r) => self::disputedFields($r) === []));

        $fields = null;
        if ($sufficient) {
            $fields = [];
            foreach (SecondReview::FIELDS as $field => $def) {
                $agreed = count(array_filter($reviews, fn ($r) => ! in_array($field, self::disputedFields($r), true)));
                $kappa = null;
                if ($def['categorical']) {
                    $pairs = array_values(array_filter(array_map(fn ($r) => self::pair($r, $field), $reviews)));
                    $kappa = self::cohensKappa($pairs);
                }
                $fields[$field] = [
                    'label' => $def['label'],
                    'categorical' => $def['categorical'],
                    'n' => $n,
                    'agreed' => $agreed,
                    'percent' => round(100 * $agreed / $n, 1),
                    'kappa' => $kappa === null ? null : round($kappa, 3),
                ];
            }
        }

        return [
            'n' => $n,
            'min_sample' => $this->minSample,
            'sufficient' => $sufficient,
            'records_fully_agreed' => $fullyAgreed,
            'fields' => $fields,
        ];
    }

    /**
     * Cohen's kappa for two raters over the same items.
     *
     * @param  list<array{0: mixed, 1: mixed}>  $pairs  [first rater's value, second rater's value]
     * @return float|null null when there are no pairs or chance agreement is 1 (undefined)
     */
    public static function cohensKappa(array $pairs): ?float
    {
        $n = count($pairs);
        if ($n === 0) {
            return null;
        }
        $observed = 0;
        $first = [];
        $second = [];
        foreach ($pairs as [$a, $b]) {
            $ka = self::key($a);
            $kb = self::key($b);
            if ($ka === $kb) {
                $observed++;
            }
            $first[$ka] = ($first[$ka] ?? 0) + 1;
            $second[$kb] = ($second[$kb] ?? 0) + 1;
        }
        $po = $observed / $n;
        $pe = 0.0;
        foreach ($first as $k => $count) {
            $pe += ($count / $n) * (($second[$k] ?? 0) / $n);
        }
        if (abs(1.0 - $pe) < 1e-12) {
            return null;
        }

        return ($po - $pe) / (1 - $pe);
    }

    /** Share of pairs whose two values match, 0..1, or null for no pairs. */
    public static function percentAgreement(array $pairs): ?float
    {
        if ($pairs === []) {
            return null;
        }

        return count(array_filter($pairs, fn ($p) => self::key($p[0]) === self::key($p[1]))) / count($pairs);
    }

    /**
     * The two reviewers' values for a categorical field on one record: the second
     * reviewer's from `coded`, the first reviewer's from the dispute entry when the
     * field was disputed and otherwise the same value. Null when it was not coded.
     *
     * @return array{0: mixed, 1: mixed}|null
     */
    public static function pair(array $review, string $field): ?array
    {
        $coded = (array) ($review['coded'] ?? []);
        if (! array_key_exists($field, $coded)) {
            return null;
        }
        $second = $coded[$field];
        foreach ((array) ($review['fields_disputed'] ?? []) as $item) {
            if (is_array($item) && ($item['field'] ?? null) === $field && array_key_exists('first', $item)) {
                return [$item['first'], $second];
            }
        }

        return [$second, $second];
    }

    /** @return list<string> */
    private static function disputedFields(array $review): array
    {
        return array_values(array_filter(array_map(fn ($i) => is_array($i) ? ($i['field'] ?? null) : null, (array) ($review['fields_disputed'] ?? []))));
    }

    /** A category as an array key that keeps true, false and strings apart. */
    private static function key(mixed $value): string
    {
        return json_encode($value) ?: '';
    }
}
