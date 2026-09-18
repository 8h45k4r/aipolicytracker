#!/bin/bash
# Azure App Service startup script (set as the app's Startup Command).
set -e
cd /home/site/wwwroot

cp /home/site/wwwroot/azure/nginx.conf /etc/nginx/sites-available/default
service nginx reload

# Zip deployments drop empty directories; Laravel needs these to exist.
mkdir -p storage/framework/cache/data storage/framework/sessions storage/framework/views storage/logs storage/app/public bootstrap/cache
chmod -R ug+rwX storage bootstrap/cache

php artisan storage:link >/dev/null 2>&1 || true
php artisan migrate --force

# Rebuild caches immediately after migrating so a failure in a later data
# step can never leave the container serving a previous deployment's routes.
# Clear every cache (config, routes, views, compiled, application cache) before rebuilding so
# nothing from the previous release survives the deploy.
php artisan optimize:clear || echo 'WARNING: optimize:clear failed'

# A cache that will not build is a bug to fix in CI, not a reason to take the site
# down: without these the app still serves, just uncompiled. `set -e` used to abort
# the whole script here, so a closure in a config file stopped the data import and
# the seeders below from ever running while the deploy still reported success.
# ci.yml now builds the same three caches, so this should never fire.
php artisan config:cache || echo 'ERROR: config:cache failed; serving uncached config. A config file is probably not serializable (closures are not).'
php artisan route:cache || echo 'ERROR: route:cache failed; serving uncached routes.'
php artisan view:cache || echo 'ERROR: view:cache failed; views will compile on first request.'

# Data steps run after caches so a data failure logs loudly but never leaves the container down.
php artisan policy:import || { echo 'WARNING: policy:import failed; serving previous data'; php artisan policy:validate 2>&1 | tail -40; }
php artisan external:import || echo 'WARNING: external:import failed; serving previous external data'

# Admin account from ADMIN_* (idempotent upsert).
php artisan db:seed --class=AdminSeeder --force || echo 'WARNING: AdminSeeder failed'
php artisan db:seed --class=ToolSeeder --force || echo 'WARNING: ToolSeeder failed'
