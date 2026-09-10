## Context

See proposal.md for motivation and scope. `AuthController::onboarding` currently performs inline validation, changes the global role and creates active organizations, venues, courts and default prices. `HostKyc` and admin review screens exist, but review only activates an already-linked organization. `Venue` exposes `is_active`, not a publication lifecycle. Customer membership models are not platform subscriptions.

The authoritative architecture rules are `.agents/rules/backend-architecture.md` and `.agents/rules/frontend-architecture.md`, not the looser size limits in archived refactor plans. Existing violations are not precedents for new code.

## Goals / Non-Goals

**Goals:** Implement the four business stages using independently testable use cases, explicit state transitions, dependency inversion and resumable client journeys. Make approval/payment retries safe and eligibility consistent across entry points.

**Non-Goals:** Repository-wide architecture cleanup, a framework migration, replacing all existing domain services, or changing wallet booking/refund semantics. Refactor only touched paths needed to remove bypasses and uphold the new boundaries.

## Decisions

### D1. Follow the repository's Clean Layered Architecture

Use the existing call flow, rather than expanding auth/admin controllers or introducing a competing application-layer layout:

`Controller -> FormRequest -> readonly DTO -> Domain Action -> Repository/Service Contract -> Infrastructure`

- Domain areas: `Partner`, `Subscription`, and `Venue`; existing `Auth`, `Admin` and booking paths delegate to their use cases.
- Example Partner Actions: `StartPartnerApplication`, `SavePartnerApplication`, `UploadPartnerDocument`, `SubmitPartnerApplication`, `ReviewPartnerApplication`, `SuspendPartner`, `RestorePartner`, `TerminatePartner`.
- Example Subscription Actions: `SelectPlatformPlan`, `ActivatePlatformTrial`, `CreateSubscriptionPayment`, `ConfirmSubscriptionPayment`, `ExpirePlatformSubscription`.
- Example Venue Actions: `CreateDraftVenue`, `CheckVenueReadiness`, `SubmitVenuePublication`, `ReviewVenuePublication`.
- Actions receive immutable DTOs or primitive identifiers, never HTTP requests. Input DTO factories follow the local `from(FormRequest)` convention. FormRequests handle input authorization/shape; Actions recheck mutable business eligibility under concurrency controls.
- Controllers delegate validation and mutation, serialize API Resources, and contain no database queries, file handling or notifications; at most seven public methods. Read-only operations can use repository interfaces. Classes remain at most 300 lines.
- All query/filter/lock/persist operations belong in `Contracts/Repositories` implementations under `Infrastructure/Repositories/Eloquent`; bindings go in `RepositoryServiceProvider`. Actions never call model static queries, `save`, `update` or relationship mutation methods directly.
- Per repo rules, Actions own multi-record `DB::transaction` boundaries; allowing this framework transaction primitive does not permit queries in Actions. Models retain state/relationships/helpers, not cross-entity orchestration.
- Storage, payment and notification adapters are injected through Contracts. Business events and durable notification intents are recorded within the transaction and delivered after commit with retry/deduplication; external network calls do not hold database locks.
- Money is integer VND; dates persist in UTC and display in Asia/Ho_Chi_Minh. User-facing messages use translation keys.

Architecture acceptance: scoped static checks reject Eloquent queries and direct storage/payment SDK calls in new/touched Actions/controllers, inline request validation, missing repository bindings and forbidden frontend imports. Unit tests exercise Actions with fake contracts; repository integration tests cover database locking/uniqueness. Do not enforce a new whole-repo baseline against unrelated legacy files.

### D2. Extend KYC as the application record, keep partner status separate

Reuse `host_kycs` with nullable organization/venue links before approval rather than create a second competing KYC source. Add application version, business/contact/address/sport data, submission snapshot and policy-driven reapplication metadata. Store documents in a separate versioned evidence collection; store consent versions and append-only review history independently.

Application transitions: `draft -> pending -> approved|rejected|revision_required`; `revision_required -> pending`. Map legacy `needs_supplement` to `revision_required`. Pending content is immutable. Rejected applications remain immutable; a new linked application is allowed only by recorded reapplication policy. Starting again resumes an existing draft or in-flight application. Duplicate business identifiers flag manual investigation and block ordinary submission until cleared without exposing another applicant.

Approved partner lifecycle lives on the organization: `approved -> suspended|terminated`, `suspended -> approved|terminated`; termination is terminal in this change. Application approval remains historical truth when a partner is suspended. This separates the PDF's post-approval partner statuses from application review status without losing any user-visible state.

