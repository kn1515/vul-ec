FROM php:8.3-apache

RUN apt-get update \
    && apt-get install -y --no-install-recommends curl libsqlite3-dev \
    && docker-php-ext-install pdo_sqlite \
    && a2enmod rewrite headers \
    && rm -rf /var/lib/apt/lists/*

COPY docker/apache.conf /etc/apache2/sites-available/000-default.conf
COPY src/ /var/www/app/

RUN mkdir -p /var/www/data \
    && chown -R www-data:www-data /var/www/data /var/www/app

EXPOSE 80
