# Rollout and Rollback Plan & Operational Safeguards Confirmation

**Change**: `fix-booking-access-loyalty-and-availability`  
**Task**: 9.5 Confirm backend-before-frontend rollout and rollback safeguards  
**Reference**: `openspec/changes/fix-booking-access-loyalty-and-availability/design.md` (Section: Migration Plan & Risks)

---

## 1. Executive Summary & Purpose

This document establishes the operational confirmation for the staged deployment and rollback strategy for the `fix-booking-access-loyalty-and-availability` change. It validates the rollout sequence, backward-compatibility invariants, immutable ledger protections, pre-activation policy normalization, and strict operational gating prescribed in `design.md`.

---

## 2. Staged Rollout Sequence (Backend-Before-Frontend)

To guarantee transaction consistency, prevent UI interactions against unmigrated backend services, and eliminate the possibility of point debit without discount application, deployment MUST proceed strictly in the following sequence:

### Phase 1: Backend Rollout First

1. **Database Migrations & Schema Adjustments**:
   - Run non-breaking, additive database migrations (persisting policy snapshots, redemption linkage, and capability mappings).
   - Ensure all database constraints preserve backward compatibility for checkouts without loyalty points.
2. **Structured Feature Error Structures**:
   - Deploy stable business error codes (`CAPABILITY_DISABLED`, `DIRECT_REDEMPTION_RETIRED`, `UNAUTHORIZED_ORGANIZATION_ACCESS`, `POLICY_NOT_FOUND`, etc.) with backward-compatible HTTP status codes (preserving HTTP 404/422/403 where established).
3. **Loyalty Preview Endpoint**:
   - Enable read-only endpoint `POST /api/v2/loyalty/preview` returning available organization balance, active policy version, maximum allowed points, calculated discount, and remaining wallet balance without mutating state or reserving inventory.
4. **Scoped POS & Organization Capabilities**:
   - Deploy `GET /api/v1/organizations/{id}/capabilities` and server-side authorization filters restricting cashier and owner access to their designated organization without granting global platform permissions.
5. **Retired Endpoint Guard (`POST /api/v2/loyalty/redeem`)**:
   - Deploy the explicit rejection guard on `/api/v2/loyalty/redeem` returning HTTP 422 with structured code `DIRECT_REDEMPTION_RETIRED`. This permanently disables unsafe, standalone debit-only redemption paths.
6. **Atomic V1/V2 Loyalty Checkout Engine**:
   - Deploy updated checkout handlers supporting atomic multi-asset transactions (booking reservation, inventory hold, point deduction, and net wallet debit committed in a single database transaction).

### Phase 2: Verification of Backend Health

Before any frontend assets or UI switches are deployed, the operations team must verify backend operational readiness:
- [ ] Health check and telemetry endpoints report healthy status.
- [ ] Direct call to `GET /api/v1/organizations/{id}/capabilities` returns expected capability flags for authorized users and denies cross-tenant requests.
- [ ] Direct call to `POST /api/v2/loyalty/preview` computes accurate discounts based on configured policy rules.
- [ ] Rejection verification: verify `POST /api/v2/loyalty/redeem` returns `422 Unprocessable Entity` (`DIRECT_REDEMPTION_RETIRED`) when invoked with legacy payloads.
- [ ] Verification that standard V1 and V2 booking checkouts without points succeed without regression.

### Phase 3: Frontend Rollout Second

Once the backend services are verified healthy in the target environment:
1. **Component and Route Deployment**:
   - Deploy updated client bundle containing:
     - `LoyaltyPointSelector.vue`: Reusable point selection input with live preview computation, balance display, and policy explanations.
     - Localized `ErrorModal.vue`: Bilingual (Vietnamese / English) error dialog driven by structured business error codes with accessible modal focus handling.
     - Per-court availability calendar: Dynamic court and date headers, nearby court selector chips, and explicit hourly time ranges (`19:00 – 20:00`).
     - Scoped route navigation: Inverse guest guard (`guestOnly`) on `/login` and `/register`, automatic redirection to authorized destinations (`/admin/organization` for owner, `/admin/pos` for cashier, root for platform denial).
