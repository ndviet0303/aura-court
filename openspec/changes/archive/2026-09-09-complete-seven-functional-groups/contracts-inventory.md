# Baseline contract inventory

This records current behavior, not a claim of conformance. Route source: `backend/routes/api.php`; resource and controller wrappers must remain explicit in frontend adapters.

## V1/V2 contracts

| Command/read | Input / identity | Response / current semantics |
|---|---|---|
| V1 POST bookings | court_id; 1–12 date slots; optional coupon_code (40 chars); authenticated verified user | 201 `{booking}`; ordinary CreateBooking action debits wallet and creates confirmed slots |
| V1 GET bookings | authenticated user | Paginated booking history |
| V1 GET bookings/{code} | booking code, owner/admin | Raw booking with court/venue, slots, payments, refunds |
| V1 POST bookings/recurring | court_id, slots, weeks | 201 `{bookings:[{booking}]}`; distinguish this wrapper from V2 series |
| V1 cancel/cancel-slots | code; reason or slot_ids | Raw updated booking; enforce existing refund policy |
| V1 reschedule | code, new court_id and slots; owner | 201 `{booking}` replacement |
| V1 payment/payment-status | booking code | `{booking,wallet,status}` / `{status,payment}`; not an independent debit command |
| V1 coupon | code, optional coupon_code | `{booking,status}`; confirmed-state eligibility must be tested |
| V2 price-quotes | court_id, 1–24 slots, tenant and idempotency headers | 201 `{quote}` with immutable snapshot and expiry |
| V2 reservations | quote_id/token or court_id+slots | 201 `{reservation}` currently held: conflicts with ordinary wallet-only target |
| V2 reservations list/detail | tenant, owner/operator; ID or code lookup | Paginated list / `{reservation}` with quote, slots, entitlements |
| V2 confirm/cancel | reservation identifier, tenant/idempotency headers | `{reservation}`; current confirm mutates status without wallet debit |
| V2 recurring-series/monthly-invoices | series horizon and invoice identity | Specialized inventory and monthly invoice lifecycle; not ordinary prepaid checkout |
| V2 split-groups/shares | reservation/group/share identity | Specialized multi-payer held lifecycle until full collection; retain payer-level refunds |
| V2 marketplace | entitlement/listing identity | Specialized paid transfer/refund lifecycle, rollout gated |
| V2 pos/orders | items(product_id, quantity), customer_id, reservation_id, method cash/qr/wallet, discount_amount, reference | 201 `{order}` with items/payments; no rental/VAT/tender contract yet |
| V2 pos/shifts | opening_cash or declared_cash and note | `{shift}`; close computes expected cash and variance |
| V2 social/matches | visibility, time range, participants, skill/location metadata | Persisted match; separate invite/join/respond commands; do not treat local friend list as authority |
| V2 tournament registration | No complete customer fee checkout contract | Admin participant creation/check-in exists; frontend local registration/payment requires new persisted command |

## Promotion policy currently implemented

`DiscountService`: coupon percent is floored against subtotal and capped at 100%; fixed coupon is capped at subtotal; max_discount further caps the coupon. Membership percent is independently floored against subtotal. Both discounts add, capped at subtotal. Active date, usage count, min spend and membership validity apply; reserve locks/increments coupon usage. This is the baseline stacking policy, not a proposed replacement.

`PriceQuoteService`: date exception/holiday before peak before weekly before base; matching scope, priority and version select the rule; quote snapshots retain slot price and version. POS currently accepts a client discount and adds membership again; this is a defect, not the policy to preserve.

## Role matrix and identity boundaries

| Role | Existing intended permission boundary |
|---|---|
| customer | Own bookings/wallet/profile; permitted public social discovery and participation |
| staff | Tenant operational actions explicitly granted by route; no owner/payout management |
| cashier | POS collections and shifts; no owner/payout management |
| venue_manager | Venue operations and granted employee/finance controls within tenant |
| organization_owner | Organization management and finance within tenant |
| platform_admin | Explicit audited elevated access per organization-access spec |

Current V1 administration uses `admin` plus demo-mutation middleware. V2 guarded routes pass explicit allowed roles to RequireOrganizationRole. That middleware permits platform admins before tenant role checks; audited elevation remains a separate acceptance obligation. OrganizationMember is an employment/access relationship, not the authoritative customer benefit directory. Booking IDs and reservation IDs belong to different tables and require explicit mapping.

## Effective feature controls

`features.php` defaults V2 reservations and tenancy to true. Payouts, split, resale, social/tournaments and tenant POS/wallet default false and are pilot-only. FeatureFlagService first checks the configured flag, then checks the supplied organization against pilot IDs; when no organization is supplied a configured pilot-only flag returns true. Configuration defaults are not evidence of environment activation. The implementation must expose the selected organization's effective capability and preserve backend guards.

V2 IdempotencyService requires a nonempty key plus authenticated user and tenant, hashes method/path/content, stores the result for 24 hours, replays matching content and rejects changed content with 409. Existing frontend retry behavior must be inspected against this contract: a new random key on retry does not protect a logical transaction.

## Acceptance links

Use acceptance-matrix.md for per-area routes/UI/persistence and test candidates. Historical V1 reads must remain compatible; ordinary V2 checkout must align with wallet payment without rewriting specialized split/monthly invoice semantics. New contracts for attendance, tabs, real customers, feed and tournament registration are defined by the delta specs and remain unimplemented.
