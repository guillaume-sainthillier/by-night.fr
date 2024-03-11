# Install dependencies only when needed
FROM node:16-alpine as deps
WORKDIR /app

COPY package.json yarn.lock ./
RUN yarn install --frozen-lockfile --ignore-scripts

# Rebuild the source code only when needed
FROM node:16-alpine AS builder
WORKDIR /app

COPY --from=deps /app/node_modules ./node_modules
COPY package.json webpack.config.js yarn.lock ./
COPY assets ./assets
COPY src ./src
COPY templates ./templates

RUN mkdir -p public && \
    yarn build && \
    yarn install --frozen-lockfile --ignore-scripts --production

FROM php:8.1-fpm-alpine

ARG APP_VERSION=dev
ENV COMPOSER_ALLOW_SUPERUSER=1 \
    APP_VERSION="${APP_VERSION}" \
    TZ="Europe/Paris"

EXPOSE 80
WORKDIR /app

# Install dependencies
RUN apk add --no-cache \
    bash \
    icu-data-full \
    icu-libs \
    imagemagick \
    libgomp \
    libjpeg \
    libpng \
    libxml2 \
    libwebp \
    libzip \
    git \
    nginx \
    rabbitmq-c \
    supervisor \
    tzdata \
    zlib && \
    echo "Europe/Paris" > /etc/timezone && \
    #Composer
    curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer && \
    # Reduce layer size
    rm -rf /var/cache/apk/* /tmp/*

# PHP Extensions
ENV PHPIZE_DEPS \
    autoconf \
    freetype-dev \
    g++ \
    gcc \
    icu-dev \
    imagemagick-dev \
    libc-dev \
    libjpeg-turbo-dev \
    libpng-dev \
    libwebp-dev \
    libxml2-dev \
    libzip-dev \
    make \
    rabbitmq-c-dev \
    zlib-dev

RUN apk add --no-cache --virtual .build-deps \
    $PHPIZE_DEPS && \
    docker-php-ext-install -j$(nproc) bcmath exif intl opcache pdo_mysql soap sockets zip && \
    pecl install amqp apcu redis imagick && \
    docker-php-ext-enable amqp apcu redis imagick && \
    apk del .build-deps && \
    rm -rf /var/cache/apk/* /tmp/*

# Config
COPY docker/prod/nginx.conf /etc/nginx/
COPY docker/prod/php.ini /usr/local/etc/php/php.ini
COPY docker/prod/pool.conf /usr/local/etc/php-fpm.d/www.conf
COPY docker/prod/supervisord.conf /etc/supervisor/conf.d/supervisord.conf
COPY docker/prod/supervisord-worker.conf /etc/supervisor/conf.d/supervisord-worker.conf

COPY . /app
COPY --from=builder /app/public/build /app/public/build
COPY --from=builder /app/public/build /assets
COPY docker/prod/entrypoint.sh /usr/local/bin/entrypoint.sh

ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]

RUN mkdir -p /run/php var/cache var/sessions var/storage/temp var/datas public/build && \
    APP_ENV=prod composer install --prefer-dist --optimize-autoloader --classmap-authoritative --no-interaction --no-ansi --no-dev && \
    APP_ENV=prod bin/console cache:clear --no-warmup && \
    APP_ENV=prod bin/console cache:warmup && \
    echo "<?php return [];" > .env.local.php && \
    chown -R www-data:www-data var public/build public/bundles /assets && \
    # Reduce container size
    rm -rf .git docker assets /root/.composer /root/.npm /tmp/*
