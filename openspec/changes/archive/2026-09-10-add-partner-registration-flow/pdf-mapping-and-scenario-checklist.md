# PDF Requirements Mapping and Scenario-to-Test Checklist

## 1. Executive Summary and Scope Boundary

This document links requirements from `nv1.pdf` section 2.3.1 (printed pages 53–67) — covering preconditions (PRE-01–09), alternate flows (ALT-01–14), partner business rules (BR-PAR-01–03), and the four progressive stages of partner onboarding — to the five OpenSpec delta specifications.

### Retained Scope Boundaries
1. **Wallet-Only Booking Payment**: In accordance with the accepted `wallet-only-payment` and `reservations-and-pricing` specifications, customer bookings are funded exclusively via the user's wallet balance. Platform subscriptions and partner plan fees are billed to the partner organization via provider collection adapters, but customer booking payments and refunds remain on the existing wallet engine.
2. **Booking Cancellation and Refund Authoritative Rules**: Existing platform cancellation time-windows, refund percentage tiers, and wallet ledger reversal semantics remain authoritative. Partner venue setup records and displays supported platform policy choices and snapshots the effective policy version on the booking, rather than introducing arbitrary custom payment processors or unvetted refund overrides.
3. **No State Corporate Registry / Automated OCR Dependency**: Document completeness and field formatting are validated by the platform, but legal verification and identity confirmation are governed by authorized human reviewer workflows.

---

## 2. Requirements Traceability Matrix

