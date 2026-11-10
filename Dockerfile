# Production image for AI Policy Tracker (Laravel 12, server-rendered Blade).
# Works on any container host (Fly.io, Render, Railway, a VPS with Docker).

# Base images are pinned to a patch release, so a deploy can never reuse a cached,
# unpatched base: a new pin is a tag the host does not have yet, and it is pulled.
# Dependabot (docker ecosystem) proposes the next patch. PHP 8.3.36 and Node 26.12.1
# are the 21 October 2026 security releases.

# ---- Stage 1: build front-end assets ----
FROM node:26.12.1-alpine AS assets
WORKDIR /app
COPY package.json package-lock.json ./
RUN npm ci --no-audit --no-fund
COPY . .
RUN npm run build

# ---- Stage 2: PHP runtime ----
# php-fpm behind nginx in the same container (debt #32): nginx serves public/ and
# listens on $PORT, so the host nginx, the compose port and the health check are
# unchanged. The CLI (artisan, queue, scheduler) is the same PHP build.
FROM php:8.5.12-fpm-alpine
# gd: phpoffice/phpspreadsheet and phpword require it, and the social-card
# renderer draws with it (FreeType for the TrueType text, JPEG for photos).
# font-dejavu: the face the renderer draws with. Without a TrueType font on
# the host no card is drawn and every share falls back to the static image,
# which is what production did until this line. The Security workflow runs
# social:doctor inside the built image so that cannot happen quietly again.
RUN apk upgrade --no-cache \
    && apk add --no-cache postgresql-dev icu-dev libzip-dev oniguruma-dev \
        libpng-dev libjpeg-turbo-dev freetype-dev font-dejavu nginx \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install pdo_pgsql pdo_mysql intl bcmath mbstring zip pcntl opcache gd \
    && rm -rf /var/cache/apk/*
COPY docker/php-production.ini /usr/local/etc/php/conf.d/zz-hardening.ini
# zzz-: loads after the image's zz-docker.conf, whose "listen = 9000" would otherwise win.
COPY docker/php-fpm-pool.conf /usr/local/etc/php-fpm.d/zzz-aip.conf
COPY docker/nginx.conf /etc/aip/nginx.conf
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
# import lock, nginx's pid and temp files in /tmp) is owned by www-data, and
# nginx binds 8080, so nothing here needs root. A process that serves the
# internet does not get it.
USER www-data
# Two checks, both must pass. /up is Laravel's health route: it proves the web
# server answers and the framework boots, without touching a page. `aip:health`
# covers what /up cannot see: the database answers, and the background scheduler
# the entrypoint starts has ticked in the last 5 minutes (skipped with
# SCHEDULER=off, and for the first 5 minutes after the container starts).
HEALTHCHECK --interval=30s --timeout=10s --start-period=60s --retries=3 \
    CMD php -r 'exit(@file_get_contents("http://127.0.0.1:" . (getenv("PORT") ?: "8080") . "/up") === false ? 1 : 0);' \
        && php artisan aip:health > /dev/null
# The php-fpm base image sets STOPSIGNAL SIGQUIT, which suits php-fpm as PID 1. Here PID 1
# is the entrypoint shell, which stops nginx, php-fpm and the background processes on
# SIGTERM (it handles SIGQUIT as well). Docker sends this on stop.
STOPSIGNAL SIGTERM
ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
