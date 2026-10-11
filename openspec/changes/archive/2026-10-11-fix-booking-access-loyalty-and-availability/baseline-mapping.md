# Baseline Test Results and Scenario-to-Regression Mapping

This artifact documents the verified baseline test executions and maps every scenario from the 5 delta specifications to its targeted regression test check.

---

## 1. Baseline Test Execution Results (5 Core Areas)

### 1.1 Authentication & Login Baseline
* **Target:** Authentication lifecycle and mode routing
* **Command:** `cd frontend && npx vitest run src/features/auth/__tests__/LoginView.spec.ts`
* **Runner:** Vitest v4.1.10 / Node v22 / npm 11.13.0
* **Result:** Passed (1 test file, 4 tests passed, duration 1.15s)
* **Observed Baseline Behavior:** Mode switching between login and register without inverse guest guard on direct URL entry or reload.

### 1.2 Administrative Venue API Consumers Baseline
* **Target:** Administrative venue list client contract
* **Command:** `cd frontend && npx vitest run src/features/admin/__tests__/AdminVenuesView.spec.ts`
* **Runner:** Vitest v4.1.10 / Node v22 / npm 11.13.0
* **Result:** Passed (1 test file, 5 tests passed, duration 1.54s)
* **Observed Baseline Behavior:** Venue list consumers called administrative venue endpoints where loose test mocks accepted `/api/v2/admin/venues` despite lack of a backend v2 route.

### 1.3 POS Capabilities Baseline
* **Target:** Scoped tenant roles and POS capability gates
* **Backend Command:** `cd backend && php artisan test tests/Feature/TenantIsolationAndRoleMatrixTest.php`
  * **Runner:** PHPUnit 11.5 / PHP 8.5.10
  * **Result:** Passed (7 tests, 40 assertions, duration 1.25s)
* **Frontend Command:** `cd frontend && npx vitest run src/features/pos/__tests__/pos-regressions.spec.ts`
  * **Runner:** Vitest v4.1.10 / Node v22 / npm 11.13.0
  * **Result:** Passed (1 test file, 7 tests passed, duration 1.75s)
* **Observed Baseline Behavior:** Global role checks coupled with platform admin; disabled feature gate returned generic English 404; POS loaded products concurrently on initialization.

### 1.4 Ordinary V1/V2 Wallet Checkout Baseline
* **Target:** Ordinary atomic wallet-funded checkout and point ledger
* **Checkout Command:** `cd backend && php artisan test tests/Feature/OrdinaryWalletCheckoutTest.php`
  * **Runner:** PHPUnit 11.5 / PHP 8.5.10
  * **Result:** Passed (13 tests, 98 assertions, duration 2.67s)
* **Point Ledger Command:** `cd backend && php artisan test tests/Feature/PosMembershipWalletTest.php`
  * **Runner:** PHPUnit 11.5 / PHP 8.5.10
  * **Result:** Passed (8 tests, 21 assertions, duration 1.68s)
* **Observed Baseline Behavior:** V1/V2 checkout creates and confirms bookings atomically via wallet with hold expiry and coupons; standalone `/api/v2/loyalty/redeem` existed as debit-only endpoint with no discount benefit or booking checkout link.

### 1.5 Availability Baseline
* **Target:** Home availability presentation and court context
* **Command:** `cd frontend && npx vitest run src/features/home/__tests__/HomeView.spec.ts`
* **Runner:** Vitest v4.1.10 / Node v22 / npm 11.13.0
* **Result:** Passed (1 test file, 2 tests passed, duration 1.24s)
* **Observed Baseline Behavior:** Single court availability rendered with a fixed title regardless of selected date; un-guarded availability responses susceptible to out-of-order resolution.

---

## 2. Target Regression Checks for Delta-Spec Scenarios (Target verification defending requirements as implementation proceeds)
### 2.1 Capability: `account-lifecycle`
*Spec file:* `specs/account-lifecycle/spec.md`

