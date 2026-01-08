FROM php:8.2-fpm

RUN apt-get update && \
    apt-get install -y git unzip libpq-dev libzip-dev && \
    docker-php-ext-install pdo pdo_pgsql && \
    rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /opt/laravel

ARG LARAVEL_VERSION="10.*"
RUN composer create-project --prefer-dist laravel/laravel=${LARAVEL_VERSION} .

COPY laravel/ /opt/laravel/

RUN chown -R www-data:www-data storage bootstrap/cache

COPY entrypoint.sh /entrypoint.sh
RUN chmod +x /entrypoint.sh

VOLUME ["/var/www/html"]

EXPOSE 9000

ENTRYPOINT ["/entrypoint.sh"]
CMD ["php-fpm"]
