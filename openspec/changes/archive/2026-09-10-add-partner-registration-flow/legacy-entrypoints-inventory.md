# Legacy Entry Points and Bypasses Inventory

## 1. Overview and Purpose

This inventory documents all existing codebase entry points that manage onboarding, KYC verification, venue activation, and booking creation. It details the structural bypasses, architectural violations, and missing security/business boundaries that must be remediated in this change.

---

## 2. Inventory of Entry Points

### 2.1 Personal Onboarding and Owner Self-Grant
- **Route**: `POST /api/v1/auth/onboarding`
- **Controller**: `App\Http\Controllers\Auth\AuthController::onboarding`
- **Frontend Entry**: `frontend/src/features/auth/views/OnboardingView.vue` (724 lines)
- **Current Behavior**:
  - The controller performs inline validation of `account_type` (`customer` or `court_owner`).
  - If `court_owner` is supplied, it immediately upgrades the user's global role to `admin`:
    `$role = $accountType === 'court_owner' ? 'admin' : 'customer';`
  - It creates an `Organization` with `status => 'active'` and a slug derived from `organization_name`.
  - It creates an `OrganizationMember` with `role => 'organization_owner'` and `status => 'active'`.
  - It creates a `Venue` with `is_active => true`.
  - It creates up to 20 `Court` records with `is_active => true`.
  - It inserts 119 hourly `CourtPriceSlot` rows (7 days x 17 hours) with fixed hardcoded prices (120,000 VND).
- **Bypasses and Defects**:
  1. **Self-Granting Ownership**: Any registered user can grant themselves an organization owner role without review or verification.
  2. **Global Admin Contamination**: The user's system role is set to `admin`, conflating platform administrative access with tenant ownership.
  3. **Auto-Published Inventory**: Venues and courts are made publicly bookable instantly without quality, contact, or legal checks.
  4. **Architecture Violation**: The controller violates the Clean Architecture call flow: inline validation, inline Eloquent mutations, direct database loops, and zero FormRequest/DTO/Action separation.
  5. **Frontend Monolith**: `OnboardingView.vue` exceeds the 250-line view architecture budget nearly threefold and orchestrates personal profile setup alongside business and venue creation.
- **Required Remediation (Tasks 1.2, 4.1)**:
  - Redesign customer onboarding to complete only personal attributes (`phone`, `play_level`, `onboarded = true`).
  - Prohibit business entity creation and role elevation in `onboarding`.
  - When owner intent is indicated, provide an informative redirect and link to the new partner registration journey (`/partner/register`).
  - Extract the backend logic into `CompleteCustomerOnboarding` Action using `OnboardingRequest` and `OnboardingData` DTO.

---

### 2.2 KYC Verification and Review
- **Routes**:
  - V1: `GET /api/v1/admin/kyc`, `GET /api/v1/admin/kyc/stats`, `GET /api/v1/admin/kyc/{id}`, `POST /api/v1/admin/kyc/{id}/review`
  - V2: `GET /api/v2/admin/kyc`, `GET /api/v2/admin/kyc/stats`, `GET /api/v2/admin/kyc/{id}`, `POST /api/v2/admin/kyc/{id}/review`
- **Controllers & Actions**:
  - `App\Http\Controllers\Admin\AdminKycController`
  - `App\Domain\Admin\Actions\ReviewHostKyc`
  - `App\Domain\Admin\Actions\GetHostKycQueue`
  - `App\Domain\Admin\Actions\GetHostKycStats`
  - `App\Models\HostKyc`
- **Frontend Entries**:
  - `frontend/src/features/admin/views/AdminKycVerificationView.vue`
  - `frontend/src/features/admin/components/kyc/*`
- **Current Behavior**:
  - KYC records are stored in `host_kycs` with foreign keys `organization_id`, `venue_id`, `user_id`.
  - `ReviewHostKyc` simply updates `host_kycs.status` to `approved`, `rejected`, or `needs_supplement`.
  - Upon approval, it runs:
    ```php
    if ($newStatus === 'approved' && $kyc->organization) {
        $kyc->organization->update(['status' => 'active']);
    }
    ```