Serialize application creation on the user record through repository locking and enforce a unique active-application slot per user. Review locks the application and checks expected version/status; approval atomically persists decision, organization, owner membership, audit and notification intent. Unique source-application linkage prevents duplicate organization provisioning. Identical retried decisions return the recorded result; stale/conflicting decisions fail without mutations.

Alternative: continue provisioning an organization during onboarding. Rejected because it grants identity/tenant semantics before review and makes abandoned drafts indistinguishable from partners.

### D3. Private evidence and authorized review

The existing file-storage contract only returns a URL and filename; it is insufficient as-is for private KYC. Introduce a dedicated `PartnerDocumentStorageInterface` under `Contracts/Storage` with private storage, authorized temporary read and deletion/retention operations, implemented in Infrastructure. Do not reuse public court uploads for identity or bank evidence.

Enforce configurable MIME/signature, size and count limits. Link opaque storage keys to application/user and document version; never accept arbitrary externally hosted evidence URLs. Applicant access uses user scope before an organization exists; reviewer access requires platform permission, recorded purpose and audit. Responses mask sensitive banking/identity fields where full disclosure is unnecessary. Failed uploads do not create complete evidence records; cleanup removes orphaned temporary files without deleting submitted history. Document replacement is allowed only in editable application states. Automated checks establish completeness, not legal authenticity.

### D4. Organization subscriptions are separate from customer memberships

Create platform plan versions, organization subscriptions, immutable consent/price snapshots, payment attempts and provider-event receipts; do not reuse `MembershipPlan` or `TenantMembershipPlan`. A plan defines prices, cycle, quotas and feature entitlements. Trial policy is configuration and cannot be restarted by replay or switching plans. No enabled plan/trial means no activation.

States: `trial`, `pending_payment`, `active`, `payment_failed`, `past_due`, `suspended`, `expired`, `cancelled`. Persist payment attempts separately from current entitlement so a failed renewal does not erase a still-valid paid period. Initial/renewal activation records explicit start/end instants and the purchased plan version. This change supports renewal and term-end cancellation; no automatic recurring charge, proration or self-service mid-cycle downgrade is introduced. Terms state supported operations before purchase.

Reuse the provider-neutral payment contract where compatible through a subscription collection adapter. A browser return only refreshes status. Verified callbacks/reconciliation must match provider reference, organization subscription, expected amount/currency and successful status, with unique event and successful-attempt activation guards. Unknown outcomes remain pending; late verified success is reconciled exactly once, never guessed from a timeout. Late failure cannot downgrade a completed success. Expiry is checked at request time as well as by a retry-safe scheduled job.

Effective capability = platform feature enabled AND tenant permission AND applicable plan entitlement; operational access needed for prior obligations is separately authorized. Quotas are checked on mutations, not only UI counters. This avoids treating existing feature flags as proof of a paid subscription.

### D5. Venue publication is an explicit, versioned review

Add venue lifecycle and publication revision/history, retaining `is_active` only as a compatibility projection, not independent authorization. States: `draft`, `pending_publication`, `revision_required`, `active`, `temporarily_closed`, `suspended`, `closed`, `archived`. A rejected publication decision leaves the venue nonpublic in `revision_required`, with reason and resubmission eligibility; the PDF defines no separate rejected venue state.

Use existing court/calendar/pricing/policy management through contracts. Readiness returns stable reason codes and field links for partner status, required metadata/images, active court, usable opening/booking windows, valid deterministic prices, supported policy configuration, collection setup and valid subscription/quotas. Optional employees/POS do not block readiness. Snapshot effective prices and supported policy versions on bookings without changing wallet/refund rules.

Submitting captures configuration version; edits to reviewed content are blocked while pending. Admin approval rechecks version and readiness in the same transaction as publication. Later material identity/location/policy edits stage a new review revision rather than silently replacing the approved public snapshot; operational closures can take effect immediately to prevent unsafe bookings.

One shared eligibility rule combines partner, venue/publication, subscription, court status and calendar eligibility. Public search, venue detail, availability and direct new-booking/recurring/reservation entry points consume it. A final mutation-time check prevents stale pages from bypassing suspension/expiry; caches are invalidated on lifecycle changes. Existing bookings retain their captured obligations and are not automatically cancelled. Payment of an existing booking follows its original terms rather than being misclassified as a new booking.

