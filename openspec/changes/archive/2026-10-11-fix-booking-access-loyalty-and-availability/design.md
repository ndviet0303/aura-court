## Context

See `proposal.md` for motivation and the five delta specs for observable requirements. This design is required because the change crosses routing, tenant authorization, shared error handling and monetary checkout.

Evidence from exploration:

- `frontend/src/router/index.ts` only guards authenticated/admin pages; guest pages lack an inverse guard. `LoginView.vue` separately chooses onboarding, admin organization or customer redirect destinations.
- `adminApi.getVenues()` uses `apiV2`, while venue CRUD lives under the v1 admin prefix. Staff, organization, schedule, pricing and shift views share this function. A frontend mock currently accepts the nonexistent v2 list, so rendering tests alone cannot detect the mismatch.
- `FeatureFlagService` checks both the configured flag and pilot organization list, returning a generic English 404. A platform admin does not bypass this feature gate. Local environment text enables POS for organizations 1–5; this is not evidence of deployed configuration or the reported account's organization.
- The parent `/admin` guard checks the global `admin` role. Backend POS methods already use scoped organization roles. `User::isPlatformAdmin()` treats `admin` as platform-wide too; using that role merely to let an owner into POS would overgrant access.
- `ErrorModal.vue` exists but contains fixed Vietnamese text. `errorMessage.ts` translates a fixed set into Vietnamese regardless of locale. POS loads capabilities and products concurrently, loads products a second time from the product composable's mount, and hides some fetch errors. Capability refs are shared and can survive account/context changes.
- `PosService::redeemPoints()` debits `PointLedger` but creates no order benefit. `writePoints()` already locks the user and protects against a negative balance. The public redemption endpoint accepts arbitrary source references; no frontend consumer or customer point-use control was found. The existing loyalty test only asserts ledger balances.
- Base loyalty configuration stores an exact ratio in metadata, but also a rounded integer per-100-VND rate; seed values can disagree. Selecting the newest published rule by version alone can include earn multiplier rules.
- **Important correction to the initial exploration:** current main specs and `OrdinaryCheckoutService` create and pay ordinary V1/V2 bookings atomically from wallet balance. There is no new-booking unpaid stage to attach point redemption to after creation. Cancellation and rescheduling are explicitly retired. The reported test row is therefore a desired customer journey, not a reproduced end-to-end UI failure in this checkout.
- `HomeView.vue` selects the first court and fetches availability with its ID. The availability backend filters by that court. The user confirmed booking 19–21h, so two disabled hourly slots are expected. The issue is unclear court/date context, not demonstrated cross-court inventory corruption.

## Goals / Non-Goals

**Goals:**

- Fix each root cause at an existing shared boundary, preserving established contracts outside the declared compatibility change.
- Make customer point use a discount inside ordinary checkout, never an independent debit or a new payment method.
- Preserve scoped privileges and truthful failure/loading states, with consistent Vietnamese/English feedback.
- Make the plan independently testable; operational provisioning is a gated step, not a hidden prerequisite for developing the other fixes.

**Non-Goals:**

- No new dependencies, generic promotion framework, replacement authorization engine or blanket global error interceptor.
- No customer cancellation/refund flow, POS loyalty input, tournament/split/recurring redemption, cross-tenant points, global availability aggregation or historical balance repair.
- No database seed rerun, password reset, production mutation or deployment as part of proposal creation.

## Decisions

### 1. Reuse a single session-aware destination rule

Add guest-only metadata to login/register and await the existing session load before deciding. Reuse the same small destination resolver after login and in the route guard. Incomplete onboarding has priority. Preserve the current admin organization default; accept a customer/internal return path only after excluding external/protocol-relative URLs, guest-auth loops and unauthorized destinations. Password-reset and email-verification routes are not blanket guest-only routes.

Keep route authorization distinct from navigation convenience. Extend access only for the in-scope organization/POS routes based on server-returned organization roles. Do not weaken the parent guard for every `/admin/*` child. Organization-owner components must use scoped organization data instead of introducing access to platform-admin venue lists. Platform-only screens remain restricted.

Alternative rejected: redirect inside only `LoginView`, because direct entry to register and post-login rules would drift. Granting a global admin role to solve an interface guard is also rejected.

