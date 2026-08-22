## 1. Phase 0 — Readiness gate

- [x] 1.1 Define the PSP capability test matrix for collection, verified webhooks, refunds, beneficiary/KYC onboarding, payouts, status retrieval, and idempotency.
- [x] 1.2 Execute the selected PSP sandbox tests and record a finance/compliance go-no-go decision with accountable operating owners.
- [x] 1.3 Produce payment, KYC, payout-failure, reconciliation, and incident exception runbooks.
- [x] 1.4 Inventory legacy venue, court, booking, payment, coupon, membership, and notification data; define organization backfill mappings and reconciliation checksums.
- [x] 1.5 Rehearse the expand-contract migration and rollback against a production-like data copy; verify `/api/v1` historical booking responses.
- [x] 1.6 Build repeatable load and concurrency suites for read peak, booking/payment writes, 2,000 sessions, and 100-way court-time contention.
- [x] 1.7 Rehearse PostgreSQL backup/restore and record RPO/RTO evidence.
- [x] 1.8 Define pilot cohort approvals, feature-flag ownership, SLO dashboards, alerts, and phase-entry review templates.

## 2. Phase 1 — Organization and access foundation

- [x] 2.1 Add organization, organization-member, role, and venue/court ownership schema with reversible migrations and legacy backfill support.
- [x] 2.2 Define and enforce the organization role-permission matrix for owner, venue manager, cashier, staff, customer, and platform administrator.
- [x] 2.3 Add organization resolution to authenticated requests, jobs, exports, cache keys, and realtime channel authorization.
- [x] 2.4 Configure a non-owner application database role, transaction-scoped tenant context, and PostgreSQL RLS policies for tenant-owned tables.
- [x] 2.5 Add the audited elevated platform-administration path for finance, KYC, and operational exceptions.
- [x] 2.6 Build organization owner views for profile, venue, court, operating hours, closures, employees, and role-managed access.
- [x] 2.7 Add automated tenant-isolation and role-matrix tests for HTTP endpoints, raw data access, jobs, exports, cache keys, and realtime channels.

## 3. Phase 1 — V2 reservations, pricing, and compatibility

- [x] 3.1 Add v2 reservation, reservation-slot, entitlement, price-rule, price-quote, and idempotency-record schemas with organization ownership.
- [x] 3.2 Implement price-rule precedence, quote expiry, and immutable accepted-quote snapshots.
- [x] 3.3 Implement v2 reservation holds and confirmation using PostgreSQL transactions and active-inventory uniqueness constraints.
- [x] 3.4 Implement durable `Idempotency-Key` handling for every v2 state-changing command, including replay and request-hash conflict responses.
- [x] 3.5 Backfill legacy booking history into compatibility mappings; validate record counts, amounts, ownership, and slot history.
- [x] 3.6 Build the `/api/v1` adapter/projection for supported legacy reads and commands backed by the canonical v2 write path.
- [x] 3.7 Build owner pricing calendar and customer reservation/quote views against `/api/v2`.
- [x] 3.8 Add API, migration, pricing, idempotency, and concurrent-inventory integration tests.

## 4. Phase 1 — Financial and asynchronous foundation

- [ ] 4.1 Define the provider-neutral collection, webhook, refund, beneficiary/KYC, payout, and payout-status contract from the approved Phase 0 PSP evidence.
- [ ] 4.2 Implement provider-event de-duplication, verified webhook processing, outbound provider idempotency, and provider-status recovery.
- [ ] 4.3 Add append-only journal, journal-entry, account, settlement, payout, reserve, and finance-exception schemas with balanced-entry validation.
- [ ] 4.4 Implement reservation-payment, refund, commission, provider-fee, tenant-payable, and reserve journal posting with compensating corrections.
- [ ] 4.5 Implement transactional outbox persistence and idempotent workers for provider work, notifications, and realtime events.
- [ ] 4.6 Migrate cache, queue, locks, and pub/sub to managed Redis; deploy stateless API, worker, scheduler, and realtime processes.
- [ ] 4.7 Implement beneficiary onboarding, settlement snapshots, D+2 eligibility, approval, payout submission/status transitions, and minimum-threshold enforcement in sandbox.
- [ ] 4.8 Implement daily reconciliation, 15-minute mismatch exception creation, finance exception workflow, and tenant-facing payout/report views.
- [ ] 4.9 Add financial invariants, duplicate-webhook, duplicate-payout, outbox, and reconciliation integration tests.
- [ ] 4.10 Add structured logs, tracing, metrics, and alerts for error rate, queue lag, webhook failures, payout mismatch, lock contention, and overbooking attempts.

## 5. Phase 1 — Pilot activation

