## Context

See proposal.md for motivation and capability scope. The existing application has V1 bookings, V2 reservations, tenant wallets, financial posting, POS, social matches and tournaments. Static exploration found local POS success after rejected requests, client-calculated totals, V1 booking IDs submitted as V2 reservation IDs, synthetic customer benefits, and tournament registration/payment performed only in local state. Ordinary V2 hold/confirm also disagrees with the current wallet-only specification. These observations require executable regression tests before changes; no runtime pass is claimed by this document.

This is cross-cutting and requires a design. One umbrella change captures the agreed scope, while task packages provide separate entry/exit gates. A package may be delivered independently but does not establish seven-area completion.

## Goals / Non-Goals

**Goals:** One authoritative outcome per business command; explicit tenant and resource identities; durable user journeys; retry-safe money/inventory transitions; tests proving the connection between frontend and persisted state.

**Non-Goals:** A rewrite of existing working domains, new payment providers, automatic production activation, advanced social recommendations, private realtime chat, double-elimination tournaments, unrestricted credit accounts or wallet transfers. Existing specialized split/resale and recurring-invoice policies are preserved rather than silently folded into ordinary checkout.

## Decisions

### 1. Reuse domain actions with explicit command contracts

Inventory current route/request/response contracts and make UI mutations return persisted resources, financial totals and identifiers. Reuse idempotency and after-commit outbox infrastructure. Retain a logical command key across network retries; distinguish a rejected operation from a committed operation with a lost response. Remove catch-and-success paths and fabricated business defaults. Test fixtures remain isolated from runtime business paths.

Alternative: optimistic locally constructed receipts. Rejected because receipt and stock state must survive reload and match the ledger. Optimistic nonfinancial presentation is acceptable only when it cannot imply a completed transaction.

### 2. Canonical identities and distinct customer records

Use an explicit legacy-booking-to-reservation mapping through the existing adapter/backfill path; never equate integer IDs. Reconcile uniqueness and tenant before use. POS requests reference the canonical reservation; mapping failures are visible errors. Customer lookup uses tenant customer relationships, not employee memberships. Registered customer relationships link to User; guest contact records do not fabricate a User or wallet. Walk-ins using the wallet-only booking path require an identified registered customer; guest records can be used for non-wallet POS sales.

Alternative: reuse organization members as the customer directory. Rejected because employment roles neither prove a membership benefit nor identify a customer's wallet.

### 3. Ordinary checkout and payments share financial invariants

V1 and V2 ordinary checkout use a common application-level orchestration for server quote validation, locking inventory/wallet, debit, confirmed records and balanced financial posting. Preserve V1 response projections and existing historical records. Separate explicit specialized recurrence/split commands from ordinary checkout. Old confirm commands cannot activate unpaid entitlements; migrate compatible clients to the canonical command and return an actionable conflict for obsolete operations rather than silent success.

Rescheduling locks old and new inventory, computes the price difference and debit/credit atomically. Cancellation follows current eligibility and refunds only the paid portion once. Quote snapshots contain product/rule version, eligible promotions, permitted stacking and exact monetary amounts. Preserve existing discount-combination policy rather than inventing a new campaign engine; document that policy in the contract inventory.

Tournament registration similarly locks capacity and the tenant wallet, uses server-published integer fee amounts, persists registration and financial references together, and returns the participant/receipt. Do not parse display strings as financial authority. Preserve free-entry support without a debit. A reload queries the participant rather than local registered IDs.

Alternative: retain two independent checkout implementations. Rejected because state and financial drift already exists. This does not require merging database tables in this change.

### 4. POS pricing, collection and attendance are separate concerns

Server pricing accepts product IDs, quantities, rental duration and permitted discount intent; it calculates configured VAT and membership once. Monetary arithmetic uses integer VND with a documented rounding rule shared by previews and final receipt. No real tax rate is inferred from UI fallback constants. The response supplies saved receipt ID, lines, tax, discounts, tender/change and totals. Enforce an open cashier shift on completion.

Cash collection requires sufficient tender; wallet requires a real customer and sufficient funds. QR creates a pending collection with provider verification, not an immediately paid row. Reuse provider infrastructure only where it can supply verified POS collection; missing capability blocks QR activation, not a fake fallback. Pending collections do not finalize paid stock effects; reserve stock where needed and release reservations on expiry. After provider confirmation, finalize once. If stock cannot be honored, create an explicit compensating outcome and exception rather than a completed sale without goods.

