<?php

namespace App\Support;

use Carbon\CarbonInterface;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * The north-star measure: accounts that act on an alert every week. Alert links carry
 * src=alert; when a signed-in account opens one, its week is marked. Anonymous opens
 * are not stored at all.
 */
final class Reliance
{
    public const WEEKS = 4;

    /**
     * Where an alert sends a reader. A tagged link to any other page (a bookmark of the
     * home page with src=alert, say) does not count.
     */
    public const RECORD_ROUTES = ['policies.show', 'obligations.show', 'changes.show', 'controls.show', 'jurisdictions.show'];

    /** An alert link: the record's address with src=alert. */
    public static function tag(string $url): string
    {
        return $url.(str_contains($url, '?') ? '&' : '?').'src=alert';
    }

    public static function recordAlertClick(Request $request): void
    {
        $user = $request->user();
        if (! $user || $request->query('src') !== 'alert' || ! $request->isMethod('GET') || ! in_array($request->route()?->getName(), self::RECORD_ROUTES, true)) {
            return;
        }
        $week = now()->startOfWeek(CarbonInterface::MONDAY)->toDateString();
        $updated = DB::table('account_weeks')->where('user_id', $user->id)->where('week_start', $week)->increment('alert_clicks', 1, ['updated_at' => now()]);
        if ($updated === 0) {
            DB::table('account_weeks')->insertOrIgnore(['user_id' => $user->id, 'week_start' => $week, 'alert_clicks' => 1, 'created_at' => now(), 'updated_at' => now()]);
        }
    }

    /** Accounts that opened at least one alert in each of the last four weeks, this one included. */
    public static function reliedOnEveryWeek(): int
    {
        $from = now()->startOfWeek(CarbonInterface::MONDAY)->subWeeks(self::WEEKS - 1)->toDateString();

        return DB::table('account_weeks')->where('week_start', '>=', $from)->where('alert_clicks', '>', 0)
            ->select('user_id')->groupBy('user_id')->havingRaw('count(distinct week_start) >= ?', [self::WEEKS])->get()->count();
    }

    /** Accounts that acted on an alert this week. */
    public static function activeThisWeek(): int
    {
        return DB::table('account_weeks')->where('week_start', now()->startOfWeek(CarbonInterface::MONDAY)->toDateString())->where('alert_clicks', '>', 0)->count();
    }
}
