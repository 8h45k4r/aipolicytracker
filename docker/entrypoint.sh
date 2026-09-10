#!/bin/sh
set -e
cd /var/www/html

# Run pending migrations, then cache config/routes/views for production.
php artisan migrate --force
php artisan storage:link >/dev/null 2>&1 || true
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Queue worker for e-mail notifications, in the background.
php artisan queue:work --tries=3 --sleep=3 &

# Serve the app. Replace with php-fpm + nginx if you need higher throughput.
exec php artisan serve --host=0.0.0.0 --port="${PORT:-8080}"
