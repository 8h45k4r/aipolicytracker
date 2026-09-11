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
        if ($denied = $this->authorize($request)) {
            return $denied;
        }
        Artisan::call('digest:send');

        return response()->json(['message' => trim(Artisan::output())]);
    }

    /** Incremental pull of new and modified AI Incident Database records (see external:sync-aiid-api). */
    public function externalSync(Request $request): JsonResponse
    {
        if ($denied = $this->authorize($request)) {
            return $denied;
        }
        @set_time_limit(280);
        $code = Artisan::call('external:sync-aiid-api', ['--max' => 300]);

        return response()->json(['message' => trim(Artisan::output())], $code === 0 ? 200 : 502);
    }

    private function authorize(Request $request): ?JsonResponse
    {
        $expected = AppSetting::get('cron_token') ?? (string) config('aipolicytracker.cron_token');
        $given = (string) $request->bearerToken();
        if ($expected === '' || $given === '' || ! hash_equals($expected, $given)) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        return null;
    }
}
