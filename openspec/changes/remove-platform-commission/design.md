## Context

See `proposal.md` for the commercial decision. This is a cross-cutting monetary change, so implementation needs a design despite adding no new architecture.

- `backend/app/Services/Finance/FinancialPostingService.php` computes a configurable default 500-basis-point commission in booking/reservation wallet payments, tournament registrations and shared provider collections. Older booking/refund helpers also reference the current rate. Its existing `compensate` operation instead mirrors recorded source entries.
- `backend/app/Services/Social/MarketplaceService.php` snapshots `marketplace.fee_bps` onto listings, then deducts it at purchase. Changing only the configuration default would leave unsold historical listings charging five percent.
- `SettlementService` derives commission from journal entries and tenant payable from signed liability entries, preserving already-captured snapshots. Finance CSV exports and frontend payout metrics expose commission; the frontend currently combines provider fees and commission and uses truthy fallback aliases.
- Existing subscription actions and frontend cards already represent monthly/yearly plans. Reuse them; do not rebuild billing or alter prices.
- The supplied image records a scope discussion, not additional execution instructions. This proposal is grounded in the user's confirmed subscription-only decision and repository specs; it does not claim a fresh review of the original PDF referenced by archived planning documents.

## Goals / Non-Goals

**Goals:** Remove all new platform transaction commissions at their monetary source, keep canonical balances and historical evidence intact, and make current pricing and historical reporting unambiguous.

**Non-Goals:** No new pricing strategy abstraction, selectable commission mode, schema teardown, retrospective financial correction, new refund flow, or general finance/subscription refactor. Existing wallet methods, provider verification, payout rules and entitlement enforcement remain in place.

## Decisions

### 1. Remove commission calculations, not just change environment defaults

Update existing payment posting methods and the shared collection path directly. Wallet-funded collections credit their full amount to tenant payable; provider collections retain applicable provider-fee allocation. Stop consulting `finance.commission_bps` and `marketplace.fee_bps` for new activity; remove obsolete configuration and active documentation references. Retain explicit zero metadata only where contract compatibility requires it, and ensure caller metadata cannot override that canonical zero.

Marketplace purchase must calculate zero fee independently of a stored listing rate. New listings store zero in the existing compatibility field; already-listed rows need not be bulk rewritten. The seller receives the asking price. The posting method must not accept a positive new platform fee from a stale caller.

Rejected: setting environment values to zero alone, which can be overridden and does not address stored listing rates. Rejected: a pluggable pricing engine, which preserves the second model the user removed.

### 2. Preserve recorded events; cut over new financial outcomes

An existing committed journal/event remains authoritative on retries. A first-time posting after cutover follows zero commission, including late callbacks that have no recorded financial outcome. Preserve event identifiers and duplicate protection rather than creating a second posting namespace. Drain or reconcile in-flight monetary operations before switching all API/worker processes; do not silently rewrite a pending transfer whose external payment/refund has already been initiated under old amounts.

Keep historical commission accounts, settlement fields and transfer fee fields for reads and authorized original-event compensation. Do not zero stored settlements, delete journal lines or backfill wallet balances. Trace all callers of legacy refund helpers: do not reopen retired booking changes, and use the existing source-journal compensation path for supported obligations rather than deriving reversals from a global rate. Missing source evidence must enter the existing exception process, not trigger a guessed reversal.

Rejected: globally suppressing all references to the commission account, which would corrupt historical compensation and reporting. Rejected: retaining a runtime old/new pricing toggle, which would allow new commissions to return.

### 3. Keep settlement compatibility and distinguish fee categories

Preserve `commission_amount` and existing CSV column positions for downstream consumers. Explain their historical-only meaning in UI/export documentation; do not relabel old values as provider fees. Use recorded amounts for old or mixed-period settlements. Show provider fees separately and show historical commission detail when nonzero, while keeping total deductions and net payable consistent with the recorded financial data. New-only periods show no current commission charge.

Use canonical fields when present, including zero; legacy aliases are fallbacks only when the canonical value is absent. Update Vietnamese/English labels and tests together. Leave historical test fixtures explicit; make current demo fixtures commission-free. Update active runbooks without rewriting archived OpenSpec changes.

Rejected: removing all commission fields from APIs/exports, which creates avoidable compatibility breaks and hides audit data. Rejected: only renaming “Fees & Commission” to “Provider fees” while keeping the same combined total.

### 4. Preserve the existing subscription boundary

Keep plan selection, price/terms snapshots, verified receipt handling, trials, renewal/expiry and quota checks. Align commercial copy to monthly/yearly rental, with actual provider fees separately disclosed. Subscription payment remains a distinct partner collection; this change does not route it through tenant booking settlement or add a replacement surcharge. Do not introduce new plan tiers or change rollout enforcement policy under cover of removing commission.

## Risks / Trade-offs

- [Mixed application versions could still charge commission] → Pause affected writes/consumers for coordinated deployment, refresh configuration and restart all workers before resuming.
- [Old unsold listings retain nonzero rates] → Enforce zero at purchase and posting boundaries; test with nonzero stored rates and stale environment values.
- [Unresolved pre-cutover transfer has externally committed amounts] → Inventory and reconcile before release; unresolved cases block the cutover rather than rewriting receipts or replaying collections.
- [Historical amounts disappear from UI or mixed-period totals] → Retain compatibility fields and test old-only, new-only and mixed datasets in API/CSV/UI.
- [Removing current-rate refund code alters unrelated obligations] → Trace callers, preserve retired-operation rejection, and verify original-journal compensation for old and new payments.
- [Adjacent pre-existing finance defects distract from scope] → Fix only defects necessary for zero-commission correctness or history preservation; record unrelated issues separately.

## Migration Plan

1. Implement and verify in the existing test environment. No destructive data migration is planned; keep historical monetary columns and accounts.
2. Before production cutover, record the release boundary and take read-only counts/totals of journals, wallet balances, settlements and completed transfers. Inventory unsold listings and unresolved transfers/provider operations. Resolve monetary operations already in flight under old amounts, or postpone cutover.
3. Pause affected new monetary writes and consumers, deploy backend/frontend together, remove obsolete rate settings, refresh application configuration and restart workers. No maintenance operation rewrites posted amounts.
4. Smoke-test one new wallet booking/reservation, tournament registration, supported provider collection and marketplace purchase; verify zero platform commission, preserved provider fees, balanced journals and retry behavior. Check a historical settlement/CSV and mixed-period report against baseline evidence before resuming.
5. Rollback must not silently restore the old commission model. If verification fails, keep affected writes paused and deploy a corrected zero-commission build. A UI-only rollback is acceptable only if recorded amounts remain accurate. Never roll back financial data or compensate customers automatically as a deployment repair.
