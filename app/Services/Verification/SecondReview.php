<?php

namespace App\Services\Verification;

use App\Enums\PolicyStatus;
use App\Enums\ReviewStatus;

/**
 * The independent second check: what it records and the rules it must meet.
 *
 * A policy record verified by one reviewer may carry `second_review`, written by a
 * different reviewer on the published roster who re-read the official source without
 * seeing the first reviewer's values. The second reviewer codes the categorical fields
 * (`coded`) and lists every field on which they disagreed (`fields_disputed`), each with
 * the first reviewer's value and how it was resolved. Agreement statistics are computed
 * from these entries by AgreementStatistics; nothing here is inferred.
 *
 * Lives in data/ (validated by policy:validate) because a verification claim is only
 * auditable if every version of it has a diff and a pull request.
 */
class SecondReview
{
    /**
     * Fields a second reviewer checks. Categorical fields carry a coded value from each
     * reviewer, so Cohen's kappa can be computed for them; the rest are compared as
     * agree or disagree, so only percent agreement is reported.
     *
     * @var array<string, array{label: string, categorical: bool}>
     */
    public const FIELDS = [
        'status' => ['label' => 'Status', 'categorical' => true],
        'is_binding' => ['label' => 'Binding or non-binding', 'categorical' => true],
        'review_status' => ['label' => 'Review status', 'categorical' => true],
        'dates' => ['label' => 'Key dates', 'categorical' => false],
        'actors' => ['label' => 'Who it applies to', 'categorical' => false],
        'obligations' => ['label' => 'Obligations', 'categorical' => false],
        'penalties' => ['label' => 'Penalties', 'categorical' => false],
        'official_source' => ['label' => 'Official source', 'categorical' => false],
    ];

    private const KEYS = ['reviewed_by', 'reviewed_on', 'sample', 'coded', 'agreed', 'fields_disputed'];

    private const DISPUTE_KEYS = ['field', 'first', 'note', 'resolution'];

    /** @return list<string> */
    public static function categoricalFields(): array
    {
        return array_keys(array_filter(self::FIELDS, fn ($f) => $f['categorical']));
    }

    /** @return list<string|bool> the values a categorical field may take */
    public static function categories(string $field): array
    {
        return match ($field) {
            'status' => PolicyStatus::values(),
            'is_binding' => [true, false],
            'review_status' => ReviewStatus::values(),
            default => [],
        };
    }

    /**
     * Rules the JSON Schema cannot express. Returns error messages for `$path`.
     *
     * @param  array  $record  the policy record carrying `second_review`
     * @param  array<string, string>  $roster  published reviewer name => roster slug
     * @return list<string>
     */
    public static function errors(array $record, array $roster, string $path = '$.second_review'): array
    {
        $review = $record['second_review'] ?? null;
        if ($review === null) {
            return [];
        }
        if (! is_array($review)) {
            return ["{$path}: must be a mapping"];
        }
        $errors = [];
        foreach (array_diff(array_keys($review), self::KEYS) as $unknown) {
            $errors[] = "{$path}: unknown key \"{$unknown}\"";
        }

        // Independence: the first reviewer must be named, and the second must be a
        // different person on the published roster. Compared by roster slug, so a
        // spelling variant of the same name cannot pass as a second person.
        $first = trim((string) ($record['reviewed_by'] ?? ''));
        $second = trim((string) ($review['reviewed_by'] ?? ''));
        if (($record['review_status'] ?? null) !== 'verified' && ! self::disputes($review, 'review_status')) {
            $errors[] = "{$path}: a second review is recorded only on a verified record (or one whose review status the second reviewer disputed)";
        }
        if ($first === '') {
            $errors[] = "{$path}: the record has no first reviewer (reviewed_by), so a second check cannot be independent of it";
        }
        $secondSlug = $roster[$second] ?? null;
        if ($second !== '' && $secondSlug === null) {
            $errors[] = "{$path}.reviewed_by: \"{$second}\" is not a published reviewer; add them to data/reviewers with a declaration of interest";
        }
        if ($second !== '' && $first !== '' && (mb_strtolower($first) === mb_strtolower($second) || ($secondSlug !== null && ($roster[$first] ?? null) === $secondSlug))) {
            $errors[] = "{$path}.reviewed_by: the second reviewer must be a different person from the first (\"{$first}\")";
        }
        if (isset($review['reviewed_on']) && (string) $review['reviewed_on'] > now()->toDateString()) {
            $errors[] = "{$path}.reviewed_on: is in the future";
        }

        $disputed = [];
        foreach ((array) ($review['fields_disputed'] ?? []) as $i => $item) {
            $field = is_array($item) ? ($item['field'] ?? null) : null;
            if (! is_string($field) || ! isset(self::FIELDS[$field])) {
                continue; // the schema reports an unknown field name
            }
            foreach (array_diff(array_keys($item), self::DISPUTE_KEYS) as $unknown) {
                $errors[] = "{$path}.fields_disputed[{$i}]: unknown key \"{$unknown}\"";
            }
            if (isset($disputed[$field])) {
                $errors[] = "{$path}.fields_disputed[{$i}]: \"{$field}\" is listed twice";
            }
            $disputed[$field] = true;
            if (self::FIELDS[$field]['categorical']) {
                // Kappa needs both reviewers' values. The second is in `coded`; the
                // first is recorded here, because the record may since have been
                // changed to the resolved value.
                if (! array_key_exists('first', $item) || ! in_array($item['first'], self::categories($field), true)) {
                    $errors[] = "{$path}.fields_disputed[{$i}].first: the first reviewer's {$field} is required and must be one of the allowed values";
                } elseif (array_key_exists($field, (array) ($review['coded'] ?? [])) && $item['first'] === $review['coded'][$field]) {
                    $errors[] = "{$path}.fields_disputed[{$i}]: \"{$field}\" is disputed but both reviewers recorded the same value";
                }
            }
        }
        if (array_key_exists('agreed', $review) && (bool) $review['agreed'] !== ($disputed === [])) {
            $errors[] = "{$path}.agreed: must be true exactly when fields_disputed is empty";
        }

        return $errors;
    }

    private static function disputes(array $review, string $field): bool
    {
        foreach ((array) ($review['fields_disputed'] ?? []) as $item) {
            if (is_array($item) && ($item['field'] ?? null) === $field) {
                return true;
            }
        }

        return false;
    }
}
