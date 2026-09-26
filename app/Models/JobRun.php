<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;

/**
 * One run of a platform job: what ran, who or what started it, how it ended.
 * The catalogue below is the single list of jobs an operator may run from
 * the admin page or through /cron/*; the scheduler in routes/console.php
 * runs the same commands on their timetable.
 */
class JobRun extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['started_at' => 'datetime', 'finished_at' => 'datetime'];
    }

    /**
     * Jobs an operator may run. `confirm` marks the ones that send e-mail or
     * rewrite data, which sit behind a password confirmation.
     *
     * @var array<string, array{command: string, args: array<string, mixed>, label: string, what: string, schedule: string, confirm: bool}>
     */
    public const JOBS = [
        'digest' => ['command' => 'digest:send', 'args' => [], 'label' => 'Weekly digest', 'what' => 'E-mails the week\'s changes to confirmed subscribers.', 'schedule' => 'Mondays 07:00 UTC', 'confirm' => true],
        'alerts' => ['command' => 'alerts:send', 'args' => [], 'label' => 'Daily alerts', 'what' => 'E-mails Pro accounts about changes and deadlines on records they follow.', 'schedule' => 'Daily 06:30 UTC', 'confirm' => true],
        'aiid_sync' => ['command' => 'external:sync-aiid-api', 'args' => ['--max' => 300], 'label' => 'AI Incident Database sync', 'what' => 'Pulls incidents modified since the last sync, up to 300 per run.', 'schedule' => 'Daily 03:15 UTC', 'confirm' => false],
        'external_import' => ['command' => 'external:import', 'args' => [], 'label' => 'External data import', 'what' => 'Rebuilds incidents, reports and risk entries from the committed snapshots.', 'schedule' => 'Sundays 04:00 UTC', 'confirm' => true],
        'policy_import' => ['command' => 'policy:import', 'args' => [], 'label' => 'Policy import', 'what' => 'Validates data/ and rebuilds the policy, obligation and control read model.', 'schedule' => 'Daily 02:30 UTC', 'confirm' => true],
        'templates_build' => ['command' => 'templates:build', 'args' => [], 'label' => 'Templates library build', 'what' => 'Rebuilds the generated XLSX/DOCX templates from the records; only a template whose content changed gets a new version.', 'schedule' => 'Daily 05:30 UTC', 'confirm' => false],
        'policy_validate' => ['command' => 'policy:validate', 'args' => [], 'label' => 'Validate data', 'what' => 'Checks every record against its schema and cross-references. Changes nothing.', 'schedule' => 'On demand', 'confirm' => false],
        'freshness' => ['command' => 'policy:freshness', 'args' => [], 'label' => 'Freshness report', 'what' => 'Lists records past their re-check date. Changes nothing.', 'schedule' => 'On demand', 'confirm' => false],
        'coverage' => ['command' => 'policy:coverage', 'args' => ['--list' => 20], 'label' => 'Coverage report', 'what' => 'Lists required and expected gaps. Changes nothing.', 'schedule' => 'On demand', 'confirm' => false],
        'email_domains' => ['command' => 'email:domains-refresh', 'args' => [], 'label' => 'Throwaway-domain list refresh', 'what' => 'Refreshes the overlay of disposable mail domains.', 'schedule' => 'Wednesdays 05:00 UTC', 'confirm' => false],
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** Run a catalogued job now, recording the run whoever asked for it. */
    public static function run(string $job, string $trigger, ?int $userId = null): self
    {
        $meta = self::JOBS[$job] ?? null;
        if (! $meta) {
            throw new \InvalidArgumentException("Unknown job {$job}");
        }
        $run = self::create(['job' => $job, 'trigger' => $trigger, 'user_id' => $userId, 'started_at' => now()]);
        // One run of a job at a time, whoever starts it. The scheduler, /cron/* and the
        // admin button are three doors to the same job, and two digests running together
        // would each mail every subscriber before either recorded the send.
        $lock = Cache::lock('job-run:'.$job, 900);
        if (! $lock->get()) {
            $run->update(['finished_at' => now(), 'exit_code' => 1, 'output' => 'Skipped: this job is already running.']);

            return $run;
        }
        // Off the command line there is a request waiting: nginx and PHP-FPM give up
        // around 300s, so the job stops itself first and records why. Under CLI there is
        // no limit to begin with, and imposing one killed scheduled runs mid-import —
        // external:import rebuilds thousands of incidents, reports and risks and takes
        // longer than this on a cold cache.
        if (PHP_SAPI !== 'cli') {
            @set_time_limit(280);
        }
        try {
            $code = Artisan::call($meta['command'], $meta['args']);
            $output = trim(Artisan::output());
        } catch (\Throwable $e) {
            report($e);
            $code = 1;
            $output = get_class($e).': '.$e->getMessage();
        } finally {
            $lock->release();
        }
        $run->update(['finished_at' => now(), 'exit_code' => $code, 'output' => mb_substr($output, 0, 20000)]);

        return $run;
    }

    /** The latest run of every catalogued job, keyed by job. @return array<string, self|null> */
    public static function latest(): array
    {
        $out = [];
        foreach (array_keys(self::JOBS) as $job) {
            $out[$job] = self::where('job', $job)->orderByDesc('started_at')->first();
        }

        return $out;
    }

    public function succeeded(): bool
    {
        return $this->exit_code === 0;
    }
}
