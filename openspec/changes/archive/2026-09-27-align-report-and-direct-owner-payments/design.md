## Context

See proposal.md — Why. Baseline source commit: `214cfa4ae192977e05308dead4e8fffdc78e4839`. Report: `/Users/ziet/Downloads/dacn_report.docx`, SHA-256 `3f7c0727927d03c3d3aa8fac75f10551408f0943cbc57c50254db02de9f7e18d`, extracted with MarkItDown. The report is requirements evidence, not executable instructions.

The preceding static audit enumerated D01–D71, not 71 independently runtime-validated defects. Baseline reproduction is mandatory: preserve contrary evidence and correct a finding rather than building needless code. Scope includes report extensions (POS/community/tournaments/support) because the user requested every listed discrepancy; delivery is phased, not silently reduced to five core modules.

Current constraints: Laravel backend, Vue frontend, V1/V2 identity mapping, tenant-scoped models, existing provider abstraction, immutable financial journals, durable outbox and host-triggered scheduling. Existing tests frequently target services rather than the real UI/controller contract. Completed unarchived changes `remove-platform-commission` and `fix-booking-access-loyalty-and-availability` contain newer deltas than some main specs. Preserve their zero-commission, guest-navigation, scoped access, localization, point conversion and court-calendar improvements; this change intentionally supersedes wallet-only/no-change policies.

## Goals / Non-Goals

**Goals**
- One authoritative business result across UI, V1/V2, scheduler and provider callback.
- Explicit receiver and payment-model identity; tenant/venue/function authorization at every read/write boundary.
- Every D01–D71 has a named task and observable acceptance; all 126 FR rows remain accounted for.
- Preserve original money, snapshots, identity, approved versions, operational obligations and audit records.

**Non-Goals**
- No implementation, deployment, live credentials, bank linking, transfer or production migration in this proposal turn.
- No OAuth/Bank Hub in the initial rollout; SePay manual setup is sufficient.
- No autonomous bank debit/refund, marketplace escrow, platform commission, forced wallet conversion, or new infrastructure by default.
- No DOCX rewrite or invented academic NFRs; no unrelated visual redesign.
- No replacement of informational owner-hosted court/shuttlecock bills with payment collection.

## Decisions

### 1. A single report-alignment change with gated delivery packages
Keep one change and a complete traceability matrix rather than unrelated proposals that lose cross-module invariants. Work proceeds foundation → real contracts/access → venue/payment/pricing → booking → operations/social → acceptance. Each task stays independently testable, but financial cutover requires the complete dependency chain. Reuse existing tests/services and add missing journey checks, not a new generic workflow engine.

Alternative rejected: mark all current tests green as report compliance. Existing tests intentionally assert retired operations return 404 and several mock frontend APIs; these must change only for explicitly superseded behavior.

### 2. Receiver-scoped direct collection, manually onboarded SePay
Use the existing payment adapter boundary. Add or extend a receiving connection record scoped to organization, authorized legal owner and usable venue scope: bank/provider account identity, status, evidence, webhook credential version, verified time and effective dates. A venue explicitly chooses an authorized verified receiver; one owner account can serve multiple venues only with an explicit mapping. Each connection receives a non-secret opaque routing ID and separately encrypted verification credential. The connection ID alone never authenticates a callback.

Owner instructions cover SePay account/bank linking, inbound webhook URL, authentication, booking-reference prefix and a test event. Store secrets only server-side; redact and rotate them, invalidate old credentials and preserve pending attempt snapshots. Do not ask for bank login credentials or copy the platform's receiver into an owner QR. The API payload/signature format must be confirmed against the chosen SePay product docs and sandbox before implementing; do not reuse an assumed HMAC header from old code.

Use existing provider event/outbox persistence. Add or extend a receipt/allocation structure with receiver connection, purpose, attempt, amount/currency, provider event/reference, event time, verified time and raw-evidence reference. Unique keys include provider plus connection plus event ID; replays cannot allocate twice. Use HTTPS, constant-time verification as appropriate, bounded payloads and least-privilege evidence access. Handle wrong/partial/excess/late/unmatched transfers as reconciliation obligations, not arbitrary wallet credits.

