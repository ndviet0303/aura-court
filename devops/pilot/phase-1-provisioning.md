# Phase 1 pilot provisioning checklist

This checklist is the deployment record for the five approved pilot organizations. Secret values are injected by the deployment platform and are never committed here.

## Required resources

- Managed PostgreSQL 17 with daily snapshots, point-in-time recovery, a non-owner `aura_app` role, and a separate restore target.
- Managed Redis 7 with TLS, authentication, persistence, separate logical databases for cache and queue, and a connection health alert.
- Stateless API, queue worker, scheduler, and realtime services from the same immutable image.
- PayOS sandbox credentials and webhook secret, with outbound idempotency enabled.
- Structured log shipping and alerts from `devops/monitoring/alerts.yml`.

## Secret and flag review

1. Inject `APP_KEY`, PostgreSQL credentials, Redis credentials/URL, SMTP credentials, and PayOS credentials through Portainer or the secret manager.
2. Set `FEATURE_MARKETPLACE_PAYOUTS=false` until the finance owner records the pilot approval.
3. Set `PILOT_ORGANIZATION_IDS` only to the approved pilot organization IDs.
4. Verify `MINIMUM_PAYOUT_AMOUNT=500000`, `SETTLEMENT_DELAY_DAYS=2`, and structured logging before enabling a payout pilot.

## Backup and monitoring acceptance

1. Run `devops/scripts/backup-restore-rehearsal.sh` against the restore target and retain the dump checksum and RPO/RTO output.
2. Run `php artisan migrate --force` in the API container, then verify `/up`, Redis health, queue depth, scheduler activity, and `realtime:work`.
3. Run `php artisan finance:reconcile --organization=<pilot-id>` and confirm a reconciliation record exists.
4. Record the five pilot IDs, image digest, migration batch, alert routing, and rollback owner in the phase-entry review.
