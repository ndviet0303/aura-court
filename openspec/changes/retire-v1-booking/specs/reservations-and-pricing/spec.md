## MODIFIED Requirements

### Requirement: Versioned reservation API compatibility
V2 SHALL be the only reservation command and query API for new work. The V1 booking API SHALL remain served only until the V1 retirement gate passes. The gate requires all of the following:
- every historical booking is mapped to exactly one reservation;
- every first-party client uses V2 for checkout, payment, history, booking changes, reviews and disputes;
- daily adoption evidence covering the preceding 30 days shows no new V1 booking and no V1 booking in an active state;
- the retirement readiness check passes.

After retirement, every V1 booking-specific endpoint SHALL reject requests without creating, reading or changing any record.

New direct-owner checkout SHALL create a pending-payment booking with exclusive expiring holds and receiver-bound payment instructions. Confirmation SHALL require verified payment, or a documented zero-payable outcome. The former cancellation/rescheduling retirement SHALL be superseded by policy-governed requests. Historical wallet bookings SHALL retain recorded amounts, payment model and terms, and SHALL remain readable through their mapped reservation. Old idempotency records MUST NOT authorize a different new command.

#### Scenario: Legacy client retrieves a historical booking
- **WHEN** a customer or administrator views a migrated historical wallet booking
- **THEN** the original code, status, financial references and ownership history SHALL remain readable through its mapped reservation, before and after V1 retirement

#### Scenario: New client creates a reservation
- **WHEN** an eligible client creates a direct-payment booking
- **THEN** it SHALL do so through V2, and the result SHALL reference one pending obligation and inventory hold without debiting a wallet

#### Scenario: Insufficient payment
- **WHEN** only partial or unverified funds are received
- **THEN** the booking SHALL remain unconfirmed and unpaid inventory SHALL follow its captured expiry policy

#### Scenario: Stale client invokes a retired command
- **WHEN** a stale old key is used against the new cancellation/change workflow
- **THEN** the server SHALL reject incompatible replay or return only its original outcome, never mutate the new model by replaying an old response

#### Scenario: New client creates a reservation with insufficient balance
- **WHEN** a direct-cohort customer has insufficient legacy wallet balance but requests a valid direct-owner booking
- **THEN** the server SHALL create the policy-bound pending direct payment without a wallet debit; confirmation SHALL still require verified owner payment or a legitimate zero-payable result

#### Scenario: Retirement gate not met
- **WHEN** any booking lacks a reservation mapping, a V1 booking was created within the preceding 30 days, a V1 booking is still pending or confirmed for a future time, or the readiness check fails
- **THEN** the V1 booking API SHALL keep serving and V1 retirement SHALL NOT proceed

#### Scenario: Stale client calls a retired V1 booking endpoint
- **WHEN** after retirement a client calls a V1 booking-specific endpoint to list, create, pay for, change or export a booking
- **THEN** the request SHALL be rejected, and no booking, slot, payment, reservation, idempotency or notification record SHALL be created or changed

#### Scenario: Historical booking without a mapping
- **WHEN** the backfill cannot map a historical booking to a reservation
- **THEN** the booking SHALL be reported with its reason, SHALL remain unchanged, and SHALL block retirement until it is resolved