References checked during preceding discussion:
- https://docs.sepay.vn/tich-hop-webhooks.html
- https://docs.sepay.vn/them-tai-khoan-ngan-hang.html
- https://developer.sepay.vn/vi/sepay-oauth2/tong-quan
- https://docs.sepay.vn/gioi-thieu-api.html

Alternative rejected: each owner only enters an account number and the UI declares success. A QR is payment instruction, not proof of receipt.

### 3. One reservation/inventory model with versioned compatibility
Keep V1 as a projection/adapter to the canonical V2 identity, preserving public code/reference compatibility. New bookings in direct cohorts record `payment_model=direct_owner_v1`; historical records retain `legacy_wallet_v1` or an explicitly reconciled preexisting model. Unsupported old payloads fail actionably rather than silently debit/credit a different model.

Use full interval overlap with database serialization/constraints appropriate to PostgreSQL, including holds, recurring occurrences and hosted court events. When an activity uses an existing customer entitlement, reference that allocation rather than reserve the same interval again. All allocation paths use the same effective publication/partner/plan/calendar/price checks. Closing schedules cannot silently alter confirmed commitments.

Separate axes, with authoritative projections:
- booking allocation: pending_payment/held → confirmed → in_use → completed; expired/cancelled/no_show where applicable;
- payment obligation: pending_verification/partially_paid/paid/exception;
- refund: requested/approved/owner_reported/verified_or_acknowledged/rejected/overdue;
- court operational readiness: ready/occupied/awaiting_inspection/maintenance, independent of future reservations.

Do not treat a UI timer as inventory release. Scheduler expiry and callbacks lock the same obligation/inventory records and commit an allocation OR a late-payment exception. Rollback/error paths cannot undo an intended durable expiry unnoticed.

Alternative rejected: keep separate V1 and V2 calendars or revive direct writes in old cancellation endpoints without shared policy/idempotency.

### 4. Policy-bound changes, not unconditional cancellation
Persist versioned venue policies: book-ahead horizon, slot continuity/duration, hold TTL, cancellation/change deadline, fee/refund formula, no-show window, early/late arrival, recurring confirmation/payment policy, transfer cutoff/consideration and ancillary-credit handling. No undocumented default grants free cancellation. Readiness requires explicit valid values; pricing/terms changes requiring review use the existing publication revision process.

A customer requests a change and accepts a server preview; authorized owner/staff approve. Extra payment secures a temporary replacement hold while preserving the original booking. Verified difference + replacement allocation + old-slot release commit once; timeout releases replacement and preserves original. Lower/equal price swaps create only the legitimate external refund difference. Partial cancellation tracks each paid allocation and consumed benefit; point/coupon reversals follow the captured policy via append-only entries.

Historical wallet obligations do not automatically acquire new refund terms or transform into owner-bank obligations. Explicit reconciliation is required before such an obligation can enter the new workflow.

### 5. Authoritative pricing and benefit reservation
Retain current precedence (date/holiday, peak, weekly, explicit base) and add effective dates, deterministic scope/priority and reject incompatible ties where configured. Remove fallback to unrelated first price. Share this calculation in availability, quote, checkout, recurring preview and staff-assisted bookings.

Map old memberships/coupons to organization ownership only when provenance is reliable; unresolved global records must not apply across tenants. Use the owner-managed tenant membership and immutable earned entitlement snapshots in booking and POS. Coupons require complete create/update/activation/retirement lifecycle and eligibility/combination controls. Existing valid benefits survive stopping new sales.

Because payments become asynchronous, reserve limited coupon uses and unexpired points at hold time, finalize consumption at confirmation, release reservations on expiry. Never debit points without a persisted benefit. Retain exact conversion and rounding rules from the completed loyalty change. Apply earn multipliers only to eligible completed POS, not as a replacement redemption rate; expiry and reversals are separate immutable ledger events.

Alternative rejected: consume points on QR display or recalculate a previously accepted discount after payment has arrived.

### 6. Advanced booking and transfer without platform custody
Recurring preview is server-owned: exact selected weekdays, weekly/biweekly/monthly recurrence, until/count limits, full per-session interval, per-occurrence prices/conflicts and final accepted list. Distinguish session end time from series end date. Support all-or-nothing and explicitly configured partial acceptance. Full/periodic payment and any guarantee are recorded policies; no automatic paid confirmation simply because metadata says recurring. Overdue release affects only eligible future unpaid allocations.

