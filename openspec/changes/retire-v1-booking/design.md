## Context

See `proposal.md` (Why) for motivation. Facts below come from the codebase and from read-only queries on `bt-server` on 2026-10-11.

**Write paths.**

- **V1:**
  - `POST v1/bookings` runs `BookingController@store` → `CreateBooking` → `OrdinaryCheckoutService::checkoutV1`. That writes `bookings`, `booking_slots` and `payments`, then `backfillBooking()` mirrors the booking into `reservations`, `reservation_slots`, `reservation_entitlements` and `legacy_booking_mappings`.
  - For direct-owner organizations, it then calls `ReservationPaymentService::initiate` on the mirrored reservation. So V1 direct-owner payment already runs on V2 objects.
- **V2:** `POST v2/reservations` and `POST v2/reservations/checkout` write only reservation tables.
- **Recurring booking** already uses V2.

**V1 routes.** These are `routes/api.php:268–283` plus admin `:367–368`, all in the `auth:sanctum, not_restricted` group. No feature flag gates them. The frontend flag `v2_reservations_enabled` is never read by the backend.

**Things that are not booking-specific despite the `v1` prefix:**

- `payments` is still written by `ProcessQrPayment` for SePay POS and wallet deposits, with `booking_id = null`, and doubles as the webhook dedupe ledger.
- `PATCH admin/payments/{payment}` (`ReviewPayment`) reviews `manual_review` payments. Only its last branch confirms a V1 booking.
- `POST reviews` resolves a `booking_code`. `POST disputes` validates `booking_id exists:bookings`.
- Admin reports (`AdminController` around lines 431 and 663) aggregate `bookings`.
- The public `GET availability` reads `booking_slots` (`AvailabilityController.php:33`).

**Frontend.**

- These flows call only V1:
  - court checkout: `useCourtSchedule.ts:257` → `bookingStore.createBooking` → `/payment/:code`
  - payment page: `usePaymentProcessing.ts`
  - history: `useUserBookings.ts`
  - cancel/reschedule modals, which use V2 only when passed `:v2="true"`
  - reviews
  - admin bookings, payments and reports: `useAdminDashboard.ts`
- V2 building blocks exist but nothing routes to them: `useV2Reservations.ts:150–227`, `V2ReservationView`, `V2ReservationPaymentModal`.

**Data (bt-server).**

- 280 bookings, all created within the last 30 days (shared dev data). 85 have no reservation mapping:
  - 61 `legacy_wallet_v1` confirmed;
  - 12 `direct_owner` confirmed, dated 2026-10-04 to 2026-10-07;
  - the rest are terminal.
- `checkoutV1` always backfills, so the unmapped direct-owner rows were written by something that bypasses checkout (E2E arrange commands or seeders are the suspects).
- 15 active `booking_slots` have no mirror, all in the past.
- `reviews` has 0 rows. `dispute_cases` has 64 rows and only a `booking_id` column.

**Gate machinery.**

- `V1AdoptionService::capture` records, per period: legacy booking count, V2 reservation count, legacy active count (`pending_payment`/`confirmed` created in the period) and legacy user count.
- `retirementReadiness()` only checks that an approved change folder with `proposal.md` and `tasks.md` exists, and that some snapshot exists.
- The command default is `--days=30`. No document sets a numeric threshold.

## Goals / Non-Goals

**Goals:**
- One reservation identity for every booking, with V1 removed only after evidence shows it is unused.
- Each phase can be shipped and rolled back on its own, and none deletes historical data.

**Non-Goals:**
- Changing reservation business rules, pricing, payment providers or the SePay webhook.
- Reworking UI design beyond pointing existing flows at V2.

## Decisions

### 1. Three phases with an evidence gate in the middle

- **Phase A** moves every client and backend reader to V2 while V1 still works. A rollback is a plain redeploy.
- **Phase B** waits for evidence.
- **Phase C** deletes the V1 surface.

The evidence window is 30 days, the existing `v1:adoption-snapshot` default. The gate is exactly the four conditions in the spec.

The readiness check's "approved change" condition is satisfied by this folder only after a human approves it. Task 6.1 records that approval.

