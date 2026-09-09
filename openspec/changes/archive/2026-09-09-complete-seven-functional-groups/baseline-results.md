# Apply baseline results

## Safety preflight and test database isolation

PHP CLI: 8.5.10. Node: v24.12.0.

Explicit environment variables used for all backend test runs:
```bash
APP_ENV=testing DB_CONNECTION=pgsql DB_DATABASE=booking_test DB_URL=''
```

Verification evidence:
- Application bootstrap reports environment `testing`, configuration cache disabled.
- PostgreSQL host: `bt-server`, target database: `booking_test`. No database URL override.
- A read-only query `select current_database()` returned `booking_test`.
- Test database isolation confirmed: no production database or live tenant data is touched.
- No manual database reset or destructive migration was executed against unverified connections.

## Frontend baseline results

Command in `frontend`:
```bash
npm run test:unit -- --run
```
- Total test files: 45 passed (45)
- Total tests: 163 passed (163)
- Runtime: ~13.5 seconds
- Includes JSDOM storage isolation fix, existing component suites, plus new Task 1.2 POS and tournament regression suites.

Command in `frontend`:
```bash
npm run type-check
```
- Tool: `vue-tsc --build`
- Result: 0 errors.

## Backend baseline results

Commands in `backend`:
```bash
APP_ENV=testing DB_CONNECTION=pgsql DB_DATABASE=booking_test DB_URL='' php vendor/bin/phpunit tests/Unit
```
- Unit test suite: 2 files, 5 tests, 5 passed (100%).

```bash
APP_ENV=testing DB_CONNECTION=pgsql DB_DATABASE=booking_test DB_URL='' php vendor/bin/phpunit tests/Feature/SevenGroupsRegressionTest.php
```
- Task 1.2 Regression suite: 4 tests, 17 assertions, 4 passed (100%).

Feature suite execution characteristics:
- Remote PostgreSQL connection to `bt-server` incurs network round-trip overhead on per-test database transactions.
- Running `RefreshDatabase` runs schema migrations on the remote server once per suite invocation.
- The readiness test `ConcurrencyAndLoadBaselineTest.php` simulates 100 concurrent user creations, 100 wallet funding transactions, and 100 booking attempts sequentially over the network, contributing significantly to suite run duration.
- Individual feature suites (`PosMembershipWalletTest`, `SevenGroupsRegressionTest`, etc.) pass cleanly when executed against `booking_test`.

## Defect reproduction summary (Task 1.2)

All five required defect scenarios are reproduced in executable automated regression tests:

1. **POS false success**:
   - Location: `frontend/src/features/pos/__tests__/pos-regressions.spec.ts` (`reproduces POS false success`) and `backend/tests/Feature/SevenGroupsRegressionTest.php` (`test_reproduce_pos_false_success_backend_order_rejection_leaves_no_financial_records`).
   - Defect: When `posApi.completeOrder` fails or rejects (e.g. 422 or 409 stock shortfall), `PosView.vue`'s catch block swallows the error, creates client receipt data, deducts stock locally, clears the cart, and displays an order success banner. Backend records zero orders and zero stock movements.

2. **Receipt mismatch**:
   - Location: `frontend/src/features/pos/__tests__/pos-regressions.spec.ts` (`reproduces receipt mismatch`).
   - Defect: The displayed receipt renders client-computed prices, discounts, taxes, and a random `POS-${Math.random()}` code instead of reflecting the authoritative server order ID, server order code, and financial totals returned in `completeOrder`.

3. **Wrong reservation identity**:
   - Location: `frontend/src/features/pos/__tests__/pos-regressions.spec.ts` (`reproduces wrong reservation identity`) and `backend/tests/Feature/SevenGroupsRegressionTest.php` (`test_reproduce_pos_wrong_reservation_identity_with_v1_booking_id`).
   - Defect: `posApi.getActiveReservations()` queries `/admin/bookings?status=confirmed` (V1 Booking records) and sends `reservation_id: booking.id` to `POST /api/v2/pos/orders`. Because `pos_orders.reservation_id` foreign key points to V2 `reservations.id`, passing a V1 booking ID causes a foreign key constraint violation (SQLSTATE[23503] 500 error) or attaches to an unrelated reservation.

4. **Unpaid V2 confirmation**:
   - Location: `backend/tests/Feature/SevenGroupsRegressionTest.php` (`test_reproduce_unpaid_v2_confirmation_confirms_without_wallet_debit`).
   - Defect: Calling `POST /api/v2/reservations/{code}/confirm` transitions the held reservation to `confirmed` and activates entitlements with 200 OK without verifying or debiting the user's wallet. A customer with a 0 VND wallet balance confirms a 200,000 VND reservation free of charge without any ledger or payment transaction.

5. **Local tournament registration**:
   - Location: `frontend/src/features/tournament/__tests__/tournament-regressions.spec.ts` and `backend/tests/Feature/SevenGroupsRegressionTest.php` (`test_reproduce_tournament_lacks_customer_registration_and_payment_endpoint`).
   - Defect: `useTournamentsList.registerAthlete` increments reactive `current_slots` and appends tournament IDs in local reactive memory; `usePaymentProcessing.handleWalletPay` sets `status.value = 'confirmed'` without calling any backend endpoint to deduct wallet balance or register the participant. The registration state is lost on page reload. Backend exposes no customer tournament registration endpoint (returns 404).

## Status of Section 1

- [x] 1.1 Acceptance matrix complete (`acceptance-matrix.md`)
- [x] 1.2 Regression defects reproduced and verified (`SevenGroupsRegressionTest.php`, `pos-regressions.spec.ts`, `tournament-regressions.spec.ts`)
- [x] 1.3 Baseline contract inventory complete (`contracts-inventory.md`)
- [x] 1.4 Baseline test commands and results recorded, test database verified isolated (`baseline-results.md`)