### 2. Correct the venue-list client, not the backend route map

Use the existing v1 client in `adminApi.getVenues()`. Preserve its list shape and CRUD version. Inventory all callers and update tests to assert the client/version rather than letting both mock clients return a venue list. Authorized owner-specific data access must remain distinct from this platform-admin list.

Alternative rejected: add a duplicate v2 admin route solely for the mistaken caller; that expands the API surface without fixing the inconsistent contract.

### 3. Keep membership, capability and provisioning separate

Capability state is keyed to the authenticated account and active organization with explicit loading/error status. Resolve it before dependent POS loads, remove the duplicate mount load and ignore late responses from the former context. Do not swallow product/shift load failures as empty data or an absent shift.

Provisioning preflight is read-only and reports account state, active membership/role, selected organization, effective POS flag/cohort and the exact intended environment. Use approved `organization_owner` access for full organization operation, not a platform-admin role. Preserve existing role limits for staff/cashier. Keep both backend role checks and feature checks.

Use the existing provisioning mechanisms only if they can target the confirmed organization without incidental password/global-role changes. The current `admin:create` command overwrites credentials, assigns global `admin` and can fall back to the first organization, so it must not be run unchanged for this scoped request. Document and test a narrowly scoped operation, including idempotent membership assignment and audit evidence. If POS enablement is required, add only the approved organization to the existing cohort and verify that changing the global flag would not unexpectedly enable other cohort members. Require explicit approval of any such wider effect.

Alternative rejected: rerun demo seeders, set permissions to unrestricted globally, replace the whole pilot list or bypass feature checks. Demo-only accounts also must not be represented as real persisted POS access.

### 4. Reuse the existing error modal with structured translation

Keep the API client's throwing behavior and status codes compatible, adding stable `code` and safe context where a business failure currently has only English text. Preserve capability-disabled HTTP 404 for compatibility but attach a code such as `CAPABILITY_DISABLED` so it is distinguishable from a genuine missing resource. Permission, organization-context and validation failures receive their own mappings; unknown failures use safe translated fallbacks.

Store a structured error descriptor until rendering, then translate using the active locale; do not store already-translated strings if the open modal must react to locale changes. Localize the existing modal's defaults, titles, controls, details and accessible labels. Preserve inline field validation and nonblocking success notices. One small screen/layout-level error owner deduplicates the same failure from parallel requests. Do not open a modal automatically for every background request or every expected unauthenticated session probe.

Add focus entry/trapping/restoration, keyboard dismissal and unique accessible labels using current UI utilities where available. Allow read-only retries. A mutation retry must retain its logical operation key or reconcile outcome first; modal retry must not create a new sale or spend points again. Cart/input stays intact on failure.

Alternative rejected: a new dialog library or broad interceptor that competes with every page's existing error handling.

### 5. Use point preview before the existing wallet checkout

Place the customer control at every supported ordinary booking confirmation entry point before the request that creates/pays the booking (including home quick booking, court detail/schedule and the V2 booking flow). Do not add a point button only to the post-payment detail screen. Reuse one small input/summary component where those entry points share the same behavior.

The read-only preview takes court/slot or owned quote context, coupon selection and requested points; the server resolves the organization from the selected court/quote, never from an arbitrary fallback organization. It returns the user's balance for that organization, base policy identity/version, selected points, discount and remaining wallet amount. An optional quote token can bind this to existing V2 quote handling; clients cannot submit an authoritative monetary discount. Preview must not reserve coupons or write point/wallet/inventory records. Capability-disabled and no-policy conditions render truthful localized guidance while ordinary checkout without points remains available when otherwise eligible.

Proposed conversion semantics for review: use the exact `points_redeemed : reward_value_vnd` metadata ratio where valid; legacy rules with no exact metadata can use a coherent explicit per-100-VND rule. Select the active base conversion policy, excluding multiplier rules. Compute `floor(requested_points * reward_value_vnd / points_redeemed)` once in whole VND. Reject zero benefit, insufficient points and discounts greater than the remaining amount instead of silently consuming excess points. Apply already-permitted coupon/membership discounts first, then loyalty to the remaining amount, respecting any explicit combination prohibition. Display the maximum usable selection from the server. Historical accepted snapshots are not recalculated.