- **Bypasses and Defects**:
  1. **Premise Inversion**: The current flow assumes the organization and venue already exist *before* KYC submission.
  2. **Non-Atomic Provisioning**: Approval does not atomically create the organization or grant owner membership.
  3. **Concurrency and Stale Decisions**: No optimistic locking or version checking on the KYC application.
  4. **Tenant Isolation Leak in V2 Routes**: In `backend/routes/api.php`, the V2 `/admin/kyc` routes are mounted inside `Route::middleware([RequireOrganizationRole::class.':organization_owner,venue_manager'])`, which inadvertently permits tenant owners to access cross-tenant KYC queues if their role matches.
  5. **No Private Document Isolation**: Identity documents are stored as public disk paths or URLs without signed temporary URLs or read-access audit logging.
- **Required Remediation (Tasks 2.1, 2.2, 2.5, 2.6, 3.4, 3.5, 3.7)**:
  - Migrate `host_kycs` to act as the primary application record with nullable pre-approval organization links, application versions, and structured business payloads.
  - Implement `PartnerDocumentStorageInterface` for private file storage, MIME validation, temporary URL generation, and cleanup.
  - Replace `ReviewHostKyc` with `ReviewPartnerApplication` Action providing atomic creation of `organizations` and `organization_members` upon approval.
  - Fix route definitions so admin KYC endpoints require platform administrator privileges (`auth:sanctum`, `admin` middleware), not tenant organization roles.

---

### 2.3 Venue Activation and Management
- **Routes**:
  - `PATCH /api/v1/admin/venues/{venue}` -> `AdminController::updateVenue`
  - `POST /api/v1/admin/venues/{venue}/deactivate` -> `AdminController::deactivateVenue`
  - `POST /api/v1/courts` -> `CourtController::store`
  - `PUT /api/v1/courts/{id}` -> `CourtController::update`
  - `POST /api/v1/courts/{id}/deactivate` -> `CourtController::deactivate`
- **Models & Persistence**:
  - `App\Models\Venue` (`is_active` boolean)
  - `App\Models\Court` (`is_active` boolean)
  - `App\Models\SportZone` (`is_active` boolean)
- **Current Behavior**:
  - Venue activation is controlled entirely by a single boolean column: `venues.is_active`.
  - An owner or admin can toggle `is_active` to `true` at any time, even if the venue has no courts, no operating hours, no prices, or an unverified owner.
- **Bypasses and Defects**:
  1. **Missing Publication Lifecycle**: There is no distinction between a draft venue, a venue submitted for platform review, an approved venue, a temporarily closed venue, or a suspended venue.
  2. **Bypassed Quality Readiness**: Venues can be activated without courts, pricing, policies, or payment setups.
  3. **No Independent Platform Publication Review**: Partner approval automatically or manually enables venue publishing without platform content review.
  4. **Unstaged Material Changes**: Editing a published venue's location or business details takes effect immediately on the public catalog without review.
- **Required Remediation (Tasks 2.4, 6.1, 6.2, 6.3, 6.4, 6.5)**:
  - Add `status` lifecycle column to `venues`: `draft`, `pending_publication`, `revision_required`, `active`, `temporarily_closed`, `suspended`, `closed`, `archived`.
  - Retain `is_active` as a read-only compatibility projection (`status === 'active'`).
  - Implement `CheckVenueReadiness` Action validating mandatory prerequisites with actionable reason codes.
  - Implement `SubmitVenuePublication` and `ReviewVenuePublication` Actions with version tracking and public snapshot capture.
  - Enforce that material edits stage a publication revision while operational closures take immediate effect.

---

### 2.4 New-Booking and Catalog Entry Points
- **Routes**:
  - `POST /api/v1/bookings` -> `BookingController::store` -> `OrdinaryCheckoutService::checkoutV1`
  - `POST /api/v1/bookings/recurring` -> `BookingController::storeRecurring`
  - `POST /api/v2/price-quotes` -> `V2ReservationController::quote` -> `PriceQuoteService::create`
  - `POST /api/v2/reservations` -> `V2ReservationController::store` -> `OrdinaryCheckoutService::checkoutV2`
  - `POST /api/v2/recurring-series` -> `V2RecurringSeriesController::store`
  - `POST /api/v2/split-groups` -> `V2SplitPaymentController::store`
  - `GET /api/v1/availability` -> `AvailabilityController::index`
  - `GET /api/v1/courts` -> `CourtController::index`
  - `GET /api/v1/venues`, `GET /api/v1/venues/{slug}` -> `VenueController`
