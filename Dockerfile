FROM php:7.2-fpm

ENV TERM="xterm" \
    DEBIAN_FRONTEND="noninteractive" \
    COMPOSER_ALLOW_SUPERUSER=1

EXPOSE 80
WORKDIR /app

# Install dependencies
RUN apt-get update -q && apt-get install -qy \
    curl \
    git \
    gnupg \
    libfreetype6-dev \
    libicu-dev \
    libjpeg62-turbo-dev \
    libpng-dev \
    nginx \
    python \
    supervisor \
    unzip \
    wget

# Install Composer
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer
RUN composer global require hirak/prestissimo --no-plugins --no-scripts

# Set timezone
RUN rm /etc/localtime
RUN ln -s /usr/share/zoneinfo/Europe/Paris /etc/localtime
RUN "date"

# Install PDO
RUN docker-php-ext-install -j$(nproc) pdo pdo_mysql

# Install OPCache
RUN docker-php-ext-install -j$(nproc) opcache

# Install INTL 
RUN docker-php-ext-install -j$(nproc) intl

# Install Redis
RUN pecl install redis
RUN docker-php-ext-enable redis

# Install BCMatch (RabbitMQ)
RUN docker-php-ext-install -j$(nproc) bcmath

# Install GD + EXIF
RUN docker-php-ext-configure gd --with-freetype-dir=/usr/include/ --with-jpeg-dir=/usr/include/
RUN docker-php-ext-install -j$(nproc) gd exif

#Zip (PhpUnit)
RUN docker-php-ext-install -j$(nproc) zip

# Sockets
RUN docker-php-ext-install -j$(nproc) sockets

COPY docker/prod/php.ini /usr/local/etc/php/php.ini

# NPM, Yarn and Grunt
RUN curl -sL https://deb.nodesource.com/setup_8.x | bash -
RUN apt-get install -y nodejs

RUN curl -sS https://dl.yarnpkg.com/debian/pubkey.gpg | apt-key add -
RUN echo "deb https://dl.yarnpkg.com/debian/ stable main" | tee /etc/apt/sources.list.d/yarn.list
RUN apt-get update && apt-get install yarn
RUN yarn global add grunt-cli

# Install nginx
COPY docker/prod/nginx.conf /etc/nginx/

# PHP FPM
COPY docker/prod/pool.conf /usr/local/etc/php-fpm.d/www.conf

COPY docker/prod/supervisord.conf /etc/supervisor/conf.d/supervisord.conf

COPY . /app
COPY docker/prod/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh
ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]

RUN mkdir -p var public/media public/uploads && \
    APP_ENV=prod composer install --optimize-autoloader --no-interaction --no-ansi --no-dev && \
    APP_ENV=prod bin/console cache:clear --no-warmup && \
    APP_ENV=prod bin/console cache:warmup && \
    chown -R www-data:www-data var public/media public/uploads && \
    yarn install && \
    grunt

# Reduce container size
RUN apt-get clean && rm -rf /var/lib/apt/lists/* /tmp/* /var/tmp/*
RUN rm -rf .git node_modules assets docker