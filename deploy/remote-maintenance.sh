#!/usr/bin/env bash
#
# Maintenance operations for aipolicytracker.org on the Docker host, run by the
# Maintenance workflow (.github/workflows/maintenance.yml) through a separate SSH key.
#
# Install outside the checkout, next to aip-deploy, and re-run whenever this file changes:
#
#   install -m 0755 /opt/aip/repo/deploy/remote-maintenance.sh /usr/local/sbin/aip-maintenance
#
# and name it as the forced command of the maintenance key in /root/.ssh/authorized_keys:
#
#   command="/usr/local/sbin/aip-maintenance",no-port-forwarding,no-agent-forwarding,no-X11-forwarding,no-pty ssh-ed25519 AAAA... aip-maintenance
#
# The caller passes one operation name as the SSH command. Only the names below are accepted.
#
# Scope: this host also runs other services. Every operation touches only this project's
# compose project ("aip"): no host-wide prune, no volumes, no build cache.
# Every operation takes the same lock as aip-deploy, so it never runs during a deploy.
set -euo pipefail

REPO="${AIP_REPO:-/opt/aip/repo}"
LOCK="${AIP_LOCK:-/run/lock/aip-deploy.lock}"
PROJECT="${AIP_PROJECT:-aip}"
LOG="${AIP_LOG:-/var/log/aip-maintenance.log}"

die() { echo "aip-maintenance: $*" >&2; exit 1; }

OP="${1:-${SSH_ORIGINAL_COMMAND:-}}"
OP="${OP%% *}"
case "$OP" in
    clear-cache|restart|prune|status) ;;
    *)
        echo "usage: aip-maintenance <operation>"
        echo "  clear-cache  Clear compiled caches and cached page content, then rebuild them (no downtime)"
        echo "  restart      Restart the app container (a short outage while the entrypoint migrates and caches)"
        echo "  prune        Remove this project's stopped containers and dangling images (not volumes, not other projects)"
        echo "  status       Memory, disk, this project's containers and the app's health"
        exit 2
        ;;
esac

log() { echo "$(date -u +%FT%TZ) $*" | tee -a "$LOG"; }

mkdir -p "$(dirname "$LOCK")"
exec 9>"$LOCK"
flock -n 9 || die "a deploy or another maintenance run holds the lock; try again shortly"

cd "$REPO/deploy"
log "maintenance: $OP"

app() {
    local cid
    cid="$(docker compose ps -q app 2>/dev/null)" || true
    [ -n "$cid" ] || die "app container not running"
    echo "$cid"
}

case "$OP" in
    clear-cache)
        docker exec "$(app)" php artisan aip:cache-clear --rebuild
        ;;
    restart)
        docker compose restart app
        # Wait for the app to answer again, as aip-deploy does, so the run reports the outcome.
        port="${AIP_PORT:-8080}"
        for _ in $(seq 1 36); do
            if curl -fsS -o /dev/null "http://127.0.0.1:${port}/up"; then log "restart: app healthy"; exit 0; fi
            sleep 5
        done
        die "app did not answer /up within 180s after restart"
        ;;
    prune)
        docker container prune -f --filter "label=com.docker.compose.project=${PROJECT}"
        docker image prune -f --filter "dangling=true" --filter "label=com.docker.compose.project=${PROJECT}"
        ;;
    status)
        free -h
        df -h /
        docker compose ps
        docker stats --no-stream --format "table {{.Name}}\t{{.MemUsage}}\t{{.MemPerc}}\t{{.CPUPerc}}" $(docker compose ps -q)
        docker exec "$(app)" php artisan aip:health || true
        ;;
esac
log "maintenance: $OP done"
