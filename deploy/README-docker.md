# Deploying aipolicytracker.org on a Docker host

This is the path for the target host and any host like it: nginx on the
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
the target host. Nothing else is installed.

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

Fill in the rest of the environment. The block above carries only what the
container needs to boot; the application reads roughly eighty keys and the
missing ones fail quietly rather than loudly:

```bash
bash ../env-fill.sh .env
```

It keeps every value already set, adds the rest from `.env.example`, generates a
`CRON_TOKEN`, and lists the handful that need a real value from you. Two of
those are not optional in practice:

- **`ADMIN_EMAILS`** — administrator access is a list of addresses, not a column
  on the users table. With it unset, nobody can reach `/backend`, and the
  middleware redirects to the homepage rather than showing an error.
- **`CRON_TOKEN`** — the scheduled workflows authenticate to `/cron/*` with it.
  Unset, every one of them gets 401 and the data stops refreshing, silently.

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

One server block, `nginx-aip-docker.conf`, works in every Cloudflare SSL mode:

| SSL mode | Cloudflare connects on | Origin certificate | Cloudflare→origin |
|---|---|---|---|
| Flexible | port 80 | not needed | **plain HTTP** (debt #34) |
| Full | port 443 | any, unchecked | encrypted |
| Full (strict) | port 443 | required, validated | encrypted |

The mode can be changed in the dashboard with no matching change here and no
window where the site is broken.

**There is deliberately no redirect from 80 to 443.** Behind Cloudflare the
origin must not redirect: under Flexible the request already arrived as https to
the visitor, so a 301 to https travels back through Cloudflare and loops until
the browser gives up. Upgrading visitors to https is the edge's job — turn on
**Always Use HTTPS** in Cloudflare instead.

Port 80 stays open because Cloudflare may use it, so the block allows only
Cloudflare's published ranges on both ports. Without that, anyone could reach
the origin directly with a forged `Host` header and bypass the edge — the WAF,
the rate limits and the bot rules with it. A request from anywhere else gets
403, which is why a `curl` from the host itself is expected to be refused.

### Install

The certificate must exist before the reload, because the block references it:

```bash
mkdir -p /etc/ssl/aip
# Cloudflare > SSL/TLS > Origin Server > Create Certificate
nano /etc/ssl/aip/origin.pem     # the Origin Certificate box
nano /etc/ssl/aip/origin.key     # the Private Key box, shown once
chmod 600 /etc/ssl/aip/origin.key
```

```bash
cp nginx-aip-docker.conf /etc/nginx/sites-available/aip
ln -sfn /etc/nginx/sites-available/aip /etc/nginx/sites-enabled/aip
nginx -t && systemctl reload nginx
```

`nginx -t` tests the whole configuration, the other site's blocks included, so a
mistake is caught before the reload rather than after it.

Then move Cloudflare to **Full (strict)**. Nothing on the host changes.

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

## Automatic deploys

Every commit that reaches `main` is deployed by `.github/workflows/deploy.yml`
once two things are green for it: the CI jobs that can finish (`PHP lint &
tests`, `JS lint & build`) and the Security workflow, which is where the
production image is built and scanned. The workflow then runs one command over
SSH, waits for the new container to answer, probes a few pages, and checks the
public site with `scripts/deploy-check.sh`. If the container never comes up,
the host puts the previous commit back and the run goes red. It can also be
started from the Actions tab (*Deploy → Run workflow*) for a specific commit.

The key the workflow holds can do exactly one thing. Its forced command is a
copy of `deploy/remote-deploy.sh` kept outside the checkout, and that script
accepts one argument, a commit sha, and refuses any sha that is not on
`origin/main`. A migration is applied by the new container on start and is
not undone by the rollback; see *Rolling back*.

One-time setup, from the machine you administer the host from:

```bash
# 1. A key used for nothing else. No passphrase: the workflow has nobody to type one.
ssh-keygen -t ed25519 -N "" -C "aip-deploy" -f ~/.ssh/aip_deploy

# 2. On the host: install the script outside the checkout and bind the key to it.
ssh aip 'install -m 0755 /opt/aip/repo/deploy/remote-deploy.sh /usr/local/sbin/aip-deploy'
ssh aip "printf 'command=\"/usr/local/sbin/aip-deploy\",no-port-forwarding,no-agent-forwarding,no-X11-forwarding,no-pty %s\n' \"$(cat ~/.ssh/aip_deploy.pub)\" >> /root/.ssh/authorized_keys"

# 3. Prove the binding: the key may deploy and nothing else.
ssh -i ~/.ssh/aip_deploy root@202.58.120.67 "$(git rev-parse origin/main)"   # deploys
ssh -i ~/.ssh/aip_deploy root@202.58.120.67 hostname                          # refused by the script

# 4. Hand the workflow what it needs. Nothing here is written into the repository.
gh secret set DEPLOY_HOST --body 202.58.120.67
gh secret set DEPLOY_USER --body root
gh secret set DEPLOY_SSH_KEY < ~/.ssh/aip_deploy
gh secret set DEPLOY_KNOWN_HOSTS --body "$(ssh-keyscan -t ed25519 202.58.120.67 2>/dev/null)"
```

When `deploy/remote-deploy.sh` changes, repeat step 2's `install` line: the
workflow cannot update the copy the key is bound to, by design. To stop
automatic deploys, remove the key's line from `authorized_keys`; the workflow
then fails at the SSH step and nothing else changes.

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
