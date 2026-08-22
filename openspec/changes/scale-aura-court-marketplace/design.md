## Context

The current Laravel/Vue application serves booking endpoints only under `/api/v1`. It has a global `admin` role, one `Venue` relation per court, synchronous application notifications, database-backed cache/queues, an inbound SePay collection webhook, and a PostgreSQL partial unique index that prevents two active booking slots for the same court and start time.

This change introduces cross-cutting tenancy, financial, migration, operational, and realtime concerns. See `proposal.md` for motivation and the delta specifications for required behaviour.

## Goals / Non-Goals

**Goals:**

- Establish organization tenancy that remains safe through HTTP requests, jobs, exports, caches, and realtime delivery.
- Make `/api/v2` the single canonical write model while retaining a controlled `/api/v1` compatibility surface.
- Preserve database-backed inventory integrity while adding durable idempotency and externally safe financial operations.
- Make all money movement derivable from append-only journals and all external effects after-commit and retryable.
- De-risk delivery through Phase 0 evidence, staged migrations, feature flags, and pilot cohorts.

**Non-Goals:**

- Replacing PostgreSQL inventory constraints with Redis locks, or using Redis as a financial source of truth.
- Dropping `/api/v1`, deleting legacy booking history, or performing an irreversible big-bang data rewrite.
- Supporting native apps, AI pricing, double-elimination, withdrawable wallets, cross-tenant wallets, or a Kubernetes migration in this change.
- Launching finance-dependent features before the PSP/compliance gate is approved.

## Decisions

### 1. Shared PostgreSQL schema with database-enforced tenant context

Tenant-owned aggregates will carry `organization_id`; this includes direct business records and operational records whose access can otherwise bypass the venue relationship (for example, financial, notification, export, and audit records). `OrganizationMember` owns role assignments; a global user identity may have memberships in several organizations. `Venue` and `Court` become organization-owned.

PostgreSQL row-level security is defense in depth. Each request and worker transaction establishes an organization context; policies enforce it for tenant tables. The normal application database role must not own the tables or bypass RLS. The narrowly scoped platform-admin path records elevated purpose and outcome in an immutable audit trail. Cache keys, queued payloads, event channels, and exports use the same explicit organization identity.

This supports approximately 200 tenants without operational per-tenant database overhead. It is preferred over application-only query scopes, which cannot protect raw queries, jobs, exports, or accidental omissions. A database-per-tenant model is rejected because it materially increases provisioning, migration, reporting, and support complexity at the intended scale.

### 2. Expand-contract migration with a v1 adapter and canonical v2 writes

The migration is additive first: introduce organization/membership data, v2 aggregates (`Reservation`, `ReservationSlot`, price quote, entitlement, and related financial records), and compatibility mappings. Legacy venues are backfilled into organizations; legacy bookings are mapped with counts/checksums and remain historically immutable.

New v2 commands write only the v2 model. Once the compatibility adapter is enabled for a pilot, a v1 create/change request is translated to the same v2 command and its response is shaped as the documented v1 representation. This avoids permanently dual-writing two mutable booking systems. Reads retain the old source or compatibility projection until every supported v1 path is verified. Legacy schema removal is deferred beyond the compatibility window.

A v1 adapter is preferred over a long-lived dual-write design because dual writes would create competing inventory and financial truth. A hard API cutover is rejected because existing clients and historical records must remain available.

### 3. PostgreSQL remains inventory authority; quotes and commands are durable

Pricing rules resolve in the required precedence order and produce a versioned quote snapshot before checkout. Reservation slots represent active inventory. PostgreSQL transactions and a partial unique constraint enforce the one-active-entitlement rule; contention returns a conflict. Redis may reduce repeated reads or coordinate short-lived non-authoritative work but never decides availability.

Every v2 state-changing command records an idempotency key, authenticated actor, normalized request hash, stored result, and expiry policy. A retry of the same request returns the original outcome; a changed request reusing that key is rejected.

This retains the strongest property of the existing system and is preferred over an availability-only Redis lock, which can expire or split during a payment workflow.

### 4. Append-only financial core with a provider boundary and transactional outbox

Financial commands first create durable intents and balanced journal entries as appropriate to their committed state. Journals are append-only, and corrections use compensating entries. Settlement snapshots derive tenant payable from journals; payout transitions retain a snapshot, provider idempotency reference, and exception state rather than allowing finance staff to edit balances.

The PSP adapter provides collection, verified webhook, refund, beneficiary/KYC, payout submission, and payout-status operations. Provider event IDs and outbound idempotency keys are unique. A transactional outbox persists notification, realtime, and provider work in the same database transaction as the source event; workers perform the side effect after commit and retry delivery safely. Daily reconciliation compares provider activity, journals, settlements, and payouts.