Attendance is a separate booking-linked record with actor/time and states not conflated with payment: not-arrived → checked-in → checked-out. Default acceptance permits same venue-local calendar day; codes/QR require authenticated staff validation. Use canonical identifiers, not a publicly scannable code as authorization.

Service tabs have open/unpaid → settled or cancelled states. Adding an item reserves available stock, but does not record a paid cash sale; removal releases the reservation. Settlement converts stock reservation into one sale movement. Checkout requires tab settlement; long-term debt and write-off policy are excluded. Keep accounting state separate from attendance timestamps.

Alternative: encode court tabs as cash plus a string reference. Rejected because it overstates cash and obscures unpaid obligations.

### 5. Minimal persisted community with explicit visibility

Add friendship requests/connections, feed posts/comments/reactions and moderation reports/actions. Use uniqueness for directed pending requests and accepted pairs, author ownership, bounded text inputs and escaped rendering. Feed audiences are organization members or accepted friends; financial and operational data remain tenant-private. No global public feed is introduced. Reports on friends-only content expose that content only through an authorized audited moderation path, not ordinary tenant browsing.

Use actual connections for match invite selection, persist accept/decline outcomes, and surface per-invite errors. Reuse player profile, match/reputation and tournament services. Notifications use recipient-scoped existing persistence/outbox and occur after commit. Reports require a moderation reason, immutable audit record, and hide/restore controls; author edits do not erase moderation history.

Alternative: unrestricted public feed or chat. Deferred because visibility, moderation and messaging would materially broaden this release.

### 6. Feature and completion gates remain authoritative

Expose effective capabilities for the selected tenant to UI navigation and forms without weakening API role checks. Disabled flags show unavailable states. Each work package passes API integration and real-API browser tests on an isolated test database before pilot activation. Finance/provider sandbox evidence is distinct from a mocked unit test; unavailable external evidence is recorded as blocked, never passed. Retain existing load, migration, provider and recovery gates.

## Risks / Trade-offs

- [Broad scope] → Ordered packages with explicit prerequisites; no all-at-once release and no completion claim based on screen inventory.
- [Legacy mapping errors] → Rehearsed explicit mapping with counts, orphan/collision checks and no numeric-ID fallback.
- [Duplicate wallet/POS services] → Establish one posting path per transaction and tests reconciling wallet, receipt, stock and journal; avoid unrelated refactors.
- [Existing tests encode held V2 semantics] → Classify ordinary versus specialized workflows before updating tests; keep regression coverage for specialized policies.
- [Spec overlap] → Ordinary prepaid bookings follow wallet specs; specialized split and monthly invoices retain their documented lifecycles and must pass separate tests.
- [Provider unavailable] → Complete integration contracts and tests, keep activation blocked pending sandbox evidence; do not replace it with simulation.
- [Community privacy] → Test organization/friend audiences, direct-ID access, notifications and moderation independently.
- [Stock reservations complicate reconciliation] → Separate reserved from on-hand quantities, audit release/settlement and test concurrent tab/POS purchases.

## Migration Plan

1. Baseline tests and route/permission/feature inventory; record existing data counts and source-to-target identities using an isolated rehearsal copy.
2. Add backward-compatible tables/columns/indexes for attendance, customer relationships, social content and tabs; preserve all historical booking/payment tables.
3. Backfill canonical identity links with unique tenant-checked mappings and a dry-run report; block activation on ambiguous or missing links. Reconstruct no historical attendance or paid status from UI assumptions.
4. Deploy compatible server contracts before frontend consumers. Introduce effective capability responses and disable unfinished commands in UI/API. Reconcile old held ordinary bookings by verified payment state or normal expiry; do not debit historical customers automatically.
5. Enable identity/catalog, ordinary checkout, POS/attendance and community packages in that order for approved test/pilot organizations after their gates. Verify finance providers separately.
6. Roll back by disabling new affected commands and reverting compatible application paths; retain additive schema and committed records. Never undo financial history by dropping tables or restoring unsafe confirmation behavior. Reconcile any in-flight provider event before reopening collections.

## Open Questions

- Exact pilot organization IDs and provider sandbox credentials are deployment inputs, not new scope or permission to activate production.
- Visual copy and feed page size may be selected during implementation without changing audience or acceptance contracts.
