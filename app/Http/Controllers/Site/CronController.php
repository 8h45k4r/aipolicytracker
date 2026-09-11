<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Models\AppSetting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;

/**
 * Trigger for scheduled work on hosts without a persistent scheduler. The
 * caller (GitHub Actions) presents a bearer token compared in constant time to
 * the operator-set cron token; nothing else is exposed.
 */
class CronController extends Controller
{
    public function digest(Request $request): JsonResponse
    {
        $expected = AppSetting::get('cron_token') ?? (string) config('aipolicytracker.cron_token');
        $given = (string) $request->bearerToken();
        if ($expected === '' || $given === '' || ! hash_equals($expected, $given)) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }
        Artisan::call('digest:send');

        return response()->json(['message' => trim(Artisan::output())]);
    }
}