Normalize policy writes and preview calculations to this shared resolver. Report conflicting legacy rule representations for review before enabling redemption for that organization; do not guess which monetary promise was intended. Policy changes between preview and confirmation cause a conflict requiring a refreshed preview, with no debit.

Alternative rejected: reuse the raw debit-only API, add its returned points to client-side totals, or recreate a pending-payment stage for new bookings.

### 6. One transaction owns redemption and checkout

Extend the existing `OrdinaryCheckoutService` entry points and their DTO/validation contracts with optional `loyalty_points` and the previewed policy identity/version. No-point requests preserve the existing API behavior. When points are requested, the authenticated beneficiary, source organization and capability are revalidated inside the checkout operation.

Use a consistent lock order across participating point and wallet paths. Reuse the existing user lock for point balance serialization and the existing wallet/inventory locks; review ordering against other award/redeem/checkout callers to avoid deadlocks. Under the checkout transaction:

1. Validate quote, policy version, ownership, organization, points, prices and inventory.
2. Recompute permitted discounts and net payable from trusted inputs.
3. Create the confirmed booking/reservation and its canonical mapping as appropriate.
4. Write the immutable point debit bound to that canonical source and save a structured loyalty snapshot with `rule_id`, `rule_version`, exact ratio, `points_spent` and `discount_vnd`.
5. Debit only the remaining wallet amount and persist the matching payment, receipt, financial postings and inventory. A zero remainder creates no fake positive or zero-value wallet debit; it still records valid fully-discounted confirmation evidence.
6. Commit; publish notifications/realtime effects only after commit. Any failure rolls back every step.

Persist the loyalty breakdown using existing metadata/snapshot containers, exposing an explicit `loyalty_discount_amount` and redeemed-points projection in the response where needed. Keep coupon, membership and loyalty amounts distinguishable. V1 booking and canonical V2 reservation projections must reference one redemption, not each debit points. Financial reports/postings must reconcile net wallet collection and the recorded discount; slot/entitlement projections must not accidentally report undiscounted collection.

Reuse V2 `IdempotencyService` with a stable logical key. For V1 checkout with points, require a stable key and bind it to the user and court-derived organization; preserve legacy no-point requests. The client reuses the key after uncertain responses and changes it only when the logical checkout content changes. Validate business-source uniqueness independently, so a different transport key cannot debit the same accepted source twice. A valid replay of the original accepted request returns its existing result rather than failing because its source is now paid.

Retire debit-only semantics of `/api/v2/loyalty/redeem`: old source-only payloads fail explicitly without mutation; new point use goes through ordinary checkout. Keep internal ledger writing available only to validated source/benefit flows. Audit external consumers before rollout rather than assuming frontend search proves none exist.

No new persistent unpaid redemption is introduced. Legacy holds do not gain retroactive point discounts in this change; automatic expiry remains unchanged. Therefore there is no new expiry refund or cancellation action to implement. Historical debits without benefits require a separate, approved reconciliation after identifying actual affected records; never bulk-credit on assumption.

### 7. Clarify a per-court calendar, do not aggregate it

Keep the existing `court_id` availability API. Put the court selector and selected local date beside the slots, and use a dynamic heading such as `Lịch trống — {court} — {date}` / `Availability — {court} — {date}`. Render or expose `startsAt–endsAt` so 19:00–20:00 and 20:00–21:00 are visibly two slots. Use venue-local date semantics, including initial date rather than UTC date slicing.

Use a request generation/context check so rapid court/date changes cannot apply an older response to the newer selection. Clear selected slots when the context changes. Handle loading/error rather than showing the former court as current. The court cards remain discovery links; they do not imply aggregation.

Alternative rejected: load every court and aggregate availability, because the current interface already has a court selector and the user confirmed the two-hour booking.

## Risks / Trade-offs

