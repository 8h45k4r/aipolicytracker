#!/bin/bash
# Azure App Service startup script (set as the app's Startup Command).
set -e
cd /home/site/wwwroot

cp /home/site/wwwroot/azure/nginx.conf /etc/nginx/sites-available/default
service nginx reload

php artisan storage:link >/dev/null 2>&1 || true
php artisan migrate --force

# One-time reference data + admin account (marker lives on persistent /home storage).
if [ ! -f /home/.aip-seeded ]; then
    php artisan db:seed --class=Database\\Seeders\\backend\\CountrySeeder --force
    php artisan db:seed --class=StatusSeeder --force
    php artisan db:seed --class=AdminSeeder --force
    touch /home/.aip-seeded
fi

php artisan config:cache
php artisan route:cache
php artisan view:cache
