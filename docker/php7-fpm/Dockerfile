# See https://github.com/docker-library/php/blob/master/7.1/fpm/Dockerfile
FROM php:7.1-fpm
ARG TIMEZONE

RUN apt-get update && apt-get install -y \
    openssl \
    git \
    unzip \
    zlib1g-dev \
    libicu-dev \
    libmagickwand-dev \
    && docker-php-ext-configure intl \
    && docker-php-ext-install intl \
    && pecl install imagick \
    && docker-php-ext-enable imagick

#Install ffmpeg
RUN apt-get install -y ffmpeg

RUN usermod -u 1000 www-data

# Supervisor
RUN apt-get install -y supervisor
COPY supervisord.conf /etc/supervisor/conf.d/captum.conf
RUN /usr/bin/supervisord -c /etc/supervisor/supervisord.conf
RUN service supervisor start
RUN supervisorctl start queue schedule

# Install NodeJS
RUN apt-get install -y gnupg
RUN curl -sL https://deb.nodesource.com/setup_13.x | bash
RUN apt-get install -y nodejs libxrender1 libfontconfig

# Install Bower
RUN npm install -g bower

# Install Composer
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer
RUN composer --version

# Set timezone
RUN ln -snf /usr/share/zoneinfo/${TIMEZONE} /etc/localtime && echo ${TIMEZONE} > /etc/timezone
RUN printf '[PHP]\ndate.timezone = "%s"\n', ${TIMEZONE} > /usr/local/etc/php/conf.d/tzone.ini
RUN "date"

# Type docker-php-ext-install to see available extensions
RUN docker-php-ext-install pdo pdo_mysql pcntl zip
#RUN apt-get update && apt-get install -y libmagickwand-dev libmagickcore-dev

#RUN yes | pecl install imagick && docker-php-ext-enable imagick
# Install xDebug
RUN yes | pecl install xdebug \
    && touch "/usr/local/etc/php/conf.d/xdebug.ini" \
    && echo "zend_extension=$(find /usr/local/lib/php/extensions/ -name xdebug.so)" > /usr/local/etc/php/conf.d/xdebug.ini \
    && echo "xdebug.remote_enable=1" >> /usr/local/etc/php/conf.d/xdebug.ini \
    && echo "xdebug.remote_host=10.254.254.254" >> /usr/local/etc/php/conf.d/xdebug.ini \
    && echo "xdebug.idekey=\"PHPSTORM\"" >> /usr/local/etc/php/conf.d/xdebug.ini \
    && echo "xdebug.remote_port=9001" >> /usr/local/etc/php/conf.d/xdebug.ini \
    && echo "xdebug.remote_connect_back=0" >> /usr/local/etc/php/conf.d/xdebug.ini

RUN echo 'alias sf="php app/console"' >> ~/.bashrc
RUN echo 'alias sf3="php bin/console"' >> ~/.bashrc

WORKDIR /var/www/symfony
