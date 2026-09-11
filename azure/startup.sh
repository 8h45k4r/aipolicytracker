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
# Load the canonical policy records from data/ (idempotent upsert).
php artisan policy:import

# Reference data + admin account, only when the database is empty (idempotent).
STATUS_COUNT=$(php artisan tinker --execute='echo \App\Models\Status::count();' 2>/dev/null | tail -n1 | tr -dc '0-9')
if [ "${STATUS_COUNT:-0}" = "0" ]; then
    php artisan db:seed --class='Database\Seeders\backend\CountrySeeder' --force
    php artisan db:seed --class=StatusSeeder --force
    php artisan db:seed --class=AdminSeeder --force
fi

# Remove fictional sample rows left by pre-1.1 seeders, then load the
# source-backed dataset if no policies exist yet (idempotent either way).
php artisan policies:purge-sample
POLICY_COUNT=$(php artisan tinker --execute='echo \App\Models\AiPolicyTracker::count();' 2>/dev/null | tail -n1 | tr -dc '0-9')
if [ "${POLICY_COUNT:-0}" = "0" ]; then
    php artisan db:seed --class=AiPolicyTrackerSeeder --force
fi

php artisan config:cache
php artisan route:cache
php artisan view:cache
