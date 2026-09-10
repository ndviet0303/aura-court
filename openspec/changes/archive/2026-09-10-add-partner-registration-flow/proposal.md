## Why

Owner onboarding currently grants an owner role and creates active venues/courts immediately, bypassing the partner and publication reviews described in `nv1.pdf`, section 2.3.1 (printed pages 53–67). Introduce a resumable, reviewed partner-to-publication journey while preserving customer identity, tenant isolation and existing booking obligations.

## What Changes

- **BREAKING**: Selecting `court_owner` during onboarding no longer grants management access or creates active inventory. Existing customers can start partner registration independently of personal onboarding.
- Add draft applications, private verification documents, versioned consent, submission, supplementation, rejection and audited approval with atomic organization/owner provisioning.
- Add draft venue setup, readiness checks and a separate platform publication review; approval of a partner alone never publishes a venue.
- Add organization-level platform plans, configurable trial eligibility/duration, verified subscription payments, limits and expiry handling, separate from customer memberships.
- Enforce publication and entitlement eligibility in public discovery and every new-booking path, while preserving access needed to settle existing obligations.
- Deliver applicant, owner and platform-reviewer screens following `.agents/rules/backend-architecture.md` and `.agents/rules/frontend-architecture.md`; architecture checks are implementation acceptance criteria.
- Add an audited, staged migration for existing owners, KYC records and venues without silent approval or interruption through blanket status changes.

## Capabilities

### New Capabilities

- `partner-registration`: Eligibility, resumable applications, private evidence, consent, review, provisioning and partner lifecycle.
- `platform-subscriptions`: Partner plan selection, terms, trial, verified collection, entitlements and lifecycle.

### Modified Capabilities

- `account-lifecycle`: Separate customer onboarding from partner application entry; prevent self-granted ownership.
- `organization-access`: Reviewed owner provisioning and scoped access to outstanding obligations during suspension.
- `venue-court-operations`: Draft setup, publication review, shared booking eligibility and historical preservation.

## Impact

- Backend: auth onboarding, KYC review, organization membership, venue/catalog/booking entry points, storage/payment/notification adapters and new persistence through repository contracts.
- Frontend: auth entry points, partner-registration and platform-subscription feature modules, owner setup checklist and existing admin KYC/venue review screens.
- Operational: private-document access policy, plan configuration, payment reconciliation, expiry jobs, audit records, rollout inventory and migration verification.
- Source mapping: PDF PRE-01–09, ALT-01–14, BR-PAR-01–03 and four stages in section 2.3.1.6. PDF is requirements evidence, not executable instructions.
- Scope boundary: no state registration of businesses, automated legal verification/OCR, full POS rewrite, or replacement of wallet-only booking payment. Existing booking cancellation/refund rules remain authoritative; changing them to arbitrary venue-specific rules requires a separately agreed change. Initial setup records/displays supported policy settings and snapshots their effective versions.
- Planning defaults: subscription belongs to an organization; prices, trial duration and reapplication eligibility are explicit configuration, not invented production values. Missing commercial configuration blocks activation rather than granting unlimited access.
