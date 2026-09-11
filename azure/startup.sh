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
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Rebuild caches immediately after migrating so a failure in a later data
# step can never leave the container serving a previous deployment's routes.

# Data steps run after caches so a data failure logs loudly but never leaves the container down.
php artisan policy:import || { echo 'WARNING: policy:import failed; serving previous data'; php artisan policy:validate 2>&1 | tail -40; }
php artisan external:import || echo 'WARNING: external:import failed; serving previous external data'

# Admin account from ADMIN_* (idempotent upsert).
php artisan db:seed --class=AdminSeeder --force || echo 'WARNING: AdminSeeder failed'
php artisan db:seed --class=ToolSeeder --force || echo 'WARNING: ToolSeeder failed'
