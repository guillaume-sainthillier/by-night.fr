FROM php:7.3-fpm-alpine

ENV COMPOSER_ALLOW_SUPERUSER=1 \
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
    nodejs \
    nodejs-npm \
    supervisor \
    tzdata \
    zlib && \
    echo "Europe/Paris" > /etc/timezone && \
    #Composer
    curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer && \
    composer global require hirak/prestissimo --no-plugins --no-scripts && \
    npm install -g yarn && \
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
    docker-php-ext-install -j$(nproc) bcmath exif gd intl opcache pdo_mysql soap sockets zip && \
    pecl install apcu redis imagick-3.4.4 && \
    docker-php-ext-enable apcu redis imagick && \
    apk del .build-deps && \
    rm -rf /var/cache/apk/* /tmp/*

# If you use Alpine, you need to set this value to "alpine"
ENV current_os=alpine
RUN version=$(php -r "echo PHP_MAJOR_VERSION.PHP_MINOR_VERSION;") \
    && curl -A "Docker" -o /tmp/blackfire-probe.tar.gz -D - -L -s https://blackfire.io/api/v1/releases/probe/php/$current_os/amd64/$version \
    && mkdir -p /tmp/blackfire \
    && tar zxpf /tmp/blackfire-probe.tar.gz -C /tmp/blackfire \
    && mv /tmp/blackfire/blackfire-*.so $(php -r "echo ini_get('extension_dir');")/blackfire.so \
    && printf "extension=blackfire.so\nblackfire.agent_socket=tcp://blackfire:8707\n" > $PHP_INI_DIR/conf.d/blackfire.ini \
    && rm -rf /tmp/blackfire /tmp/blackfire-probe.tar.gz

# Config
COPY nginx.conf /etc/nginx/
COPY php.ini /usr/local/etc/php/php.ini
COPY pool.conf /usr/local/etc/php-fpm.d/www.conf
COPY supervisord.conf /etc/supervisor/conf.d/supervisord.conf
COPY supervisord-worker.conf /etc/supervisor/conf.d/supervisord-worker.conf

COPY entrypoint.sh /usr/local/bin/entrypoint.sh
ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
