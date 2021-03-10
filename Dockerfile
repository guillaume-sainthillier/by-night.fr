# Dockerfile
FROM node:14-alpine as builder

ENV NODE_ENV=production
WORKDIR /app

ADD package.json webpack.config.js yarn.lock ./
ADD assets ./assets
ADD src ./src
ADD templates ./templates

RUN mkdir -p public && \
    NODE_ENV=development yarn install && \
    yarn run build

FROM php:7.4-fpm-alpine

ARG APP_VERSION=dev
ENV COMPOSER_ALLOW_SUPERUSER=1 \
    APP_VERSION="${APP_VERSION}" \
    TZ="Europs/Paris"

EXPOSE 80
WORKDIR /app

# Install dependencies
RUN apk add --no-cache \
    bash \
    icu-libs \
    imagemagick \
    libxml2 \
    libzip \
    git \
    nginx \
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
    cmake \
    file \
    freetype-dev \
    g++ \
    gcc \
    git \
    icu-dev \
    imagemagick-dev \
    libc-dev \
    libjpeg-turbo-dev \
    libpng-dev \
    libxml2-dev \
    libzip-dev \
    make \
    pcre-dev \
    pkgconf \
    re2c \
    zlib-dev
RUN apk add --no-cache --virtual .build-deps \
    $PHPIZE_DEPS && \
    docker-php-ext-install -j$(nproc) bcmath exif intl opcache pdo_mysql soap sockets zip && \
    pecl install apcu redis imagick-3.4.4 && \
    docker-php-ext-enable apcu redis imagick && \
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

RUN mkdir -p /run/php var/cache var/storage/temp var/datas public/build && \
    APP_ENV=prod composer install --prefer-dist --optimize-autoloader --classmap-authoritative --no-interaction --no-ansi --no-dev && \
    APP_ENV=prod bin/console cache:clear --no-warmup && \
    APP_ENV=prod bin/console cache:warmup && \
    echo "<?php return [];" > .env.local.php && \
    chown -R www-data:www-data var public/build public/bundles /assets && \
    # Reduce container size
    rm -rf .git docker assets /root/.composer /root/.npm /tmp/*
