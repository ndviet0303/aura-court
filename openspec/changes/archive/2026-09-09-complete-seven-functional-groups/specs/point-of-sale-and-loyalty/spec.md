## ADDED Requirements

### Requirement: Authoritative POS receipt and identity
The platform SHALL calculate product price, rental duration, applicable configured VAT and eligible discounts on the server and return a persisted receipt whose total equals payment and ledger totals. Membership discount SHALL be applied once. Attached legacy bookings SHALL resolve explicitly to a same-tenant canonical reservation, never by numerical ID coincidence.

#### Scenario: Receipt totals
- **WHEN** a cashier sells a rental with a membership discount and configured VAT
- **THEN** the saved receipt, payment and financial totals agree after reload

#### Scenario: Unmapped booking
- **WHEN** a legacy booking has no canonical mapping
- **THEN** attachment is rejected explicitly rather than linking an unrelated reservation

### Requirement: Shift and payment completion evidence
Finalizing POS sales SHALL require an open shift for the cashier. Cash sales SHALL record sufficient tender and change; wallet sales SHALL require an identified customer and sufficient funds. QR sales SHALL remain unpaid until verified provider confirmation. Failed or uncertain payment SHALL NOT finalize inventory or paid financial effects.

#### Scenario: Closed shift
- **WHEN** a cashier finalizes a cart without an open shift
- **THEN** the sale is rejected and the cart is retained

#### Scenario: Pending QR
- **WHEN** a cashier displays a QR code before provider confirmation
- **THEN** the order is not marked paid and cannot be printed as a paid receipt

#### Scenario: Cash reconciliation
- **WHEN** a cashier closes a shift
- **THEN** expected cash includes only actual completed cash collections and records any declared variance