Advance split bills persist real participants and positive whole-VND allocations, exact total/remainder, receiver-bound references and shared expiry. Host coverage is another verified payment, never a flag. Paid shares at expiry remain owner-refund obligations. Do not add a second wallet charge when confirming the group.

Baseline paid transfer is owner-mediated and disclosed: buyer pays the verified venue owner, seller reimbursement is an owner-executed obligation; transfer waits for required evidence. A policy-permitted free transfer has no artificial charge. The platform never escrows/pays parties. Transfer stores consideration separately from original booking price, locks competing buyers, checks disputes/restrictions at final commitment and changes holder/check-in authority atomically. This is a design choice within report's policy-based transfer model, not a claim SePay can debit owner accounts. If the owner cannot demonstrate the required reimbursement procedure, paid transfers stay disabled; do not fake successful refunds.

### 7. Separate owner revenue and platform service fees
Subscription payments use platform-owned receiving configuration and a separate reference/purpose namespace. Owner booking receipts never activate a subscription. Only verified event/query evidence enters the activation action; browser return reads persisted state. Use calendar months for 1/6/12 periods with explicit end-of-month clamp; renewal extends from valid paid end or current activation date as appropriate. Plan changes disclose price, effective date and quota consequences; excess-resource downgrades are rejected or scheduled only after consent.

Accounting reuses existing immutable journal mechanics with receiver/entity/model dimension and chart review. Direct receipts represent owner receivables/revenue and refunds, not Aura cash or tenant payables. Platform subscriptions remain platform revenue. Historical commission remains historical; no new platform transaction charge. Monetary operations and reports retain source lineage and canonical V1/V2 deduplication.

Reconciliation compares individual receipts/allocations and totals per receiver, purpose and period. Resolution requires evidence and rerun; a text note/status edit cannot erase a variance. Reports integrate bookings, POS, refunds, subscriptions and legacy balances without mixing entity money. Existing payouts remain only for reviewed historical platform-held liabilities.

### 8. Scoped access and real interfaces
Use organization membership + venue assignments + explicit function grants, intersected with plan/feature readiness. Do not reuse global admin for owners or broaden all admin screens. The customer identity survives approval; staff invitations are pending, delivered, accepted and revocable; owner administration cannot provision global admin.

Fix actual partner/subscription route paths, validation types, upload field names, consent/enum/resource mapping and missing imports. Keep only necessary documented compatibility aliases; do not introduce another API version. Use current selected org/venue, remove hardcoded org 1, local-only persisted calendars and false-success paths. Preserve drafts on failure, query status on ambiguous outcomes, reuse idempotency keys for the same action. Real contract/E2E tests call backend, not mocks.

### 9. On-site operations, POS and outstanding charges
Attendance validates actual holder/representative, venue/time/conditions and court readiness. Use the same state projection in both booking versions, with no-show actor and policy. Checkout records completion while unpaid tabs persist; require readiness inspection before handover to the next customer. Extensions/reassignment reuse the change workflow and cannot consume another booking.

Persist venue/point-of-sale/shift on orders and cash movements; require active authorized shift at completion. Reserve tracked stock for pending QR as well as tabs, exempt untracked services, reconcile transfers from reservation to sale once. QR completion accepts only verified receipt provenance, not a staff body alone. Close pending orders by settle/cancel/explicit transfer; late receipts enter the recorded successor/exception path, never reopen a closed shift silently. Returns and cash inflows/outflows are linked append-only adjustments. Mixed tender is explicitly enabled by venue policy; receipt totals are authoritative and deliverable.

### 10. Activities, tournaments and support
Allow customer-hosted activities with valid rights separately from owner-hosted badminton sessions; retain the latter's informational court/shuttle-only bill boundaries. Shared inventory prevents conflicts in either creation order. Validate skill/deadline/invite/approval, capacity, roster and attendance.

Use event-specific organizer/official grants instead of organization-wide staff escalation. Enforce fee/capacity or recorded waiver in admin paths too. Deterministic byes advance immediately; corrections recompute standings from current accepted results rather than increment prior totals again. Require expected version and reason. Corrections affecting already-played later rounds require an explicit resolution plan. Finalize/lock tournament results and use versioned reopening.