2. **Client Cache Invalidation**:
   - Purge CDN and application caches to ensure end users load the current client bundle with integrated preview bindings.

---

## 3. Rollback Safeguards

Should issues emerge post-deployment, rollback must adhere to non-negotiable data integrity safeguards:

### 3.1 Point Ledger and Wallet Transaction Immutability
- **Permanent Financial Records**: Every committed point debit, wallet payment, and booking confirmation represents an authoritative financial transaction.
- **No Mass Reversals or Data Wipes**: Under no circumstance shall rollback scripts execute retroactive mass-credits, balance wipes, or blanket database rollbacks on historical ledger tables.
- **Isolated Anomaly Handling**: Any disputed or failed transaction that occurred during an operational incident must be addressed through recorded, auditable administrative compensation entries rather than destructive database modifications.

### 3.2 Unsafe Endpoint Rejection Invariant
- **Strict Retirement of `/api/v2/loyalty/redeem`**: The standalone debit-only redemption endpoint was identified as unsafe because it detaches point consumption from booking price calculation.
- **Permanent Retirement**: Under **all** rollback scenarios (whether rolling back the frontend, reverting feature flags, or applying emergency patches), `POST /api/v2/loyalty/redeem` MUST remain retired and reject calls with HTTP 422 (`DIRECT_REDEMPTION_RETIRED`).
- **Prohibition on Silent Point Drops**: The system must never revert to code that accepts point selections from a user but fails to apply the corresponding financial discount at checkout.

### 3.3 Independent Client and Server Rollback
- **Frontend-Only Rollback**: If client-side rendering or UX issues arise, the frontend bundle can be rolled back independently. Disabling or reverting `LoyaltyPointSelector.vue` returns users to the baseline no-points booking flow. The backend transparently processes checkout requests without points without errors.
- **Backend Rollback Protections**: If a backend defect requires rolling back service binaries, previous bookings created with loyalty discounts retain their immutable snapshot references (`loyalty_points_used`, `loyalty_discount_amount`).
- **Zero In-Flight Corruptions**: Because checkout is atomic (points and wallet debited within the same database transaction boundary), in-flight failures cleanly roll back without orphaned point debits or unposted reservations.

---

## 4. Pre-Activation Policy Normalization

Enabling loyalty redemption for an organization requires prior administrative verification of policy parameters. Legacy or unreviewed organizations MUST NOT have point redemption enabled until administrative normalization is completed.

### Required Policy Parameters for Review:
1. `reward_value_vnd`:
   - Must be explicitly defined as an integer monetary value per point unit (e.g., 50 VND per point).
2. `conversion_ratio`:
   - Must define the baseline exchange unit ratio (e.g., 100 points = 5,000 VND).
   - Ambiguous, non-whole, or unapproved ratios must be normalized.
3. `min_points_redeemed`:
   - Must establish the minimum redemption threshold (e.g., 100 points) to eliminate micro-redemptions that yield negligible monetary benefit.
4. `rounding_mode`:
   - Must specify deterministic whole-VND rounding (`ROUND_HALF_UP` or standard integer truncation) to prevent sub-VND fractional currency values.

### Operational Policy Rule:
Organizations with missing, zero, or unverified loyalty configurations default to `points_redemption_enabled = false`. The UI gracefully renders the baseline booking experience with an informative note or hides the redemption selector without disrupting ordinary wallet bookings.

---

## 5. Deployment Authorization Statement

> **OPERATIONAL NOTICE**:  
> This document confirms and validates the architectural correctness of the rollout sequence, rollback safeguards, and policy normalization requirements specified in `design.md` (Task 9.5).  
> **This confirmation does NOT constitute an authorization to trigger automated or manual deployment to staging or production environments.**  
> Any execution against live environments requires explicit, separate operational sign-off and scheduled change authorization by authorized project stakeholders.