| Source ID | Description in PDF Section 2.3.1 | Primary Delta Spec | Mapped Requirement / Scenario | Target Test Strategy |
|---|---|---|---|---|
| **PRE-01** | Authenticated user with verified identity/contact, active and unrestricted account status | `partner-registration` | `Requirement: Eligible and unique application initiation` / `Scenario: Unverified or restricted account` | Feature unit test on `StartPartnerApplication` rejecting unverified email/phone or restricted account status |
| **PRE-02** | Applicant must not already be an active owner of a registered organization | `partner-registration` | `Requirement: Eligible and unique application initiation` / `Scenario: Existing owner` | Integration test verifying recognized owners are routed to their organization dashboard without creating a new draft |
| **PRE-03** | Single active application constraint: maximum one draft or pending application per user | `partner-registration` | `Requirement: Eligible and unique application initiation` / `Scenario: Concurrent starts` | Concurrency/lock test ensuring parallel start requests return the identical application ID via database constraint |
| **PRE-04** | Complete declared business information (type, registration/tax code, representative, address, sports, bank account) | `partner-registration` | `Requirement: Resumable business information and private evidence` / `Scenario: Resume an incomplete draft` | FormRequest validation and Action unit test ensuring draft saves partial data, while submission checks all mandatory business fields |
| **PRE-05** | Required verification evidence attached based on business type (ID, business license, premises rights, bank statement, power of attorney) | `partner-registration` | `Requirement: Resumable business information and private evidence` / `Scenario: Resume an incomplete draft` | FormRequest validation and Action unit test checking required document keys per business classification |
| **PRE-06** | Evidence files adhere to configured formats (PDF, JPG, PNG), file size limits, and clean temporary upload handling | `partner-registration` | `Requirement: Resumable business information and private evidence` / `Scenario: Invalid or unauthorized evidence` | Storage adapter unit test for MIME validation, file size rejection, and cleanup of aborted uploads |
| **PRE-07** | Versioned consent to cooperation terms, platform fees, data processing, and settlement rules | `partner-registration` | `Requirement: Versioned submission and correction loop` / `Scenario: Missing requirements` | DTO and Action tests verifying versioned consent strings are stored and missing consents reject submission |
| **PRE-08** | Commercial plan catalog configured with valid price, billing period, quotas, and trial terms | `platform-subscriptions` | `Requirement: Versioned platform plan and fee consent` / `Scenario: Plan changes after selection` | Repository test verifying plan catalog retrieval, fee disclosure, and rejection of activation when commercial config is absent |
| **PRE-09** | Venue readiness criteria satisfied before publication submission (active partner, metadata, courts, pricing, policies, active plan) | `venue-court-operations` | `Requirement: Draft venue readiness` / `Scenario: Incomplete venue` | `CheckVenueReadiness` unit test returning actionable failure checklist for missing courts, prices, or operating hours |
| **ALT-01** | Unverified or suspended user attempts to initiate partner registration | `partner-registration` | `Requirement: Eligible and unique application initiation` / `Scenario: Unverified or restricted account` | HTTP test returning 403 Forbidden with verification guidance |
| **ALT-02** | Existing owner opens partner registration | `account-lifecycle`, `partner-registration` | `Requirement: Partner entry independent of personal onboarding` / `Scenario: Existing owner` | HTTP test returning redirect/actionable state to existing organization |
| **ALT-03** | Race condition: concurrent application initiation requests | `partner-registration` | `Requirement: Eligible and unique application initiation` / `Scenario: Concurrent starts` | Integration test with parallel DB transactions verifying single draft creation |
| **ALT-04** | Partial application save and later resumption | `partner-registration` | `Requirement: Resumable business information and private evidence` / `Scenario: Resume an incomplete draft` | HTTP test proving partial draft persistence, reload, and omission of organization/venue records |
| **ALT-05** | Unauthorized access to another applicant's private KYC document | `partner-registration` | `Requirement: Resumable business information and private evidence` / `Scenario: Invalid or unauthorized evidence` | Storage/Controller test proving cross-tenant/cross-user 403 response on private document read |
| **ALT-06** | Duplicate business identity detection (conflicting tax code or duplicate registration number) | `partner-registration` | `Requirement: Resumable business information and private evidence` / `Scenario: Duplicate business identity` | Action test pausing application into manual platform investigation without leaking third-party details |
| **ALT-07** | Submission attempted with missing mandatory fields or expired consent versions | `partner-registration` | `Requirement: Versioned submission and correction loop` / `Scenario: Missing requirements` | HTTP test returning 422 Unprocessable Entity with exact field-level issue descriptors |
| **ALT-08** | Applicant attempts to edit application while in `pending` review status | `partner-registration` | `Requirement: Versioned submission and correction loop` / `Scenario: Edit during review` | HTTP test returning 409 Conflict; underlying submitted snapshot remains unchanged |
| **ALT-09** | Reviewer requests correction/supplementation (`revision_required`) | `partner-registration` | `Requirement: Versioned submission and correction loop` / `Scenario: Supplement and resubmit` | Action and HTTP test verifying transition to `revision_required`, applicant resubmission, and version bump |
| **ALT-10** | Reviewer rejects application with reason and reapplication terms | `partner-registration` | `Requirement: Authorized and atomic review` / `Scenario: Rejected application` | Action test proving application immutability, recorded rejection reasons, and reapplication eligibility checks |
| **ALT-11** | Stale review decision or conflicting concurrent reviewers | `partner-registration` | `Requirement: Authorized and atomic review` / `Scenario: Conflicting reviews or failed provisioning` | Concurrency test ensuring optimistic lock failure/conflict returned without partial provisioning |
| **ALT-12** | Subscription payment failure, timeout, or delayed callback | `platform-subscriptions` | `Requirement: Verified and idempotent paid activation` / `Scenario: Late confirmed payment` / `Scenario: Renewal fails before current period ends` | Webhook/reconciliation tests for late success idempotent processing and failed renewal preserving paid period |
| **ALT-13** | Platform reviewer requests corrections on venue publication or rejects publication | `venue-court-operations` | `Requirement: Independent publication review` / `Scenario: Correct or reject publication` | Action test verifying venue moves to `revision_required` with feedback reasons and remains nonpublic |
| **ALT-14** | Material edit to an already published venue or emergency operational closure | `venue-court-operations` | `Requirement: Independent publication review` / `Requirement: Venue lifecycle and historical policy preservation` | Test verifying operational closure takes effect immediately while identity/location edits create a staged publication revision |
| **BR-PAR-01** | One current application per applicant; verified contact prerequisite; draft immutability on submission | `partner-registration` | `Requirement: Eligible and unique application initiation` & `Requirement: Versioned submission and correction loop` | DB unique active-slot index and transaction lock test |
| **BR-PAR-02** | Atomic partner approval: creates Organization, assigns `organization_owner`, retains customer role, keeps venue in draft | `organization-access`, `partner-registration` | `Requirement: Reviewed ownership provisioning` & `Requirement: Authorized and atomic review` | Unit/Integration test checking single DB transaction, rollback on failure, user retains customer bookings |
| **BR-PAR-03** | Partner suspension or plan expiry blocks new bookings but preserves access to settle existing obligations | `organization-access`, `platform-subscriptions`, `venue-court-operations` | `Requirement: Restricted operations preserve settlement access` & `Requirement: Expiry and outstanding obligations` | Integration test verifying customer booking creation fails on suspended venue, while existing booking check-in/refund succeeds |

