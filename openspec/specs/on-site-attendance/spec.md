## Purpose

Tracks actual booking attendance and on-site service settlement independently of booking payment status for auditable venue operations.

## Requirements

### Requirement: Booking attendance lifecycle
Assigned staff with attendance permission SHALL verify current effective holder or authorized representative, correct venue, payment eligibility, time window and operational court readiness before handover. Payment SHALL not imply attendance. Booking/reservation operational states SHALL consistently project confirmed, in_use, completed and no_show while payment remains separate. Duplicate check-in/out SHALL return prior outcomes. No-show SHALL use captured lateness policy and actor evidence. Checkout SHALL record actual end independently of unpaid ancillary obligations and move the court to awaiting readiness inspection, not automatically available.

#### Scenario: Repeated scan
- **WHEN** staff repeat a valid holder's check-in
- **THEN** one attendance SHALL persist without extending play time

#### Scenario: Invalid scan
- **WHEN** the scan is outside allowed time, uses a former transferred holder, targets another venue, or the court is occupied/blocked
- **THEN** no handover SHALL occur and protected details SHALL remain hidden

#### Scenario: Outstanding ancillary amount
- **WHEN** a playing customer checks out with an unpaid tab
- **THEN** attendance SHALL close while the tab remains a separately tracked receivable

#### Scenario: Readiness inspection
- **WHEN** staff complete post-play inspection successfully
- **THEN** the court SHALL become operationally ready without making reserved future intervals available

#### Scenario: No-show
- **WHEN** the allowed arrival window expires and authorized staff record absence
- **THEN** one policy-bound no-show state and audit SHALL persist across V1/V2 views

### Requirement: Walk-in booking
Staff SHALL create walk-in bookings through the same calendar, price, conflict and payment rules as customer bookings, explicitly identifying the customer and recording the staff actor.

#### Scenario: Walk-in conflict
- **WHEN** staff request a slot already reserved
- **THEN** the request fails without a second booking or debit

### Requirement: Reservation service tab
A persisted unpaid tab SHALL attach to one canonical reservation and reserve tracked stock without recording paid sales. Removing/cancelling unpaid items SHALL release reservations. Verified settlement SHALL convert reserved quantities into a sale once. Checkout SHALL not require tab payment; unpaid charges SHALL persist with responsible customer/booking, amount and collection history until collected or resolved by an authorized adjustment.

#### Scenario: Unpaid tab
- **WHEN** staff attach valid items and reload
- **THEN** the unpaid tab SHALL remain linked and excluded from collected-cash totals

#### Scenario: Tab settlement
- **WHEN** verified tab settlement is retried
- **THEN** exactly one payment, inventory conversion and financial event SHALL persist

#### Scenario: Unpaid checkout
- **WHEN** staff close attendance while a tab remains unpaid
- **THEN** checkout SHALL succeed and the outstanding tab SHALL remain payable afterward

### Requirement: On-site changes and incident handling
Authorized staff SHALL record operational incidents and request court reassignment or extension using the same policy, full-interval conflict, price and verified-payment rules as booking changes. Current play SHALL not extend into another reservation; equipment issues SHALL support evidence and scoped maintenance.

#### Scenario: Extension overlaps next booking
- **WHEN** staff request an extension into occupied court-time
- **THEN** the extension SHALL fail without modifying either booking

#### Scenario: Incident reassignment
- **WHEN** an incident requires moving a player to an available equivalent court
- **THEN** the authorized swap SHALL preserve price/payment history and record actor, reason and affected court intervals
