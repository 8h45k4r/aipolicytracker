# Production image for AI Policy Tracker (Laravel 12 + Inertia/React).
# Works on any container host (Fly.io, Render, Railway, a VPS with Docker).

# ---- Stage 1: build front-end assets ----
FROM node:26-alpine AS assets
WORKDIR /app
COPY package.json package-lock.json ./
RUN npm ci --no-audit --no-fund
COPY . .
RUN npm run build

# ---- Stage 2: PHP runtime ----
FROM php:8.3-cli-alpine
RUN apk upgrade --no-cache
# gd: phpoffice/phpspreadsheet and phpword require it, and the social-card
# renderer draws with it (FreeType for the TrueType text, JPEG for photos).
# font-dejavu: the face the renderer draws with. Without a TrueType font on
# the host no card is drawn and every share falls back to the static image,
# which is what production did until this line. The Security workflow runs
# social:doctor inside the built image so that cannot happen quietly again.
RUN apk add --no-cache postgresql-dev icu-dev libzip-dev oniguruma-dev \
        libpng-dev libjpeg-turbo-dev freetype-dev font-dejavu \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install pdo_pgsql pdo_mysql intl bcmath mbstring zip pcntl opcache gd \
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
# Two checks, both must pass. /up is Laravel's health route: it proves the web
# server answers and the framework boots, without touching a page. `aip:health`
# covers what /up cannot see: the database answers, and the background scheduler
# the entrypoint starts has ticked in the last 5 minutes (skipped with
# SCHEDULER=off, and for the first 5 minutes after the container starts).
HEALTHCHECK --interval=30s --timeout=10s --start-period=60s --retries=3 \
    CMD php -r 'exit(@file_get_contents("http://127.0.0.1:" . (getenv("PORT") ?: "8080") . "/up") === false ? 1 : 0);' \
        && php artisan aip:health > /dev/null
ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
