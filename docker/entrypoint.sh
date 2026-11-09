#!/bin/sh
set -e
cd /var/www/html

# Until the servers start, a stop request ends the boot at once (PID 1 ignores SIGTERM
# without a handler, and docker would wait out the grace period before killing it).
trap 'exit 143' TERM INT

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
imports=$!

# Background processes are supervised: if one exits it is started again after a pause,
# and on shutdown each is sent SIGTERM and waited for, so a job in progress finishes or
# stops cleanly instead of being killed with its scheduler lock still held.
# Usage: supervise <name> <command...>   (runs in the background; its pid is in $!)
supervise() {
  name="$1"; shift
  (
    child=""
    trap 'touch /tmp/aip-stopping; [ -n "$child" ] && kill -TERM "$child" 2>/dev/null; [ -n "$child" ] && wait "$child" 2>/dev/null; exit 0' TERM INT
    while [ ! -f /tmp/aip-stopping ]; do
      "$@" &
      child=$!
      code=0
      wait "$child" || code=$?
      [ -f /tmp/aip-stopping ] && break
      echo "WARNING: $name exited with status $code; starting it again in 5s"
      sleep 5
    done
  ) &
}
rm -f /tmp/aip-stopping

# Queue worker. Nothing is queued today: mail is sent during the request so a form
# can tell the visitor when delivery failed. The worker is kept so a job added later
# is picked up without a change here. queue:work finishes the job in hand on SIGTERM.
supervise queue php artisan queue:work --tries=3 --sleep=3 --max-time=3600
queue=$!

# The scheduler: digests, alerts, syncs and imports on their own timetable
# (routes/console.php), so the container needs no external cron.
#
# Exactly one scheduler may run for a deployment. A host that drives the jobs
# from its own crontab (deploy/cron-install.sh) must start the container with
# SCHEDULER=off, or every job runs twice and every subscriber is mailed twice.
#
# The HEALTHCHECK (Dockerfile) runs `php artisan aip:health`, which fails when this
# scheduler stops writing its once-a-minute tick. Supervision restarts it; the health
# check still reports a scheduler that keeps failing.
scheduler=""
if [ "${SCHEDULER:-work}" = "work" ]; then
  supervise scheduler sh -c 'exec php artisan schedule:work > /dev/null 2>&1'
  scheduler=$!
else
  echo "NOTE: SCHEDULER=${SCHEDULER}; this container is not scheduling jobs"
fi

# Serve the app: php-fpm for PHP, nginx for everything else, on $PORT.
#
# Both run in the background and this shell waits on them. If either stops, the
# whole container stops, so the restart policy brings up a fresh one (with artisan
# serve as PID 1, a crash did the same). On SIGTERM (docker stop, a deploy) nginx and
# php-fpm finish in-flight requests, and the background processes are asked to stop
# and waited for. compose gives the container stop_grace_period to do it.
sed "s/__PORT__/${PORT:-8080}/" /etc/aip/nginx.conf > /tmp/nginx.conf
php-fpm --nodaemonize --force-stderr &
fpm=$!
nginx -e /dev/stderr -c /tmp/nginx.conf -g 'daemon off;' &
web=$!

stop() {
  touch /tmp/aip-stopping
  for pid in $queue $scheduler $imports; do kill -TERM "$pid" 2>/dev/null || true; done
  kill -QUIT "$web" 2>/dev/null || true
  kill -QUIT "$fpm" 2>/dev/null || true
  for pid in $web $fpm $queue $scheduler $imports; do wait "$pid" 2>/dev/null || true; done
  exit "${1:-0}"
}
trap 'stop 0' TERM INT

while kill -0 "$fpm" 2>/dev/null && kill -0 "$web" 2>/dev/null; do
  sleep 2
done
echo "ERROR: $(kill -0 "$fpm" 2>/dev/null && echo nginx || echo php-fpm) stopped; stopping the container"
stop 1
