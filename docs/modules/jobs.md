# Jobs and schedule

**What it is.** One catalogue of the recurring work the platform does, in `App\Models\JobRun::JOBS`: the weekly digest, the daily alerts, the incremental AI Incident Database sync, the external and policy imports, data validation, the freshness and coverage reports and the throwaway-domain refresh. Each entry names the artisan command, what it does, when the scheduler runs it and whether it needs a password confirmation.

**Why it exists.** The same jobs were started from three places (GitHub Actions, the container entrypoint, an operator's terminal) and nothing on the platform could say when one last ran or whether it finished. A site whose whole argument is that its records are current should be able to answer that from its own dashboard.

## How a job runs

| Trigger | Path | Recorded as |
|---|---|---|
| Scheduler | `routes/console.php` → `JobRun::run($job, 'schedule')`. A server needs `* * * * * php artisan schedule:run`; the container runs `schedule:work` from `docker/entrypoint.sh`. | `schedule` |
| Admin | `/backend/admin/jobs` → "Run now". Needs the `jobs.run` capability; jobs marked `confirm` go through `password.confirm`. | `admin`, with the user |
| Cron endpoint | `POST /cron/{digest,alerts,external-sync}` with the cron token. | `cron` |

Every path writes a row to `job_runs`: job, trigger, user, start, finish, exit code and the first 20,000 characters of output. `JobRun::latest()` feeds the dashboard panel and the jobs table. A minute-by-minute `scheduler:tick` entry writes `scheduler.last_tick` to the cache, which is how the jobs page can tell an operator that cron is not wired up yet.

## Settings

`App\Models\AppSetting::KEYS` is the list an owner may change from `/backend/admin/settings`; `App\Providers\AppSettingsServiceProvider` applies them over the environment on every request. Besides mail and billing, it now covers the contact address, X handle, newsletter URL, analytics tokens and consent, drawn social cards, throwaway-mailbox enforcement, the staleness threshold and the search-engine verification tokens. Values are encrypted at rest; secrets are only ever shown masked. An empty field keeps the environment value, so a deployment that prefers environment variables is unaffected.

## Tests

`tests/Feature/JobsAndSettingsTest.php`: every job is listed with its timetable; a job runs from the browser and records its outcome; a mail-sending job is unreachable without a password confirmation; the cron endpoint records its run; stored settings override the environment for the contact address, cards, staleness and the X handle, and reach the served pages.
