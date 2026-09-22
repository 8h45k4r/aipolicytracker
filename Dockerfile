# Production image for AI Policy Tracker (Laravel 11 + Inertia/React).
# Works on any container host (Fly.io, Render, Railway, a VPS with Docker).

# ---- Stage 1: build front-end assets ----
FROM node:20-alpine AS assets
WORKDIR /app
COPY package.json package-lock.json ./
RUN npm ci --no-audit --no-fund
COPY . .
RUN npm run build

# ---- Stage 2: PHP runtime ----
FROM php:8.3-cli-alpine
RUN apk add --no-cache postgresql-dev icu-dev libzip-dev oniguruma-dev \
    && docker-php-ext-install pdo_pgsql pdo_mysql intl bcmath mbstring zip pcntl opcache \
    && rm -rf /var/cache/apk/*
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-interaction --prefer-dist --no-scripts --optimize-autoloader
COPY . .
COPY --from=assets /app/public/build ./public/build
RUN composer dump-autoload --optimize \
    && chown -R www-data:www-data storage bootstrap/cache public

ENV APP_ENV=production APP_DEBUG=false LOG_CHANNEL=stderr
EXPOSE 8080
COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh
# Everything the entrypoint writes (caches, the storage link, uploads, the
# import lock in /tmp) is owned by www-data, and the listener binds 8080, so
# nothing here needs root. A process that serves the internet does not get it.
USER www-data
ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
