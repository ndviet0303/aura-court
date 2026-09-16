# Stage 1: Composer
FROM php:8.5-alpine AS base

# Install necessary packages
# Install necessary packages
RUN apk update && apk add --no-cache \
    git \
    unzip

# RUN adduser -D -h /home/drnxloc drnxloc

# Copy composer binary from the official composer image
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

COPY . /build/

# Set the working directory
WORKDIR /build

RUN cp .env.example .env

RUN composer install --no-dev --optimize-autoloader --ignore-platform-reqs

# Cache events only. Runtime configuration and closure routes must resolve from
# the final /app/ptb-api path and the environment supplied by Portainer.
RUN php artisan event:cache

# Portainer environment variables override the defaults from this template at
# runtime.

# Stage 2: Final
FROM base AS final

# Copy the application from the composer stage
COPY --from=base /build /app
RUN mkdir -p \
        /app/storage/framework/cache/data \
        /app/storage/framework/sessions \
        /app/storage/framework/views \
        /app/storage/logs \
        /app/bootstrap/cache \
    && chown -R www-data:www-data /app/storage /app/bootstrap/cache 2>/dev/null || true

# Set the working directory
WORKDIR /app
