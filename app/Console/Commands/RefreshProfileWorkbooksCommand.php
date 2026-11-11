<?php

namespace App\Console\Commands;

use App\Mail\WorkbookUpdatedMail;
use App\Models\AlertChannel;
use App\Models\ApplicabilityProfile;
use App\Services\Workbooks\ComplianceWorkbook;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Rebuilds the screen behind every saved profile whose account has the workbook,
 * stores a new workbook version where the screened records changed, and emails
 * the owner what changed. A profile with no change gets no version and no email.
 *
 * The owner is not emailed when they have turned alert emails off: the version is
 * still stored and shown on the profile page.
 */
class RefreshProfileWorkbooksCommand extends Command
{
    protected $signature = 'profiles:workbooks {--no-mail : Store new versions without emailing anyone}';

    protected $description = 'Store a new compliance workbook version for each saved profile whose screened records changed, and email the owner';

    public function handle(ComplianceWorkbook $workbook): int
    {
        $counts = ['checked' => 0, 'new' => 0, 'mailed' => 0, 'failed' => 0];
        ApplicabilityProfile::with('user')->orderBy('id')->chunkById(100, function ($profiles) use ($workbook, &$counts) {
            foreach ($profiles as $profile) {
                $user = $profile->user;
                if (! $user || ! $user->entitled('profiles.workbook')) {
                    continue;
                }
                $counts['checked']++;
                try {
                    $version = $workbook->refresh($profile);
                } catch (Throwable $e) {
                    $counts['failed']++;
                    Log::warning('Workbook refresh failed', ['profile' => $profile->id, 'error' => class_basename($e)]);

                    continue;
                }
                // The first version is the baseline the account already sees; only a change is news.
                if (! $version || $version->record_changes === null || ! $version->hasRecordChanges()) {
                    $counts['new'] += $version ? 1 : 0;

                    continue;
                }
                $counts['new']++;
                $emailOn = AlertChannel::where('user_id', $user->id)->where('kind', 'email')->value('enabled') ?? true;
                if ($this->option('no-mail') || ! $emailOn || ! $user->hasVerifiedEmail()) {
                    continue;
                }
                try {
                    Mail::to($user->email)->send(new WorkbookUpdatedMail($profile, $version));
                    $version->update(['notified_at' => now()]);
                    $counts['mailed']++;
                } catch (Throwable $e) {
                    $counts['failed']++;
                    Log::warning('Workbook update email failed', ['profile' => $profile->id, 'error' => class_basename($e)]);
                }
            }
        });

        $this->info("Profiles checked: {$counts['checked']}; new versions: {$counts['new']}; emails sent: {$counts['mailed']}; failures: {$counts['failed']}.");

        return $counts['failed'] > 0 ? self::FAILURE : self::SUCCESS;
    }
}
