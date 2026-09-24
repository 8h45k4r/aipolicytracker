# Production image for AI Policy Tracker (Laravel 11 + Inertia/React).
# Works on any container host (Fly.io, Render, Railway, a VPS with Docker).

# ---- Stage 1: build front-end assets ----
FROM node:22-alpine AS assets
WORKDIR /app
COPY package.json package-lock.json ./
RUN npm ci --no-audit --no-fund
COPY . .
RUN npm run build

# ---- Stage 2: PHP runtime ----
FROM php:8.3-cli-alpine
RUN apk add --no-cache postgresql-dev icu-dev libzip-dev oniguruma-dev \
    && docker-php-ext-install pdo_pgsql pdo_mysql intl bcmath mbstring zip pcntl opcache \
    && rm -rf /var/cache/apk/* \
    && printf 'expose_php=Off\n' > /usr/local/etc/php/conf.d/zz-hardening.ini
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-interaction --prefer-dist --no-scripts --optimize-autoloader
COPY . .
COPY --from=assets /app/public/build ./public/build
# The runtime user owns only what it writes. public/ stays root-owned: the web
# server executes any .php file under it, so a writable docroot would turn any
# future file-write bug into code execution. The storage link is made here, as
# root, for the same reason.
RUN composer dump-autoload --optimize \
    && ln -sfn ../storage/app/public public/storage \
    && chown -R www-data:www-data storage bootstrap/cache

ENV APP_ENV=production APP_DEBUG=false LOG_CHANNEL=stderr
EXPOSE 8080
COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh
# Everything the entrypoint writes (caches, the storage link, uploads, the
# import lock in /tmp) is owned by www-data, and the listener binds 8080, so
# nothing here needs root. A process that serves the internet does not get it.
USER www-data
# /up is Laravel's health route: it boots the framework without touching a page.
HEALTHCHECK --interval=30s --timeout=5s --start-period=60s --retries=3 \
    CMD php -r 'exit(@file_get_contents("http://127.0.0.1:" . (getenv("PORT") ?: "8080") . "/up") === false ? 1 : 0);'
ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
