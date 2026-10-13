FROM php:8.2-apache

RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        libpng-dev \
        libjpeg62-turbo-dev \
        libfreetype6-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j"$(nproc)" gd mysqli exif \
    && a2enmod rewrite headers \
    && rm -rf /var/lib/apt/lists/*

COPY apache/lab.conf /etc/apache2/sites-available/000-default.conf
COPY php/lab.ini /usr/local/etc/php/conf.d/lab.ini
COPY public /var/www/html/public
COPY src /var/www/html/src
COPY secrets /var/www/secrets

RUN mkdir -p /var/www/storage/uploads /var/www/storage/avatars \
    && chown -R www-data:www-data /var/www/storage /var/www/html /var/www/secrets \
    && chmod 750 /var/www/secrets /var/www/storage

WORKDIR /var/www/html
EXPOSE 80
