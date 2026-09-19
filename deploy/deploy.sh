#!/usr/bin/env bash
#
# Release script for aipolicytracker.org. Run on the server as the `aip` user:
#
#   sudo -u aip /var/www/aip/deploy.sh [git-ref]
#
# Layout it expects:
#   /var/www/aip/releases/<timestamp>   one directory per release
#   /var/www/aip/shared/.env            the only copy of the environment
#   /var/www/aip/shared/storage         persistent storage, symlinked into each release
#   /var/www/aip/current                symlink to the live release
#
# The swap is a symlink rename, so requests in flight finish against the old
# release and the next request gets the new one. It is not true zero downtime:
# migrations run before the swap, so a migration that is not backward compatible
# is visible to the old code for a few seconds. For this application's change
# profile that is an acceptable trade; if it stops being true, take the site to
# maintenance mode around the migration instead.

set -euo pipefail

APP_DIR=/var/www/aip
REPO="${DEPLOY_REPO:-https://github.com/8h45k4r/aipolicytracker.git}"
REF="${1:-main}"
KEEP=3
PHP_FPM_POOL="${PHP_FPM_SERVICE:-php8.3-fpm}"   # match the host's PHP version

log() { printf '[%s] %s\n' "$(date -u +%H:%M:%S)" "$*"; }
fail() { printf '[%s] FAILED: %s\n' "$(date -u +%H:%M:%S)" "$*" >&2; exit 1; }

[ -f "$APP_DIR/shared/.env" ] || fail "no $APP_DIR/shared/.env — create it before the first deploy"
[ -d "$APP_DIR/shared/storage" ] || fail "no $APP_DIR/shared/storage"

RELEASE="$APP_DIR/releases/$(date -u +%Y%m%d%H%M%S)"
log "release $RELEASE from $REF"

mkdir -p "$RELEASE"
git clone --depth 1 --branch "$REF" "$REPO" "$RELEASE" >/dev/null 2>&1 \
  || fail "clone of $REF failed"
cd "$RELEASE"
log "commit $(git rev-parse --short HEAD)"

composer install --no-dev --optimize-autoloader --no-interaction --no-progress \
  || fail "composer install failed"

# Front-end assets.
#
# The lighter option by a wide margin is to not build here at all: a build needs
# Node plus roughly 200 MB of node_modules, which is most of this deployment's
# memory budget for something that runs for ninety seconds. Preferred order:
#
#   1. the release already carries public/build (built in CI, shipped in the ref)
#   2. npm exists on the host and we build as a fallback
#   3. neither, and we stop rather than serve a site with no stylesheet
if [ -f public/build/manifest.json ]; then
  log "assets: prebuilt manifest found, not rebuilding"
elif command -v npm >/dev/null 2>&1; then
  log "assets: no manifest, building with npm (slow, needs memory)"
  npm ci --no-audit --no-fund >/dev/null 2>&1 || fail "npm ci failed"
  npm run build >/dev/null 2>&1 || fail "npm run build failed"
  rm -rf node_modules
else
  fail "no public/build/manifest.json and no npm on this host: build assets in CI and ship them in the ref"
fi

# Shared state. Storage is replaced wholesale so a release never carries its own.
ln -sfn "$APP_DIR/shared/.env" "$RELEASE/.env"
rm -rf "$RELEASE/storage"
ln -sfn "$APP_DIR/shared/storage" "$RELEASE/storage"

php artisan migrate --force || fail "migrations failed — nothing was swapped, the live release is untouched"

# Record data is rebuilt from the repository on every release, the same way the
# previous host did it. A failure here is loud but not fatal: the previous data
# is still in the database and the site keeps serving it.
php artisan policy:import   || log "WARNING: policy:import failed; serving previous data"
php artisan external:import || log "WARNING: external:import failed; serving previous external data"

php artisan config:cache || fail "config:cache failed — a config file is probably not serializable"
php artisan route:cache  || fail "route:cache failed"
php artisan view:cache   || fail "view:cache failed"
php artisan storage:link >/dev/null 2>&1 || true

# Swap. `ln -T` makes the rename atomic rather than creating a link inside the
# existing directory, which is the classic way this step goes wrong.
ln -sfnT "$RELEASE" "$APP_DIR/current"
log "current -> $RELEASE"

# Reload rather than restart: existing requests finish, and no other pool on the
# host is affected.
sudo /bin/systemctl reload "$PHP_FPM_POOL" || fail "could not reload $PHP_FPM_POOL"

# Prove it serves before declaring success.
code=$(curl -s -o /dev/null -w '%{http_code}' -H 'Host: aipolicytracker.org' https://127.0.0.1/up -k || true)
[ "$code" = "200" ] || fail "health check returned $code — roll back with deploy/README.md"
log "health 200"

# Prune, keeping the current release and the two before it.
cd "$APP_DIR/releases"
ls -1dt */ 2>/dev/null | tail -n +$((KEEP + 1)) | xargs -r rm -rf
log "kept $(ls -1d */ 2>/dev/null | wc -l) releases"

log "done"
