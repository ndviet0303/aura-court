## 1. Baseline and affected money paths

- [x] 1.1 Trace callers of commission-bearing collection, marketplace and legacy refund helpers; record reachable paths and existing regression baselines for booking/reservation, tournament, provider collection, compensation and settlement. Keep retired cancellation/rescheduling rejected.
- [x] 1.2 Add focused regression cases for zero commission despite nonzero legacy configuration/metadata, a five-percent unsold marketplace listing, and unchanged historical journals/settlement snapshots. Establish expected gross/provider-fee/payable values from the delta specs.

## 2. Remove new commission at its source

- [x] 2.1 Remove current-rate commission calculation and deduction from booking/reservation wallet payments, tournament registration and shared/legacy collection posting paths. Preserve provider fees, balanced journals and stable idempotency keys; prevent supplied metadata from misreporting nonzero new commission.
- [x] 2.2 Make new marketplace listings and purchases commission-free, including unsold listings with stored old rates. Set seller refund to the asking price, prevent positive new platform-fee posting, and preserve transfer eligibility, failed-refund compensation and buyer-race safety.
- [x] 2.3 Retire finance/marketplace commission configuration and any active fee-setting surfaces. Trace/remove obsolete current-rate reversal behavior without restoring retired endpoints; verify supported compensation uses original journal allocations and that replayed historical events do not repost or recalculate money.

## 3. Keep history and align subscription-only presentation

- [x] 3.1 Verify settlement/API/CSV outputs preserve immutable historical amounts and compatibility fields, produce zero commission for new-only activity, and correctly account for mixed periods and authorized historical adjustments without recalculating snapshots.
- [x] 3.2 Update payout metrics, settlement details/tables and Vietnamese/English labels to separate provider fees from historical commissions. Honor canonical zero over stale aliases; retain correct total deductions and net payable in old-only, new-only and mixed views.
- [x] 3.3 Align partner pricing and fee-consent copy with one monthly/yearly subscription model while retaining price/terms snapshots and separate provider-fee disclosure. Verify existing verified activation, trial, renewal/expiry and quota behavior without changing plan prices or billing architecture.
- [x] 3.4 Update current demo fixtures and active finance/fee documentation to zero commission, retain explicit historical fixtures for regression coverage, and distinguish historical mismatch handling from unexpected post-retirement commission. Do not rewrite archived changes or production history.

## 4. Verification and release handoff

- [x] 4.1 Run affected backend tests covering balanced posting, duplicate and late events, old-event replay, authorized compensation, wallet-only payment, tournament registration, marketplace races/failure paths, and settlement/API/CSV historical compatibility. Record failures and resolve regressions attributable to this change.
- [x] 4.2 Run affected frontend payout/subscription/localization tests and type checking; verify monthly/yearly copy, provider-fee separation, historical detail and canonical-zero handling. Record the exact commands/results.
- [x] 4.3 Prepare and rehearse the cutover checklist in a test environment: baseline historical totals, inventory/reconcile in-flight old-fee operations, coordinate API/worker restart, verify new zero-commission samples and unchanged history, and document a recovery path that cannot restore new commissions. Production rollout requires separate authorization.
- [x] 4.4 Re-run strict OpenSpec validation and review remaining commission references; each must be historical compatibility, authorized original-event compensation, or a regression assertion—not an active new-charge path. Attach verification evidence before marking implementation complete.
