ARG API_IMAGE=api:latest
FROM ${API_IMAGE} AS api
FROM registry.gitlab.com/bantool/devops/frankenphp:latest AS runner

# The API image stores the application artifact and its env template at /app.
# Portainer injects important production variables at runtime.
COPY --from=api /app /app/booking-api
COPY devops/configs/booking.prod.Caddyfile /etc/frankenphp/Caddyfile
COPY devops/configs/php.ini-production "$PHP_INI_DIR/php.ini"

# Runtime defaults supplied by the deployment pipeline; Portainer can override them.
ARG APP_REVISION=unknown
ARG FEATURE_DIRECT_OWNER_PAYMENTS=false
ARG FEATURE_TENANT_POS_WALLET=false
ARG FEATURE_MARKETPLACE_SPLIT_PAYMENTS=false
ARG FEATURE_MARKETPLACE_SLOT_RESALE=false
ARG PILOT_ORGANIZATION_IDS=
ENV FEATURE_DIRECT_OWNER_PAYMENTS=${FEATURE_DIRECT_OWNER_PAYMENTS} \
    FEATURE_TENANT_POS_WALLET=${FEATURE_TENANT_POS_WALLET} \
    FEATURE_MARKETPLACE_SPLIT_PAYMENTS=${FEATURE_MARKETPLACE_SPLIT_PAYMENTS} \
    FEATURE_MARKETPLACE_SLOT_RESALE=${FEATURE_MARKETPLACE_SLOT_RESALE} \
    FEATURE_MARKETPLACE_PAYOUTS=false \
    PILOT_ORGANIZATION_IDS=${PILOT_ORGANIZATION_IDS}
RUN printf '{"revision":"%s"}\n' "$APP_REVISION" > /app/booking-api/public/build-info.json

ENV APP_ENV=production \
    APP_DEBUG=false \
    APP_URL=https://api.example.com \
    FRONTEND_URL=https://frontend.example.com \
    SEPAY_ENABLED=true \
    SEPAY_BASE_URL=https://my.sepay.vn

EXPOSE 8080

RUN chown -R www-data:www-data /app/booking-api/storage /app/booking-api/bootstrap/cache
