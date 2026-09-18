#!/usr/bin/env bash
#
# One-shot server preparation for aipolicytracker.org on a host that already
# runs something else. Run as root:
#
#   CONFIRM=yes bash deploy/bootstrap-server.sh
#
# It is idempotent: running it twice changes nothing the second time.
#
# What it will touch, and nothing else:
#   /var/www/aip/**
#   /etc/php/<detected>/fpm/pool.d/aip.conf
#   /etc/nginx/sites-available/aip and the symlink in sites-enabled
#   /etc/ssl/aip/            (directory only; you install the certificate)
#   /etc/logrotate.d/aip
#   one PostgreSQL role and one database, both named aip*
#
# It refuses to edit any shared configuration file. If one needs changing it
# stops and says which, so a human can decide.

set -euo pipefail

APP_DIR=/var/www/aip
DB_NAME=aip_db
DB_USER=aip_user
SITE=aipolicytracker.org
HERE="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"

ok()   { printf '  \033[32mok\033[0m    %s\n' "$*"; }
info() { printf '  ..    %s\n' "$*"; }
warn() { printf '  \033[33mwarn\033[0m  %s\n' "$*"; }
die()  { printf '\n  \033[31mSTOP\033[0m  %s\n\n' "$*" >&2; exit 1; }

[ "$(id -u)" = 0 ] || die "run as root"
[ "${CONFIRM:-}" = "yes" ] || die "re-run with CONFIRM=yes once you have taken a panel snapshot"

echo
echo "== 1. what is already here"

command -v nginx >/dev/null || die "nginx is not installed; this script will not install a web server on a host serving another site"
ok "nginx $(nginx -v 2>&1 | sed 's/.*\///')"

PHPV="$(ls -1d /etc/php/*/fpm 2>/dev/null | sed 's#/etc/php/##;s#/fpm##' | sort -V | tail -1 || true)"
[ -n "$PHPV" ] || die "no PHP-FPM found under /etc/php"
php_ok=$(printf '%s\n8.2\n' "$PHPV" | sort -V | head -1)
[ "$php_ok" = "8.2" ] || die "PHP $PHPV is older than the 8.2 this application requires"
ok "PHP $PHPV with FPM"

command -v psql >/dev/null || die "no PostgreSQL client; install postgresql-client before running this"
sudo -u postgres psql -Atc 'select 1' >/dev/null 2>&1 || die "cannot reach PostgreSQL as the postgres user"
ok "PostgreSQL reachable"

# Every extension the application needs. gd and the font are for the preview
# cards; without them the site serves a static image and says nothing about it.
MISSING=""
for ext in pdo_pgsql mbstring xml curl zip intl gd bcmath fileinfo tokenizer; do
  php -m 2>/dev/null | grep -qix "$ext" || MISSING="$MISSING $ext"
done
[ -z "$MISSING" ] || die "PHP extensions missing:$MISSING — install them, then re-run"
ok "PHP extensions present"

fc-list 2>/dev/null | grep -qi dejavu || warn "no DejaVu font: preview cards will fall back to a static image (apt install fonts-dejavu-core)"

