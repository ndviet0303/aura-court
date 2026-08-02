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

# Runtime configuration is injected by Portainer/deployment. Do not ship the
# build-time local .env into the production image where it can override it.
RUN rm -f .env

# Stage 2: Final
FROM alpine:latest AS final

# Copy the application from the composer stage
COPY --from=base /build /app
RUN mkdir -p \
        /app/storage/framework/cache/data \
        /app/storage/framework/sessions \
        /app/storage/framework/views \
        /app/storage/logs

# Set the working directory
WORKDIR /app

# Expose port or other settings as needed
# EXPOSE 8000
