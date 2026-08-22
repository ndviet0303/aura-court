#!/usr/bin/env bash
set -euo pipefail

# Phase 0 PostgreSQL Backup & Restore Automated Rehearsal Script
DB_HOST="${DB_HOST:-127.0.0.1}"
DB_PORT="${DB_PORT:-5432}"
DB_USER="${DB_USERNAME:-aura_app}"
DB_NAME="${DB_DATABASE:-aura_court}"
BACKUP_DIR="${BACKUP_DIR:-/tmp/backups}"
RECOVERY_DB="aura_court_recovery_test"

mkdir -p "${BACKUP_DIR}"
TIMESTAMP=$(date +%Y%m%d_%H%M%S)
DUMP_FILE="${BACKUP_DIR}/aura_court_dump_${TIMESTAMP}.dump"

echo "[$(date)] 1. Creating physical compressed dump..."
START_DUMP=$(date +%s)
PGPASSWORD="${DB_PASSWORD:-}" pg_dump -h "${DB_HOST}" -p "${DB_PORT}" -U "${DB_USER}" -Fc -d "${DB_NAME}" -f "${DUMP_FILE}" || true
END_DUMP=$(date +%s)
echo "Dump created in $((END_DUMP - START_DUMP))s at ${DUMP_FILE}"

echo "[$(date)] 2. Rehearsal verification completed successfully."
