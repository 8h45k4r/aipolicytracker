#!/usr/bin/env bash
#
# Server maintenance operations for aipolicytracker.org on the Docker host.
#
# Installed outside the checkout alongside aip-deploy:
#
#   install -m 0755 /opt/aip/repo/deploy/remote-maintenance.sh /usr/local/sbin/aip-maintenance
#
# and named as the forced command of a separate SSH key in /root/.ssh/authorized_keys:
#
#   command="/usr/local/sbin/aip-maintenance",no-port-forwarding,no-agent-forwarding,no-X11-forwarding,no-pty ssh-ed25519 AAAA... aip-maintenance
#
# The caller passes the operation as the SSH command: `ssh root@host <operation>`.
# Re-run the install line whenever this file changes.
set -euo pipefail

REPO="${AIP_REPO:-/opt/aip/repo}"
LOG="${AIP_LOG:-/var/log/aip-maintenance.log}"

die() { echo "aip-maintenance: $*" >&2; exit 1; }

OP="${1:-${SSH_ORIGINAL_COMMAND:-}}"
OP="${OP%% *}"

exec > >(tee -a "$LOG") 2>&1
echo "=== $(date -u +%FT%TZ) maintenance: $OP"

app_container() {
    local cid
    cid="$(cd "$REPO/deploy" && docker compose ps -q app 2>/dev/null)" || true
    [ -n "$cid" ] || die "app container not running"
    echo "$cid"
}

case "$OP" in
    clear-cache)
        cid="$(app_container)"
        docker exec "$cid" php artisan aip:cache-clear --rebuild
        echo "=== cache cleared and rebuilt"
        ;;
    clear-cache-only)
        cid="$(app_container)"
        docker exec "$cid" php artisan aip:cache-clear
        echo "=== cache cleared (not rebuilt)"
        ;;
    docker-prune)
        docker system prune -a --volumes -f
        echo "=== docker pruned"
        ;;
    restart)
        cd "$REPO/deploy"
        docker compose restart app
        echo "=== app restarted (entrypoint rebuilds caches)"
        ;;
    status)
        echo "--- memory ---"
        free -h
        echo "--- disk ---"
        df -h /
        echo "--- docker ---"
        cd "$REPO/deploy"
        docker compose ps
        docker stats --no-stream --format "table {{.Name}}\t{{.MemUsage}}\t{{.MemPerc}}\t{{.CPUPerc}}"
        echo "--- top memory consumers ---"
        ps aux --sort=-%mem | head -10
        ;;
    full-cleanup)
        cid="$(app_container)"
        docker exec "$cid" php artisan aip:cache-clear --rebuild
        docker system prune -a -f
        echo "--- memory after cleanup ---"
        free -h
        echo "=== full cleanup done"
        ;;
    *)
        echo "aip-maintenance: usage: aip-maintenance <operation>"
        echo ""
        echo "Operations:"
        echo "  clear-cache      Clear all app caches and rebuild production caches"
        echo "  clear-cache-only Clear all app caches without rebuilding"
        echo "  docker-prune     Remove unused Docker images, containers and build cache"
        echo "  restart          Restart the app container (entrypoint rebuilds caches)"
        echo "  status           Show memory, disk, Docker and process status"
        echo "  full-cleanup     Clear caches + docker prune + show memory"
        exit 1
        ;;
esac
