#!/usr/bin/env bash
#
# Drive the recurring jobs from the host crontab, for a deployment that does not
# want a long-running scheduler process inside the container. Run as root:
#
#   bash deploy/cron-install.sh
#
# It installs ONE entry: `schedule:run` every minute, inside the container. The
# timetable itself lives in routes/console.php, so what runs and when is decided
# in the repository and shown on the admin "Jobs and schedule" page, not here.
#
# Why one entry rather than one per job. This script used to install three, each
# calling artisan directly. Those runs bypassed the job log, so the admin page
# reported "never run" while the jobs were in fact running, and any change to a
# schedule had to be made twice, in the repository and in a crontab nobody reads.
#
# Why on the host rather than through the /cron/* endpoints. Those endpoints
# exist so a workflow outside the network can trigger a job, and they are guarded
# by CRON_TOKEN. That makes every run depend on the site being publicly
# reachable, on the edge not challenging the caller, and on the token in the
# repository's secrets still matching the one in the environment file. All three
# failed at once on this deployment and the jobs stopped for weeks without
# anything saying so.
#
# EXACTLY ONE SCHEDULER MAY RUN. The container starts its own unless it is given
# SCHEDULER=off. Installing this while the container is also scheduling sends
# every digest and every alert twice, so the script refuses to do it.
#
# Idempotent: re-running replaces the block rather than appending a second copy,
# which is also how the three per-job entries this replaces are removed.

set -euo pipefail

[ "$(id -u)" = 0 ] || { echo "run as root" >&2; exit 1; }
command -v docker >/dev/null || { echo "docker not found" >&2; exit 1; }

CONTAINER="${AIP_CONTAINER:-aip-app}"
LOG=/var/log/aip-cron.log
MARK_BEGIN="# BEGIN aipolicytracker jobs"
MARK_END="# END aipolicytracker jobs"

docker inspect "$CONTAINER" >/dev/null 2>&1 || {
  echo "container $CONTAINER is not present; start it before installing cron" >&2
  exit 1
}

# Refuse to create a second scheduler. The container's own is a `schedule:work`
# process; if one is running, the operator has to turn it off first.
if docker exec "$CONTAINER" sh -c 'ps ax 2>/dev/null | grep -q "[s]chedule:work"'; then
  cat >&2 <<EOF
$CONTAINER is already running the scheduler (schedule:work).

Installing host cron as well would run every job twice: two digests to every
subscriber, two alert mails to every Pro account.

Either keep the container scheduler and stop here, or restart the container with
SCHEDULER=off (deploy/compose.yml: environment: SCHEDULER=off) and run this again.
EOF
  exit 1
fi

# One lock, because a minute-by-minute entry must never stack up behind a slow run.
BLOCK="$(cat <<EOF
$MARK_BEGIN
# Managed by deploy/cron-install.sh. The timetable is routes/console.php.
* * * * * flock -n /run/aip-schedule.lock docker exec $CONTAINER php artisan schedule:run >> $LOG 2>&1
$MARK_END
EOF
)"

touch "$LOG"; chmod 640 "$LOG"

current="$(crontab -l 2>/dev/null || true)"
# Drop any previous block, then append the current one. This is what removes the
# three per-job entries earlier versions installed.
cleaned="$(printf '%s\n' "$current" | sed "/^${MARK_BEGIN}$/,/^${MARK_END}$/d")"
printf '%s\n%s\n' "$cleaned" "$BLOCK" | sed '/^$/N;/^\n$/D' | crontab -

echo "installed:"
crontab -l | sed -n "/^${MARK_BEGIN}$/,/^${MARK_END}$/p" | sed 's/^/  /'
echo
echo "log: $LOG"
echo
echo "The timetable and the last outcome of every job are on the admin page:"
echo "  https://aipolicytracker.org/backend/admin/jobs"
echo
echo "Check it is ticking (the page says when the scheduler last reported):"
echo "  docker exec $CONTAINER php artisan schedule:list"