Alternative: a single `is_active` toggle. Rejected because it conflates publication approval, entitlement and temporary operational closure.

### D6. Feature-based frontend, with server-owned workflow state

Add `features/partner-registration/{api,types,composables,components,views}` and `features/platform-subscriptions/{api,types,composables,components,views}`. Keep admin review in existing admin modules and venue setup in the owner area using typed feature APIs, not cross-feature composable/store imports.

Provide: application entry/status, business-information step, document step, consent/review step, supplement/history view; owner readiness checklist; plan/fee confirmation and payment status; admin application/publication queues and decision forms. Draft data persists on the server and reload resumes the last state. Auth onboarding only expresses partner intent and links into this journey; existing users enter through account/navigation without repeating onboarding.

Views orchestrate components/composables, never import raw HTTP clients. API modules return typed promises via `core/api`; composables use shared loading/error helpers. Types live in feature types and are exported from the barrel. Pinia is used only for shared state inside the feature; no new legacy `@/stores` state. Shared UI is props/events only. Routes lazy-load. Lists paginate. Vietnamese text uses i18n, styles are scoped/theme-based, and raw server errors never reach users.

Use stricter applicable limits: views at most 250 total lines and 200 script lines (target under 150); components at most 300 lines. Split wizard sections rather than enlarge current OnboardingView. Permission/UI state is refreshed from the server after approval and organization switch.

### D7. Scoped contracts and testable API behavior

Expose authenticated applicant resources for own application, evidence, submission and history; owner-scoped resources for venue readiness/publication and subscription selection/payment/status; platform-only review and partner lifecycle resources; provider-authenticated callbacks. Keep application/review and subscription controllers separate to respect method limits. Existing KYC review routes become compatibility delegates to the same Action, never an alternate bypass.

Resources expose canonical status, version, allowed actions, missing requirements and sanitized history. Mutation DTOs include expected version and retry identifiers where needed. Stale transitions return a conflict; invalid/unfinished fields return field errors; authorization failures reveal no foreign records. No new user-facing path depends on local counters, seed-only data or redirect success.

## Risks / Trade-offs

- Legacy auto-approved owners lack evidence → inventory and explicit platform-reviewed migration mapping; never fabricate approvals.
- Review/payment concurrency → repository locks, unique links/events and atomic audit/provisioning with race tests.
- Sensitive documents leak through public upload conventions → dedicated private adapter, access audit and negative tests.
- New eligibility accidentally blocks refunds → separate new-sale permission from outstanding-obligation access; regression tests.
- PDF describes broader policy customization than current wallet specs → retain existing payment/refund contracts and disclose supported policies; broader policy changes are separate scope.
- Dual venue representations diverge → one authoritative lifecycle with compatibility projection and backfill checks.
- Large cross-cutting rollout → staged tasks, shadow readiness checks and per-organization rollout admission.

## Migration Plan

1. Inventory existing owners, KYC links/statuses, active venues, booking obligations and provider capabilities; produce a read-only reconciliation report before data changes.
2. Deploy additive schema/contracts and dark-launch new flows. Preserve existing public behavior for unmigrated organizations; disable legacy self-granting onboarding for new applicants at cutover.
3. Map legacy supplementation statuses and retain original IDs/documents/audit. Do not copy public evidence URLs as trusted private keys; inventory and securely migrate with an access verification report.
4. Require an explicit reviewed mapping for each legacy organization: partner decision evidence, approved venue snapshot and valid entitlement. No blanket free trial, fabricated KYC, role replacement or automatic downgrade to draft. Unresolved organizations are reported and not admitted to the new enforcement cohort.
5. Enable new workflow for new partners and reconciled legacy cohorts; compare shadow/public results and verify customer booking plus owner obligation access. Complete legacy reconciliation before global enforcement.
6. Rollback UI/rollout flags to a compatible release without reopening owner self-grant or dropping new data. Preserve immutable applications, transactions and audit; pause new partner submission/publication if necessary. Review explicit cohort mappings before reversing enforcement; never delete payment/history records in rollback.

## Open Questions

- Which commercial values (prices, billing cycles, trial duration, quotas) and terms versions will operations publish? They are configuration with fail-closed activation.
- Which accepted MIME types, size/count limits, retention period and reviewer notification templates will operations configure? The private, versioned access model does not depend on those values.
- Which provider account and production callback credentials will be supplied? Validate through the existing provider-neutral contract in sandbox before enabling paid activation; do not embed secrets in artifacts.