---

## 3. Four-Stage Lifecycle Mapping (Section 2.3.1.6)

### Stage 1: Partner Application and KYC Verification
- **Input State**: Authenticated customer with verified contact.
- **Workflow**: `draft -> pending -> revision_required -> pending -> approved | rejected`.
- **Atomic Boundary**: Approval triggers creation of `organizations`, `organization_members` (as `organization_owner`), and audit logs in a single database transaction. The applicant retains their customer identity and past personal bookings.
- **Artifacts**: FormRequests (`SavePartnerApplicationRequest`, `SubmitPartnerApplicationRequest`, `ReviewPartnerApplicationRequest`), Actions (`StartPartnerApplication`, `SavePartnerApplication`, `SubmitPartnerApplication`, `ReviewPartnerApplication`), Repositories (`HostKycRepositoryInterface`, `PartnerDocumentStorageInterface`).

### Stage 2: Platform Subscription and Entitlements
- **Input State**: Approved partner organization without an active platform plan.
- **Workflow**: `SelectPlan -> AcceptTerms -> InitiatePayment/ActivateTrial -> VerifyPayment -> EntitlementActive`.
- **States**: `trial`, `pending_payment`, `active`, `payment_failed`, `past_due`, `suspended`, `expired`, `cancelled`.
- **Invariants**: Integer VND pricing; UTC timestamps; provider-neutral payment collection adapter; late payment webhook reconciles exactly once; quotas (venues, courts, staff) enforced at domain mutation boundaries.
- **Artifacts**: FormRequests (`SelectPlatformPlanRequest`, `InitiateSubscriptionPaymentRequest`), Actions (`SelectPlatformPlan`, `ActivatePlatformTrial`, `CreateSubscriptionPayment`, `ConfirmSubscriptionPayment`, `ExpirePlatformSubscription`), Repositories (`PlatformPlanRepositoryInterface`, `PlatformSubscriptionRepositoryInterface`).

### Stage 3: Venue Setup and Readiness Validation
- **Input State**: Partner organization with active subscription entitlement.
- **Workflow**: `CreateDraftVenue -> ConfigureMetadata/Images -> AddCourts -> DefinePrices -> SetPolicies -> ValidateReadiness`.
- **Readiness Checklist**:
  - [x] Approved, active partner organization.
  - [x] Active platform subscription with available venue quota.
  - [x] Basic venue profile (name, address, location/coordinates, contact phone, at least one photo).
  - [x] At least one active court with configured sport type.
  - [x] Valid operating hours and opening/booking windows.
  - [x] Deterministic pricing rules covering operating windows.
  - [x] Supported platform cancellation and refund policy configured.
  - [x] Collection and settlement details complete.
