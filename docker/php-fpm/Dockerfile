FROM php:fpm

# Install dependencies
RUN apt-get update && apt-get install -y \
    git \
    libicu-dev \
    libpng-dev \
#    nodejs \
#    npm \
    python \
    unzip \
    wget

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

# Install BCMatch (RabbitMQ)
RUN docker-php-ext-install bcmath

# Install Blackfire probe
RUN version=$(php -r "echo PHP_MAJOR_VERSION.PHP_MINOR_VERSION;") \
    && curl -A "Docker" -o /tmp/blackfire-probe.tar.gz -D - -L -s https://blackfire.io/api/v1/releases/probe/php/linux/amd64/$version \
    && tar zxpf /tmp/blackfire-probe.tar.gz -C /tmp \
    && mv /tmp/blackfire-*.so $(php -r "echo ini_get('extension_dir');")/blackfire.so \
    && printf "extension=blackfire.so\nblackfire.agent_socket=tcp://blackfire:8707\n" > $PHP_INI_DIR/conf.d/blackfire.ini

# Install GD + EXIF
RUN docker-php-ext-install gd exif

COPY php.ini /usr/local/etc/php/php.ini

# NPM and GRUNT
RUN curl -sL https://deb.nodesource.com/setup_6.x | bash -
RUN apt-get install -y nodejs
#RUN ln -s /usr/bin/nodejs /usr/bin/node
RUN npm install -g bower
RUN npm install -g grunt-cli
#RUN npm install grunt --save-dev

#PHPUnit
RUN wget https://phar.phpunit.de/phpunit-6.4.phar
RUN chmod +x phpunit-6.4.phar
RUN sudo mv phpunit-6.4.phar /usr/local/bin/phpunit


# Permission fix
RUN chmod 777 /var/www
RUN usermod -u 1000 www-data

# Set www-data as current user (for using composer)
#USER www-data

# Add some shortcuts
#RUN echo 'alias php="su -l www-data -s /bin/bash -c php"' >> ~/.bashrc
RUN echo 'alias sf="php bin/console"' >> ~/.bashrc
RUN echo 'alias ls="ls --color=auto"' >> ~/.bashrc
RUN echo 'alias ll="ls -alF"' >> ~/.bashrc

WORKDIR /var/www/symfony
