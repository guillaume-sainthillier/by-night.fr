# Dockerfile
FROM node:8-alpine as builder

ENV NODE_ENV=production
WORKDIR /app

ADD package.json webpack.config.js yarn.lock ./
ADD assets ./assets

RUN mkdir -p public && \
    npm install -g yarn && \
    NODE_ENV=development yarn install && \
    yarn run build

FROM php:7.3-fpm-stretch

ARG APP_VERSION=dev
ENV TERM="xterm" \
    DEBIAN_FRONTEND="noninteractive" \
    COMPOSER_ALLOW_SUPERUSER=1 \
    APP_VERSION="${APP_VERSION}"

EXPOSE 80
WORKDIR /app

# Install dependencies
RUN apt-get update -q && \
    apt-get install -qy \
    git \
    gnupg \
    libfreetype6-dev \
    libicu-dev \
    libjpeg62-turbo-dev \
    libmagickwand-dev \
    libpng-dev \
    libxml2-dev \
    libzip-dev \
    nginx \
    supervisor \
    unzip && \
    cp /usr/share/zoneinfo/Europe/Paris /etc/localtime && echo "Europe/Paris" > /etc/timezone && \
    #Composer
    curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer && \
    composer global require hirak/prestissimo --no-plugins --no-scripts && \
    # Reduce layer size
    apt-get clean && rm -rf /var/lib/apt/lists/* /tmp/* /var/tmp/*

# PHP Extensions
RUN docker-php-ext-install -j$(nproc) bcmath exif gd intl opcache pdo pdo_mysql soap sockets zip && \
    pecl install apcu redis imagick-3.4.4 && \
    docker-php-ext-enable apcu redis imagick

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

RUN mkdir -p /run/php var/cache var/log var/sessions var/storage/temp var/datas public/build && \
    APP_ENV=prod composer install --optimize-autoloader --no-interaction --no-ansi --no-dev && \
    APP_ENV=prod bin/console cache:clear --no-warmup && \
    APP_ENV=prod bin/console cache:warmup && \
    echo "<?php return [];" > .env.local.php && \
    chown -R www-data:www-data var public/build public/bundles /assets public/uploads && \
    # Reduce container size
    rm -rf .git docker assets /root/.composer /root/.npm /tmp/*
