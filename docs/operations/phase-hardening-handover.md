# Final hardening and operational handover

Run the following from the release image and retain the JSON output with the phase-entry review:

```bash
php artisan hardening:acceptance
php artisan hardening:drill delayed-webhook
php artisan hardening:drill redis-restart
php artisan hardening:drill queue-lag
php artisan hardening:drill realtime-degradation
php artisan hardening:security-review
php artisan v1:adoption-snapshot --days=30
```

Run `hardening:drill peak-load` against the pilot configuration and run `hardening:drill database-failover` during the managed PostgreSQL failover window. The backup script records a SHA-256 checksum and can restore into an isolated target when `RESTORE_DB` is supplied.

The on-call handover must include the image digest, migration batch, PostgreSQL/Redis owners, PayOS/KYC and payout escalation owners, alert routing, feature-flag rollback commands, RPO/RTO evidence, reconciliation exception procedure, and the `/api/v1` compatibility owner.

`v1:retirement-readiness` remains a hard stop until a separate approved change under `openspec/changes/` contains its proposal and task artifacts plus a recent adoption snapshot, and every V1 booking has a complete, non-drifted legacy mapping (`legacy_mapping_valid`). Bookings that block it are listed in `unmapped_bookings` with their reason.
