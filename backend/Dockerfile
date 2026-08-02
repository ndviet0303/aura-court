FROM php:8.5-fpm-alpine

RUN apk add --no-cache icu-dev libzip-dev oniguruma-dev postgresql-dev $PHPIZE_DEPS \
    && docker-php-ext-install pdo_pgsql mbstring intl opcache \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && apk del $PHPIZE_DEPS

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
WORKDIR /var/www/html
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader
COPY . .
RUN mkdir -p storage/framework/cache storage/framework/sessions storage/framework/views bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache

USER www-data
CMD ["php-fpm", "-F"]