| # | Scenario Name | Target Test File | Target Test Description / Assertion |
|---|---------------|-------------------|-------------------------------------|
| 1 | Direct navigation with a valid session | `src/features/auth/__tests__/guestNavigation.spec.ts` | `redirects authenticated onboarded customer away from /login to home` & `restores valid customer session on direct URL entry or reload` |
| 2 | Onboarding is incomplete | `src/features/auth/__tests__/guestNavigation.spec.ts` | `redirects authenticated user with incomplete onboarding to /onboarding` |
| 3 | Unsafe or unauthorized redirect | `src/features/auth/__tests__/guestNavigation.spec.ts` | `rejects unsafe external redirect query parameter and redirects to role default` & `rejects unauthorized admin redirect for customer` |
| 4 | Expired session | `src/features/auth/__tests__/guestNavigation.spec.ts` | `forces session resolution and does not redirect when cached user session has expired in backend` & `leaves guest-only routes accessible for anonymous or expired sessions` |

---

### 2.2 Capability: `api-alignment`
*Spec file:* `specs/api-alignment/spec.md`

| # | Scenario Name | Target Test File | Target Test Description / Assertion |
|---|---------------|-------------------|-------------------------------------|
| 1 | Venue and court management load | `src/features/admin/__tests__/AdminVenuesView.spec.ts` | `loads venue list via api.get('/admin/venues') and renders venue data without 404` |
| 2 | Shared venue consumers load | `src/features/admin/__tests__/AdminVenueConsumers.spec.ts` | `staff, schedule, pricing, organization and shift views query v1 admin venues endpoint` |
| 3 | Organization capability is disabled | `src/features/admin/__tests__/PosCapabilities.spec.ts` | `displays capability disabled explanation rather than route not found or unauthenticated` |
| 4 | Locale changes | `src/shared/components/__tests__/ErrorModal.spec.ts` | `dynamically updates modal title, message, and button labels when locale changes between vi and en` |
| 5 | Concurrent failures | `src/shared/components/__tests__/ErrorModal.spec.ts` | `deduplicates parallel capability errors into a single dialog and preserves form/cart state` |
| 6 | Keyboard interaction | `src/shared/components/__tests__/ErrorModal.spec.ts` | `traps focus inside dialog on open, supports Escape key dismissal, and restores focus on close` |
| 7 | Unknown service failure | `src/shared/components/__tests__/ErrorModal.spec.ts` | `renders localized generic fallback message without leaking raw stack trace or internal routes` |
| 8 | Disabled POS on entry | `src/features/pos/__tests__/pos-regressions.spec.ts` | `displays disabled capability banner and blocks checkout when tenant_pos_and_wallet is false` |
| 9 | Capability lookup fails | `src/features/admin/__tests__/useTenantCapabilities.spec.ts` | `sets status to error on fetch failure without concluding capability is permanently disabled` |
| 10 | Organization changes | `src/features/admin/__tests__/useTenantCapabilities.spec.ts` | `resets capability state on organization switch and discards late responses from prior context` |

---

### 2.3 Capability: `organization-access`
*Spec file:* `specs/organization-access/spec.md`

| # | Scenario Name | Target Test File | Target Test Description / Assertion |
|---|---------------|-------------------|-------------------------------------|
| 1 | Owner uses their organization and POS | `backend/tests/Feature/AdminOrganizationAccessTest.php` | `organization owner accesses POS and organization views without platform admin role` |
| 2 | Cashier attempts owner administration | `backend/tests/Feature/AdminOrganizationAccessTest.php` | `cashier role is forbidden from organization management and platform admin child routes` |
| 3 | Confirmed account receives organization access | `backend/tests/Feature/ProvisionPosAccountTest.php` | `runbook assigns scoped organization owner membership without resetting password or granting platform admin` |
| 4 | Target information is missing | `backend/tests/Feature/ProvisionPosAccountTest.php` | `provisioning command aborts without mutation when target account or organization is omitted` |
| 5 | Role exists but capability is disabled | `backend/tests/Feature/AdminOrganizationAccessTest.php` | `returns 404 with CAPABILITY_DISABLED when organization member attempts access with feature flag disabled` |

---

### 2.4 Capability: `point-of-sale-and-loyalty`
*Spec file:* `specs/point-of-sale-and-loyalty/spec.md`

