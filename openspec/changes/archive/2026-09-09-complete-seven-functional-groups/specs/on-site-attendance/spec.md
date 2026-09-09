## Purpose

Tracks actual booking attendance and on-site service settlement independently of booking payment status for auditable venue operations.

## ADDED Requirements

### Requirement: Booking attendance lifecycle
Authorized venue staff SHALL check in a confirmed booking using its code or QR and check it out after attendance. Payment confirmation SHALL NOT imply attendance. Duplicate scans SHALL return the existing outcome; cancelled, wrong-tenant and non-current-day bookings SHALL be rejected. Attendance SHALL record actor and timestamps without rewriting payment history.

#### Scenario: Repeated scan
- **WHEN** staff scan a valid current-day booking twice
- **THEN** one check-in is recorded and both responses identify that attendance

#### Scenario: Invalid scan
- **WHEN** staff scan another tenant's or cancelled booking
- **THEN** no attendance is created and protected details are not disclosed

### Requirement: Walk-in booking
Staff SHALL create walk-in bookings through the same calendar, price, conflict and payment rules as customer bookings, explicitly identifying the customer and recording the staff actor.

#### Scenario: Walk-in conflict
- **WHEN** staff request a slot already reserved
- **THEN** the request fails without a second booking or debit

### Requirement: Reservation service tab
The platform SHALL support a persisted unpaid service tab attached to a canonical reservation. Posting a tab item SHALL reserve available stock without recording paid cash sales; settlement SHALL convert the reservation to a sale stock movement exactly once. Removing an unpaid item SHALL release its stock reservation. Checkout SHALL require all attached tabs settled; deferred credit beyond checkout is excluded.

#### Scenario: Unpaid tab
- **WHEN** staff attach an in-stock service item to a reservation
- **THEN** the tab persists as unpaid and is absent from paid cash totals

#### Scenario: Tab settlement
- **WHEN** staff settle an open tab with valid payment and retry
- **THEN** one payment, sale and stock effect are recorded

#### Scenario: Unpaid checkout
- **WHEN** staff attempt checkout with an unpaid tab
- **THEN** checkout is blocked and the outstanding amount is shown

