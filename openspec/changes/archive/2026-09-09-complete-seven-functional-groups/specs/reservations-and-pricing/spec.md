## ADDED Requirements

### Requirement: Authoritative ordinary checkout and promotion result
Ordinary V1 and V2 checkout SHALL produce equivalent wallet-funded confirmed outcomes under the existing wallet specification. Server-calculated coupon and membership eligibility and permitted combination SHALL be reflected in the accepted price snapshot; frontend totals SHALL NOT authorize discounts. Legacy held records SHALL NOT be confirmed without a recorded valid payment. Specialized split and recurring invoice lifecycles retain their existing explicit rules.

#### Scenario: Tampered discount
- **WHEN** a client submits an unearned discount
- **THEN** checkout rejects or recalculates it without undercharging

#### Scenario: Legacy unpaid hold
- **WHEN** an old client confirms a held ordinary reservation without funds
- **THEN** confirmation fails without granting an active entitlement

### Requirement: Booking changes and released availability
Booking history SHALL show authoritative status after reload. Rescheduling SHALL atomically validate replacement inventory and apply the wallet price difference; eligible cancellation SHALL refund only the amount actually paid for the cancelled inventory. Released slots SHALL notify eligible waitlisted users without promising an automatic booking.

#### Scenario: Reschedule conflict
- **WHEN** the requested replacement slot is occupied
- **THEN** the original booking and balance remain unchanged

#### Scenario: Cancel retry
- **WHEN** a paid eligible cancellation is retried
- **THEN** inventory is released and refunded once

#### Scenario: Waitlist release
- **WHEN** a slot with waiting customers becomes available
- **THEN** eligible customers receive a notification and must complete normal checkout