- **Current Query Logic**:
  - `AvailabilityController`:
    `Court::whereKey($id)->where('is_active', true)->whereHas('venue', fn ($q) => $q->where('is_active', true))`
  - `CourtController::index`:
    `Court::where('is_active', true)->whereHas('venue', fn ($q) => $q->where('is_active', true))`
  - `OrdinaryCheckoutService::checkoutV1`:
    `$this->courts->findActiveWithActiveVenue($data->courtId)` (checks `is_active` on court and venue)
  - `OrdinaryCheckoutService::checkoutV2`:
    `Court::query()->withoutGlobalScope('tenant_isolation')->findOrFail($quote->court_id)` (does not check venue publication status or organization subscription at all)
- **Bypasses and Defects**:
  1. **No Partner Status Verification**: A booking can be created on a venue whose organization is suspended or unapproved.
  2. **No Platform Subscription Enforcement**: Bookings proceed even if the partner has no platform subscription, an expired subscription, or has exceeded plan quotas.
  3. **No Draft Protection in Direct API Calls**: If a customer knows a court ID belonging to a draft venue, direct checkout endpoints could allow booking creation.
  4. **Inconsistent V1 vs V2 Logic**: V1 checks `is_active`, while V2 relies on `PriceQuote` which only checks court presence.
- **Required Remediation (Tasks 3.6, 5.6, 5.7, 6.6, 6.7)**:
  - Create a unified `EffectiveBookingEligibilityService` / `CheckBookingEligibility` domain service.
  - Eligibility rule:
    `Partner Status (Organization) === 'approved'` AND
    `Platform Subscription === 'active'` (or valid `trial`) AND
    `Venue Status === 'active'` AND
    `Court.is_active === true` AND
    `Slot within operating calendar and not blocked`.
  - Integrate this shared eligibility check across `AvailabilityController`, `CourtController`, `PriceQuoteService`, `checkoutV1`, and `checkoutV2`.
  - Recheck eligibility inside the mutation transaction with proper locks to prevent race conditions.
  - Preserve access for authorized staff and customers to existing bookings, check-in, and refunds on suspended or expired venues.

---

## 3. Summary of Delegates and Cutover Strategy

| Subsystem | Legacy Entry Point | New Domain Delegate | Cutover Strategy |
|---|---|---|---|
| Customer Onboarding | `AuthController::onboarding` | `CompleteCustomerOnboarding` Action | Remove organization, venue, and role creation. Retain personal profile completion. Route owners to partner application. |
| Partner Application | None (was embedded in onboarding) | `StartPartnerApplication`, `SavePartnerApplication`, `SubmitPartnerApplication` | New dedicated feature endpoints under `/api/v1/partner/application`. |
| Partner Review | `AdminKycController::review` -> `ReviewHostKyc` | `ReviewPartnerApplication` Action | Deprecate legacy review action. Route all admin KYC decisions through `ReviewPartnerApplication` with atomic provisioning. |
| Platform Subscriptions | None (customer memberships only) | `SelectPlatformPlan`, `ActivatePlatformTrial`, `ConfirmSubscriptionPayment` | New organization-scoped subscription endpoints under `/api/v1/organization/subscription`. |
| Venue Publication | `AdminController::updateVenue` (`is_active` toggle) | `CheckVenueReadiness`, `SubmitVenuePublication`, `ReviewVenuePublication` | Restrict `is_active` to projection. Require publication review workflow for public listing. |
| Booking Eligibility | `Court::findActiveWithActiveVenue` | `EffectiveBookingEligibilityService` | Inject unified eligibility check into all public catalog, availability, quote, and booking checkout flows. |
