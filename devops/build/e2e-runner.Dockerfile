# Dockerfile for Aura Court Isolated E2E CI Runner
# Based on Playwright Ubuntu Noble image with complete PHP 8.5 CLI, Composer, and PostgreSQL 16
FROM mcr.microsoft.com/playwright:v1.62.1-noble

ENV DEBIAN_FRONTEND=noninteractive

# Install PHP, Composer, and PostgreSQL server/client
RUN apt-get update && apt-get install -y --no-install-recommends software-properties-common ca-certificates \
    && add-apt-repository --yes ppa:ondrej/php \
    && apt-get update && apt-get install -y --no-install-recommends \
    php8.5-cli \
    php8.5-pgsql \
    php8.5-mbstring \
    php8.5-bcmath \
    php8.5-intl \
    php8.5-xml \
    php8.5-curl \
    php8.5-zip \
    composer \
    postgresql \
    postgresql-contrib \
    sudo \
    curl \
    git \
    unzip \
    && rm -rf /var/lib/apt/lists/*

# Configure postgres user for loopback passwordless access
USER postgres
RUN /etc/init.d/postgresql start && \
    psql --command "CREATE USER root WITH SUPERUSER PASSWORD 'postgres';" && \
    /etc/init.d/postgresql stop

USER root

# Default working directory
WORKDIR /work
