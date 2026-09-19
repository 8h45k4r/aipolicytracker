# Deploying aipolicytracker.org on a Docker host

This is the path for `certifyi-prod-01` and any host like it: nginx on the
metal, reverse-proxying containers, with **no PHP, no PostgreSQL and no Node
installed on the host**.

The other files in this directory (`bootstrap-server.sh`, `deploy.sh`,
`nginx-aip.conf`, `php-fpm-aip.conf`) are for a host with a native PHP-FPM and
PostgreSQL. **They do not apply here.** `bootstrap-server.sh` refuses on this
host, correctly, with `no PHP-FPM found under /etc/php`.

## What gets created

| Object | Name | Exposed |
|---|---|---|
| Application container | `aip-app` | `127.0.0.1:8080` only |
| Database container | `aip-db` | **nothing** — reachable only on the private network |
| Network | `aip-net` | private bridge |
| Volumes | `aip-db-data`, `aip-storage` | — |
| nginx server block | `/etc/nginx/sites-enabled/aip` | 80, 443 for this hostname |

Every name is prefixed `aip`, so nothing collides with an existing container,
volume or network. The database publishes no host port, so the other tenant on
this host cannot reach it even by accident, and neither can anything else.

## Prerequisites on the host

Only Docker with the Compose plugin, and nginx. Both are already present on
`certifyi-prod-01`. Nothing else is installed.

## First deploy

```bash
mkdir -p /opt/aip && cd /opt/aip
git clone --depth 1 https://github.com/8h45k4r/aipolicytracker.git repo
cd repo/deploy
```

Write the environment. The password is generated here and never printed:

```bash
umask 077
cat > .env <<EOF
APP_NAME=AIPolicyTracker
APP_URL=https://aipolicytracker.org
APP_KEY=base64:$(openssl rand -base64 32)
DB_DATABASE=aip_db
DB_USERNAME=aip_user
DB_PASSWORD=$(openssl rand -base64 32 | tr -d '/+=')
EOF
```

`APP_KEY` is generated rather than copied because this deployment starts with an
empty database. **If you ever restore a dump from another host, the `APP_KEY`
from that host must be used instead** — `app_settings` values and admin
second-factor secrets are encrypted with it, and a new key makes them
unreadable without any error to tell you so.

Build and start:

```bash
docker compose up -d --build
docker compose logs -f app
```

The entrypoint migrates, imports the policy records from `data/`, caches config,
routes and views, starts a queue worker, then serves on port 8080. Wait for the
`Server running on` line.

Check it before nginx is involved at all:

```bash
curl -s -o /dev/null -w '%{http_code}\n' http://127.0.0.1:8080/up
```

## nginx

The Cloudflare origin certificate must be in place first, because SSL mode
Full (strict) validates it:

```bash
mkdir -p /etc/ssl/aip
# paste the certificate and key from Cloudflare > SSL/TLS > Origin Server
nano /etc/ssl/aip/origin.pem
nano /etc/ssl/aip/origin.key
chmod 600 /etc/ssl/aip/origin.key
```

```bash
cp nginx-aip-docker.conf /etc/nginx/sites-available/aip
ln -sfn /etc/nginx/sites-available/aip /etc/nginx/sites-enabled/aip
nginx -t && systemctl reload nginx
```

`nginx -t` tests the whole configuration, the other site's blocks included, so a
mistake here is caught before the reload rather than after it.

## Verify, then move DNS

```bash
curl -s -o /dev/null -w 'up:       %{http_code}\n' -H 'Host: aipolicytracker.org' https://127.0.0.1/up -k
curl -s -o /dev/null -w 'homepage: %{http_code}\n' -H 'Host: aipolicytracker.org' https://127.0.0.1/ -k
curl -s -o /dev/null -w 'policies: %{http_code}\n' -H 'Host: aipolicytracker.org' https://127.0.0.1/policies -k
docker stats --no-stream aip-app aip-db
```

Only when all three answer 200: Cloudflare DNS, `A` record for
`aipolicytracker.org` and `www` to this host, proxied, SSL mode Full (strict).

Moving DNS before this check is what takes the site down: the hostname resolves
to a host whose nginx has no block for it, so it falls through to the other
site's block, whose certificate does not match, and Cloudflare answers 502.

## Subsequent deploys

```bash
cd /opt/aip/repo && git pull
cd deploy && docker compose up -d --build
```

The old container keeps serving until the new one is built and healthy. Assets
are built inside the image, so the host still needs no Node.

## Rolling back

```bash
cd /opt/aip/repo && git checkout <previous-commit>
cd deploy && docker compose up -d --build
```

A rollback does not undo a migration. If a release migrated destructively, the
database needs restoring separately.

## Logs

```bash
docker compose logs --tail=100 app
tail -50 /var/log/nginx/aip-error.log
```

## Resource cost

Memory is capped in `compose.yml`: 512 MB for the application, 256 MB for
PostgreSQL. Both are limits, not reservations. `docker stats` shows the real
figure.

## Not done here

- **No backups.** The database lives in the `aip-db-data` volume and nothing
  copies it anywhere. Recorded as debt.
- **No HTTP caching beyond the asset headers**, and no Redis. The application
  uses the database for cache and sessions, which is adequate at this size.
