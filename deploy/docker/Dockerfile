FROM php:8.2-apache

RUN apt-get update \
    && apt-get install -y --no-install-recommends unzip libzip-dev \
    && rm -rf /var/lib/apt/lists/* \
    && docker-php-ext-install pdo_mysql zip \
    && a2enmod rewrite

COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer

COPY deploy/docker/php.ini /usr/local/etc/php/conf.d/zz-custom.ini

WORKDIR /var/www/app

ENV APACHE_DOCUMENT_ROOT=/var/www/app/public
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf /etc/apache2/apache2.conf
