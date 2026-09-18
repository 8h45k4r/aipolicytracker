# Deploying aipolicytracker.org on the EverestCloud VPS

Runbook for `202.58.120.67`. The host also runs Certifyi; **nothing in this
document changes a shared configuration file, another site's pool, another
database, or any unit that is not this site's.** Where a shared file would have
to change, the step says stop and ask.

## Layout

```
/var/www/aip/
  releases/<timestamp>/   one directory per release, last 3 kept
  shared/.env             the only copy of the environment, never in git
  shared/storage/         persistent storage, symlinked into every release
  current -> releases/…   the live release
  deploy.sh               this repository's deploy/deploy.sh
```

## Before the first deploy

### 1. The single most important step

**Copy `APP_KEY` from Azure into `shared/.env`. Do not generate a new one.**

Three things in the database are encrypted with it:

| Encrypted with `APP_KEY` | What breaks with a new key |
|---|---|
| `app_settings` values | The Resend API key and the payment provider keys become unreadable. Mail stops. Billing breaks. |
| `users.two_factor_secret` | Every administrator is locked out of the backend. |
| `users.two_factor_recovery_codes` | The escape hatch is gone too; each account needs `php artisan admin:two-factor-reset`. |

A new key looks like it worked, right up to the first email or the first admin
sign-in. Copy the old one.

### 2. One command instead of steps 2 to 5

`deploy/bootstrap-server.sh` does everything in the next four sections in one
idempotent pass, and refuses rather than guesses:

```bash
CONFIRM=yes bash deploy/bootstrap-server.sh
```

It stops with an explanation if nginx, PHP 8.2 or later, PostgreSQL or any
required extension is absent; if another vhost already claims this hostname; or
if the certificate is not installed yet. It prints the access list of every other
database on the host for you to check, and changes none of them. It generates the
database password itself and never prints it. Running it twice changes nothing
the second time.

It installs no web server, no database server and no PHP. On a host that already
serves another site, that is deliberate: those are shared, and a script should not
touch them.

The manual equivalents follow, for reading or for doing it by hand.

### 2b. Users, directories, services

```bash
adduser --system --group --home /var/www/aip --shell /bin/bash aip
mkdir -p /var/www/aip/{releases,shared/storage/{app,framework/{cache/data,sessions,views},logs}}
chown -R aip:aip /var/www/aip
chmod 750 /var/www/aip                 # Certifyi's user cannot read this tree
```

Install the PHP version the audit reported, plus:

```
php-pgsql php-mbstring php-xml php-curl php-zip php-intl php-gd php-bcmath
postgresql-client-16 fonts-dejavu-core
```

`php-gd` and `fonts-dejavu-core` are both required: without them the social
preview cards silently fall back to a static image. Confirm with
`sudo -u aip php /var/www/aip/current/artisan social:doctor`.

### 3. Database

```sql
CREATE ROLE aip_user LOGIN PASSWORD '<openssl rand -base64 32>';
CREATE DATABASE aip_db OWNER aip_user;
REVOKE CONNECT ON DATABASE aip_db FROM PUBLIC;
GRANT CONNECT ON DATABASE aip_db TO aip_user;
```

Then confirm the separation, and check the other database while you are there:

```sql
SELECT datname, datacl FROM pg_database WHERE datname IN ('aip_db', '<certifyi_db>');
```

`aip_user` must appear on `aip_db` and nowhere else.

### 4. nginx and PHP-FPM

Copy `deploy/nginx-aip.conf` to `/etc/nginx/sites-available/aip` and
`deploy/php-fpm-aip.conf` to `/etc/php/<VERSION>/fpm/pool.d/aip.conf`, then:

```bash
nginx -t && systemctl reload nginx
systemctl reload php<VERSION>-fpm
```

TLS uses a Cloudflare Origin Certificate you create yourself:

```
/etc/ssl/aip/origin.pem      the certificate
/etc/ssl/aip/origin.key      the key, chmod 600, owned by root
```

Cloudflare SSL mode must be **Full (strict)**.

### 5. What this site does *not* need

- **No queue worker.** One notification implements `ShouldQueue` and nothing
  else queues. Leave `QUEUE_CONNECTION=sync` and the footprint is zero. A worker,
  permanent or scheduled, would be memory spent on nothing.
- **No scheduler cron.** Nothing in this application registers a scheduled task.
  Every recurring job is a GitHub Actions workflow calling a `/cron/*` endpoint
  with `CRON_TOKEN`. Adding `schedule:run` to crontab would run an empty
  schedule every minute.

Both were in the original migration plan. The repository says otherwise.

## Deploying

```bash
sudo -u aip /var/www/aip/deploy.sh main
```

The script clones the ref, installs dependencies, migrates, rebuilds the record
data, warms the caches, swaps the `current` symlink, reloads the pool, and only
then checks that the site answers 200. If the health check fails it says so and
leaves the previous release in place.

### Assets

The script does not build assets if the release already carries
`public/build/manifest.json`. Building on the server needs Node and roughly
200 MB of `node_modules`, which is most of this deployment's memory budget for
ninety seconds of work. Ship the built assets in the ref, or add the CI workflow
that publishes them as an artifact.

## Rolling back

```bash
ls -1dt /var/www/aip/releases/*/          # newest first
ln -sfnT /var/www/aip/releases/<previous> /var/www/aip/current
sudo systemctl reload php<VERSION>-fpm
curl -s -o /dev/null -w '%{http_code}\n' -H 'Host: aipolicytracker.org' https://127.0.0.1/up -k
```

**A rollback does not undo a migration.** If the failed release migrated the
database, restore from the dump taken before the deploy, or write the down
migration. This is the reason the deploy takes a dump first on any release that
carries a migration.

## Logs

`/var/www/aip/shared/storage/logs/` holds the application log, both nginx logs
and the two PHP-FPM logs. Add `/etc/logrotate.d/aip`:

```
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
```

## Verifying the move

```bash
# the site answers on the box, before any DNS change
curl -s -o /dev/null -w '%{http_code}\n' -H 'Host: aipolicytracker.org' https://127.0.0.1/up -k
curl -s -H 'Host: aipolicytracker.org' https://127.0.0.1/ -k | grep -c 'og:image'

# memory this site actually costs
ps -o rss=,cmd= -u aip | awk '{s+=$1} END {printf "aip total RSS: %.0f MB\n", s/1024}'
free -h

# the other site is unharmed
curl -s -o /dev/null -w 'certifyi: %{http_code}\n' https://<certifyi-host>/
```

## Cutover

Cloudflare, DNS: change the `A` record for `aipolicytracker.org` to
`202.58.120.67`, proxied (orange). Same for `www`. SSL mode Full (strict).

**One expectation to correct.** Moving the origin does not change the edge. The
site is currently unreachable to Googlebot and that fault was traced to the
Cloudflare layer, which this migration does not touch. If indexing is the reason
for the move, the move will not fix it.

## After cutover

Leave Azure alone for **30 days**, not 7. Deleting a PostgreSQL Flexible Server
deletes its automated backups with it, and after that the dump is the only copy
of the data that does not rebuild from this repository: accounts, subscribers,
download history, submissions, billing records and the audit log.

Then, in this order:

1. App Service `aip-scaffolders`
2. Plan `aip-scaffolders-plan`
3. PostgreSQL `aip-scaffolders-db`
4. Resource group `aip-scaffolders-rg`