- [Money-affecting scope is larger than a label fix] → Implement and test preview, exact policy resolution, atomic checkout and retry together; do not enable the input against the old debit-only backend.
- [Conflicting or rounded legacy loyalty rates] → Inventory base rules, require reviewed normalization of ambiguous records, retain accepted historical snapshots and test non-divisible rates.
- [Existing V1/V2 mapping produces two apparent sources] → Bind one immutable redemption to the canonical identity and test both retrieval paths and replay.
- [Lock ordering or post-commit notifications drift] → Audit all affected locks, use a consistent order, test concurrent checkouts and inject failures before commit; verify no successful external side effect escapes a rollback.
- [Broadening an admin parent exposes other children] → Per-route role decisions and filtered navigation; prove cashier/customer denial and platform-only denial, not only owner success.
- [Shared translation utility affects other screens] → Maintain safe fallback behavior and run its existing regression tests plus scoped two-locale checks.
- [A feature switch enables more organizations than intended] → Compare before/after effective capability sets and stop provisioning if the confirmed scope cannot be preserved.
- [Deployed build differs from the inspected checkout] → Record commit/build and environment during verification; do not claim a browser reproduction based only on source analysis or the supplied image.
- [Existing uncommitted changes overlap future work] → Preserve `OrganizationVenueSeeder.php`, the deleted `LoginForm.spec.ts` and modified `LoginView.spec.ts`; reconcile deliberately before implementation, never reset them.

## Migration Plan

1. Review this proposal and exact loyalty conversion behavior; confirm the operational provisioning target separately before any account/environment mutation.
2. Implement independently safe auth, venue-client, calendar-context and localized error changes with focused tests. Keep backend guard status compatibility.
3. Inventory current base loyalty rules and any external redemption callers read-only; prepare an explicit compatibility notice for the debit-only endpoint. Do not seed or rewrite historical ledgers.
4. Deliver preview, atomic V1/V2 loyalty checkout, snapshots and source/key uniqueness safeguards together. Add only additive storage if existing containers cannot enforce the required invariants; preserve immutable history.
5. Deploy the backend before exposing the new frontend control; verify selected organization's reviewed policy/capability and full checkout tests before enabling that journey. No production deployment is authorized by this planning request.
6. Execute the scoped provisioning step only after account, organization, environment and access mode are confirmed; read back membership/capability results and verify allowed and denied operations.
7. Record actual UAT environment, versions, browser/device, account role and anonymized transaction references. Test both locales and reload after accepted operations.

Rollback: disable the new loyalty entry point while keeping persisted snapshots/read paths and rejection of unsafe debit-only requests. Never restore a path that silently discards submitted point selections or charges the full amount after promising a discount. Keep historical point and wallet records; do not reverse successful checkouts as a deployment rollback. Revert nonfinancial UI/client changes independently if needed. Reverting provisioning must affect only the explicitly recorded changes after impact review, not overwrite whole membership/cohort state.

### Confirmed Staged Rollout and Rollback Safeguards (Task 9.5)

1. **Rollout Sequence**: Backend must be deployed first (providing loyalty preview `/api/v2/loyalty/preview`, scoped capabilities, structured bilingual feature errors, and retired standalone debit rejection `/api/v2/loyalty/redeem` returning HTTP 422). Once verified healthy and stable in the target environment, the frontend UI deployment follows.
2. **Rollback Safeguards**: Point ledger entries and wallet transaction records are strictly immutable; no retroactive balance wipes or destructive rollbacks of completed financial records are permitted. The unsafe standalone debit endpoint rejection remains permanently enforced. Frontend UI and backend services maintain independent rollback capabilities without corrupting historical transactions or snapshots.
3. **Policy Normalization**: Any organization adopting customer loyalty redemption requires pre-activation administrative review and policy normalization (validating non-zero conversion ratios, whole-currency rounding boundaries, and positive balance thresholds) prior to enabling customer point controls.
4. **Gated Deployment**: Deployment to production or staging environments requires separate, explicitly authorized operational execution; completing this implementation and local verification does not authorize or trigger automatic environment deployment.

## Open Questions

- Which exact environment, organization and account should receive access, and is the request for a new account or upgrading an existing one? These values affect the gated operational execution only, not the planned role/capability behavior.
- What browser/device and deployed build produced the original reports? Capture actual values during UAT rather than filling the test template with guessed environment details.
