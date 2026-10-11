## 1. Baseline and compatibility checks

- [x] 1.1 Reconcile the implementation checkout with the inspected spec/code; preserve the existing user edits in the seeder and auth tests, and inventory all ordinary booking confirmation entry points before adding point selection.
- [x] 1.2 Inventory loyalty base policies, exact versus rounded conversion fields, canonical booking mappings and callers of the debit-only redemption API; record conflicting policies/external consumers without rewriting balances or live configuration.
- [x] 1.3 Record the baseline tests for auth, venue API consumers, POS capabilities, ordinary V1/V2 wallet checkout and availability; map each delta-spec scenario to its targeted regression check.

## 2. Authentication and venue route fixes

- [x] 2.1 Add guest-only login/register handling and share the session-aware, onboarding-aware destination rule with successful login; reject unsafe/circular/unauthorized return paths.
- [x] 2.2 Verify direct URL entry, reload, expired/anonymous sessions, admin/customer defaults, incomplete onboarding and unaffected reset/verification flows.
- [x] 2.3 Correct the shared administrative venue-list client to v1, retain response/CRUD compatibility and update mocks so an undefined v2 route cannot pass the test.
- [x] 2.4 Verify `/admin/manage`, `/admin/venues` and shared staff, schedule, pricing, organization and shift consumers under their existing authorized roles, including surfaced request failures.

## 3. Scoped organization access and POS readiness

- [x] 3.1 Apply server-backed scoped route/navigation rules to organization/POS entry without opening unrelated admin children; replace platform-only data dependencies in the owner organization screen with authorized same-organization data.
- [x] 3.2 Key capability state to the account/organization, reset it on logout/context change, distinguish failed lookup from disabled capability and discard stale responses.
- [x] 3.3 Resolve capabilities before POS data loads, remove duplicate product loading and expose product/shift load failures while preserving the cart and blocking unsupported checkout.
- [x] 3.4 Verify owner and cashier permitted actions, customer/foreign-organization denial, platform-only page denial, disabled POS and capability fetch failure; exercise direct API access as well as UI navigation.
- [x] 3.5 Prepare and test a scoped account-provisioning operation/runbook with explicit targets, credential preservation, idempotent membership assignment, audit output and effective-cohort impact checks; do not execute it against a real target yet.

## 4. Shared bilingual error feedback

- [x] 4.1 Add stable business error codes for capability and organization access while preserving existing HTTP compatibility; define safe mappings for session, validation/conflict, missing-resource, network and server errors.
- [x] 4.2 Localize `ErrorModal` and the shared error resolver into Vietnamese/English using structured errors that update with locale; include all controls, details and accessible labels without exposing raw infrastructure messages.
- [x] 4.3 Integrate one deduplicated error dialog per in-scope screen/operation; preserve inline validation and user input, and retain the original logical key for mutation retries.
- [x] 4.4 Verify both locales, locale switching with an open dialog, unknown fallback, parallel failures, focus containment/restoration, Escape/dismissal and no duplicate sale or point debit on retry.

## 5. Authoritative loyalty preview and policy handling

- [x] 5.1 Implement one base redemption policy resolver shared by configuration/preview/checkout; exclude earn multipliers, use the exact approved ratio and reject ambiguous legacy policy data without changing historical snapshots.
- [x] 5.2 Expose a read-only same-organization loyalty preview for ordinary court/slot or owned quote context, returning balance, policy/version, permitted selection, discount and remaining wallet amount without reserving coupons or inventory.
- [x] 5.3 Add tests for the 100-points/5,000-VND example, non-divisible ratios and whole-VND rounding, zero/negative/fractional input, insufficient points, zero benefit, excessive discount, missing/disabled policy, earn-multiplier exclusion, promotion combinations and policy-version changes.
- [x] 5.4 Replace debit-only public redemption semantics with an explicit rejection/migration response; prove arbitrary source references cannot consume points and check the inventoried external consumer impact before rollout.

## 6. Atomic points and wallet booking checkout

- [x] 6.1 Extend ordinary V1/V2 checkout validation and data flow with optional point selection and preview policy identity/version; preserve requests without points and require a stable operation key for V1 requests that spend points.
- [x] 6.2 Integrate trusted loyalty calculation and source-bound immutable point debit into the existing transaction for booking, inventory, net wallet payment and financial postings; establish compatible lock ordering with all affected point/wallet callers.
- [x] 6.3 Persist policy/ratio, points and monetary benefit snapshots; project an explicit loyalty breakdown through booking/reservation/payment responses and ensure V1-to-V2 mapping references one redemption.
- [x] 6.4 Handle fully discounted zero-payable checkout without a fabricated wallet debit, and keep notifications/realtime success effects after commit only; preserve legacy hold, no-cancellation and wallet-only contracts.
- [x] 6.5 Verify full rollback with injected failures after point writing and before final commit, insufficient wallet balance and inventory conflict; assert unchanged points, wallet, coupon usage, booking/inventory and financial records.
- [x] 6.6 Verify simultaneous point-funded checkouts, identical-key replay, conflicting-key content, repeated business sources, lost responses, foreign sources and already-paid/expired sources; assert one accepted outcome with nonnegative balances.
- [x] 6.7 Verify V1/V2 parity, no-point regression, persisted totals after reload and balanced financial output including loyalty reduction and zero-payable cases.

## 7. Customer point-use interface

- [x] 7.1 Add the reusable localized point selector and server-driven summary before every supported ordinary booking confirmation action; show the correct organization balance and explain unavailable/no-policy states without blocking otherwise-valid no-point checkout.
- [x] 7.2 Refresh preview when court, slots, coupon or points change; ignore stale previews, bind confirmation to the displayed policy and retain the same logical key after an uncertain response.
- [x] 7.3 Display persisted points used, loyalty reduction and net amount in booking/payment detail; refresh the correct organization's point/wallet balance only after authoritative success.
- [x] 7.4 Verify the complete customer journey in Vietnamese and English, including discovery before payment, preview without debit, rejected checkout retaining input, reload and repeat confirmation without duplicate effects.

## 8. Per-court availability clarity

- [x] 8.1 Put the selected court, venue-local play date and nearby court selector beside the home slots; replace the fixed today heading and expose each slot's start/end range in both locales.
- [x] 8.2 Guard availability responses by court/date context, clear incompatible selections and show loading/failure states instead of stale availability; initialize dates using local venue semantics.
- [x] 8.3 Verify court A booked 19–21h disables exactly its two hourly slots, court B remains selectable when free, other dates render correctly and rapid switches cannot overwrite the latest selection; cover both locales and a timezone date boundary.

## 9. Verification and approved operational handoff

- [x] 9.1 Run the affected backend/frontend suites, type checks and build checks; document results and unresolved failures without claiming a deployed browser reproduction from source inspection alone.
- [x] 9.2 Record actual build/commit, frontend/backend versions, environment URL, browser/version, OS/device, timezone, date, account role and organization during UAT; attach anonymized evidence for each reported journey.
- [x] 9.3 Obtain the user's exact account, organization, environment and new-versus-upgrade choice; inspect and present the intended membership/capability changes, then execute only the confirmed scoped provisioning. Leave this task incomplete while those inputs are missing.
- [x] 9.4 Read back provisioned membership/effective capabilities and verify authorized organization/POS actions plus cross-tenant/platform-only denial; do not report a demo simulation as persisted full access.
- [x] 9.5 Confirm backend-before-frontend rollout and the rollback safeguards in `design.md`; preserve history and unsafe-endpoint rejection, and document any separate reviewed policy normalization needed before loyalty activation. Deployment requires its own explicit authorization.
