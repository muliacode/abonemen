# syntax=docker/dockerfile:1
#
# SPIKE (#14): the Abonemen container image on FrankenPHP.
#
# The official FrankenPHP image carries PHP's build toolchain (about 100 MB),
# so it is only the build stage. The runtime stage is plain Alpine with
# FrankenPHP, libphp, the extensions Abonemen needs and their shared libraries.

ARG FRANKENPHP_IMAGE=dunglas/frankenphp:1.13.0-php8.4-alpine
ARG ALPINE_IMAGE=alpine:3.24

# --- PHP with Abonemen's extensions ------------------------------------------
FROM ${FRANKENPHP_IMAGE} AS php

RUN install-php-extensions intl gd zip pcntl bcmath \
    && rm -f /usr/local/bin/php-cgi /usr/local/bin/phpdbg

# Shared libraries that FrankenPHP, libphp and the extensions link against,
# as apk "so:" dependencies for the runtime stage.
RUN apk add --no-cache pax-utils \
    && scanelf --needed --nobanner --format '%n#p' --recursive /usr/local \
        | tr ',' '\n' | sort -u \
        | awk 'system("[ -e /usr/local/lib/" $1 " ]") == 0 { next } { print "so:" $1 }' \
        > /runtime-deps

# --- Composer dependencies ----------------------------------------------------
FROM php AS vendor

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --no-interaction --prefer-dist
COPY . .
RUN composer dump-autoload --no-dev --optimize --classmap-authoritative \
    && php artisan package:discover

# --- Front-end build (Wayfinder runs artisan, so this stage has PHP too) ------
FROM vendor AS assets

RUN apk add --no-cache nodejs npm \
    && composer install --no-interaction --prefer-dist \
    && npm ci --ignore-scripts \
    && npm run build

# --- Runtime -------------------------------------------------------------------
FROM ${ALPINE_IMAGE}

COPY --from=php /runtime-deps /tmp/runtime-deps
RUN apk add --no-cache $(cat /tmp/runtime-deps) ca-certificates mailcap tzdata \
    && rm /tmp/runtime-deps \
    && addgroup -S -g 10001 abonemen \
    && adduser -S -D -H -u 10001 -G abonemen -h /data abonemen

COPY --from=php /usr/local/bin/frankenphp /usr/local/bin/php /usr/local/bin/
COPY --from=php /usr/local/lib /usr/local/lib
COPY --from=php /usr/local/etc/php /usr/local/etc/php
COPY docker/php.ini /usr/local/etc/php/conf.d/zz-abonemen.ini
COPY docker/Caddyfile /etc/frankenphp/Caddyfile
COPY --chmod=0755 docker/entrypoint.sh /usr/local/bin/abonemen-entrypoint

WORKDIR /app
COPY --from=vendor /app /app
COPY --from=assets /app/public/build /app/public/build
# /data belongs to the app user: a new named volume takes its owner from here.
RUN rm -rf node_modules tests storage .git .github docs \
    && mkdir -p /data && chown 10001:10001 /data \
    && ln -s /data/storage /app/storage \
    && php artisan route:cache \
    && php artisan event:cache

ENV APP_ENV=production \
    APP_DEBUG=false \
    LOG_CHANNEL=stderr \
    DB_CONNECTION=sqlite \
    DB_DATABASE=/data/database.sqlite \
    SESSION_DRIVER=database \
    CACHE_STORE=database \
    QUEUE_CONNECTION=database \
    ABONEMEN_RUN_MODE=daemon \
    SERVER_NAME=:8080 \
    XDG_CONFIG_HOME=/tmp/caddy-config \
    XDG_DATA_HOME=/data/caddy \
    APP_CONFIG_CACHE=/tmp/laravel/config.php \
    APP_SERVICES_CACHE=/tmp/laravel/services.php \
    APP_PACKAGES_CACHE=/app/bootstrap/cache/packages.php

USER 10001:10001
VOLUME /data
EXPOSE 8080

HEALTHCHECK --interval=30s --timeout=5s --start-period=20s \
    CMD ["php", "-r", "exit(@file_get_contents('http://127.0.0.1:8080/up') === false ? 1 : 0);"]

ENTRYPOINT ["abonemen-entrypoint"]
