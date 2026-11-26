<?php

namespace App\Services\ExternalData;

use App\Models\ExternalIncident;
use Illuminate\Support\Carbon;

/**
 * How current the incident records are, said the same way on every page that shows them.
 *
 * Records arrive two ways: the live sync from the AI Incident Database API (stamped
 * `synced_at`) and the weekly export (stamped `snapshot_date`). The newer of the two is
 * the date the data runs to. Checked on 24 November 2026, the API refused requests that
 * do not come from a web browser, so the weekly export is the route that keeps the data
 * current.
 */
final class IncidentFreshness
{
    /** Days without new data before a page says the updates have stopped (weekly export plus margin). */
    public const STALE_AFTER_DAYS = 10;

    /** @return array{as_of: ?Carbon, days: ?int, stale: bool, synced: ?Carbon, snapshot: ?Carbon} */
    public static function current(): array
    {
        $synced = ExternalIncident::max('synced_at');
        $snapshot = ExternalIncident::max('snapshot_date');
        $synced = $synced ? Carbon::parse($synced) : null;
        $snapshot = $snapshot ? Carbon::parse($snapshot) : null;
        $asOf = collect([$synced, $snapshot])->filter()->sortDesc()->first();
        $days = $asOf ? (int) $asOf->copy()->startOfDay()->diffInDays(now()->startOfDay()) : null;

        return ['as_of' => $asOf, 'days' => $days, 'stale' => $days !== null && $days > self::STALE_AFTER_DAYS, 'synced' => $synced, 'snapshot' => $snapshot];
    }

    /** One sentence for a page: when the data runs to, and whether updates have stopped. */
    public static function sentence(?array $f = null): string
    {
        $f ??= self::current();
        if ($f['as_of'] === null) {
            return 'No incident data has been imported yet.';
        }
        $date = $f['as_of']->format('j F Y');

        return $f['stale']
            ? "Incident data runs to {$date}. No new data has arrived from the AI Incident Database for {$f['days']} days, so newer incidents are not shown yet."
            : "Incident data runs to {$date}, from the AI Incident Database's weekly export.";
    }
}
