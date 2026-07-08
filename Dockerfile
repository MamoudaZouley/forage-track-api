FROM php:8.3-fpm-alpine

RUN apk add --no-cache nginx supervisor git curl libpng-dev libzip-dev postgresql-dev zip unzip \
    && docker-php-ext-install pdo pdo_pgsql zip gd

WORKDIR /var/www

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
COPY . .

RUN composer install --optimize-autoloader --no-dev --no-interaction

COPY docker/nginx.conf /etc/nginx/nginx.conf
COPY docker/supervisord.conf /etc/supervisord.conf
COPY docker/entrypoint.sh /entrypoint.sh
RUN chmod +x /entrypoint.sh

RUN chown -R www-data:www-data /var/www/storage /var/www/bootstrap/cache

EXPOSE 10000
ENTRYPOINT ["/entrypoint.sh"]