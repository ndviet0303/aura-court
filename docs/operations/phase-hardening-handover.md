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

The on-call handover must include the image digest, migration batch, PostgreSQL/Redis owners, PayOS/KYC and payout escalation owners, alert routing, feature-flag rollback commands, RPO/RTO evidence, and reconciliation exception procedure.

V1 booking is retired (`openspec/changes/retire-v1-booking`, approved by the project owner on 2026-10-13). The owner skipped the 30-day adoption gate, so `v1:retirement-readiness` was not a release gate. It still audits legacy mappings: every V1 booking needs a complete, non-drifted mapping (`legacy_mapping_valid`), and bookings without one are listed in `unmapped_bookings` with their reason. `bookings` and `booking_slots` are frozen history; read them through their mapped reservations.
