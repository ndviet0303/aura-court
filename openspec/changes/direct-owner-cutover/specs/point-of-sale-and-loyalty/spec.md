## MODIFIED Requirements

### Requirement: Closed-loop tenant wallet
The platform SHALL provide wallet credit only for cohorts on the legacy wallet model. Such credit SHALL be scoped to its issuing tenant, cannot become negative, and cannot be withdrawn, transferred to another user, or spent at another tenant. A cohort on direct-owner payments SHALL NOT be offered wallet top-up, a wallet page or wallet tender in booking or POS, and the server SHALL reject wallet tender for its orders. Wallet balances and liability MUST reconcile to their recorded financial entries.

#### Scenario: Customer pays with tenant wallet credit
- **WHEN** a legacy-cohort customer has sufficient wallet credit at its issuing tenant
- **THEN** the platform SHALL debit the wallet and record the corresponding payment and liability effects

#### Scenario: Customer attempts cross-tenant transfer
- **WHEN** a customer requests a wallet withdrawal, user transfer, or payment at another tenant
- **THEN** the platform SHALL reject the request without changing wallet credit

#### Scenario: Direct cohort wallet tender
- **WHEN** a cashier in a direct-owner cohort opens the tender choices or submits an order with wallet tender
- **THEN** wallet SHALL not be offered, and a submitted wallet tender SHALL be rejected without changing balances, stock or the cart

### Requirement: Shift and payment completion evidence
POS finalization SHALL require the cashier's authorized open shift. Cash SHALL record sufficient tender/change. Direct QR SHALL name the venue's verified owner receiving connection, the exact QR amount and a server-generated reference, and SHALL require authenticated server-side matching of receiver, order reference and amount. A POS QR MUST NOT name the platform's account, and QR tender SHALL be unavailable when the venue has no verified receiving connection. A matching verified receipt SHALL complete the order without cashier action and SHALL be recorded as revenue once. Employee-posted references alone MUST NOT complete QR orders. Unknown payments SHALL preserve unpaid inventory reservations until policy expiry; an expired pending QR order SHALL release its reservations, and a later receipt for it SHALL become a finance exception rather than completing it. Legacy wallet spend, where explicitly retained, SHALL require identified customer, same issuer and sufficient balance without creating new pooled custody.

#### Scenario: Closed shift
- **WHEN** a cashier tries to finalize without an authorized open shift
- **THEN** the sale SHALL fail while retaining the cart

#### Scenario: Pending QR
- **WHEN** an employee posts confirm-qr with empty or self-declared evidence
- **THEN** the order SHALL stay unpaid and no paid stock/ledger outcome SHALL be produced

#### Scenario: Verified QR
- **WHEN** a matching provider receipt is received twice
- **THEN** one order completion and one stock/financial event SHALL occur

#### Scenario: QR names the venue owner
- **WHEN** a cashier creates a QR order at a venue with a verified receiving connection
- **THEN** the QR SHALL show that venue owner's account, the exact amount and a server-generated reference; at a venue without a verified connection, QR tender SHALL be refused with setup guidance

#### Scenario: Pending QR expires
- **WHEN** a QR order is not paid before its expiry
- **THEN** its stock reservations SHALL release once and a later matching transfer SHALL become a finance exception instead of completing the order

#### Scenario: Cash reconciliation
- **WHEN** a cashier closes a shift with completed cash, QR and refund/outflow records
- **THEN** expected cash SHALL include only applicable actual cash events and persist variance without including bank QR as cash
