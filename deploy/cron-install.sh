#!/usr/bin/env bash
#
# Install the recurring jobs as host cron entries that run artisan inside the
# container. Run as root on the host:
#
#   bash deploy/cron-install.sh
#
# Why on the host rather than through the /cron/* endpoints:
#
# Those endpoints exist so a workflow outside the network can trigger a job, and
# they are guarded by CRON_TOKEN. That makes every run depend on the site being
# publicly reachable, on the edge not challenging the caller, and on the token in
# the repository's secrets still matching the one in the environment file. All
# three of those failed at once on this deployment and the jobs stopped for weeks
# without anything saying so: a scheduled workflow that fails notifies nobody who
# is watching the site.
#
# Running them here removes all three dependencies. The job does not traverse the
# internet, needs no token, and cannot be challenged by the edge.
#
# The schedules match the workflows they replace, so behaviour does not change:
#
#   alerts:send             06:30 daily          (was daily-alerts.yml)
#   digest:send             06:00 Mondays        (was weekly-digest.yml)
#   external:sync-aiid-api  :20 every six hours  (was sync-aiid.yml)
#
# Output goes to /var/log/aip-cron.log, which logrotate already covers if the
# bootstrap installed its rule; otherwise it is a plain file and grows slowly.
#
# Idempotent: re-running replaces the block rather than appending a second copy.

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

# A job must never pile up behind a slow predecessor, so each is wrapped in
# flock against its own lock file. Without it a sync that takes longer than its
# interval would start a second copy against the same tables.
run() { printf 'flock -n /run/aip-%s.lock docker exec %s php artisan %s >> %s 2>&1\n' "$1" "$CONTAINER" "$2" "$LOG"; }

BLOCK="$(cat <<EOF
$MARK_BEGIN
# Managed by deploy/cron-install.sh. Edit there, not here.
30 6 * * *   $(run alerts 'alerts:send')
0  6 * * 1   $(run digest 'digest:send')
20 */6 * * * $(run aiid 'external:sync-aiid-api --max=300')
$MARK_END
EOF
)"

touch "$LOG"; chmod 640 "$LOG"

current="$(crontab -l 2>/dev/null || true)"
# Drop any previous block, then append the current one.
cleaned="$(printf '%s\n' "$current" | sed "/^${MARK_BEGIN}$/,/^${MARK_END}$/d")"
printf '%s\n%s\n' "$cleaned" "$BLOCK" | sed '/^$/N;/^\n$/D' | crontab -

echo "installed:"
crontab -l | sed -n "/^${MARK_BEGIN}$/,/^${MARK_END}$/p" | sed 's/^/  /'
echo
echo "log: $LOG"
echo
echo "Disable the workflows these replace, or they will run the same jobs twice:"
echo "  .github/workflows/daily-alerts.yml"
echo "  .github/workflows/weekly-digest.yml"
echo "  .github/workflows/sync-aiid.yml"
echo
echo "Test one now without waiting for its schedule:"
echo "  docker exec $CONTAINER php artisan alerts:send --dry-run"
