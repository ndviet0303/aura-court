## 1. Retire booking mutation paths

- [x] 1.1 Trace every production caller of whole/partial V1 cancellation, rescheduling, V2 cancellation and admin cancellation-refund processing; distinguish shared expiry/finance helpers and out-of-scope lifecycle callers before deletion.
- [x] 1.2 Remove the three V1 booking mutation routes and corresponding controller/service/action/request wiring. Preserve create, payment, history, attendance and automatic pending-booking expiry paths.
- [x] 1.3 Remove V2 cancellation route/controller/service action and unused wrapper wiring. Preserve V2 inventory-release functionality required by automatic expiry and specialized lifecycle contracts.
- [x] 1.4 Remove legacy admin refund PATCH route and `ProcessRefund` mutation wiring. Retain authorized GET history, refund records and shared financial posting functions still used by supported workflows.

## 2. Remove obsolete policy configuration

- [x] 2.1 Remove organization cancellation-grace and membership cancellation-waiver writes; return validation errors for obsolete submitted fields and prevent alternate write paths from restoring these benefits.
- [x] 2.2 Remove cancellation-policy validation from venue readiness and obsolete fields from new snapshot builders while preserving existing snapshots and all other readiness/price rules.
- [x] 2.3 Remove frontend organization/membership policy controls, related payload fields and advertised cancellation benefits, including obsolete benefits in dynamic public presentation.

## 3. Remove customer actions and retain read-only history

- [x] 3.1 Remove V1 cancellation/rescheduling client methods, composable state/handlers, cancel modal and booking-card actions; retain history filters/status labels and supported payment/check-in/review actions.
- [x] 3.2 Remove V2 cancellation client methods, composable state/handlers, card/detail actions and held-banner cancellation; retain countdown, payment/confirmation and automatic expiry behavior.
- [x] 3.3 Convert the existing admin cancellation-refund panel to read-only historical presentation, remove approval/rejection calls and actionable pending-refund KPI, and reuse authorized listing to include historical statuses without altering any row.
- [x] 3.4 Update Vietnamese/English court, checkout, booking and membership copy to state no cancellation or rescheduling; remove operation-only translations/types/styles while preserving historical financial labels and generic back/close controls.

## 4. Verify backend invariants and unaffected lifecycles

- [x] 4.1 Replace retired-operation success tests in `BookingApiTest`, `BookingFlowsTest`, `OrdinaryWalletCheckoutTest` and `ConcurrentSlotAndWalletCheckoutTest` with HTTP 404/no-side-effects coverage for whole/partial/V2 cancellation and cheaper/equal/more-expensive rescheduling, including repeated requests and V2 previously used idempotency keys.
- [x] 4.2 Update suspended-partner and policy-snapshot tests to reject retired commands for existing/new bookings, customer/staff/admin actors and legacy unpaid holds while preserving historical reads and captured data.
- [x] 4.3 Add admin retirement/read-only history coverage: approval and rejection fail without changing pending/completed refund rows, bookings, wallet balances or journals; authorized history remains accessible and existing access restrictions remain enforced.
- [x] 4.4 Assert blocked commands do not change slot/entitlement state, totals, coupon usage, wallet transactions, journals or replacement-booking counts and do not emit availability/waitlist notifications.
- [x] 4.5 Verify V1/V2 automatic unpaid expiry remains retry-safe, releases eligible inventory/coupon usage and produces required after-commit availability/waitlist updates without crediting wallets or releasing confirmed paid bookings; adapt cancellation-triggered waitlist tests to supported release paths.
- [x] 4.6 Test obsolete policy-write rejection, readiness without cancellation configuration, preserved historical snapshots and continued ordinary wallet checkout/check-in behavior.
- [x] 4.7 Run existing historical refund/ledger/settlement and cancelled-booking check-in tests, plus marketplace/split, recurring-overdue, dispute, POS and subscription regressions; retain unrelated financial behavior and historical report amounts.

## 5. Verify frontend and document the release

- [x] 5.1 Update/add frontend tests for absent V1/V2 cancellation/rescheduling controls and calls, read-only admin refund history, removed policy fields and correct Vietnamese/English notices. Verify historical cancelled/refunded labels and normal supported actions remain usable.
- [x] 5.2 Run the project's backend/frontend test, type-check and build commands in proportion to the affected modules; record results and any pre-existing failures separately.
- [x] 5.3 Search active application code and documentation for retired API callers and cancellation/rescheduling promises; remove remaining in-scope references without altering archive artifacts or unrelated refunds/cancellation lifecycles.
- [x] 5.4 Document coordinated backend/frontend rollout, old-instance/worker draining, retired-URL smoke checks, read-only pending-refund inventory and rollback constraints. Record that a later explicit spec sync/archive must correct the main wallet spec Purpose as well as merge all five deltas; do not deploy or archive automatically.