Alternatives considered:
- **Big-bang removal.** It breaks the only routed checkout, has no usage evidence, and contradicts the documented hard stop.
- **Feature-flagging V1 off without deleting code.** It keeps two code paths alive indefinitely, against the repo's "cut over cleanly, no shims" rule.

### 2. History lives in reservations; the V1 projection freezes at Phase C

- Phase A starts by running the existing backfill (`BackfillLegacyReservations`) until every booking has exactly one mapping. Any booking it cannot map is reported and blocks the gate, never forced.
- Writers that bypass checkout (E2E arrange, seeders) are fixed to call `backfillBooking()` or to write V2 directly.
- In Phase C every `syncProjection` caller is removed. `bookings`/`booking_slots` stop changing and remain a historical snapshot. The reservation is authoritative.

Alternatives considered:
- **Keep syncing the projection.** It costs a write on every reservation change for a table nothing reads.
- **Drop the V1 tables.** Forbidden: the platform-readiness and ledger specs keep historical bookings, refunds and payment references reportable.

### 3. Withdrawn endpoints are removed routes

Phase C deletes the route definitions, so the router answers 404 and no controller code runs. That satisfies the spec's "rejected without creating, reading or changing any record". Stale bundles are not a realistic population after a 30-day window with zero V1 traffic.

Alternative: **410 stubs with a "please reload" message.** They keep dead controller code and route tests around for a client population the gate has already shown to be empty.

### 4. Reviews and disputes reference reservations

- Phase A migration adds nullable `reservation_id`, with an FK to `reservations` and an index, to `reviews` and `dispute_cases`. It backfills `dispute_cases.reservation_id` through `legacy_booking_mappings` and makes `booking_id` nullable on both tables, kept for history.
- `POST reviews` takes `reservation_code`. `POST disputes` takes `reservation_id`, validated against reservations the caller may act on. Both routes keep their paths, because they are not booking-specific.
- Backend and frontend ship in the same release, with no dual-field acceptance.

Alternative: **resolve booking codes through the mapping inside the new APIs.** That keeps V1 identity in new contracts forever.

### 5. Admin reads

- Add `GET api/v2/admin/reservations` for the admin booking list. Mapped legacy bookings appear in it, because every booking has a reservation.
- Reimplement `admin/reports` and `admin/reports.csv` over reservations and owner-payment receipts, behind their existing paths.
- `GET admin/payments` and admin refunds stay as they are. They list `payments`/`refunds` rows, which remain valid history and live POS/wallet data.
- `PATCH admin/payments/{payment}` keeps reviewing manual payments. Its V1-booking confirmation branch is deleted in Phase C. By then the gate guarantees no V1 booking is `pending_payment`.
- V1 admin no-show is replaced by the existing `POST v2/attendance/no-show`.

### 6. Conflict checks drop `booking_slots` only after coverage is proven

- In Phase A, the six overlap checks keep querying both tables, because V1 still writes.
- In Phase C, the `booking_slots` branch is removed. It is safe because the gate guarantees that every booking is mapped, so each active `booking_slot` has a mirrored `reservation_slot`.
- A Phase C precheck query asserts there is no active future `booking_slot` without a mapped active reservation slot.
- With `harden-db-integrity` archived first, the store-level exclusion constraint already covers mirrored V1 slots.

### 7. Frontend cutover

- Route `V2ReservationView` for reservation detail. Its payment modal replaces the `/payment/:code` page for new bookings.
- `/payment/:code` keeps working through Phase B, because pending V1 bookings may still need it. Phase C removes it. By then the gate guarantees that every booking an old notification link can point to is terminal or in the past.
- Checkout uses the V2 quote → hold/checkout calls already in `useV2Reservations`.
- History lists reservations. The cancel/reschedule modals drop the `v2` prop and always call V2.
- Remove the `v2_reservations_enabled` capability usage in Phase A. Rollback of Phase A is a frontend redeploy, and the readiness doc's flag-based rollback note is updated accordingly.

## Risks / Trade-offs

