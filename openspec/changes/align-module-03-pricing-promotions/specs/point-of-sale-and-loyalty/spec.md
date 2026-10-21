## ADDED Requirements

### Requirement: Versioned POS memberships and point earning
Memberships SHALL be tenant-scoped and benefits/version/validity preserved for earned periods even when plans stop selling. Membership benefits SHALL apply only to POS sales. Court bookings SHALL NOT receive a membership discount and SHALL NOT redeem, reserve or earn points; their amount payable SHALL follow "Voucher eligibility and discount". Qualifying completed identified-customer POS transactions SHALL earn points under active base earn rules plus applicable disclosed membership multipliers. No checkout SHALL redeem points, and a standalone point debit MUST NOT succeed. Redemptions accepted before this rule SHALL keep their conversion policy version, points spent and monetary benefit so later rule changes cannot alter the recorded transaction. Expiry/reversal SHALL be explicit auditable events, never mutable ledger edits; retries MUST NOT duplicate earning, expiry or reversal.

#### Scenario: Membership plan changes
- **WHEN** a membership plan is revised or retired
- **THEN** current members SHALL retain earned snapshot benefits through the agreed validity period

#### Scenario: Configured multiplier
- **WHEN** a qualifying POS transaction uses a published member earn multiplier
- **THEN** earned points SHALL follow that multiplier once, not an unrelated newest rule

#### Scenario: Expired points
- **WHEN** a customer views their point balance after some lots expire
- **THEN** available balance SHALL exclude expired lots and retries SHALL not duplicate expiry

#### Scenario: Court booking offers no membership benefit or points
- **WHEN** a member with unexpired points books a court
- **THEN** the booking SHALL show no membership discount and no points control, SHALL reserve and debit no points, and SHALL earn no points

#### Scenario: Historical redemption stays recorded
- **WHEN** a customer or operator opens a booking or POS sale that redeemed points before this rule
- **THEN** its points spent, policy version and monetary benefit SHALL be shown unchanged

#### Scenario: Legacy debit-only request
- **WHEN** a client submits `/api/loyalty/redeem` for any source
- **THEN** the API SHALL reject the request with an actionable error without changing points or order totals

## MODIFIED Requirements

### Requirement: Authoritative POS receipt and identity
The platform SHALL calculate product price, rental duration, applicable configured VAT and eligible discounts on the server and return a persisted receipt whose total equals payment and ledger totals. Membership discount SHALL be applied once. POS orders SHALL NOT accept a voucher or coupon: a request that names one SHALL be rejected with 422 and SHALL create no order, as defined in "Voucher scope". Attached legacy bookings SHALL resolve explicitly to a same-tenant canonical reservation, never by numerical ID coincidence.

#### Scenario: Receipt totals
- **WHEN** a cashier sells a rental with a membership discount and configured VAT
- **THEN** the saved receipt, payment and financial totals agree after reload

#### Scenario: Voucher code at POS
- **WHEN** a cashier submits a POS order that names a voucher or coupon code
- **THEN** the request SHALL be rejected with 422, no order SHALL be created and the cart SHALL be kept

#### Scenario: Unmapped booking
- **WHEN** a legacy booking has no canonical mapping
- **THEN** attachment is rejected explicitly rather than linking an unrelated reservation

## REMOVED Requirements

### Requirement: Versioned tenant memberships and loyalty
**Reason**: Court bookings no longer receive membership discounts or redeem points (decision Q2 = A, 2026-10-21; report Module 03 BR-64, BR-66), and no live checkout redeems points, so the redemption, reservation and rollback scenarios no longer hold.
**Migration**: Replaced by "Versioned POS memberships and point earning", which keeps tenant-scoped membership snapshots, POS point earning with disclosed multipliers, auditable expiry and reversal, the preserved record of accepted redemptions and the rejection of `/api/loyalty/redeem`.

### Requirement: Authoritative loyalty conversion and preview
**Reason**: Court bookings no longer redeem points (decision Q2 = A, 2026-10-21): report Module 03 defines the amount payable of a court booking as the rental minus at most one voucher discount (BR-64, BR-66), with no membership or point reduction.
**Migration**: The `/api/loyalty/preview` endpoint and the booking points control are removed. Bookings that already recorded a point reduction keep it, as stated in "Authoritative ordinary checkout and voucher result" and "Versioned POS memberships and point earning". Points are still earned at POS.

### Requirement: Source-bound idempotent redemption
**Reason**: No live checkout redeems points after decision Q2 = A; the only redemption path was ordinary court booking checkout, and the POS redemption method is not routed.
**Migration**: `/api/loyalty/redeem` keeps rejecting every request, as stated in "Versioned POS memberships and point earning". Accepted redemptions stay readable with their source, points and benefit. A future point redemption at POS would need its own change.
