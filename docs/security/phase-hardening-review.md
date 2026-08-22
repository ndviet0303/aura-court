# Phase hardening security review

- Secrets are injected through deployment variables; committed env files contain placeholders only.
- KYC and bank-account writes are provider-verified and tenant-scoped; platform access uses `ElevatedAccessService` and immutable audit logs.
- PostgreSQL RLS policies, non-owner runtime role, transaction tenant context, tenant-scoped cache/jobs/exports, and realtime authorization are reviewed together.
- Export/report routes remain behind organization authorization and audit logging.
- v2 state-changing routes require `Idempotency-Key`; public webhook routes require provider signature verification and replay de-duplication.
- Rate-limit configuration and alert thresholds are reviewed in the release environment, including webhook, auth, queue, lock-contention, and overbooking alerts.

Run `php artisan hardening:security-review` and attach its output to the rollout record. Any `missing` result is a no-go.
