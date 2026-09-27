## MODIFIED Requirements

### Requirement: POS order, inventory, and booking attachment
Authorized venue operators SHALL manage products/services, stock receipts/issues/adjustments, draft orders and cash/direct-owner QR collections. New direct-model orders SHALL not require platform wallet funding; authorized historical closed-loop balances remain separately governed. Each order SHALL record venue, point of sale, cashier, shift and optional canonical booking/customer. Tracked goods SHALL reserve stock for pending payment and deduct only at completion; untracked services SHALL not require stock. Failed/expired/cancelled drafts SHALL release reservations. Offline drafts MUST NOT finalize payment or stock.

#### Scenario: Cashier completes a POS order
- **WHEN** a cashier sells an in-stock item and an untracked service
- **THEN** one receipt SHALL charge both while stock changes only for the tracked item

#### Scenario: Pending QR competes for stock
- **WHEN** two pending orders request the final tracked unit
- **THEN** only one SHALL reserve it and expiry SHALL release that reservation once

#### Scenario: POS loses connectivity
- **WHEN** connectivity is lost before finalization
- **THEN** the draft SHALL remain and no paid receipt or final stock effect SHALL be fabricated

### Requirement: Shift close and cash reconciliation
A cash-managed venue SHALL record cashier shifts per venue/point of sale, prevent duplicate open shifts in that scope and bind each order/cash event to its shift. Expected cash SHALL equal opening cash plus verified cash collections and recorded inflows minus authorized cash refunds/outflows. Closure SHALL require pending orders resolved, cancelled or explicitly handed over; variance SHALL require explanation and review. Closed shifts MUST NOT receive new sales or mutable totals; late provider events SHALL enter documented successor/exception handling.

#### Scenario: Shift balances
- **WHEN** declared cash equals the recorded cash-event total
- **THEN** one successful reconciliation SHALL persist

#### Scenario: Shift has a cash difference
- **WHEN** declared cash differs
- **THEN** the variance, mandatory explanation, actor and review outcome SHALL remain auditable

#### Scenario: Pending QR at close
- **WHEN** a cashier closes a shift with an unresolved QR order
- **THEN** closure SHALL require a recorded allowed disposition and a later receipt SHALL not silently alter the closed shift

### Requirement: Versioned tenant memberships and loyalty
Memberships SHALL be tenant-scoped and benefits/version/validity preserved for earned periods even when plans stop selling. Booking and POS SHALL use the same authoritative entitlements and combination restrictions. Qualifying completed identified-customer POS transactions SHALL earn points under active base earn rules plus applicable disclosed membership multipliers. Redemption SHALL bind to a permitted benefit and source transaction, reserve available unexpired points while payment is pending, and consume them atomically at confirmation. Expiry/reversal SHALL be explicit auditable events, never mutable ledger edits; retries and concurrent spending MUST NOT overdraw points.

#### Scenario: Membership plan changes
- **WHEN** a membership plan is revised or retired
- **THEN** current members SHALL retain earned snapshot benefits through the agreed validity period

#### Scenario: Configured multiplier
- **WHEN** a qualifying POS transaction uses a published member earn multiplier
- **THEN** earned points SHALL follow that multiplier once, not an unrelated newest rule

#### Scenario: Expired points
- **WHEN** a customer previews or spends points after expiry
- **THEN** available balance SHALL exclude expired lots and retries SHALL not duplicate expiry or redemption

#### Scenario: Payment rollback
- **WHEN** payment/benefit persistence fails after points were reserved
- **THEN** no irrevocable point debit or orphan discount SHALL remain

#### Scenario: Customer redeems loyalty points
- **WHEN** an eligible customer redeems unexpired tenant points in a valid paid or zero-payable transaction
- **THEN** the permitted benefit and single point consumption SHALL commit together with source/version; unpaid hold reservations SHALL not be presented as irreversible consumption

### Requirement: Shift and payment completion evidence
POS finalization SHALL require the cashier's authorized open shift. Cash SHALL record sufficient tender/change; direct QR SHALL require authenticated server-side matching of receiver, order reference and amount. Employee-posted references alone MUST NOT complete QR orders. Unknown payments SHALL preserve unpaid inventory reservations until policy expiry. Legacy wallet spend, where explicitly retained, SHALL require identified customer, same issuer and sufficient balance without creating new pooled custody.

#### Scenario: Closed shift
- **WHEN** a cashier tries to finalize without an authorized open shift
- **THEN** the sale SHALL fail while retaining the cart

#### Scenario: Pending QR
- **WHEN** an employee posts confirm-qr with empty or self-declared evidence
- **THEN** the order SHALL stay unpaid and no paid stock/ledger outcome SHALL be produced

#### Scenario: Verified QR
- **WHEN** a matching provider receipt is received twice
- **THEN** one order completion and one stock/financial event SHALL occur

#### Scenario: Cash reconciliation
- **WHEN** a cashier closes a shift with completed cash, QR and refund/outflow records
- **THEN** expected cash SHALL include only applicable actual cash events and persist variance without including bank QR as cash

## ADDED Requirements

### Requirement: POS returns adjustments and auditable receipt delivery
Authorized users SHALL cancel/adjust unfinished orders with reasons and record post-sale returns/refunds as linked immutable adjustment events. Stock SHALL re-enter only when eligible returned goods are accepted. Actual refund evidence and shift cash effects SHALL reconcile; original receipts MUST NOT be deleted. Printed/sent receipts SHALL use persisted totals and accurately distinguish unpaid, paid and adjusted outcomes. Multiple payment methods SHALL be supported only under an explicitly enabled venue policy and complete only when verified allocations equal the total.

#### Scenario: Return paid goods
- **WHEN** a cashier accepts an authorized eligible return
- **THEN** a linked adjustment SHALL preserve the original sale, track owner refund execution and change stock/cash only as actually justified

#### Scenario: Mixed tender
- **WHEN** enabled policy permits cash plus QR
- **THEN** the order SHALL complete only after total verified cash and QR allocations equal the payable amount

#### Scenario: Receipt reload
- **WHEN** a user prints or sends a completed receipt after reload
- **THEN** amounts, status, cashier and shift SHALL match stored financial records
