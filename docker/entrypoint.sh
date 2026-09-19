#!/bin/sh
set -e
cd /var/www/html

# Run pending migrations, then cache config/routes/views for production.
php artisan migrate --force
# Load the canonical policy records from data/ (idempotent upsert).
php artisan policy:import
# And the incident and risk rows from data/external/. This line was missing, so
# a container deployment came up with empty incidents and risks tables while the
# release script running the same application loaded them. Nothing reported the
# difference: the pages render, they are simply empty. Both imports are
# idempotent upserts, so running them on every start costs a few seconds and
# makes the container and the release script agree.
php artisan external:import
php artisan storage:link >/dev/null 2>&1 || true
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Queue worker for e-mail notifications, in the background.
php artisan queue:work --tries=3 --sleep=3 &

# Serve the app. Replace with php-fpm + nginx if you need higher throughput.
exec php artisan serve --host=0.0.0.0 --port="${PORT:-8080}"
