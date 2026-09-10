<?php

namespace App\Jobs;

use App\Models\BookMark;
use App\Notifications\UpdateAiPolicyTrackerNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SendAiPolicyTrackerNotificationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     */
    protected $aiPolicyTracker;
    protected $method;
    public function __construct($aiPolicyTracker, $method)
    {
        $this->aiPolicyTracker = $aiPolicyTracker;
        $this->method = $method; // method mean what function run (create,delete, update)
    }
    /**
     * Execute the job.
     */
    public function handle(): void
    {
        if ($this->method == 'update') {
            $bookmarks = BookMark::whereHas('user')
                ->with('user')
                ->where('ai_policy_tracker_id', $this->aiPolicyTracker->id)
                ->get();

            foreach ($bookmarks as $bookmark) {
                $updatingUser = $bookmark->user;
                if ($updatingUser) {
                    $updatingUser->notify(new UpdateAiPolicyTrackerNotification($this->aiPolicyTracker, $updatingUser, "AI Policy Tracker has been updated to the system!"));
                }
            }
        }
    }
}
