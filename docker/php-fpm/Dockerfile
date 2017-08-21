FROM php:fpm

# Install dependencies
RUN apt-get update && apt-get install -y \
    git \
    libicu-dev \
    unzip

# Install Composer
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer
RUN composer --version

# Set timezone
RUN rm /etc/localtime
RUN ln -s /usr/share/zoneinfo/Europe/Paris /etc/localtime
RUN "date"

# Install PDO
RUN docker-php-ext-install pdo pdo_mysql 

# Install OPCache
RUN docker-php-ext-install opcache 

# Install INTL 
RUN docker-php-ext-install intl

# Install APCu
RUN pecl install apcu
RUN echo "extension=apcu.so" > /usr/local/etc/php/conf.d/apcu.ini

# Install Redis
RUN pecl install redis
RUN echo "extension=redis.so" > /usr/local/etc/php/conf.d/redis.ini

# Add symfony cli shortcut
RUN echo 'alias sf="php bin/console"' >> ~/.bashrc

COPY php.ini /usr/local/etc/php/php.ini

WORKDIR /var/www/symfony
