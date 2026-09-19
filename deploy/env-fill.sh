#!/usr/bin/env bash
#
# Bring deploy/.env up to the full set of keys the application reads.
#
#   bash deploy/env-fill.sh /opt/aip/repo/deploy/.env
#
# The first .env written for the container carried only the six values needed to
# boot it. The application reads far more, and the missing ones fail quietly:
# no CRON_TOKEN means every scheduled workflow gets 401, and no ADMIN_EMAILS
# means nobody is an administrator, because that check is a list of addresses
# rather than a column.
#
# What this does:
#   - keeps every value already set, untouched
#   - adds every key from .env.example that is absent, with its example default
#   - generates CRON_TOKEN if it is missing, because it has no sensible default
#   - writes the result with mode 600 and keeps a timestamped backup
#   - prints key names and whether each is set, never a value
#
# It does not invent secrets. Keys that only you can supply are listed at the
# end as NEEDS A REAL VALUE, and the site works without them except for the
# feature each one drives.

set -euo pipefail

ENVF="${1:-}"
[ -n "$ENVF" ] || { echo "usage: bash deploy/env-fill.sh /path/to/.env" >&2; exit 1; }
HERE="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
EXAMPLE="$HERE/.env.example"
[ -f "$EXAMPLE" ] || { echo "cannot find .env.example at $EXAMPLE" >&2; exit 1; }

umask 077
if [ -f "$ENVF" ]; then
  cp -p "$ENVF" "$ENVF.bak.$(date +%Y%m%d%H%M%S)"
else
  : > "$ENVF"
fi

have() { grep -qE "^${1}=" "$ENVF"; }

# .env.example is a development template. Copying it verbatim onto a production
# host hands over APP_DEBUG=true, DB_CONNECTION=sqlite and a session cookie that
# is not marked Secure. The keys whose correct production value differs from the
# example's are listed here and win over it. Everything else comes from the
# example unchanged.
prod_value() {
  case "$1" in
    APP_ENV)               echo "production" ;;
    APP_DEBUG)             echo "false" ;;
    DB_CONNECTION)         echo "pgsql" ;;
    LOG_CHANNEL)           echo "stderr" ;;
    LOG_LEVEL)             echo "warning" ;;
    # Behind TLS the session cookie must be Secure, or it is sent in clear on
    # any request that somehow reaches the origin over http.
    SESSION_SECURE_COOKIE) echo "true" ;;
    SESSION_ENCRYPT)       echo "true" ;;
    MAIL_MAILER)           echo "resend" ;;
    APP_MAINTENANCE_DRIVER) echo "cache" ;;
    # An example address in ADMIN_EMAILS is worse than an empty one: it reads as
    # configured while granting the backend to nobody.
    ADMIN_EMAILS|ADMIN_EMAIL|CONTACT_EMAILS|MAIL_FROM_ADDRESS|ADMIN_PASSWORD) echo "" ;;
    *) return 1 ;;
  esac
}

added=0
overridden=0
while IFS= read -r line; do
  case "$line" in ''|\#*) continue ;; esac
  key="${line%%=*}"
  case "$key" in *[!A-Z0-9_]*|'') continue ;; esac
  have "$key" && continue
  if pv="$(prod_value "$key")"; then
    printf '%s=%s\n' "$key" "$pv" >> "$ENVF"
    overridden=$((overridden + 1))
  else
    # Strip any trailing comment; dotenv mostly copes, but the file is read by
    # people too and a value with prose after it invites a bad edit.
    printf '%s\n' "${line%%  #*}" >> "$ENVF"
  fi
  added=$((added + 1))
done < "$EXAMPLE"

# The scheduled workflows authenticate with this; an example value would be
# worse than none, because it would look configured while being public.
if ! grep -qE '^CRON_TOKEN=.+' "$ENVF"; then
  sed -i '/^CRON_TOKEN=/d' "$ENVF"
  printf 'CRON_TOKEN=%s\n' "$(openssl rand -hex 32)" >> "$ENVF"
  echo "  CRON_TOKEN generated (not printed; read it back with: grep '^CRON_TOKEN=' $ENVF)"
fi

chmod 600 "$ENVF"
echo "  $added key(s) added ($overridden with production values instead of the example's); existing values untouched"
echo

# Report, never reveal. A key counts as needing attention when its value is
# still the example's placeholder.
echo "  keys that still need a real value:"
NEEDS=0
while IFS= read -r line; do
  case "$line" in ''|\#*) continue ;; esac
  key="${line%%=*}"
  val="$(grep -E "^${key}=" "$ENVF" | head -1 | cut -d= -f2-)"
  case "$val" in
    ''|change-me*|your-*|*example.com*|null)
      case "$key" in
        ADMIN_EMAILS|ADMIN_EMAIL|CONTACT_EMAILS|RESEND_KEY|MAIL_FROM_ADDRESS|\
        GOOGLE_SITE_VERIFICATION|LEGAL_ENTITY_NAME|LEGAL_PRIVACY_EMAIL|\
        LEGAL_GOVERNING_LAW|DODO_PAYMENTS_API_KEY)
          printf '    %-28s %s\n' "$key" "NEEDS A REAL VALUE"; NEEDS=$((NEEDS + 1)) ;;
      esac ;;
  esac
done < "$EXAMPLE"
[ "$NEEDS" = 0 ] && echo "    none"
echo
echo "  restart the container to pick these up:"
echo "    cd $(dirname "$ENVF") && docker compose up -d"
