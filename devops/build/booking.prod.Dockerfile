ARG API_IMAGE=api:latest
FROM ${API_IMAGE} AS api
FROM registry.gitlab.com/bantool/devops/frankenphp:latest AS runner

# The API image stores the application artifact and its env template at /app.
# Portainer injects important production variables at runtime.
COPY --from=api /app /app/booking-api
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
