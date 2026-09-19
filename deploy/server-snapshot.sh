#!/usr/bin/env bash
#
# Copy the live server configuration into the repository and show what drifted.
# Run as root on the host, from inside the checkout:
#
#   bash deploy/server-snapshot.sh            # report drift, change nothing
#   bash deploy/server-snapshot.sh --write    # also update deploy/live/
#
# Why this exists. The repository is supposed to describe the server, and twice
# in one evening it did not. A guard in the nginx block rejected every visitor
# and had to be commented out on the host to restore service, so the live file
# and the committed file disagreed with nobody recording it. Earlier, the
# environment file on the host carried six of the roughly eighty keys the
# application reads, and nothing compared the two.
#
# A snapshot the repository holds turns both into a visible diff rather than
# something rediscovered during an outage.
#
# It records no secrets. The environment appears as key names with "set" or
# "empty" beside each, never a value. Certificates are recorded by fingerprint
# and expiry, never by content.

set -euo pipefail

[ "$(id -u)" = 0 ] || { echo "run as root: the nginx and TLS paths are not readable otherwise" >&2; exit 1; }

WRITE=0
[ "${1:-}" = "--write" ] && WRITE=1

HERE="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
OUT="$HERE/live"
TMP="$(mktemp -d)"
trap 'rm -rf "$TMP"' EXIT

ENVF="${AIP_ENV:-$HERE/.env}"
NGINX_SITE="${AIP_NGINX:-/etc/nginx/sites-available/aip}"
CONTAINER="${AIP_CONTAINER:-aip-app}"

mkdir -p "$TMP"

# --- nginx: the file as it actually is on disk --------------------------------
if [ -f "$NGINX_SITE" ]; then
  cp "$NGINX_SITE" "$TMP/nginx-aip.conf"
else
  echo "# not present on this host at $NGINX_SITE" > "$TMP/nginx-aip.conf"
fi

# --- environment: key names and whether each is set, never a value ------------
{
  echo "# Key names only. Values are never recorded here."
  echo "# 'set' means non-empty; it says nothing about whether the value is correct."
  if [ -f "$ENVF" ]; then
    grep -oE '^[A-Z_][A-Z0-9_]*=' "$ENVF" | tr -d '=' | sort | while read -r k; do
      v="$(grep -E "^${k}=" "$ENVF" | head -1 | cut -d= -f2-)"
      [ -n "$v" ] && echo "$k set" || echo "$k empty"
    done
  else
    echo "# no environment file at $ENVF"
  fi
} > "$TMP/env-keys.txt"

# --- containers: images and state, no ports or mounts -------------------------
{
  docker ps --format '{{.Names}}\t{{.Image}}\t{{.Status}}' 2>/dev/null | grep '^aip-' || echo "no aip containers running"
} > "$TMP/containers.txt"

# --- TLS: fingerprint and dates, never the certificate itself -----------------
{
  for f in /etc/ssl/aip/origin.pem; do
    if [ -s "$f" ]; then
      echo "$f"
      openssl x509 -in "$f" -noout -subject -enddate -fingerprint -sha256 2>/dev/null | sed 's/^/  /'
    else
      echo "$f: absent or empty"
    fi
  done
} > "$TMP/tls.txt"

# --- cron: the managed block only ---------------------------------------------
{
  crontab -l 2>/dev/null | sed -n '/^# BEGIN aipolicytracker jobs$/,/^# END aipolicytracker jobs$/p' \
    || true
} > "$TMP/cron.txt"
[ -s "$TMP/cron.txt" ] || echo "# no managed cron block installed" > "$TMP/cron.txt"

# --- report --------------------------------------------------------------------
drift=0
mkdir -p "$OUT"
for f in nginx-aip.conf env-keys.txt containers.txt tls.txt cron.txt; do
  if [ -f "$OUT/$f" ] && diff -q "$OUT/$f" "$TMP/$f" >/dev/null 2>&1; then
    printf '  %-20s unchanged\n' "$f"
  else
    printf '  %-20s DRIFTED\n' "$f"
    drift=1
    if [ -f "$OUT/$f" ]; then
      diff -u "$OUT/$f" "$TMP/$f" | sed 's/^/      /' | head -40 || true
    else
      echo "      (no snapshot recorded yet)"
    fi
  fi
done

echo
if [ "$WRITE" = 1 ]; then
  cp "$TMP"/*.txt "$TMP/nginx-aip.conf" "$OUT/"
  echo "  deploy/live/ updated. Review with git diff, then commit."
  echo "  Nothing here contains a secret, but read the diff before pushing."
else
  [ "$drift" = 1 ] && echo "  Drift found. Re-run with --write to record it." || echo "  No drift."
fi

exit 0
