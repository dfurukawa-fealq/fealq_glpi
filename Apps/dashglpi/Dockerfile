FROM composer:2 AS composer

FROM php:8.5.12-apache

COPY --from=composer /usr/bin/composer /usr/bin/composer

RUN apt-get update \
    && apt-get install -y --no-install-recommends libgmp-dev libcurl4-openssl-dev libonig-dev unzip \
    && docker-php-ext-install pdo pdo_mysql mysqli gmp mbstring curl \
    && a2enmod rewrite headers \
    && mkdir -p /var/lib/php/sessions \
    && chown www-data:www-data /var/lib/php/sessions \
    && chmod 700 /var/lib/php/sessions

# Keep DASHGLPI sessions independent from the image's temporary defaults.
# The application sets the cookie name at runtime; this provides a stable,
# writable server-side store for that cookie across Apache workers.
RUN printf '%s\n' \
    'session.save_path=/var/lib/php/sessions' \
    'session.use_strict_mode=1' \
    'session.cookie_httponly=1' \
    'session.cookie_samesite=Lax' \
    > /usr/local/etc/php/conf.d/99-dashglpi-session.ini

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
