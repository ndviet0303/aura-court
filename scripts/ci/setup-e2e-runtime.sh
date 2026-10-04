#!/usr/bin/env bash
set -euo pipefail

# CI Runner E2E Runtime Provisioner (Task 6.2 / R02)
# Ensures complete PHP 8.5+, Composer, and PostgreSQL 16+ runtime are available for isolated E2E.

echo "=== [CI E2E Runtime Setup] Checking environment prerequisites ==="

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
BACKEND_DIR="${ROOT_DIR}/backend"
FRONTEND_DIR="${ROOT_DIR}/frontend"

# 1. Install missing Linux runtime packages if running inside container
if [[ -f /etc/debian_version ]] || [[ -f /etc/lsb-release ]]; then
  NEED_INSTALL=0
  if ! command -v php >/dev/null 2>&1 || ! php -r 'exit(version_compare(PHP_VERSION, "8.4.1", ">=") ? 0 : 1);'; then NEED_INSTALL=1; fi
  if ! command -v composer >/dev/null 2>&1; then NEED_INSTALL=1; fi
  if ! command -v psql >/dev/null 2>&1; then NEED_INSTALL=1; fi
  if ! command -v createdb >/dev/null 2>&1; then NEED_INSTALL=1; fi

  if [[ ${NEED_INSTALL} -eq 1 ]]; then
    echo "[CI E2E Runtime Setup] Provisioning PHP, Composer, and PostgreSQL inside Linux container..."
    export DEBIAN_FRONTEND=noninteractive
    apt-get update -qq
    apt-get install -y -qq software-properties-common ca-certificates >/dev/null
    add-apt-repository --yes ppa:ondrej/php >/dev/null
    apt-get update -qq
    apt-get install -y -qq \
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
      ca-certificates \
      git \
      unzip >/dev/null
  fi

  # Each CI job owns its temporary loopback-only cluster; never alter a shared cluster.
  if [[ "${CI:-}" == "true" ]] && [[ "$(id -u)" -eq 0 ]]; then
    CI_E2E_PG_DIR="$(mktemp -d /tmp/booking-ci-pg.XXXXXX)"
    chown postgres:postgres "${CI_E2E_PG_DIR}"
    CI_E2E_PG_BIN="$(pg_config --bindir)"
    su postgres -s /bin/bash -c "'${CI_E2E_PG_BIN}/initdb' -D '${CI_E2E_PG_DIR}' --username='${USER:-root}' --auth-local=trust --auth-host=trust" >/dev/null
    su postgres -s /bin/bash -c "'${CI_E2E_PG_BIN}/pg_ctl' -D '${CI_E2E_PG_DIR}' -l '${CI_E2E_PG_DIR}/server.log' -o '-h 127.0.0.1 -p 5432' -w start" >/dev/null
  fi
fi

# 2. Verify CLI executables
command -v php >/dev/null 2>&1 || { echo "FATAL: php executable not found in PATH"; exit 1; }
command -v composer >/dev/null 2>&1 || { echo "FATAL: composer executable not found in PATH"; exit 1; }
command -v psql >/dev/null 2>&1 || { echo "FATAL: psql executable not found in PATH"; exit 1; }
command -v createdb >/dev/null 2>&1 || { echo "FATAL: createdb executable not found in PATH"; exit 1; }
command -v node >/dev/null 2>&1 || { echo "FATAL: node executable not found in PATH"; exit 1; }
command -v npm >/dev/null 2>&1 || { echo "FATAL: npm executable not found in PATH"; exit 1; }

echo "[CI E2E Runtime Setup] Found: $(php -v | head -n1), $(node -v), $(psql --version)"

# 3. Bootstrap backend dependencies
if [[ "${CI:-}" == "true" ]] || [[ ! -d "${BACKEND_DIR}/vendor" ]]; then
  echo "[CI E2E Runtime Setup] Installing backend composer dependencies..."
  (cd "${BACKEND_DIR}" && APP_ENV=testing BROADCAST_CONNECTION=null composer install --no-interaction --prefer-dist --optimize-autoloader --no-progress)
fi

# CI checkouts have no application key. Helpers and the HTTP server share this testing key.
if [[ "${CI:-}" == "true" ]] && [[ ! -f "${BACKEND_DIR}/.env.testing" ]]; then
  cp "${BACKEND_DIR}/.env.example" "${BACKEND_DIR}/.env.testing"
  (cd "${BACKEND_DIR}" && APP_ENV=testing BROADCAST_CONNECTION=null php artisan key:generate --env=testing --force --no-interaction >/dev/null)
fi

# 4. Bootstrap frontend dependencies
if [[ ! -d "${FRONTEND_DIR}/node_modules" ]]; then
  echo "[CI E2E Runtime Setup] Installing frontend npm dependencies..."
  (cd "${FRONTEND_DIR}" && npm ci --prefer-offline)
fi

echo "=== [CI E2E Runtime Setup] Prerequisites successfully verified ==="
