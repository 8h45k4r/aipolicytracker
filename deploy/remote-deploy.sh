#!/usr/bin/env bash
#
# Deploy one commit of aipolicytracker.org on the Docker host.
#
# This is the whole of what the deploy key may do. It is installed outside
# the checkout, so that updating the checkout cannot change what the key runs:
#
#   install -m 0755 /opt/aip/repo/deploy/remote-deploy.sh /usr/local/sbin/aip-deploy
#
# and named as the forced command of the key in /root/.ssh/authorized_keys:
#
#   command="/usr/local/sbin/aip-deploy",no-port-forwarding,no-agent-forwarding,no-X11-forwarding,no-pty ssh-ed25519 AAAA... aip-deploy
#
# The caller passes the full commit sha as the SSH command: `ssh root@host <sha>`.
# Re-run the install line whenever this file changes; the workflow cannot do it.
#
# What it does, in order: take a lock, fetch, refuse any commit that is not on
# origin/main, check the commit out, build the image while the old container
# keeps serving, swap, wait for /up, probe a few pages. If any of that fails it
# puts the previous commit back and exits non-zero so the run goes red.
# A rollback does not undo a migration; the runbook says what to do then.
#
# Run by hand with the same argument:  aip-deploy <sha>
set -euo pipefail

# Overridable so the script can be exercised against a stub host; sshd does
# not pass a caller's environment to a forced command, so they are fixed in use.
REPO="${AIP_REPO:-/opt/aip/repo}"
LOCK="${AIP_LOCK:-/run/lock/aip-deploy.lock}"
LOG="${AIP_LOG:-/var/log/aip-deploy.log}"
HEALTH="http://127.0.0.1:${AIP_PORT:-8080}"
HEALTH_WAIT="${AIP_HEALTH_WAIT:-360}"   # half-seconds: three minutes, the entrypoint migrates and imports first
PROBE_PATHS="/ /policies /obligations /api/v1/ /llms.txt"

die() { echo "aip-deploy: $*" >&2; exit 1; }

# The sha arrives as the forced command's original command line, or as $1 when run by hand.
SHA="${1:-${SSH_ORIGINAL_COMMAND:-}}"
SHA="${SHA%% *}"
[[ "$SHA" =~ ^[0-9a-f]{40}$ ]] || die "usage: aip-deploy <full 40-character commit sha>"

mkdir -p "$(dirname "$LOCK")"
exec 9>"$LOCK"
flock -n 9 || die "another deploy is running"

exec > >(tee -a "$LOG") 2>&1
echo "=== $(date -u +%FT%TZ) deploy $SHA"

cd "$REPO"
git fetch -q origin main
git merge-base --is-ancestor "$SHA" origin/main || die "refusing: $SHA is not on origin/main"
PREV="$(git rev-parse HEAD)"

wait_healthy() {
    local i
    for ((i = 0; i < HEALTH_WAIT; i++)); do
        curl -sf -o /dev/null "$HEALTH/up" && return 0
        sleep 0.5
    done
    echo "the new container did not answer /up within $((HEALTH_WAIT / 2))s"
    docker compose logs --tail=30 app || true
    return 1
}

probe() {
    local p code ok=0
    for p in $PROBE_PATHS; do
        code="$(curl -s -o /dev/null -w '%{http_code}' "$HEALTH$p")"
        printf '  %-14s %s\n' "$p" "$code"
        [ "$code" = 200 ] || ok=1
    done
    return $ok
}

build_and_swap() {
    git reset -q --hard "$1"
    cd "$REPO/deploy"
    docker compose build app 2>&1 | tail -3
    docker compose up -d
    wait_healthy && probe
}

if build_and_swap "$SHA"; then
    docker image prune -f >/dev/null 2>&1 || true
    echo "=== deployed $SHA (was $PREV)"
    exit 0
fi

echo "=== deploy of $SHA failed; restoring $PREV"
if build_and_swap "$PREV"; then
    echo "=== restored $PREV; the failed deploy is above. A migration that ran is still applied."
else
    echo "=== restore of $PREV also failed; the site needs a person now"
fi
exit 1