This is preferred over direct provider calls from request transactions, which can leave a successful external charge without durable internal state. A mutable balance table is rejected because it cannot provide a complete audit and correction history.

### 5. Redis-backed workers and versioned realtime events

Managed Redis becomes the queue, cache, lock-support, and pub/sub service. The API, workers, scheduler, and realtime service remain stateless container processes. Outbox consumers publish organization-authorized events only after commit; tournament and other collaborative streams include a monotonic aggregate version. Clients detect version gaps and resynchronize from an authoritative HTTP endpoint.

Database queues and synchronous notifications are not sufficient for the target latency and failure isolation. Kubernetes is intentionally not selected: managed database/Redis plus stateless containers achieves the required scale without adding cluster-operating work.

### 6. Feature-specific financial boundaries

Recurring series materialize and reserve all occurrences only after confirmation, invoice monthly, and release future occurrences on delinquency. Split groups aggregate paid shares under one locked group and confirm exactly once. Marketplace transfer changes entitlement only after the coordinated buyer-payment and seller-refund outcome is accepted; compensation preserves the original owner when that outcome fails. POS offline use is restricted to local drafts to avoid unreconciled stock and cash changes. Wallet credit is a tenant-scoped liability account, never a transferable balance.

The alternative of treating split, resale, POS, or wallet as variants of the old `Booking`/`Payment` records is rejected because their payer, ownership, lifecycle, and accounting requirements differ.

## Risks / Trade-offs

- **[PSP cannot support payout, beneficiary onboarding, or refund semantics]** → Phase 0 approves no finance-dependent feature until sandbox evidence and operating ownership exist; unsupported features remain feature-flagged.
- **[RLS misconfiguration leaks tenant data]** → Use a non-owner runtime role, explicit transaction context, and automated cross-tenant tests for APIs, jobs, exports, cache keys, and channels.
- **[Legacy migration corrupts historical access]** → Use expand-contract migrations, rehearsal checksums, compatibility reads, a pilot copy, and no destructive schema change during the window.
- **[External side effect succeeds while process fails]** → Use provider idempotency, durable outbox/intents, provider-status recovery, and exception queues.
- **[Ledger or payout discrepancy]** → Derive balances from immutable journals, snapshot settlements, reconcile daily, and preserve corrections as compensating entries.
- **[Schedule contention or duplicate payment under load]** → Retain the database uniqueness rule, durable command idempotency, webhook deduplication, and concurrent test suites.
- **[Realtime events are delayed or missed]** → Publish after commit, monitor queue lag, include aggregate versions, and provide polling/resynchronization fallback.
- **[Single FTE becomes delivery bottleneck]** → Hold phase gates, limit initial formats/integrations, pilot by feature flag, and plan time-boxed QA/DevOps support around Phase 1 and hardening.

## Migration Plan

1. **Phase 0 — prove readiness.** Create PSP/compliance evidence and accountable exception runbooks; inventory legacy data; rehearse tenant backfill and rollback; baseline concurrency/load/recovery; define alert and SLO dashboards. Do not enable financial marketplace capabilities.
2. **Phase 1 — create the SaaS and finance foundation.** Add organization/membership data and RLS; backfill existing venue/court data; introduce v2 reservations, pricing quotes, idempotency, ledger, outbox, provider adapter, settlement/payout sandbox, Redis, and observability. Exercise v1 compatibility with five pilot operators.
3. **Phase 2 — add advanced booking only on the approved foundation.** Roll out recurrence, monthly invoices, split groups, listing/transfer orchestration, and payment/refund failure compensation to a controlled pilot.
4. **Phase 3 — add social and tournaments.** Roll out player discovery, reputation/dispute flows, deterministic tournament formats, and versioned realtime delivery by regional pilot.
5. **Phase 4 — add POS, memberships, loyalty, and wallet.** Roll out connected POS first, then tenant benefits and closed-loop credit; complete reconciliation, recovery, security, and rollout rehearsals.
6. **Rollback approach.** Each phase is independently feature-flagged. Disable new writes and retain committed records for investigation; compensate financial events rather than deleting them. Keep `/api/v1`, legacy tables, and compatibility reads until pilot results and migration checks permit a separately approved retirement change.

## Open Questions

- Which PSP satisfies the required sandbox contract, and which party contractually owns KYC, payout exceptions, and customer funds? Phase 0 must resolve this before Phase 1 finance activation.
- What is the exact externally communicated end date for `/api/v1` compatibility? The date can be set after pilot adoption data is available without changing the adapter approach.
