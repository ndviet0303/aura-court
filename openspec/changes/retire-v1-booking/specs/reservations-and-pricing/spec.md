## RENAMED Requirements

- FROM: `### Requirement: Versioned reservation API compatibility`
- TO: `### Requirement: Unversioned reservation API`

## MODIFIED Requirements

### Requirement: Unversioned reservation API
The platform SHALL serve one reservation command and query API under `/api`, with no version segment in any path. The V1 booking API SHALL be retired only when all of the following hold:
- every historical booking is mapped to exactly one reservation;
- every active booking slot at a future time has an active mapped reservation slot;
- every first-party client uses the reservation API for checkout, payment, history, reviews and disputes;
- the retiring change records the project owner's approval and the evidence it relied on.

After retirement, every V1 booking-specific endpoint SHALL reject requests without creating, reading or changing any record. Historical bookings SHALL stay unchanged as read-only history, and their live state SHALL be kept on the mapped reservation.

New direct-owner checkout SHALL create a pending-payment booking with exclusive expiring holds and receiver-bound payment instructions. Confirmation SHALL require verified payment, or a documented zero-payable outcome. Historical wallet bookings SHALL retain recorded amounts, payment model and terms, and SHALL remain readable through their mapped reservation. Old idempotency records MUST NOT authorize a different new command.

#### Scenario: Legacy client retrieves a historical booking
- **WHEN** a customer or administrator views a migrated historical wallet booking
- **THEN** its original code, financial references and ownership history SHALL remain readable through its mapped reservation, which SHALL show its current status

#### Scenario: New client creates a reservation
- **WHEN** an eligible client creates a direct-payment booking
- **THEN** it SHALL do so through the reservation API, and the result SHALL reference one pending obligation and inventory hold without debiting a wallet

#### Scenario: Insufficient payment
- **WHEN** only partial or unverified funds are received
- **THEN** the booking SHALL remain unconfirmed and unpaid inventory SHALL follow its captured expiry policy

#### Scenario: Stale client invokes a retired command
- **WHEN** a client calls a removed cancellation, refund or reschedule command, or replays an old idempotency key against a different command
- **THEN** the server SHALL reject it or return only its original outcome, never mutate the new model by replaying an old response

#### Scenario: New client creates a reservation with insufficient balance
- **WHEN** a direct-cohort customer has insufficient legacy wallet balance but requests a valid direct-owner booking
- **THEN** the server SHALL create the policy-bound pending direct payment without a wallet debit; confirmation SHALL still require verified owner payment or a legitimate zero-payable result

#### Scenario: Retirement preconditions not met
- **WHEN** any booking lacks a reservation mapping, an active future booking slot lacks an active mapped reservation slot, or the owner's approval is not recorded
- **THEN** the V1 booking API SHALL keep serving and V1 retirement SHALL NOT proceed

#### Scenario: Stale client calls a retired V1 booking endpoint
- **WHEN** after retirement a client calls a V1 booking-specific endpoint to list, create, pay for, change or export a booking
- **THEN** the request SHALL be rejected, and no booking, slot, payment, reservation, idempotency or notification record SHALL be created or changed

#### Scenario: Stale client calls a versioned path
- **WHEN** a client calls a path under `/api/v1` or `/api/v2`
- **THEN** the server SHALL answer not found without creating or changing any record

#### Scenario: Historical booking without a mapping
- **WHEN** the backfill cannot map a historical booking to a reservation
- **THEN** the booking SHALL be reported with its reason, SHALL remain unchanged, and SHALL block retirement until it is resolved
