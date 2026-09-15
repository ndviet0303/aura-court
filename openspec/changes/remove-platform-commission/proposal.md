## Why

The agreed product scope is one platform monetization model: partner organizations rent a service plan with monthly or yearly billing. Existing percentage commissions in booking, tournament and marketplace money flows contradict that scope and make subscription fees appear to coexist with revenue sharing.

## What Changes

- Make platform subscriptions the sole platform service-charge model; monthly/yearly are billing cycles, not competing business models. Reuse existing plan selection, verified payment, renewal, expiry and quota enforcement.
- **BREAKING (commercial behavior)**: Stop deducting platform commission on newly posted booking/reservation payments, tournament registrations and provider collections. Remove the marketplace transfer fee as well, so it cannot remain an alternative platform transaction charge.
- Keep booking/tournament wallet checkout and actual provider-fee treatment unchanged. Do not replace commission with another platform surcharge or deduct subscription charges from tenant settlements.
- Preserve posted journals, receipts, settlement snapshots and historical commission values. Existing event retries return their recorded outcome; separately authorized compensation uses original entries, not a current commission rate.
- Align current UI, bilingual fee labels, finance exports, demo data and operational documentation with subscription-only pricing while clearly identifying historical commissions where present.

## Capabilities

### New Capabilities

None.

### Modified Capabilities

- `platform-subscriptions`: Establish monthly/yearly service plans as the only platform monetization model, with provider fees disclosed separately.
- `financial-ledger-and-payouts`: Eliminate new commission deductions while preserving ledger balance, historical accounting, compensation, settlement and reporting compatibility.
- `advanced-booking-marketplace`: Remove the percentage marketplace fee without changing transfer eligibility, payment safety or exactly-once entitlement transfer.

## Impact

- Backend: `FinancialPostingService`, `MarketplaceService`, finance/marketplace configuration, settlement/report/export surfaces, related seeders and tests.
- Frontend: partner subscription/fee messaging, finance payout metrics and settlement detail/table, Vietnamese/English translations and tests.
- Contracts: retain historical `commission_amount` and compatible report fields rather than destructively dropping them; new commission-free activity contributes zero. Actual provider fees remain distinct.
- Operations: coordinated cutover across API and queue workers, immutable financial history, and updated exception guidance. No new infrastructure or dependencies.
- Non-goals: changing plan prices/quotas, implementing a new billing engine, changing payout timing/thresholds, redesigning wallet payments, restoring retired booking cancellation/rescheduling, or retroactively refunding previously charged commissions.