- [V2 checkout UI misses something V1 checkout had: points control, coupon, zero-payable, legacy wallet cohort] → Phase A acceptance must cover each with browser tests on the V2 flow before the release. The legacy-wallet cohort keeps working, because V2 checkout already supports both payment models. V2 checkout has no coupon support; see Implementation Decision I2.
- [Backfill cannot map some historical bookings] → They are reported and block the gate, per spec. Resolve them case by case with a reviewed data fix.
- [Backend and frontend deploy at different times in Phase A] → Release backend first only for additive parts: the new admin route, the `reservation_id` columns, reports. Release the review/dispute payload change together with the frontend.
- [A V1 booking for a far-future date keeps the gate closed] → Intended. Such a booking is still served by V1 until it is in the past or terminal. If needed, the owner can reschedule it through V2.
- [Spec overlap with `harden-db-integrity`] → Different requirements in the same capabilities. Archive `harden-db-integrity` first.
- [Bypass writers keep creating unmapped bookings on shared environments] → Fixed in Phase A (Decision 2). The gate's mapping check catches regressions.

## Migration Plan

1. **Phase A release:** migration for `reservation_id` on reviews/disputes, backend readers, new admin route, frontend cutover. Run the backfill and verify mapping count = booking count on each environment.
2. **Phase B:** schedule `v1:adoption-snapshot --days=30` daily. Review the latest snapshot weekly. The gate opens when the spec conditions hold and `v1:retirement-readiness` passes.
3. **Phase C release:** delete routes and code, and freeze the projection. Do not run any migration that alters V1 tables.
4. **Rollback:**
   - Phase A: redeploy the previous frontend and backend. The `reservation_id` columns are additive, and their `down()` drops them.
   - Phase C: redeploy the previous release. The V1 tables are unchanged. Bookings made through V2 in the meantime stay valid.

## Implementation Decisions (Phase A, 2026-10-11)

Decisions made while applying the change, with the options considered. Contracts are restated where they refine the decisions above.

- **I1. Scope of this apply: Phase A code only.** Task 0.1 is open (`harden-db-integrity` is not archived and production does not have the exclusion constraint). Phase A does not depend on it at runtime; Decision 6 needs it before Phase C. Tasks 1.2 and 4.x write to bt-server and production and need the owner to run them. Phase B needs 30 real days, and Phase C needs approval (6.1). Task 5.1 is code (a scheduler entry), so it ships with Phase A and starts the evidence window on deploy.
- **I2. Coupon at V2 checkout is not added in Phase A; the coupon case leaves 3.7.**
  - **Facts:** the court UI never sends a coupon. On `/payment`, `POST bookings/{code}/coupon` returns 409 for direct-owner bookings and 422 for legacy-wallet bookings, which are already confirmed. So no customer can apply a coupon at checkout today. `checkoutV2` has no coupon reserve or release.
  - **Options:**
    - **(A)** Add coupon reserve, consume and release to the V2 quote and checkout now.
    - **(B)** Keep Phase A to pointing existing flows at V2.
  - **Chosen: B.** A is a pricing change, which this change lists as a non-goal. B removes nothing a user can reach.
  - The main spec still requires V1 and V2 to share coupons, so the gap is tracked as task 5.5: a separate change that must be archived before Phase C.
- **I3. One effective-status rule.**
  - Reports and the returning-customer check treat a mapped mirror (`status = legacy`) by its mapping's `legacy_status`, and every other reservation by `reservations.status`.
  - Report numbers must equal the old report on the same data. A parity test with literal expectations was written green against the old code first.
  - Owner-payment receipts are used only where the old report read payments. Wallet and zero-payable bookings have no receipts, so receipts alone cannot reproduce the old revenue.
  - "Every other reservation" includes a mirror whose status is not `legacy`. V1 writers keep that status current in V2 terms (I14).
  - A V1 no-show leaves its mirror `confirmed`, as a V2 no-show does: `AttendanceService::markNoShow` records attendance and keeps the reservation `confirmed`.
  - The same rule is used by the report and its CSV status, the returning-customer check, `CreateReview` and the frontend status display.
  - **Options** (decided when I14 made the admin no-show sync `legacy_status`):
    - **(A)** Read every mapped mirror by `legacy_status` (`COALESCE`). A V1 no-show would leave the report while a V2 no-show stays in. It also breaks parity, because the old report counted that mirror as confirmed.
    - **(B)** Also move the mirror to a `no_show` status. V2 has no such reservation status.
    - **(C)** Apply the rule as written.
  - **Chosen: C.** The parity literals stay unchanged. The old report never reads `legacy_status`, so the I14 sync does not move its numbers on the parity dataset.
  - Two branch tests that built a V1 cancellation by hand, leaving the mirror `confirmed`, now use a V1 no-show and a mirror that was already `legacy`. No writer produces that cancellation: the owner-refund and reschedule paths cancel the mirror too.