- [ ] 5.1 Provision the approved pilot infrastructure, secrets, backups, and monitoring configuration.
- [ ] 5.2 Run the Phase 1 migration rehearsal and controlled migration for five approved pilot organizations.
- [ ] 5.3 Execute tenant isolation, financial integrity, load, recovery, security, and `/api/v1` compatibility acceptance suites.
- [ ] 5.4 Conduct sandbox money-flow UAT and rollback rehearsal; record the Phase 2 entry decision.

## 6. Phase 2 — Advanced booking and marketplace

- [ ] 6.1 Add recurring-series, occurrence, monthly-invoice, and delinquency data models linked to reservation inventory.
- [ ] 6.2 Implement weekly/monthly series creation, 12-month/104-occurrence limits, full-horizon confirmation, monthly invoicing, and future-only release on delinquency.
- [ ] 6.3 Add split-group and split-share models; implement exact share validation, payment references, expiry, host shortfall coverage, and payer-level refund allocation.
- [ ] 6.4 Add marketplace listing and entitlement-transfer models with eligibility, original-price ceiling, two-hour cutoff, and one-sale concurrency control.
- [ ] 6.5 Implement buyer collection, seller refund less marketplace fee, compensation, and listing recovery for transfer failures.
- [ ] 6.6 Build customer flows for series, invoice payment, split status, listing checkout, and refund/transfer notifications.
- [ ] 6.7 Add recurrence, split-webhook race, listing-checkout race, payer-allocation, and marketplace compensation end-to-end tests.
- [ ] 6.8 Run the approved Phase 2 pilot, reconciliation review, and rollback rehearsal before expanding access.

## 7. Phase 3 — Social, matchmaking, and tournaments

- [ ] 7.1 Add player-profile, availability, approximate-location, match-post, invitation, and participation models with tenant/privacy authorization.
- [ ] 7.2 Implement public and invite-only match creation, discovery filters, join/invite flows, and protected-post access controls.
- [ ] 7.3 Add qualifying activity, reputation, dispute, moderation, and immutable audit records.
- [ ] 7.4 Implement the rolling-20 reputation calculation, verified attendance/no-show/late-cancel events, and dispute resolution workflow.
- [ ] 7.5 Add tournament, participant, seed, fixture, standing, and versioned tournament-event models.
- [ ] 7.6 Implement deterministic single-elimination and round-robin generation, check-in, score entry, optimistic version conflicts, and standings.
- [ ] 7.7 Publish organization-authorized versioned realtime tournament updates and provide resynchronization endpoints and degraded polling fallback.
- [ ] 7.8 Build player, match, moderation, tournament-admin, bracket, and scoreboard experiences.
- [ ] 7.9 Add authorization, reputation, bracket determinism, score-conflict, event-latency, and realtime recovery tests; run a regional pilot.

## 8. Phase 4 — POS, membership, loyalty, and wallet

- [ ] 8.1 Add tenant POS catalog, SKU/barcode, stock-movement, cart/order, payment, and reservation-attachment models.
- [ ] 8.2 Implement connected POS order completion for cash, QR, and wallet payments with stock and financial journal effects.
- [ ] 8.3 Implement local POS draft storage that blocks payment and inventory finalization while offline.
- [ ] 8.4 Add shift open/close, expected-cash calculation, variance, reconciliation, and auditable exception handling.
- [ ] 8.5 Add versioned tenant membership-plan, entitlement, loyalty-rule, and point-ledger models.
- [ ] 8.6 Implement membership benefit application plus loyalty award/redemption with source-linked audit records.
- [ ] 8.7 Add tenant wallet accounts and liability journals; enforce sufficient credit, no negative balance, and no withdrawal, transfer, or cross-tenant spend.
- [ ] 8.8 Build POS, cashier shift, membership, loyalty, wallet, and tenant-reporting views.
- [ ] 8.9 Add POS reconciliation, offline, stock, membership-version, loyalty, wallet-liability, and isolation end-to-end tests.

## 9. Final hardening and rollout

- [ ] 9.1 Run full API authorization, tenant-isolation, financial-invariant, concurrency, end-to-end, and accessibility test suites across enabled phases.
- [ ] 9.2 Run peak-load, delayed/replayed-webhook, Redis-restart, database-failover, queue-lag, and realtime-degradation drills.
- [ ] 9.3 Perform security review of secrets, KYC/bank-data handling, RLS, exports, elevated access, rate limits, and audit trails.
- [ ] 9.4 Rehearse quarterly backup restore, document operational handover, and approve progressive tenant rollout.
- [ ] 9.5 Collect v1 adoption evidence and create a separate approved change before announcing or executing v1 retirement.
