## MODIFIED Requirements

### Requirement: Versioned reservation API compatibility
V1 and V2 SHALL remain supported projections of one authoritative reservation/inventory identity during migration. New direct-owner checkout SHALL create a pending-payment booking with exclusive expiring holds and receiver-bound payment instructions; confirmation SHALL require verified payment, or a documented zero-payable outcome. Neither version SHALL offer cancellation, refunds or rescheduling of a booking; a holder who cannot use a booking can pass it on only through slot resale. Historical wallet bookings SHALL retain recorded amounts, payment model and terms; old idempotency records MUST NOT authorize a different new command.

#### Scenario: Legacy client retrieves a historical booking
- **WHEN** a client retrieves a migrated historical wallet booking
- **THEN** the original code, status, financial references and ownership history SHALL remain readable

#### Scenario: New client creates a reservation
- **WHEN** an eligible client creates the same direct-payment booking through either supported version
- **THEN** the result SHALL reference one pending obligation and inventory hold without debiting a wallet

#### Scenario: Insufficient payment
- **WHEN** only partial or unverified funds are received
- **THEN** the booking SHALL remain unconfirmed and unpaid inventory SHALL follow its captured expiry policy

#### Scenario: Stale client invokes a retired command
- **WHEN** a client calls a removed cancellation, refund or reschedule command, or replays an old idempotency key against a different command
- **THEN** the server SHALL reject it or return only its original outcome, never mutate the new model by replaying an old response

#### Scenario: New client creates a reservation with insufficient balance
- **WHEN** a direct-cohort customer has insufficient legacy wallet balance but requests a valid direct-owner booking
- **THEN** the server SHALL create the policy-bound pending direct payment without a wallet debit; confirmation SHALL still require verified owner payment or a legitimate zero-payable result

### Requirement: Trigger real-time status hooks
Committed hold, confirmation, expiry, release, incident court reassignment and operational readiness changes SHALL publish scoped inventory updates after commit. Rejected or rolled-back actions SHALL not publish success or released availability.

#### Scenario: Successful reservation triggers hook
- **WHEN** a reservation commits
- **THEN** authorized viewers SHALL receive its actual court-time state

#### Scenario: Unpaid inventory expires
- **WHEN** an unpaid hold expires durably
- **THEN** one committed release SHALL publish availability and notify eligible waitlisted users

#### Scenario: Retired operation does not publish availability
- **WHEN** a client calls a removed cancellation or reschedule command
- **THEN** the request SHALL be rejected and no release or replacement event SHALL be emitted

## ADDED Requirements

### Requirement: Final bookings and released availability
A confirmed booking SHALL be final. Customers, staff and owners SHALL NOT be offered cancellation, refunds or rescheduling: no endpoint or interface SHALL create a cancellation request, refund quote, refund obligation, reschedule preview or reschedule request. A holder who cannot attend SHALL be directed to slot resale under the venue's transfer policy. Ending a recurring contract SHALL release only future occurrences that are unpaid; paid occurrences SHALL remain confirmed. Suspended venues SHALL retain authorized servicing of existing bookings; legacy terms SHALL not be silently rewritten. Waitlist notices SHALL never promise automatic booking.

#### Scenario: Cancellation, refund or reschedule request
- **WHEN** a client calls a removed cancellation, refund or reschedule endpoint, or a customer looks for a way to cancel or move a paid booking
- **THEN** the request SHALL be rejected without changing any booking, inventory, payment, refund or notification record, and the interface SHALL offer slot resale instead

#### Scenario: Recurring contract ends
- **WHEN** an authorized operator ends a recurring contract that has both paid and unpaid future occurrences
- **THEN** only the unpaid future occurrences SHALL release, exactly once, and the paid occurrences SHALL remain confirmed without refund obligations

#### Scenario: Expired unpaid hold
- **WHEN** expiry processes paid and unpaid bookings together
- **THEN** only eligible unpaid holds SHALL release, without refunding unpaid value

#### Scenario: Legacy policy
- **WHEN** a historical booking carries older cancellation, refund or change terms
- **THEN** it SHALL retain them as historical evidence without making cancellation, refunds or rescheduling available again

### Requirement: One-hour booking slots
A booking SHALL consist of one or more booking slots, each priced for its own interval and each resellable on its own. Seeded venues SHALL use one-hour slots that start on the hour, with booking policies that state whole-hour durations, and new courts SHALL default to a one-hour slot length. Extra time on site SHALL be booked as further slots with their own payment, never by lengthening a booked slot. Existing bookings SHALL keep their recorded slots. Vietnamese interfaces, error messages and notifications SHALL name the booking unit as an hour ("giờ", "1h" or "khung giờ") and SHALL NOT show the word "slot".

#### Scenario: Two-hour booking
- **WHEN** a customer books a court with a one-hour slot length from 19:00 to 21:00
- **THEN** the booking SHALL hold two one-hour slots, 19:00–20:00 and 20:00–21:00, each priced for its own hour and each resellable on its own

#### Scenario: Hour wording
- **WHEN** a Vietnamese-language user sees a booking selection, a booking error, a waitlist notification or a resale action
- **THEN** the text SHALL count hours ("2 giờ") or name one hour ("Pass lại 1h này") and SHALL NOT show the word "slot"

#### Scenario: Seeded data uses one-hour slots
- **WHEN** the demo seed finishes
- **THEN** every seeded court SHALL have a 60-minute slot length, every seeded reservation slot, entitlement and price slot SHALL start on the hour and last 60 minutes, and every seeded booking policy SHALL state whole-hour durations

## REMOVED Requirements

### Requirement: Policy-governed booking changes and released availability
**Reason**: The thesis report no longer includes booking cancellation, refunds or rescheduling. Cancellation always ended in an owner refund obligation, and rescheduling either produced a refund difference or needed a second payment flow. Slot resale is the only remaining way to pass on a booking.
**Migration**: Replaced by "Final bookings and released availability". Remove the cancellation, refund and reschedule endpoints (V1 and V2) and the refund inbox. Existing cancellation, refund and reschedule records stay as read-only history.
