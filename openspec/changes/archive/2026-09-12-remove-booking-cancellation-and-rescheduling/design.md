## Context

See `proposal.md` for motivation and scope. This is cross-cutting across two API generations, financial side effects, tenant setup and customer/admin UI, so a design is required.

- V1 routes reach `BookingService` and `CancelBooking`, `CancelBookingSlots`, `RescheduleBooking`. Cancellation credits wallets, posts reversals and releases mapped V2 inventory; rescheduling creates a replacement booking and applies a debit or credit for the difference.
- V2 `ReservationService::cancel` independently credits the wallet and releases slots/entitlements. Its private `release` helper is also used by expiry-related behavior and must not be deleted indiscriminately.
- Admin `ProcessRefund` changes legacy refund/booking state and may post financial reversals. Its GET list currently paginates pending records; the UI presents these as actionable work.
- Organization grace periods are used by venue readiness and snapshots. Membership cancellation benefits and Vietnamese/English copy also promise retired behavior.
- Existing success tests explicitly assert cancellation after venue suspension, grace-period snapshot eligibility, partial refunds and cheaper-slot rescheduling. They must change alongside runtime behavior; historical ledger tests remain valuable.

## Goals / Non-Goals

**Goals:**

- Remove the callable business paths, not just visible buttons, across V1 and V2.
- Reuse existing history, expiry, wallet and ledger infrastructure with no new dependencies or policy framework.
- Separate immutable historical representation from currently available actions.

**Non-Goals:**

- Do not delete refund tables, wallet transaction types, legacy status values, stored snapshots or balanced posting primitives that still support history/other financial workflows.
- Do not redesign marketplace resale, split collection, recurring contracts/invoices, disputes, subscriptions, POS cancellation or waitlist opt-out. Their independent lifecycle contracts remain unchanged; an occurrence accessed through the retired ordinary booking endpoints still cannot be cancelled/rescheduled through those endpoints.
- Do not resolve old pending refunds or migrate balances as part of feature removal. No production mutation is authorized by this planning change.

## Decisions

### 1. Remove mutation routes and their operation-specific implementation

Remove the three V1 booking mutation routes, V2 cancellation route and legacy admin refund PATCH route listed in the delta specs. Standard routing returns HTTP 404 to valid stale-client requests. Remove corresponding controller/service methods, action classes, operation-only request/DTO classes and dependency wiring after checking every caller. Remove the V2 cancellation wrapper if unused. Keep shared release/payment/history infrastructure and unrelated mutation routes.

This is a permanent capability removal. A new feature flag, an enormous cancellation grace period or a UI-only restriction would leave executable paths or tenant overrides behind. A custom 410 tombstone handler would add endpoints solely to explain removed endpoints; the standard 404 is sufficient. Test V2 requests with old idempotency keys as well as fresh keys so cached success cannot bypass route retirement. Do not add a broad model-level ban on `cancelled`/`refunded` values: historical records and specialized lifecycles still use them.

### 2. Preserve data and keep cancellation-refund history read-only

Retain GET history and existing serialized historical states. Keep authorized refund lookup/listing available; reuse the current admin refund panel as a clearly labelled read-only historical list, remove approval/rejection actions and the actionable pending-refund KPI, and allow the list to include historical statuses instead of presenting only a work queue. Preserve existing auth/tenant scoping.

Remove `ProcessRefund` as a callable cancellation-refund workflow, but keep shared posting functions where unrelated compensation or ledger verification still uses them. Existing pending rows remain pending historical records with no automatic settlement or rejection. If operations later need to resolve a legacy obligation, that requires an explicitly authorized separate decision, not a hidden replacement cancellation route.

Dropping refund storage or erasing old enum values would damage receipts, check-in rejection rules, reports and settlement reconciliation. Keeping read-only records is the minimal non-destructive approach.

### 3. Use a fixed policy without new configuration

Remove cancellation-grace and cancellation-fee-waiver editing from organization/membership forms, API payload builders and backend accepted write fields. Explicitly reject obsolete fields with the existing validation error shape (422); silently accepting them would imply they remain effective. Remove the cancellation-policy readiness prerequisite and stop adding obsolete policy fields to new snapshots. Preserve other snapshot contents and existing stored values verbatim; old cancellation terms are historical, not executable permissions.

Remove only capability-specific cancellation/rescheduling benefit copy, including dynamic membership descriptions that would otherwise expose stored obsolete perks. Keep meaningful `cancelled`, `expired` and `refunded` historical labels. Do not translate generic modal-close/back buttons into booking-policy actions.

### 4. Keep automatic release separate and verifiable

Preserve `ExpirePendingBookings`, V2 expiry processing, coupon usage release, after-commit availability events and waitlist notifications. During implementation, trace actual scheduler/service callers rather than assuming V2 cancellation is the only release path. If expiry shares code with cancellation, retain the smallest expiry-specific path needed by its current contract. A repeated expiry must not refund an unpaid hold, release a confirmed paid booking or duplicate coupon/notification effects.

Leave specialized overdue recurring-invoice release, split expiry and marketplace compensation untouched. Their existing regression tests provide the boundary against over-broad deletion.

### 5. Update contracts and tests together

