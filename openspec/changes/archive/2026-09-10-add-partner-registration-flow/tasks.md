## 1. Contract baseline and architecture guardrails

- [x] 1.1 Map PDF PRE-01–09, ALT-01–14 and BR-PAR-01–03 to the five delta specs and create a scenario-to-test checklist, retaining the documented wallet/refund scope boundary.
- [x] 1.2 Inventory all legacy onboarding, KYC review, venue activation and new-booking entry points; document delegates and bypasses that must be changed.
- [x] 1.3 Add scoped backend architecture checks for thin controllers, FormRequests, readonly DTOs, Action/repository boundaries, contract bindings and class/method limits.
- [x] 1.4 Add scoped frontend checks for raw HTTP imports, legacy store additions, feature types, lazy routes and view/component size limits.

## 2. Persistence and infrastructure contracts

- [x] 2.1 Add application version/snapshot/contact fields, nullable preapproval links and a unique current-application slot; test concurrent initiation constraints.
- [x] 2.2 Add versioned document, consent and review-history persistence plus organization partner status and unique source-application linkage.
- [x] 2.3 Add platform plan versions, subscriptions, payment attempts and unique provider receipt/activation records, using integer money and UTC timestamps.
- [x] 2.4 Add venue lifecycle, publication revision/history and approved public snapshot storage with a defined legacy is_active projection.
- [x] 2.5 Implement repository contracts and Eloquent adapters for the new records and required user/organization locks; bind and integration-test them.
- [x] 2.6 Implement private partner-document storage contract/adapter, scoped temporary reads, validation, masking and failed-upload cleanup tests.
- [x] 2.7 Implement durable after-commit audit/notification delivery through contracts with retry/deduplication and no network calls inside database locks.

## 3. Partner application use cases and endpoints

- [x] 3.1 Implement start/resume and save Actions with FormRequests, readonly DTOs and sanitized Resources; test verified-contact, restriction and existing-owner checks.
- [x] 3.2 Implement evidence attachment/replacement and business-type completeness rules, including authorization documents and manual duplicate-identity investigation/clearance.
- [x] 3.3 Implement submission snapshots, versioned consents and pending-content locking with missing-field and stale-version responses.
- [x] 3.4 Implement review/supplement/reject Actions with mandatory reasons, immutable history and policy-driven linked reapplication.
- [x] 3.5 Implement atomic approval and organization-owner provisioning; test retry, conflicting reviewers and rollback on partial provisioning failure.
- [x] 3.6 Implement partner suspension/restoration/termination and audited explanation/support access; test restoration without valid entitlement.
- [x] 3.7 Expose applicant-owned and platform-reviewer endpoints with authorization tests; route existing KYC review entry points through the same use cases.

## 4. Auth and organization integration

- [x] 4.1 Extract touched onboarding behavior into FormRequest/DTO/Action flow and remove owner self-grant, automatic active venue/court creation and default-price provisioning.
- [x] 4.2 Preserve customer identity and organization-scoped membership permissions; update owner routing/capability responses without relying on global admin role replacement.
- [x] 4.3 Add separate authorization for existing obligations during suspension/expiry; test owner, staff, customer and cross-tenant negative cases.

## 5. Subscription lifecycle

- [x] 5.1 Implement configurable versioned plan catalog, fee/terms display contract and owner selection; reject activation with missing commercial configuration.
- [x] 5.2 Implement trial activation with persisted eligibility/period and replay protection; define bounded pre-plan draft setup without granting booking entitlement.
- [x] 5.3 Implement payment initiation and persisted status through a provider-neutral subscription adapter; validate compatibility in sandbox before paid rollout.
- [x] 5.4 Implement authenticated confirmation and reconciliation Actions with reference/amount/currency checks; test duplicate success, timeout/late success and out-of-order failure.
- [x] 5.5 Implement renewal, term-end cancellation and independent payment-attempt status so failed renewal preserves the current paid period.
- [x] 5.6 Implement request-time entitlement checks, expiry processing and reminder notifications; test expired access with delayed scheduler execution.
- [x] 5.7 Enforce plan feature/venue/court/staff limits through domain use cases and repository locks; test concurrent quota consumption without privilege expansion.

## 6. Venue readiness and publication

- [x] 6.1 Implement draft venue creation and supported metadata/images/court/calendar/pricing/policy setup through existing or extended contracts, without auto-publication.
- [x] 6.2 Implement readiness Action with stable reason codes and field links; test each missing prerequisite and ensure optional staff/POS do not block submission.
- [x] 6.3 Implement versioned publication submission and platform review, correction/rejection reasons and notifications; recheck readiness on approval.
- [x] 6.4 Implement approved public snapshots and staged material edits plus immediate operational closure behavior; test stale decisions and expired subscriptions during review.
- [x] 6.5 Implement venue temporary closure/suspension/closure/archive/restoration with audited transitions and preservation of prior obligations.
- [x] 6.6 Integrate shared effective eligibility into public catalog/detail/availability and every inventoried new-booking path, including recurring/reservation flows and compatibility routes.
- [x] 6.7 Add final mutation-time eligibility checks and cache invalidation; test direct requests against draft venues and stale availability after suspension.
- [x] 6.8 Persist effective supported policy/price snapshots and regress existing wallet payment/refund behavior, including payment of already-created bookings.

## 7. Feature-based user journeys

- [x] 7.1 Create partner-registration typed API/types/barrel and composables using shared loading/error helpers; add lazy routes and existing-customer/auth entry points.
- [x] 7.2 Build decomposed business-information, private-document and consent wizard components with server-persisted draft resume and field errors.
- [x] 7.3 Build application status, supplementation/history and reapplication/support views; test refresh, pending edit prevention and post-approval permission refresh.
- [x] 7.4 Extend admin KYC queue/detail composables and components for real application versions, authorized document reads and review conflicts; keep API access out of views.
- [x] 7.5 Build owner venue setup checklist and publication status/correction screens plus platform publication review queue/detail.
- [x] 7.6 Build platform-subscriptions feature API/types/composables and decomposed plan/fee/trial/payment/status views; browser return must only refresh persisted status.
- [x] 7.7 Add Vietnamese i18n strings, masked sensitive fields, scoped theme styles, accessible form states and paginated queues; verify view/component size budgets.

## 8. Rollout, regression and acceptance

- [x] 8.1 Build and test read-only legacy reconciliation reporting for owners/KYC/venues/evidence and outstanding obligations without mutating production data.
- [x] 8.2 Implement audited, rerunnable backfill from explicit reviewed mappings; retain IDs/history and report unresolved records without fabricated approval or blanket entitlement.
- [x] 8.3 Add new-partner/cohort rollout controls and shadow eligibility comparisons; test compatible rollback without reopening owner self-grant or deleting new records.
- [x] 8.4 Run Action unit tests with fake contracts and repository/HTTP integration tests for transitions, unauthorized access, concurrency, private files and callback replay.
- [x] 8.5 Run frontend tests and end-to-end paths for draft resume, supplement/resubmit, rejection, trial/paid activation and publication through first customer booking.
- [x] 8.6 Run negative/regression paths for expiry/suspension, direct booking bypass, existing-obligation handling, legacy accounts and unchanged wallet/refund contracts.
- [x] 8.7 Run scoped architecture checks, backend/frontend lint/type/build checks and applicable existing test suites; record commands and outcomes with any unrelated baseline failures.
- [x] 8.8 Document rollout configuration, production enablement prerequisites, migration reconciliation and rollback runbook; require explicit operational approval before production data migration or activation.
