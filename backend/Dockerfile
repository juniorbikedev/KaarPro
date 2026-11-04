# Image de l'API KaarPro (Laravel sur PHP-FPM)

# Étape 1 : dépendances PHP, sans les outils de développement
FROM composer:2 AS dependances
WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist --no-interaction
COPY . .
RUN composer dump-autoload --optimize --classmap-authoritative --no-dev

# Étape 2 : image d'exécution
FROM php:8.3-fpm-alpine
RUN apk add --no-cache libpq \
    && apk add --no-cache --virtual .compilation postgresql-dev \
    && docker-php-ext-install pdo_pgsql opcache \
    && apk del .compilation
COPY docker/php.ini /usr/local/etc/php/conf.d/kaarpro.ini

WORKDIR /var/www/html
COPY --from=dependances --chown=www-data:www-data /app .
RUN php artisan package:discover --ansi \
    && mkdir -p storage/app/private storage/framework/cache/data storage/framework/sessions storage/framework/views storage/logs \
    && chown -R www-data:www-data storage bootstrap/cache \
    && chmod +x docker/entrypoint.sh

USER www-data
EXPOSE 9000
ENTRYPOINT ["docker/entrypoint.sh"]
CMD ["php-fpm"]