- **I4. Court and home checkout make one call.**
  - Both post `{court_id, slots, loyalty_points?, loyalty_policy_version?}` to `POST v2/reservations/checkout` through a `bookingStore` action. The server creates the quote and checks out against it in one transaction (`resolveQuote`).
  - A separate client quote step would add a round trip and a quote countdown that the court UI does not show. Prices are server-calculated either way.
  - Loyalty fields are sent only when points > 0 (`min:1`).
- **I5. Cross-org lookup.**
  - **Owners:** `GET v2/me/reservations` lists the caller's reservations across organizations. `v2/reservations/{code}` (show, payment, confirm) resolves the caller's own reservation in any organization, then binds the tenant context to that reservation's organization.
  - **Operators:** they still see only their context organization.
  - **Quotes:** `resolveQuote` filters by organization, so a quote token cannot cross tenants.
  - Without these, a deep link for a user with several organizations returns 404.
- **I6. Reviews.**
  - The V1 eligibility is translated as:
    - reservation `confirmed`, `in_use` or `completed`, or a mirror whose `legacy_status` is `confirmed`, `no_show` or `completed`;
    - all slots ended;
    - one review per reservation (409).
  - For mapped mirrors the review also stores the legacy `booking_id`. The request never accepts booking fields. The V1 history and the migration's `down()` keep working.
- **I7. Disputes.**
  - The party is the reservation owner or an entitlement holder.
  - The duplicate check keeps its existing scope, keyed by `reservation_id`.
  - `reference_type` and `reference_id` record the reservation, so the link survives a rollback of the column.
  - `amount_disputed` falls back to the reservation total.
  - `ArbitrateDispute` links the refund obligation to the reservation and takes the court and slots from it when there is no booking.
- **I8. Migration.**
  - `reviews.reservation_id` is unique and cascades on delete, as `booking_id` does. `dispute_cases.reservation_id` is set to null on delete, like the other dispute FKs.
  - Both tables are backfilled through `legacy_booking_mappings`. Task 2.1 names only disputes; without reviews, historical reviews would vanish from reservation history.
  - `dispute_cases.booking_id` was already nullable.
  - `down()` copies `booking_id` back. It refuses to run while a review has no legacy booking, rather than delete it.
- **I9. Backfill and readiness.**
  - A booking that fails to map is reported as an orphan with reason `Mapping failed: …` and the run continues.
  - Reviews and disputes are re-linked after every non-dry run, not only after a valid one. On bt-server, zero-slot orphans keep `valid` false.
  - `v1:retirement-readiness` now requires `legacy_mapping_valid` and lists the unmapped bookings with reasons. This is the spec scenario "Historical booking without a mapping".
  - The mirror keeps the booking's `created_at`, so date-filtered reports do not shift.
- **I10. Origin of the unmapped bt-server bookings (task 1.1).**
  - **12 `direct_owner` `BK-HIST-*` rows:** written inline by `frontend/e2e/p1-pricing-promotions-loyalty.spec.ts`. It uses the literal `payment_model` `direct_owner`, which no app writer produces. The rows were written when the p1 specs ran with `test:e2e:prod` against the database in `backend/.env`.
  - **50 zero-slot rows (`BK-CANON`, `BK-DSP`, `BK-SEC`, `BK-EVD`, `BK-REM`):** written by the reporting and dispute specs.
  - **23 `DEMO-BK-*` rows:** written by `BookingPaymentSeeder`.
  - **App writers:** all go through `checkoutV1`, which always backfills.
  - **Fixes:** the seeder now maps every booking it writes. The specs write V2 rows, or bookings with slots mapped through `backfillBooking`. The existing rows are left for the reviewed 1.2 run.
