<?php

namespace App\Helpers\Logs;

use Illuminate\Support\Str;
use App\Models\AIPolicyActivityLog;
use Illuminate\Support\Facades\Auth;

class AiPolicyActivityLogHelper
{
    /**
     * Create an activity log for a user.
     *
     * @param  string  $userAgent  The user agent string of the user's browser.
     * @param  string  $activityName  The name of the activity.
     * @param  string|null  $description  The description of the activity (optional).
     */
    public static function createAiPolicyActivityLog(
        string $ai_policy_tracker_id,
        string $activityName,
        ?string $description = null
    ): void {
        $authUser = Auth::user();

        if ($authUser) {

            AIPolicyActivityLog::create([
                // 'id'=> Str::uuid(),
                'user_id' => $authUser->id ?? 1,
                'ai_policy_tracker_id' => $ai_policy_tracker_id,
                'activity_name' => $activityName,
                'description' => $description,
            ]);
        }
    }
}
