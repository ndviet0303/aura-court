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

command -v pg_dump >/dev/null || { echo "pg_dump is required" >&2; exit 1; }
command -v sha256sum >/dev/null || { echo "sha256sum is required" >&2; exit 1; }

echo "[$(date)] 1. Creating compressed dump..."
START_DUMP=$(date +%s)
PGPASSWORD="${DB_PASSWORD:-}" pg_dump -h "${DB_HOST}" -p "${DB_PORT}" -U "${DB_USER}" -Fc -d "${DB_NAME}" -f "${DUMP_FILE}"
END_DUMP=$(date +%s)
echo "Dump created in $((END_DUMP - START_DUMP))s at ${DUMP_FILE}"
test -s "${DUMP_FILE}"
sha256sum "${DUMP_FILE}" | tee "${DUMP_FILE}.sha256"

if [[ -n "${RESTORE_DB:-}" ]]; then
  command -v pg_restore >/dev/null || { echo "pg_restore is required for restore rehearsal" >&2; exit 1; }
  echo "[$(date)] 2. Restoring into the isolated target ${RESTORE_DB}..."
  START_RESTORE=$(date +%s)
  PGPASSWORD="${RESTORE_DB_PASSWORD:-${DB_PASSWORD:-}}" pg_restore --exit-on-error --no-owner -h "${RESTORE_DB_HOST:-${DB_HOST}}" -p "${RESTORE_DB_PORT:-${DB_PORT}}" -U "${RESTORE_DB_USER:-${DB_USER}}" -d "${RESTORE_DB}" "${DUMP_FILE}"
  END_RESTORE=$(date +%s)
  echo "Restore completed in $((END_RESTORE - START_RESTORE))s"
else
  echo "[$(date)] 2. Restore target not supplied; dump checksum is the rehearsal evidence."
fi

echo "[$(date)] Rehearsal verification completed successfully."