| # | Scenario Name | Target Test File | Target Test Description / Assertion |
|---|---------------|-------------------|-------------------------------------|
| 1 | Membership plan changes | `backend/tests/Feature/PosMembershipWalletTest.php` | `entitlements preserve assigned plan version even after tenant publishes updated plan version` |
| 2 | Customer redeems loyalty points | `backend/tests/Feature/LoyaltyCheckoutTest.php` | `ordinary booking checkout atomically applies loyalty discount and writes immutable point debit` |
| 3 | Benefit persistence fails | `backend/tests/Feature/LoyaltyCheckoutTest.php` | `transaction rollback restores point ledger and leaves wallet balance unchanged on failure` |
| 4 | Legacy debit-only request | `backend/tests/Feature/PosMembershipWalletTest.php` | `POST /api/v2/loyalty/redeem with arbitrary payload rejects with 422 and mutates zero points` |
| 5 | Preview does not spend points | `backend/tests/Feature/LoyaltyPreviewPolicyTest.php` | `previewing 100 points for 5,000 VND discount returns calculation without mutating point ledger` |
| 6 | Excessive or ineffective redemption | `backend/tests/Feature/LoyaltyPreviewPolicyTest.php` | `rejects points exceeding balance, points producing 0 VND discount, or discount exceeding subtotal` |
| 7 | Policy changes after preview | `backend/tests/Feature/LoyaltyCheckoutTest.php` | `checkout rejects with 422 version mismatch when policy version changes between preview and commit` |
| 8 | Earn multiplier is newer than redemption policy | `backend/tests/Feature/LoyaltyPreviewPolicyTest.php` | `preview resolves base redemption rule ratio even when a multiplier rule has higher version number` |
| 9 | Response is lost after commit | `backend/tests/Feature/LoyaltyCheckoutTest.php` | `idempotent checkout replay with identical key returns cached booking without duplicate debit` |
| 10 | Two checkouts compete for points | `backend/tests/Feature/LoyaltyCheckoutTest.php` | `concurrent checkouts against limited balance accept exactly one and reject the competing request` |
| 11 | Foreign or already-paid source | `backend/tests/Feature/LoyaltyCheckoutTest.php` | `redemption referencing another tenant's booking or already-paid booking is rejected with 422` |

---

### 2.5 Capability: `reservations-and-pricing`
*Spec file:* `specs/reservations-and-pricing/spec.md`

| # | Scenario Name | Target Test File | Target Test Description / Assertion |
|---|---------------|-------------------|-------------------------------------|
| 1 | Tampered discount | `backend/tests/Feature/OrdinaryWalletCheckoutTest.php` | `checkout rejects unearned coupon or discount tampering and recomputes authoritative total` |
| 2 | Legacy unpaid hold | `backend/tests/Feature/OrdinaryWalletCheckoutTest.php` | `confirming reservation without wallet funds fails without creating active entitlement` |
| 3 | Customer discovers the loyalty control before payment | `frontend/src/features/booking/__tests__/LoyaltyPointSelector.spec.ts` | `renders localized points selector before checkout button showing organization balance and preview` |
| 4 | Atomic points and wallet checkout | `backend/tests/Feature/LoyaltyCheckoutTest.php` | `checkout records point debit, loyalty reduction, charges net wallet remainder and confirms slots atomically` |
| 5 | Failure after a partial checkout step | `backend/tests/Feature/LoyaltyCheckoutTest.php` | `injected failure rolls back point debit, wallet debit, booking creation and financial ledger` |
| 6 | Loyalty covers the payable amount | `backend/tests/Feature/LoyaltyCheckoutTest.php` | `100% point-funded booking creates zero-payable confirmation without fabricated wallet transaction` |
| 7 | Reload after successful checkout | `frontend/src/features/booking/__tests__/BookingDetailLoyalty.spec.ts` | `booking detail view renders points used, loyalty discount amount and net wallet paid from snapshot` |
| 8 | One court is booked from 19 to 21 | `frontend/src/features/home/__tests__/HomeAvailability.spec.ts` | `Court A booked 19:00–21:00 disables exactly 19:00–20:00 and 20:00–21:00 under Court A heading` |
| 9 | Another court remains available | `frontend/src/features/home/__tests__/HomeAvailability.spec.ts` | `switching to Court B shows 19:00–20:00 and 20:00–21:00 slots available under Court B heading` |
| 10 | Different play date | `frontend/src/features/home/__tests__/HomeAvailability.spec.ts` | `selecting different date renders slots for that date without misleading today label` |
| 11 | Court switches while requests are pending | `frontend/src/features/home/__tests__/HomeAvailability.spec.ts` | `late response for Court A cannot overwrite newer availability for Court B in store or view` |
