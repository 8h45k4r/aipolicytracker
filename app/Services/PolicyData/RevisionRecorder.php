<?php

namespace App\Services\PolicyData;

use App\Models\RecordRevision;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;

/**
 * Compares a record before and after an import and writes a revision when a field a
 * reader relies on has changed. Long text is reported as "revised" rather than diffed:
 * the page shows the current wording, and the repository holds every earlier one.
 */
final class RevisionRecorder
{
    /** Tracked fields per record type, with the label a reader sees. */
    public const FIELDS = [
        'policy' => [
            'title' => 'Title', 'short_title' => 'Short title', 'status' => 'Status', 'is_binding' => 'Binding',
            'adopted_on' => 'Adopted', 'in_force_on' => 'In force', 'applies_from' => 'Applies from',
            'official_source_url' => 'Official source', 'review_status' => 'Review status',
            'last_verified_at' => 'Last verified', 'reviewed_by' => 'Reviewed by', 'summary_plain' => 'Summary',
        ],
        'obligation' => [
            'title' => 'Title', 'is_binding' => 'Binding', 'applies_from' => 'Applies from', 'source_reference' => 'Source reference',
            'official_source_url' => 'Official source', 'review_status' => 'Review status', 'summary' => 'Summary', 'practical_action' => 'Practical action',
        ],
        'change' => [
            'title' => 'Title', 'occurred_on' => 'Date', 'impact_level' => 'Impact', 'status_after' => 'Status after',
            'official_source_url' => 'Official source', 'review_status' => 'Review status', 'what_changed' => 'What changed',
        ],
        'control' => [
            'title' => 'Title', 'kind' => 'Kind', 'review_status' => 'Review status', 'purpose' => 'Purpose',
        ],
    ];

    /** @var array<string, array<string, mixed>> "type:slug" => fields an admin decision will set after the import */
    private static array $overrides = [];

    /** @param  array<string, array<string, mixed>>  $overrides */
    public static function useOverrides(array $overrides): void
    {
        self::$overrides = $overrides;
    }

    /** Fields whose old and new text are not quoted, only marked as revised. */
    private const PROSE = ['summary_plain', 'summary', 'practical_action', 'what_changed', 'purpose'];

    /** @return array<string, string|null> the tracked fields of a stored record, normalised */
    public static function snapshot(string $type, ?Model $record): ?array
    {
        if (! $record) {
            return null;
        }
        $out = [];
        foreach (array_keys(self::FIELDS[$type]) as $field) {
            $out[$field] = self::normalise($record->getAttribute($field));
        }

        return $out;
    }

    /** Write a revision if the record differs from its snapshot. Returns true when one was written. */
    public static function record(string $type, ?array $before, Model $after): bool
    {
        if ($before === null) {
            return false; // a new record has no earlier state to compare
        }
        $now = self::snapshot($type, $after);
        foreach (self::$overrides[$type.':'.$after->getAttribute('slug')] ?? [] as $field => $value) {
            if (array_key_exists($field, $now)) {
                $now[$field] = self::normalise($value);
            }
        }
        $changes = [];
        foreach (self::FIELDS[$type] as $field => $label) {
            if (($before[$field] ?? null) === ($now[$field] ?? null)) {
                continue;
            }
            $prose = in_array($field, self::PROSE, true);
            $changes[] = ['field' => $field, 'label' => $label, 'from' => $prose ? null : $before[$field], 'to' => $prose ? 'revised' : $now[$field]];
        }
        if ($changes === []) {
            return false;
        }
        RecordRevision::create([
            'record_type' => $type,
            'record_slug' => $after->getAttribute('slug'),
            'aipt_id' => $after->getAttribute('aipt_id'),
            'content_version' => $after->getAttribute('content_version'),
            'change_summary' => $after->getAttribute('change_summary'),
            'fields' => $changes,
            'changed_at' => now(),
        ]);

        return true;
    }

    private static function normalise(mixed $value): ?string
    {
        return match (true) {
            $value === null || $value === '' => null,
            $value instanceof CarbonInterface => $value->toDateString(),
            $value instanceof \BackedEnum => (string) $value->value,
            is_bool($value) => $value ? 'yes' : 'no',
            default => trim(preg_replace('/\s+/u', ' ', (string) $value)),
        };
    }
}