- **Artifacts**: Action `CheckVenueReadiness` returning structured readiness DTO (`is_ready`, `reasons`, `field_links`), FormRequest `SubmitVenuePublicationRequest`.

### Stage 4: Venue Publication Review and Booking Activation
- **Input State**: Draft venue meeting 100% of readiness criteria.
- **Workflow**: `SubmitPublication -> PendingReview -> ReviewerDecision (Approve | Revision Required) -> Active / Published`.
- **Runtime Eligibility Invariant**:
  ```
  IsEligibleForNewBooking =
    Organization.IsActivePartner &&
    Organization.HasValidSubscription &&
    Venue.Status == 'active' &&
    Court.IsActive &&
    Calendar.IsOpenAtSlotTime
  ```
- **Outstanding Obligation Rule**: When a venue is suspended, temporarily closed, or subscription expires, `IsEligibleForNewBooking` evaluates to `false`. However, authorized staff and customers retain read/update access for existing bookings, check-in, dispute resolution, and refunds under the authoritative wallet rules.

---

## 4. Scenario-to-Test Verification Plan

### A. Unit Tests (Domain Layer with Mocks/Fakes)
- `tests/Unit/Domain/Partner/StartPartnerApplicationTest.php`: Unverified user, existing owner, draft resumption.
- `tests/Unit/Domain/Partner/SavePartnerApplicationTest.php`: Field persistence, duplicate tax code detection.
- `tests/Unit/Domain/Partner/SubmitPartnerApplicationTest.php`: Missing mandatory fields, consent version check, pending lock.
- `tests/Unit/Domain/Partner/ReviewPartnerApplicationTest.php`: Reviewer role check, atomic approval, idempotent retry, revision required.
- `tests/Unit/Domain/Subscription/SelectPlatformPlanTest.php`: Valid plan selection, missing commercial config rejection.
- `tests/Unit/Domain/Subscription/ActivatePlatformTrialTest.php`: Replay prevention, duration calculation.
- `tests/Unit/Domain/Subscription/ConfirmSubscriptionPaymentTest.php`: Idempotent webhook confirmation, amount mismatch.
- `tests/Unit/Domain/Venue/CheckVenueReadinessTest.php`: Missing courts, missing pricing, valid readiness.
- `tests/Unit/Domain/Venue/ReviewVenuePublicationTest.php`: Recheck readiness at approval, expired subscription during review.

### B. Integration Tests (Database & Repository Layer)
- `tests/Integration/Repositories/EloquentHostKycRepositoryTest.php`: Concurrency lock on application creation, user unique constraint.
- `tests/Integration/Repositories/EloquentPlatformSubscriptionRepositoryTest.php`: Quota checks, subscription status transitions.
- `tests/Integration/Repositories/EloquentVenueLifecycleRepositoryTest.php`: Venue publication history, compatibility projection of `is_active`.
- `tests/Integration/Storage/PartnerDocumentStorageTest.php`: Private disk isolation, signed temporary URL generation, orphan file cleanup.

### C. Feature & HTTP Integration Tests
- `tests/Feature/Partner/PartnerApplicationFlowTest.php`: Complete journey from draft to submission, review, and organization provisioning.
- `tests/Feature/Subscription/PlatformSubscriptionFlowTest.php`: Plan purchase, trial activation, webhook reconciliation, quota enforcement.
- `tests/Feature/Venue/VenuePublicationAndBookingEligibilityTest.php`: Readiness verification, publication review, and public booking eligibility enforcement.
- `tests/Feature/Auth/OnboardingCutoverTest.php`: Proving personal onboarding no longer creates active organizations, venues, or owner roles.
- `tests/Feature/Obligations/ExistingObligationsAccessTest.php`: Proving existing bookings and refunds operate on suspended venues while new bookings are blocked.
