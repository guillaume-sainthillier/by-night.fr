FROM php:7.2-fpm

ENV TERM="xterm" \
    DEBIAN_FRONTEND="noninteractive" \
    COMPOSER_ALLOW_SUPERUSER=1

EXPOSE 80
WORKDIR /app

# Install dependencies
RUN apt-get update -q && \
    apt-get install -qy \
    curl \
    git \
    gnupg \
    libfreetype6-dev \
    libicu-dev \
    libjpeg62-turbo-dev \
    libpng-dev \
    libxml2-dev \
    nginx \
    python \
    supervisor \
    unzip \
    wget && \
    cp /usr/share/zoneinfo/Europe/Paris /etc/localtime && echo "Europe/Paris" > /etc/timezone && \

    #Composer
    curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer && \
    composer global require hirak/prestissimo --no-plugins --no-scripts && \

    #NPM
    curl -sL https://deb.nodesource.com/setup_8.x | bash - && \
    apt-get install -y nodejs && \
    curl -sS https://dl.yarnpkg.com/debian/pubkey.gpg | apt-key add - && \
    echo "deb https://dl.yarnpkg.com/debian/ stable main" | tee /etc/apt/sources.list.d/yarn.list && \
    npm install -g grunt-cli yarn && \

    # Reduce layer size
    apt-get clean && rm -rf /var/lib/apt/lists/* /tmp/* /var/tmp/*

# PHP Extensions
RUN docker-php-ext-configure gd --with-freetype-dir=/usr/include/ --with-jpeg-dir=/usr/include/ && \
    docker-php-ext-install -j$(nproc) bcmath exif gd intl opcache pdo pdo_mysql soap sockets zip && \
    pecl install apcu && \
    docker-php-ext-enable apcu && \
    pecl install redis && \
    docker-php-ext-enable redis

# Config
COPY docker/prod/nginx.conf /etc/nginx/
COPY docker/prod/php.ini /usr/local/etc/php/php.ini
COPY docker/prod/pool.conf /usr/local/etc/php-fpm.d/www.conf
COPY docker/prod/supervisord.conf /etc/supervisor/conf.d/supervisord.conf

COPY . /app
COPY docker/prod/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh
ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]

RUN mkdir -p /run/php var public/media public/uploads && \
    yarn install && \
    grunt && \
    APP_ENV=prod composer install --optimize-autoloader --no-interaction --no-ansi --no-dev && \
    composer dump-env prod && \
    bin/console cache:clear --no-warmup && \
    bin/console cache:warmup && \
    chown -R www-data:www-data var public/media public/uploads && \
    # Reduce container size
    rm -rf .git assets node_modules docker /root/.composer /root/.npm /tmp/*

VOLUME /app/public/prod
CMD ["supervisord", "-c", "/etc/supervisor/conf.d/supervisord.conf"]