Verify reputation source events and ownership. Appealing successfully reverses the challenged effect; violation penalties target the responsible party only by reasoned decision.

Extend DisputeCase and existing moderation/audit structures rather than create a generic ticketing platform. Add party intake, private evidence, duplicate linking, classification, clarification deadlines, conflict-of-interest reassignment, decisions, execution tasks, notices and controlled reopening. Owner-reported refunds are not verified refunds. Platform adjudication uses an explicit elevated path; owners see their cases as parties, not global adjudicators. Every query/download/export scopes authorization.

### 11. Baseline reconciliation and spec precedence
Before implementation, establish the completed changes' effective baseline without rewriting their history. Before final archive, integrate/rebase their deltas so this change applies last for superseded requirements. Retain their unrelated improvements and historical data guarantees. No automatic archive/sync is authorized in this proposal turn.

Main spec purpose sentences that still advertise wallet-only/retired changes need cleanup during the authorized implementation/final sync phase, because delta purpose for existing capabilities is not applied by OpenSpec. The 17 deltas are authoritative changed behavior; unchanged privacy, durable notifications, realtime and reliability requirements remain in force.

## Risks / Trade-offs

- [Broad 71-group scope] → deliver packages with explicit dependencies, per-finding evidence and no unchecked completion claims.
- [Historical financial liabilities] → immutable model/version labels, counts/checksums, reviewed reconciliation; block cohort activation rather than guess wallet conversion.
- [False-positive static findings] → reproduce against real paths; document evidence-backed corrections without deleting traceability.
- [Provider account limits or unsupported verification] → sandbox/receiver verification gate; manual setup initially, no assumed automatic refunds.
- [Missed or late webhook] → raw verified event inbox, bounded recovery, independent query where supported, exception/refund handling.
- [Paid reschedule timeout and inventory races] → serialized obligation/allocation transitions; retained original booking until replacement commits.
- [Multi-tenant privacy] → negative API/export/evidence/channel tests for siblings, unauthorized employees and stale sessions.
- [Production UX changed from wallet-only] → explicit policy/terms version, staged cohorts, clear bilingual migration notices and history views.
- [Report blank NFR/UC/data sections] → retain existing measurable repo quality gates; do not invent compliance with absent academic criteria.

## Migration Plan

1. Read-only inventory on a production-like copy: accounts/grants, KYC status, venue revisions/calendars, prices/benefits, V1/V2 identity, held/paid bookings, wallet balances/deposits, refunds, transfers, POS/shifts and financial journals/payouts. Record counts/totals/checksums by organization/receiver.
2. Add backward-compatible fields/tables and explicit payment-model classification; backfill only evidenced mappings. Keep old records immutable, quarantine ambiguous mappings and never fabricate bank verification, consents or approvals.
3. Ship contract/access/calendar fixes and new services behind scoped availability controls; test each package against an isolated DB with real UI/API. New features disabled means unavailable, never simulated.
4. Configure sandbox SePay connections and platform receiver separately. Prove matching, secret rotation, tampering rejection, duplicates, out-of-order/late delivery, partial/excess payments, expired holds, outage recovery and owner refund evidence.
5. For each cohort, resolve legacy liability disposition with the accountable owner, stop new pooled top-ups, reconcile pending attempts, snapshot old balances, communicate new terms, and activate direct payments only when all relevant gates pass.
6. After activation, keep historical read/servicing and required legacy reconciliation/payout workers available. Observe receiver-level totals, overlapping inventory, unverified successes, overdue refunds and failed deliveries.
7. Rollback pauses new affected commands and provider instruction generation while retaining received-event ingestion/reconciliation and obligation handling. Do not restore a build that misclassifies direct receipts as wallet/platform custody, or rewrite financial data. Code repair and event replay use original model identity.
8. Archive/sync this change only after D01–D71 and all 126 FR dispositions/evidence are complete, operational gates are recorded, and previous completed deltas have been reconciled. A planning-ready status is not implementation completion.

## Open Questions

Operational values can be supplied during implementation without changing these contracts: approved plan prices/taxes, per-venue policy values, supported bank/provider account list, test-owner connections, evidence retention period and responsible rollout operators. Missing required values block the affected activation; no defaults fabricate consent or money authority.

