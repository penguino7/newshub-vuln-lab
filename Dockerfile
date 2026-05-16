FROM php:8.2-apache

COPY apache-newshub.conf /etc/apache2/conf-available/newshub.conf

RUN apt-get update \
    && apt-get install -y --no-install-recommends libonig-dev \
    && docker-php-ext-install mysqli pdo_mysql mbstring \
    && a2enmod rewrite \
    && a2enconf newshub \
    && rm -rf /var/lib/apt/lists/*

COPY www/ /var/www/html/

RUN chown -R www-data:www-data /var/www/html
