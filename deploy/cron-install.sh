#!/usr/bin/env bash
#
# Drive the recurring jobs from the host crontab, for a deployment that does not
# want a long-running scheduler process inside the container. Run as root:
#
#   bash deploy/cron-install.sh
#
# It installs ONE entry for the jobs: `schedule:run` every minute, inside the
# container. The timetable itself lives in routes/console.php, so what runs and
# when is decided in the repository and shown on the admin "Jobs and schedule"
# page, not here.
#
# It also installs the nightly database backup (deploy/backup.sh at 03:17 UTC),
# but only when BACKUP_RCLONE_REMOTE is set, in the environment or in deploy/.env.
# Without an off-host destination the backup is a manual decision: see
# deploy/README-docker.md, "Backups". If /etc/cron.d/aip-backup already exists
# (the manual install described there), the backup entry is left out so the dump
# is not taken twice.
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

HERE="$(cd "$(dirname "$0")" && pwd)"
CONTAINER="${AIP_CONTAINER:-aip-app}"
LOG=/var/log/aip-cron.log
BACKUP_LOG=/var/log/aip-backup.log
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

# The backup destination, read the way backup.sh reads it: the environment wins,
# then deploy/.env, as plain text. Only this one key is read.
REMOTE="${BACKUP_RCLONE_REMOTE:-}"
if [ -z "$REMOTE" ] && [ -f "$HERE/.env" ]; then
  REMOTE="$(sed -n 's/^BACKUP_RCLONE_REMOTE=//p' "$HERE/.env" | tail -1 | tr -d "\r\"'")"
fi

BACKUP_LINE=""
if [ -z "$REMOTE" ]; then
  backup_note="backup: not installed (BACKUP_RCLONE_REMOTE is not set; see deploy/README-docker.md, Backups)"
elif [ -f /etc/cron.d/aip-backup ]; then
  backup_note="backup: not installed here, /etc/cron.d/aip-backup already runs it"
else
  BACKUP_LINE="17 3 * * * bash $HERE/backup.sh >> $BACKUP_LOG 2>&1"
  backup_note="backup: nightly at 03:17 UTC, log $BACKUP_LOG"
fi

# One lock, because a minute-by-minute entry must never stack up behind a slow run.
BLOCK="$MARK_BEGIN
# Managed by deploy/cron-install.sh. The timetable is routes/console.php.
* * * * * flock -n /run/aip-schedule.lock docker exec $CONTAINER php artisan schedule:run >> $LOG 2>&1"
if [ -n "$BACKUP_LINE" ]; then
  BLOCK="$BLOCK
# Nightly database dump, copied off the host (deploy/backup.sh).
$BACKUP_LINE"
fi
BLOCK="$BLOCK
$MARK_END"

touch "$LOG"; chmod 640 "$LOG"
if [ -n "$BACKUP_LINE" ]; then touch "$BACKUP_LOG"; chmod 640 "$BACKUP_LOG"; fi

current="$(crontab -l 2>/dev/null || true)"
# Drop any previous block, then append the current one. This is what removes the
# three per-job entries earlier versions installed.
cleaned="$(printf '%s\n' "$current" | sed "/^${MARK_BEGIN}$/,/^${MARK_END}$/d")"
printf '%s\n%s\n' "$cleaned" "$BLOCK" | sed '/^$/N;/^\n$/D' | crontab -

echo "installed:"
crontab -l | sed -n "/^${MARK_BEGIN}$/,/^${MARK_END}$/p" | sed 's/^/  /'
echo
echo "log: $LOG"
echo "$backup_note"
echo
echo "The timetable and the last outcome of every job are on the admin page:"
echo "  https://aipolicytracker.org/backend/admin/jobs"
echo
echo "Check it is ticking (the page says when the scheduler last reported):"
echo "  docker exec $CONTAINER php artisan schedule:list"
