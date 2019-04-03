FROM php:7.2-fpm

ENV COMPOSER_ALLOW_SUPERUSER 1

# Install dependencies
RUN apt-get update && apt-get install -y \
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
RUN composer --version
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

# Install Blackfire probe
RUN version=$(php -r "echo PHP_MAJOR_VERSION.PHP_MINOR_VERSION;") \
    && curl -A "Docker" -o /tmp/blackfire-probe.tar.gz -D - -L -s https://blackfire.io/api/v1/releases/probe/php/linux/amd64/$version \
    && tar zxpf /tmp/blackfire-probe.tar.gz -C /tmp \
    && mv /tmp/blackfire-*.so $(php -r "echo ini_get('extension_dir');")/blackfire.so \
    && printf "extension=blackfire.so\nblackfire.agent_socket=tcp://blackfire:8707\n" > $PHP_INI_DIR/conf.d/blackfire.ini

# Blackfire for CLI
RUN mkdir -p /tmp/blackfire \
    && curl -A "Docker" -L https://blackfire.io/api/v1/releases/client/linux_static/amd64 | tar zxp -C /tmp/blackfire \
    && mv /tmp/blackfire/blackfire /usr/bin/blackfire \
    && rm -Rf /tmp/blackfire

# Install GD + EXIF
RUN docker-php-ext-configure gd --with-freetype-dir=/usr/include/ --with-jpeg-dir=/usr/include/
RUN docker-php-ext-install -j$(nproc) gd exif

#Zip (PhpUnit)
RUN docker-php-ext-install -j$(nproc) zip

# Sockets
RUN docker-php-ext-install -j$(nproc) sockets

COPY php.ini /usr/local/etc/php/php.ini

# NPM, Yarn and Grunt
RUN curl -sL https://deb.nodesource.com/setup_8.x | bash -
RUN apt-get install -y nodejs

RUN curl -sS https://dl.yarnpkg.com/debian/pubkey.gpg | apt-key add -
RUN echo "deb https://dl.yarnpkg.com/debian/ stable main" | tee /etc/apt/sources.list.d/yarn.list
RUN apt-get update && apt-get install yarn
RUN yarn global add grunt-cli

# Add some shortcuts
RUN echo 'alias ls="ls --color=auto"' >> ~/.bashrc
RUN echo 'alias ll="ls -alF"' >> ~/.bashrc

# Install nginx
COPY nginx.conf /etc/nginx/

# RUN usermod -u 1000 www-data

COPY supervisord.conf /etc/supervisor/conf.d/supervisord.conf

EXPOSE 80
WORKDIR /app
CMD ["supervisord", "-c", "/etc/supervisor/conf.d/supervisord.conf"]