- **I11. E2E pilot list.** The local E2E backend uses `PILOT_ORGANIZATION_IDS = org1,org2` instead of `*`, so a legacy-wallet cohort organization can exist in the 3.7 tests.
- **I12. Frontend dead code.**
  - Removed with the cutover, because nothing references them any more: `bookingStore.createBooking`, `bookingApi.create` and `bookingApi.list`, the admin `getBookings`, and the V1 `markNoShow`. The admin dashboard has no no-show action, so `markNoShow` was already unused; venue no-show stays on `POST v2/attendance/no-show`.
  - Kept until Phase C: `/payment/:code`, `PaymentView`, `profileApi.getBookings` (task 6.7) and the V1 backend routes.
- **I13. Release order (no change to 4.1).**
  - Reports filter on `created_at`. Ship the report rewrite after the 1.2 run has aligned mirror `created_at` values on each environment.
  - Check drift with `SELECT count(*) FROM legacy_booking_mappings m JOIN bookings b ON b.id = m.legacy_booking_id JOIN reservations r ON r.id = m.reservation_id WHERE r.created_at <> b.created_at`.
  - `--audit` and `--dry-run` report the drift as `created_at_drift`. A real run sets each drifted mirror's `created_at` to its booking's, reports `created_at_aligned`, and honours `--organization`. Drift does not change `valid`.
  - **Options:** a manual `UPDATE` on each environment, or the backfill run. **Chosen: the run.** It is already the reviewed write step on every environment (1.2, 4.2), it has a dry-run preview, and it is tested.
- **I14. V1 writers that still run keep the mirror in sync.**
  - Phase A readers read the mirror, so a V1 transition that leaves it stale shows the wrong history and report figures. It also turns into a permanent checksum collision that blocks the gate with no fix path.
  - `ExpirePendingBookings` now checksums freshly loaded slots.
  - Admin no-show (`AdminController::noShow`) now syncs the projection. The mirror stays `confirmed`, as a V2 no-show does (I3).
  - The V1 confirm branch of `ReviewPayment` confirms the mirror reservation, its slots and its entitlements, then syncs.
  - The backfill reports `valid: false`, and exits 1, while any collision remains.
  - Option considered: leave V1 untouched (plan D36). That was rejected, because it makes readiness fail for mapped bookings and report figures drift.
- **I15. Report data source (task 2.4).**
  - Reports read reservations only, with the I3 status rule. Owner-payment receipts are not read, because the old report never read payments for these figures.
  - A literal parity test (`ReportParityTest`) passed against the old code before the rewrite, and still passes after it.
  - Known residual differences, outside the parity dataset:
    - A V1 booking confirmed through `ReviewPayment`, or marked no-show, before I14 shipped is a checksum collision in the 1.2 audit. The run reports it and does not repair it; the owner reviews each one. Until then, a confirmed booking with a held mirror is left out of the report.
    - Revenue is filtered on `reservations.created_at`. Mirrors created before I9 need the I13 alignment.


## Implementation Decisions (Phase C, 2026-10-13)

- **C1. Approval and gate (tasks 5.2–5.4, 6.1).**
  - The project owner approved Phase C on 2026-10-13 in the working session ("bỏ hẳn v1 đi, chỉ dùng v2 thôi rồi đổi tên") and chose to drop the version prefix (C5).
  - That decision skipped the 30-day adoption gate (5.2) and the production `v1:retirement-readiness` run (5.4). The retirement relied on the 5.3 precheck instead.
  - 5.3 on production (read-only, 2026-10-13):
    - 72 bookings and 72 mappings, 0 unmapped;
    - 0 active future booking slots without an active mapped reservation slot;
    - 3 V1 bookings still `pending_payment` (`DEMO-BK-066`, `067`, `069`), each mapped to a held reservation that expires between 2026-10-18 and 2026-10-20;
    - 16 V1 bookings confirmed for future times;
    - no booking ever used a coupon.
  - 0.1: `reservation_slots_no_overlap` exists on production (checked 2026-10-13). `harden-db-integrity` is applied but not archived yet.
- **C2. Frozen V1 rows.**
  - After Phase C nothing writes `bookings`, `booking_slots` or the mapping tables, except `backfillBooking()` for seeds and fixtures.
  - The 3 pending demo bookings therefore stay `pending_payment` in `bookings` while the sweep expires their reservations. Their live status is the reservation's.
  - **Options:** a one-off data fix that expires them in `bookings`, or leave them. **Chosen: leave them.** The tables are frozen history by design (decision 2), and every reader reads the mirror.
