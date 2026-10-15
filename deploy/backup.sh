#!/usr/bin/env bash
#
# Back up the production database, and prove a backup restores. Run as root on the
# host, from cron (see deploy/README-docker.md, "Backups"):
#
#   bash deploy/backup.sh                 take a dump, check it, copy it off the host, prune
#   bash deploy/backup.sh --verify-latest restore the newest dump into a scratch database
#                                         inside the container and count its rows
#
# A dump is pg_dump's custom format: compressed, restorable table by table with
# pg_restore. Each is checked with `pg_restore --list` before it counts as taken, so a
# truncated file never replaces a good one in the rotation.
#
# Settings come from the environment or deploy/.env (values are never printed):
#   BACKUP_DIR           where dumps are kept on the host     (default /var/backups/aip)
#   BACKUP_KEEP_DAYS     how many days of dumps to keep         (default 14)
#   BACKUP_RCLONE_REMOTE an rclone destination off this host, e.g. "b2:aip-backups".
#                        Without it the dumps stay on this machine, which protects
#                        against a bad migration but not against losing the host.
#   AIP_DB_CONTAINER     the PostgreSQL container               (default aip-db)
#
# Exit status is non-zero on any failure, so cron mail or a monitor sees it.

set -euo pipefail

HERE="$(cd "$(dirname "$0")" && pwd)"
if [ -f "$HERE/.env" ]; then
  # Only the variables this script needs, without echoing anything.
  eval "$(grep -E '^(DB_DATABASE|DB_USERNAME|BACKUP_DIR|BACKUP_KEEP_DAYS|BACKUP_RCLONE_REMOTE|AIP_DB_CONTAINER)=' "$HERE/.env" | sed 's/^/export /')"
fi

CONTAINER="${AIP_DB_CONTAINER:-aip-db}"
DIR="${BACKUP_DIR:-/var/backups/aip}"
KEEP="${BACKUP_KEEP_DAYS:-14}"
DB="${DB_DATABASE:?set DB_DATABASE (deploy/.env)}"
USER_NAME="${DB_USERNAME:?set DB_USERNAME (deploy/.env)}"

[ "$(id -u)" = 0 ] || { echo "run as root" >&2; exit 1; }
command -v docker >/dev/null || { echo "docker not found" >&2; exit 1; }
docker inspect "$CONTAINER" >/dev/null 2>&1 || { echo "container $CONTAINER is not running" >&2; exit 1; }

log() { printf '%s %s\n' "$(date -u +%Y-%m-%dT%H:%M:%SZ)" "$*"; }

verify_latest() {
  local latest scratch tables
  latest="$(ls -1t "$DIR"/aip-*.dump 2>/dev/null | head -1)"
  [ -n "$latest" ] || { echo "no dump in $DIR" >&2; exit 1; }
  scratch="aip_restore_check"
  log "restoring $(basename "$latest") into scratch database $scratch"
  docker exec "$CONTAINER" psql -U "$USER_NAME" -d postgres -qc "DROP DATABASE IF EXISTS $scratch" -c "CREATE DATABASE $scratch" >/dev/null
  docker exec -i "$CONTAINER" pg_restore -U "$USER_NAME" -d "$scratch" --no-owner --exit-on-error < "$latest"
  tables="$(docker exec "$CONTAINER" psql -U "$USER_NAME" -d "$scratch" -tAc "select count(*) from policy_instruments")"
  docker exec "$CONTAINER" psql -U "$USER_NAME" -d postgres -qc "DROP DATABASE $scratch" >/dev/null
  [ "${tables:-0}" -gt 0 ] || { echo "restore produced no policy records" >&2; exit 1; }
  log "restore OK: $tables policy records in $(basename "$latest")"
}

if [ "${1:-}" = "--verify-latest" ]; then
  verify_latest
  exit 0
fi

umask 077
mkdir -p "$DIR"
stamp="$(date -u +%Y%m%d-%H%M)"
file="$DIR/aip-$stamp.dump"
tmp="$file.partial"

log "dumping $DB"
docker exec "$CONTAINER" pg_dump -U "$USER_NAME" -d "$DB" -Fc --no-owner > "$tmp"
# A dump that pg_restore cannot list is not a backup.
docker exec -i "$CONTAINER" pg_restore --list < "$tmp" > /dev/null
mv "$tmp" "$file"
log "dump OK: $(basename "$file") ($(du -h "$file" | cut -f1))"

if [ -n "${BACKUP_RCLONE_REMOTE:-}" ]; then
  command -v rclone >/dev/null || { echo "BACKUP_RCLONE_REMOTE is set but rclone is not installed" >&2; exit 1; }
  rclone copy "$file" "$BACKUP_RCLONE_REMOTE" --quiet
  log "copied off host to the configured rclone remote"
else
  log "WARNING: BACKUP_RCLONE_REMOTE is not set; this dump exists only on this host"
fi

find "$DIR" -name 'aip-*.dump' -type f -mtime +"$KEEP" -delete
find "$DIR" -name '*.partial' -type f -mmin +120 -delete
log "kept $(ls -1 "$DIR"/aip-*.dump | wc -l) dumps (older than $KEEP days removed)"