# Guard: never write over another site's pool or vhost.
[ -e "/etc/php/$PHPV/fpm/pool.d/aip.conf" ] && info "pool file exists, will be replaced"
for f in /etc/nginx/sites-enabled/*; do
  [ -e "$f" ] || continue
  case "$(basename "$f")" in aip) ;; *)
    grep -qs "$SITE" "$f" && die "another vhost ($f) already claims $SITE — resolve that first" ;;
  esac
done
ok "no other vhost claims $SITE"

echo
echo "== 2. user and directories"

id aip >/dev/null 2>&1 || adduser --system --group --home "$APP_DIR" --shell /bin/bash aip >/dev/null
ok "user aip"

mkdir -p "$APP_DIR"/releases \
         "$APP_DIR"/shared/storage/{app/public,framework/{cache/data,sessions,views},logs}
chown -R aip:aip "$APP_DIR"
# 750 so the other site's user cannot read this tree.
chmod 750 "$APP_DIR"
ok "$APP_DIR"

echo
echo "== 3. database"

if sudo -u postgres psql -Atc "select 1 from pg_roles where rolname='$DB_USER'" | grep -q 1; then
  info "role $DB_USER exists, leaving its password alone"
  DB_PASS=""
else
  DB_PASS="$(openssl rand -base64 32)"
  sudo -u postgres psql -qc "CREATE ROLE $DB_USER LOGIN PASSWORD '$DB_PASS'" >/dev/null
  ok "role $DB_USER created"
fi

if sudo -u postgres psql -Atc "select 1 from pg_database where datname='$DB_NAME'" | grep -q 1; then
  info "database $DB_NAME exists"
else
  sudo -u postgres createdb -O "$DB_USER" "$DB_NAME"
  ok "database $DB_NAME created"
fi

sudo -u postgres psql -qc "REVOKE CONNECT ON DATABASE $DB_NAME FROM PUBLIC" >/dev/null
sudo -u postgres psql -qc "GRANT CONNECT ON DATABASE $DB_NAME TO $DB_USER" >/dev/null
ok "PUBLIC cannot connect to $DB_NAME"

# Report, do not change, the state of every other database.
echo
echo "  other databases on this host, for you to check:"
sudo -u postgres psql -Atc \
  "select datname, coalesce(array_to_string(datacl,' '),'(default: PUBLIC may connect)')
     from pg_database where datistemplate=false and datname<>'$DB_NAME'" \
  | sed 's/^/    /'

echo
echo "== 4. environment"

ENVF="$APP_DIR/shared/.env"
if [ -f "$ENVF" ]; then
  info ".env exists, not overwritten"
else
  umask 077
  cat > "$ENVF" <<ENVEOF
APP_NAME=AIPolicyTracker
APP_ENV=production
APP_DEBUG=false
APP_URL=https://$SITE
# APP_KEY MUST be copied from the old host. Do not run key:generate.
# The stored provider keys and every admin second factor are encrypted with it.
APP_KEY=

DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=$DB_NAME
DB_USERNAME=$DB_USER
DB_PASSWORD=$DB_PASS

SESSION_DRIVER=database
CACHE_STORE=database
QUEUE_CONNECTION=sync
LOG_CHANNEL=daily
LOG_LEVEL=warning

FILESYSTEM_LOCAL_ROOT=$APP_DIR/shared/storage/app
SOCIAL_CARDS_ENABLED=true
EMAIL_DOMAIN_ENFORCEMENT=true
ENVEOF
  chown aip:aip "$ENVF"; chmod 600 "$ENVF"
  ok ".env written with a generated database password (not printed)"
fi
grep -q '^APP_KEY=.\+' "$ENVF" || warn "APP_KEY is still empty in $ENVF — copy it from the old host before deploying"

echo
echo "== 5. php-fpm pool, nginx, logrotate"

sed "s#/run/php/php-fpm-aip.sock#/run/php/php-fpm-aip.sock#" "$HERE/php-fpm-aip.conf" \
  > "/etc/php/$PHPV/fpm/pool.d/aip.conf"
ok "pool /etc/php/$PHPV/fpm/pool.d/aip.conf"

install -d -m 755 /etc/ssl/aip
cp "$HERE/nginx-aip.conf" /etc/nginx/sites-available/aip
sed -i "s#/etc/php/[0-9.]*/fpm#/etc/php/$PHPV/fpm#g" /etc/nginx/sites-available/aip
ln -sfn /etc/nginx/sites-available/aip /etc/nginx/sites-enabled/aip
ok "vhost /etc/nginx/sites-available/aip"

cat > /etc/logrotate.d/aip <<'LOGEOF'
/var/www/aip/shared/storage/logs/*.log {
    weekly
    rotate 4
    compress
    delaycompress
    missingok
    notifempty
    create 0640 aip aip
    sharedscripts
    postrotate
        systemctl reload nginx >/dev/null 2>&1 || true
    endscript
}
LOGEOF
ok "logrotate"

cp "$HERE/deploy.sh" "$APP_DIR/deploy.sh"
sed -i "s/^PHP_FPM_POOL=.*/PHP_FPM_POOL=\"\${PHP_FPM_SERVICE:-php$PHPV-fpm}\"/" "$APP_DIR/deploy.sh"
chown aip:aip "$APP_DIR/deploy.sh"; chmod 750 "$APP_DIR/deploy.sh"
ok "deploy script at $APP_DIR/deploy.sh"

echo
echo "== 6. reload"

if [ ! -s /etc/ssl/aip/origin.pem ] || [ ! -s /etc/ssl/aip/origin.key ]; then
  warn "no certificate at /etc/ssl/aip/origin.{pem,key} yet — nginx will fail its test until you install one"
  warn "skipping the nginx reload; re-run this script after installing the certificate"
else
  nginx -t || die "nginx config test failed; nothing was reloaded"
  systemctl reload nginx
  ok "nginx reloaded"
fi

systemctl reload "php$PHPV-fpm"
ok "php$PHPV-fpm reloaded (only this pool was added)"

echo
echo "  Next:"
echo "    1. put APP_KEY from the old host into $ENVF"
echo "    2. install the Cloudflare origin certificate into /etc/ssl/aip/"
echo "    3. restore the database dump, then:  sudo -u aip $APP_DIR/deploy.sh main"
echo
