#syntax=docker/dockerfile:1.7-labs

# Versions
FROM dunglas/frankenphp:1.11-php8.4-alpine AS php_upstream
FROM node:24-alpine as node_upstream

# Base image
FROM php_upstream as php_base
WORKDIR /app

RUN IPE_GD_WITHOUTAVIF=1 \
    install-php-extensions \
        @composer \
        amqp \
        apcu \
        bcmath \
        exif \
        intl \
        imagick \
        opcache \
        pcntl \
        pdo_mysql \
        redis \
        sockets \
        zip

# Composer install stage
FROM php_base as php_builder
WORKDIR /app

ENV COMPOSER_ALLOW_SUPERUSER=1
ENV SERVER_NAME=:80

COPY --link composer.json composer.lock symfony.lock ./
RUN --mount=type=cache,target=.cache/composer \
    APP_ENV=prod COMPOSER_CACHE_DIR=.cache/composer composer install --no-interaction --no-dev --no-scripts --prefer-dist

# Install node dependencies
FROM node_upstream as node_builder
WORKDIR /app

# Copy vendor directory from php_builder for Symfony UX packages
# @symfony/stimulus-bridge needs to read controllers.json from vendor packages
COPY --from=php_builder --link /app/vendor ./vendor
COPY --link package.json yarn.lock ./
RUN --mount=type=cache,target=.cache/yarn \
    YARN_CACHE_FOLDER=.cache/yarn yarn install --frozen-lockfile --ignore-scripts

# Build assets
FROM node_upstream AS node_assets_builder
WORKDIR /app

ARG APP_VERSION=dev
ENV SENTRY_RELEASE="${APP_VERSION}"

COPY --from=node_builder --link /app/node_modules ./node_modules
COPY --from=node_builder --link /app/vendor ./vendor
COPY --link assets ./assets
COPY --link src ./src
COPY --link templates ./templates
COPY --link package.json webpack.config.js yarn.lock ./

RUN mkdir -p public && \
    yarn build

FROM php_base

EXPOSE 80
WORKDIR /app

ARG APP_VERSION=dev
ENV APP_VERSION="${APP_VERSION}"
ENV APP_ENV=prod
ENV COMPOSER_ALLOW_SUPERUSER=1
ENV SERVER_NAME=:80
ENV FRANKENPHP_CONFIG="import worker.Caddyfile"

# Install dependencies
RUN apk add --no-cache \
    bash \
    icu-data-full \
    linux-headers \
    git \
    supervisor \
    tzdata && \
    echo "Europe/Paris" > /etc/timezone

# Composer install before sources
COPY --from=php_builder --link /app/vendor ./vendor
COPY --from=node_assets_builder --link /app/public/build ./public/build

COPY --link --exclude=assets --exclude=docker . .

# Config
COPY --link docker/Caddyfile /etc/frankenphp/Caddyfile
COPY --link docker/worker.Caddyfile /etc/frankenphp/worker.Caddyfile
COPY --link docker/php.ini $PHP_INI_DIR/conf.d/app.ini
COPY --link docker/supervisord-worker.conf /etc/supervisor/conf.d/supervisord-worker.conf
COPY --link docker/entrypoint.sh /usr/local/bin/docker-entrypoint

RUN mkdir -p /run/php var/cache var/sessions var/storage/temp var/datas public/build && \
    APP_ENV=prod composer dump-autoload --optimize --classmap-authoritative --no-dev --no-interaction && \
    APP_ENV=prod bin/console cache:clear --no-warmup && \
    APP_ENV=prod bin/console cache:warmup && \
    APP_ENV=prod bin/console ux:icons:warm-cache && \
    APP_ENV=prod bin/console assets:install && \
    echo "<?php return [];" > .env.local.php && \
    rm -rf /root/.cache


HEALTHCHECK --start-period=60s CMD curl -f http://localhost:2019/metrics || exit 1
ENTRYPOINT ["docker-entrypoint"]
CMD [ "--config", "/etc/frankenphp/Caddyfile", "--adapter", "caddyfile" ]
