## Why

The application exposes all seven functional areas, but several customer journeys stop at local UI state or disagree with backend and current specifications. Complete these journeys with authoritative persisted outcomes before claiming functional completeness; the immediate risks are false payment success, divergent V1/V2 booking semantics, and incorrect POS identity and totals.

## What Changes

- Complete account lifecycle and enforce the existing organization role matrix through both navigation and APIs.
- Complete venue/court schedules, closures, availability and pricing/promotion integration without rebuilding working modules.
- Align ordinary V1/V2 booking with atomic wallet payment, cancellation/refund, recurrence and waitlist behavior; preserve historical API compatibility.
- Complete tournament registration and wallet fee collection, provider-backed wallet deposits, financial reports and existing gated payout/reconciliation workflows.
- Make POS receipts authoritative, require shifts, resolve legacy booking identities explicitly, and implement booking attendance, walk-ins and reservation service tabs.
- Replace fabricated customer/friend/business data with persisted customer records, friendships, match invitations and a moderated basic community feed.
- Require end-to-end acceptance evidence across seven areas and preserve financial rollout gates. Incompatible clients that depend on ordinary V2 hold/confirm behavior require a documented transition before activation.

## Capabilities

### New Capabilities
- `account-lifecycle`: Persisted account/profile lifecycle and role-aware access journeys.
- `venue-court-operations`: Court catalog, operating schedules, closures and availability integration.
- `on-site-attendance`: Booking check-in/out, walk-ins and auditable service-tab settlement.
- `customer-community`: Real customer records, friendships, basic feed, moderation and notifications.

### Modified Capabilities
- `reservations-and-pricing`: Explicit checkout authority, promotion totals, compatibility and cancellation/waitlist integration.
- `wallet-payment`: Authoritative tournament registration and fee outcomes and verified deposit journeys.
- `point-of-sale-and-loyalty`: Server-priced receipts, shift enforcement, canonical reservation links and payment-method evidence.
- `api-alignment`: Fail-closed mutations and capability-aware UI instead of successful local fallbacks.
- `platform-readiness`: Seven-area acceptance matrix and staged completion gates.

Existing organization access, financial ledger/payouts, social tournaments and advanced booking requirements remain acceptance obligations; implementation fixes that restore them do not redefine those specifications.

## Impact

Affected areas: backend routes/controllers/domain actions/services/models/migrations; frontend auth, court, booking, wallet, finance, POS, admin, social, notification and tournament features; backend and browser integration tests. New persistence is required for attendance, service tabs, customer relationships and feed/moderation. Reuse existing notification/outbox, financial posting, idempotency and tenant boundaries. No new external provider is selected, no production feature is enabled, and no application code is changed by this planning change.

Delivery is one umbrella change with independently gated work packages: identity/catalog, booking/money, on-site, community/tournaments, and integrated acceptance. Real-time private chat, advanced social ranking, new tournament formats, wallet withdrawals/transfers and accounting-suite functionality are excluded.