- **C3. Expiry sweep kept, V1 reminder removed (task 6.3).**
  - Task 6.3 lists `ExpirePendingBookings` for deletion, but it is also the only sweep for held reservations.
  - **Options:** delete it and write a new reservation sweep, or keep it and drop its V1 loop. **Chosen: keep it.** The scheduled task keeps the name `booking:expire-pending`.
  - It releases the reserved coupon use (C4) and reserved points, then notifies the waitlist and the customer.
  - `NotifyExpiringBookings`, the V1-only reminder, is deleted with its schedule. Reservations never had a reminder, and none is added.
- **C4. Coupons on reservations (task 5.5) folded in.**
  - Quote, hold and checkout accept `coupon_code`. The quote validates it strictly, minimum spend included.
  - Hold and checkout reserve one use under a row lock and record it in `metadata.coupon_usage`. Confirmation keeps it. Expiry or release of a held reservation gives it back exactly once (`released_at`).
  - The Playwright coupon case from 5.5 is not written yet (6.10).
- **C5. Unversioned API.**
  - **Options:** `/api/*` with no version, keep `/api/v2`, or move to `/api/v3`. **Chosen by the owner: `/api/*`.**
  - The two route groups merge. Former V1 routes stay top-level. Former V2 routes stay in the `ResolveTenantContext` group, so each route keeps its middleware.
  - Where both versions defined the same path, one is kept:

    | Path | Kept | Why |
    |---|---|---|
    | `/api/admin/disputes*` | former V2 | owner or manager, scoped to their organization; the frontend already used it |
    | `/api/disputes*` | former V1 | customer disputes |
    | `/api/admin/kyc*` | former V2 | platform admin only |
    | `/api/organizations*`, `/api/notifications*`, `GET /api/wallet`, `/api/wallet/deposits*`, `/api/admin/reports*` | former V1 | the frontend used these |
    | `GET /api/capabilities` | former V2 | organization-scoped capabilities |
    | `POST /api/webhooks/sepay` | former V1 | SePay wallet deposits and POS QR; the former V2 `webhooks/{provider}` route still serves other providers such as `payos`, and nothing called `/api/v2/webhooks/sepay` |

  - `PosController::wallet`, the former `GET v2/wallet`, is deleted. `GET /api/wallet` serves the wallet.
  - Code that matched a version segment now matches the unversioned path: `request()->is(...)` in `Venue`, `Court`, `SportZone`, `SportZoneController`, `AdminController`, `ResolveTenantContext` and `SimulateDemoMutations`. Without this, admin venue serialization and tenant scoping broke.
  - Renamed: `V2ReservationController` → `ReservationController`, `V2PriceRuleController` → `PriceRuleController`, `ResolvesV2Organization` → `ResolvesOrganization`.
  - Kept names: the `v1:*` and `v2:*` artisan commands, `V1AdoptionService`, and the `AURA_V2_RESERVATION_` QR prefix, which is printed on existing QR codes.
  - External URLs change at deploy: the Google and Facebook OAuth redirect URIs, and the SePay webhook URLs.
  - Idempotency records store method and path, so a retry that crosses the deploy boundary does not replay. This is acceptable.
  - The frontend now has one HTTP client, which sends an `Idempotency-Key` on every mutation as the former V2 client did. Former V1 routes that do not read the header ignore it.
  - Demo-account write simulation still covers only the former V1 admin routes, as before.
- **C6. Dispute arbitration gap, not changed.** `/api/admin/disputes/{id}/arbitrate` lets an owner of the dispute's organization decide, while the support-case spec reserves decisions for platform reviewers. This predates the change and stays out of scope.
- **C7. Reconciliation with `direct-owner-cutover` (its task 12.1).**
  - That change's wording is applied to this delta: the client list drops "booking changes", the superseded-by-policy sentence is gone, and the stale-client scenario names the removed cancellation, refund and reschedule commands.
  - This change renames the requirement (`Versioned reservation API compatibility` → `Unversioned reservation API`). Whichever change archives second rebases its MODIFIED block, header included, onto the main spec (its design 14).
