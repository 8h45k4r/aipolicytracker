<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Models\AppSetting;
use App\Models\JobRun;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

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
        $run = JobRun::run('digest', 'cron');

        return response()->json(['message' => (string) $run->output], $run->succeeded() ? 200 : 502);
    }

    /** Daily change and deadline alerts for Pro accounts (see alerts:send). */
    public function alerts(Request $request): JsonResponse
    {
        if ($denied = $this->authorize($request)) {
            return $denied;
        }
        $run = JobRun::run('alerts', 'cron');

        return response()->json(['message' => (string) $run->output], $run->succeeded() ? 200 : 502);
    }

    /** Incremental pull of new and modified AI Incident Database records (see external:sync-aiid-api). */
    public function externalSync(Request $request): JsonResponse
    {
        if ($denied = $this->authorize($request)) {
            return $denied;
        }
        $run = JobRun::run('aiid_sync', 'cron');

        return response()->json(['message' => (string) $run->output], $run->succeeded() ? 200 : 502);
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