Replace success tests for retired operations with endpoint-unavailable and no-side-effects cases; do not just delete their coverage. Test whole/partial/V2 cancellation, higher/equal/lower-price rescheduling, customer/admin actors, suspended venues, historical grace periods, legacy holds, repeated calls and V2 old idempotency keys. Assert inventory/entitlements, amounts, wallet/ledger rows, coupon usage and notifications remain unchanged.

Keep historical refund/journal/check-in tests and add UI tests for absent actions and truthful bilingual policy. Update active documentation; do not rewrite archived change artifacts. The wallet main spec's existing Purpose still promises cancellation refunds: because delta Purpose updates are ignored by OpenSpec, correct that sentence when synchronizing/archiving this change, not by editing the main spec during proposal creation.

## Risks / Trade-offs

- [Stale clients lose supported mutations] → Intentional breaking change; publish the removed endpoint list and release UI/server together. No success replay is allowed for retired commands.
- [A generic delete breaks other financial workflows] → Trace references and keep shared wallet/ledger primitives and historical values; run unrelated compensation/marketplace/POS/subscription regressions.
- [Old snapshots promise a former policy] → Preserve evidence, show the current no-cancel/no-reschedule policy and explicitly retire the commands for old as well as new records. Any operational communication about historical obligations is outside automated data changes.
- [Pending cancellation refunds remain unresolved] → Preserve them visibly as read-only historical records; never auto-credit or auto-reject them during rollout.
- [Old app instances or jobs execute retired code during deployment] → Drain in-flight requests and relevant workers before switching traffic; do not treat a rolling mixture of versions as a completed rollout.
- [Frontend-only removal misses server entry points] → Verify route absence and end-to-end negative tests, then audit operation callers, policy writes and both locales.

## Migration Plan & Operational Rollout Runbook

### 1. Pre-Deployment: Nonproduction Verification & Inventory Audit
- Implement and execute full regression test suites across backend and frontend environments (`php artisan test`, `npm run test:unit`, `npm run type-check`, `npm run build-only`).
- Perform read-only audit of existing historical pending cancellation refunds:
  - Query existing records: `SELECT id, booking_id, amount, status, created_at FROM refunds WHERE status = 'pending';`
  - Verify that pending rows remain intact and unchanged; ensure zero automated bulk transitions or wallet ledger credits are triggered.
  - Confirm authorized GET `/api/v1/admin/refunds` and `/api/v1/bookings` return these historical records accurately with read-only badges.

### 2. Coordinated Release & Worker Draining
- Deploy backend route/controller/action removals and frontend UI changes as a single coordinated, atomic deployment. A split release where frontend still provides cancellation UI or backend still exposes cancellation routes creates data inconsistency risks.
- **Old-Instance & Worker Draining:**
  - Drain in-flight HTTP requests on old instances prior to cutover.
  - Gracefully restart and drain background queue workers (`php artisan queue:restart`) to ensure no queued jobs execute stale code or attempt to process retired cancellation mutations.
  - Flush and rebuild route, config, and view caches (`php artisan route:cache`, `php artisan config:cache`, `php artisan view:cache`).

### 3. Post-Deployment Smoke Checks (Retired URLs & Active Workflows)
- Perform HTTP smoke checks asserting standard `404 Not Found` for all retired mutation endpoints:
  - `POST /api/v1/bookings/{code}/cancel` -> 404
  - `POST /api/v1/bookings/{code}/cancel-slots` -> 404
  - `POST /api/v1/bookings/{code}/reschedule` -> 404
  - `POST /api/v2/reservations/{code}/cancel` -> 404
  - `PATCH /api/v1/admin/refunds/{id}` -> 404
- Verify negative invariant: Ensure repeated requests and V2 requests bearing previously used idempotency keys also return 404 without altering any entity states, ledgers, or waitlists.
- Verify positive workflows:
  - Create and pay for new bookings via ordinary wallet checkout.
  - Automatic unpaid hold expiry continues to release expired slots retry-safely and trigger waitlist notifications without crediting wallets.
  - Booking attendance check-in functions normally for valid non-cancelled bookings.
  - Admin financial reports, dispute resolution, and settlement reconciliation reflect unaltered historical amounts.

### 4. Rollback Constraints
- **Zero Destructive DB Migrations:** No tables, columns, or rows were dropped or altered by destructive DDL migrations.
- **Rollback Policy:** A code rollback to an older version would inadvertently resurrect retired cancellation and rescheduling capabilities, violating core business rules. Therefore, technical rollback requires explicit product and executive approval. Any critical defect must be addressed via a forward-fix patch while strictly maintaining endpoint retirement and non-cancellation policies.

### 5. Spec Synchronization & Archive Notice
- **Explicit OpenSpec Sync/Archive:** Do NOT deploy or archive automatically.
- When explicitly executing `/opsx-sync` or `/opsx-archive`:
  - Synchronize the five delta specs: `api-alignment`, `financial-ledger-and-payouts`, `reservations-and-pricing`, `venue-court-operations`, and `wallet-payment`.
  - Crucially, manually correct the `Purpose` section of the main wallet spec (`openspec/specs/wallet-payment/spec.md`), updating any legacy statement promising cancellation refunds to reflect the authoritative policy of no cancellation or rescheduling.
  - Historical archive artifacts must be preserved verbatim as non-executable project history.
