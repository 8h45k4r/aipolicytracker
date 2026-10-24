#!/bin/sh
set -e
cd /var/www/html

# Start time, for `php artisan aip:health`: the scheduler has not reported yet in
# the first minutes, so the health check skips that part until this is 5 minutes old.
touch /tmp/aip-booted 2>/dev/null || true

# Everything before the server starts is downtime. nginx proxies to this port,
# so until the listener is up every request is a 502. The rule here is that only
# work the application cannot serve without belongs above the exec line.
#
# Fail loudly when required environment variables are missing, before anything
# else runs against a database that may not even be reachable.
php artisan aip:env-check

# Migrations qualify: the schema has to match the code before a request is
# answered, and they take seconds.
php artisan migrate --force

# The storage volume outlives the image and can carry ownership from an older
# one. A job that cannot write there fails in the middle of the night with a
# message only the job log sees, so say so here, where `docker logs` looks.
mkdir -p storage/app/external storage/app/social-cards 2>/dev/null || true
for d in storage/app storage/app/external storage/framework/views storage/logs bootstrap/cache; do
  [ -w "$d" ] || echo "WARNING: $d is not writable by $(id -un); jobs that write there will fail. Fix: docker compose up -d (the storage-init service chowns the volume)."
done

# Caches qualify: without them every request compiles views and reparses config.
php artisan storage:link >/dev/null 2>&1 || true
php artisan config:cache
php artisan route:cache
php artisan view:cache

# The record imports do not qualify. policy:import rebuilds ~200 records and
# external:import rebuilds roughly 1,700 incidents, 6,400 reports and 2,500
# risks, which together took over a minute on the current host. Running them
# above the exec line put that minute in front of the listener on every restart,
# for data that is identical to what the previous container was already serving.
#
# They run in the background instead, after the server is accepting requests.
# Both are idempotent upserts, so the pages serve the previous import until the
# new one finishes and then serve the new one. A first boot shows empty lists
# for about a minute; every later boot shows no gap at all.
#
# A lock guards against two containers importing into the same database at once,
# which a rolling replacement would otherwise do.
#
# The presence check is not defensive padding. `flock -n 9 || exit 0` on an image
# without flock exits 127, the `|| exit 0` swallows it, and the imports never run
# while the boot log still looks clean -- data silently frozen at whatever the
# last successful import left. An absent lock is a smaller problem than absent
# data, so a missing flock runs the imports unlocked and says so.
(
  if command -v flock >/dev/null 2>&1; then
    flock -n 9 || { echo "NOTE: import lock held elsewhere; skipping this run"; exit 0; }
  else
    echo "NOTE: flock unavailable in this image; importing without a lock"
  fi
  php artisan policy:import   || echo "WARNING: policy:import failed; serving previous records"
  php artisan external:import || echo "WARNING: external:import failed; serving previous records"
) 9>/tmp/aip-import.lock &

# Queue worker, in the background. Nothing is queued today: mail is sent during
# the request so a form can tell the visitor when delivery failed, and no class
# implements ShouldQueue. The worker idles on an empty queue and is kept so a job
# added later is picked up without a change here. If it dies, nothing is lost
# today; `docker compose restart app` brings it back.
php artisan queue:work --tries=3 --sleep=3 &

# The scheduler: digests, alerts, syncs and imports on their own timetable
# (routes/console.php), so the container needs no external cron.
#
# Exactly one scheduler may run for a deployment. A host that drives the jobs
# from its own crontab (deploy/cron-install.sh) must start the container with
# SCHEDULER=off, or every job runs twice and every subscriber is mailed twice.
#
# The HEALTHCHECK (Dockerfile) runs `php artisan aip:health`, which fails when this
# scheduler stops writing its once-a-minute tick, so a dead scheduler marks the
# container unhealthy instead of going unnoticed.
if [ "${SCHEDULER:-work}" = "work" ]; then
  php artisan schedule:work > /dev/null 2>&1 &
else
  echo "NOTE: SCHEDULER=${SCHEDULER}; this container is not scheduling jobs"
fi

# Serve the app. --no-reload is what makes PHP_CLI_SERVER_WORKERS count:
# without it artisan watches .env and starts a single worker, so production
# answered one request at a time. Replace with php-fpm + nginx for more.
exec php artisan serve --host=0.0.0.0 --port="${PORT:-8080}" --no-reload
