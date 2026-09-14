FROM composer:2 AS composer

FROM php:8.3-apache

COPY --from=composer /usr/bin/composer /usr/bin/composer

RUN apt-get update \
    && apt-get install -y --no-install-recommends libgmp-dev libcurl4-openssl-dev libonig-dev unzip \
    && docker-php-ext-install pdo pdo_mysql mysqli gmp mbstring curl \
    && a2enmod rewrite headers

WORKDIR /var/www/html

COPY composer.json composer.lock /tmp/dashglpi-deps/
RUN cd /tmp/dashglpi-deps \
    && composer install --no-dev --prefer-dist --no-interaction --no-progress \
    && mkdir -p /var/www/html/php-vendor \
    && cp -a vendor/. /var/www/html/php-vendor/

COPY . /var/www/html/

RUN ln -sfn /var/www/html/public/css /var/www/html/css \
    && ln -sfn /var/www/html/public/js /var/www/html/js \
    && ln -sfn /var/www/html/public/vendor /var/www/html/vendor \
    && chown -R www-data:www-data /var/www/html

EXPOSE 80
