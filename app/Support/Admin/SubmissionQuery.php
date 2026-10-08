<?php

namespace App\Support\Admin;

use App\Enums\SubmissionStatus;
use App\Models\ContributorSubmission;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

/**
 * The Submissions list's filter, in one place: the page, its CSV export and the "all
 * matching" decision all read the same query string and so act on the same rows.
 */
final class SubmissionQuery
{
    public const SORTS = ['received' => 'created_at', 'type' => 'type', 'status' => 'status', 'summary' => 'summary'];

    public static function filters(Request $request): ListFilters
    {
        return ListFilters::from($request, self::SORTS);
    }

    /** @return array{0:?string, 1:?string} the type and status filters, each only if it names a real value */
    public static function scope(Request $request): array
    {
        $type = array_key_exists((string) $request->query('type'), ContributorSubmission::TYPES) ? (string) $request->query('type') : null;
        $status = in_array($request->query('status'), SubmissionStatus::values(), true) ? (string) $request->query('status') : null;

        return [$type, $status];
    }

    public static function query(Request $request, ?ListFilters $filters = null): Builder
    {
        $filters ??= self::filters($request);
        [$type, $status] = self::scope($request);
        $query = ContributorSubmission::query()
            ->when($type, fn ($q) => $q->where('type', $type))
            ->when($status, fn ($q) => $q->where('status', $status))
            ->when($request->filled('subject'), fn ($q) => $q->where('subject_slug', (string) $request->query('subject')));
        $filters->search($query, ['summary', 'details', 'submitter_email', 'submitter_name', 'subject_slug']);
        $filters->dateRange($query, 'created_at');

        return $filters->order($query);
    }
}
