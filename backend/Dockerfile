# Build the Laravel application and its vendor dependencies once.
FROM php:8.5-fpm-alpine AS base

RUN apk add --no-cache \
        git \
        unzip \
        icu-dev \
        libzip-dev \
        oniguruma-dev \
        postgresql-dev \
        $PHPIZE_DEPS \
    && docker-php-ext-install -j1 pdo_pgsql mbstring intl opcache \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && apk del $PHPIZE_DEPS

COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /build
COPY composer.json composer.lock ./
RUN composer install \
        --no-dev \
        --no-interaction \
        --no-progress \
        --prefer-dist \
        --optimize-autoloader \
        --ignore-platform-reqs \
        --no-scripts

COPY . ./
RUN cp .env.example .env \
    && composer dump-autoload --optimize --no-scripts \
    && php artisan event:cache \
    && rm -f .env \
    && mkdir -p \
        storage/framework/cache/data \
        storage/framework/sessions \
        storage/framework/views \
        storage/logs \
        bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache

# Keep the PHP runtime from the build stage. A plain alpine:latest final stage
# would discard PHP and make the API/queue/scheduler commands unusable.
FROM base AS final

WORKDIR /app
RUN cp -a /build/. /app/ \
    && chown -R www-data:www-data /app/storage /app/bootstrap/cache

USER www-data
EXPOSE 9000
CMD ["php-fpm", "-F"]
