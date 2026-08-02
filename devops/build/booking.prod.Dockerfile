ARG API_IMAGE=api:latest
FROM ${API_IMAGE} AS api
FROM registry.gitlab.com/bantool/devops/frankenphp:latest AS runner

# The API image stores the application artifact at /app.
# Runtime configuration and secrets must be injected by the deployment system;
# do not bake the local env template into a production image.
COPY --from=api /app /app/booking-api
# The API image is built with a temporary .env for Composer/Artisan build steps;
# runtime configuration must come from Portainer instead.
RUN rm -f /app/booking-api/.env
COPY devops/configs/booking.prod.Caddyfile /etc/frankenphp/Caddyfile
COPY devops/configs/php.ini-production "$PHP_INI_DIR/php.ini"

ENV APP_ENV=production \
    APP_DEBUG=false \
    APP_URL=https://api.example.com \
    FRONTEND_URL=https://frontend.example.com \
    SEPAY_ENABLED=true \
    SEPAY_BASE_URL=https://my.sepay.vn

EXPOSE 8080

RUN chown -R www-data:www-data /app/booking-api/storage /app/booking-api/bootstrap/cache
