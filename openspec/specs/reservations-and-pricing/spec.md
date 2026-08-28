# Reservations and Pricing Specification

## Purpose

Defines tenant-aware reservation and quote behaviour for the v2 API while preserving
the existing v1 booking contract throughout a controlled migration period.

## Requirements

### Requirement: Versioned reservation API compatibility
The platform SHALL expose new reservation functionality under `/api/v2` and SHALL continue serving the existing `/api/v1` booking interface during the declared migration period. A legacy booking MUST remain retrievable through its legacy interface even after its inventory and ownership are represented in the v2 domain.

#### Scenario: Legacy client retrieves a historical booking
- **WHEN** a client calls the supported `/api/v1` booking endpoint for a migrated booking
- **THEN** the platform SHALL return the documented v1-compatible representation

#### Scenario: New client creates a reservation
- **WHEN** an authorized client submits a valid reservation command to `/api/v2`
- **THEN** the platform SHALL create and return a v2 reservation representation

### Requirement: Immutable price quotes
The platform SHALL calculate a price quote from active tenant and venue rules using this precedence: holiday or date exception, peak rule, weekly time rule, then base price. The accepted quote MUST snapshot the applied prices and rule version so later rule changes cannot change the checkout amount.

#### Scenario: Exception overrides a weekly price
- **WHEN** a requested slot matches both a date exception and a weekly time rule
- **THEN** the returned quote SHALL use the date exception price

#### Scenario: Price rules change after quote acceptance
- **WHEN** an administrator changes a pricing rule after checkout accepts a quote
- **THEN** the reservation's recorded amount SHALL remain unchanged

### Requirement: Active inventory exclusivity
The platform SHALL permit no more than one active reservation entitlement for a court-time. It MUST return a valid conflict rather than create overlapping active inventory when simultaneous requests compete for the same court-time.

#### Scenario: Concurrent reservation attempts
- **WHEN** 100 requests simultaneously reserve the same available court-time
- **THEN** exactly one request SHALL succeed and every other request SHALL receive a conflict response

### Requirement: Idempotent v2 business commands
Every v2 command that creates or changes business state SHALL require an `Idempotency-Key`. Retrying the same key with the same authenticated principal and request content SHALL return the original result without repeating the state change; reuse with different content SHALL be rejected.

#### Scenario: Client retries a reservation request
- **WHEN** a client repeats an accepted v2 reservation command with the same idempotency key and content
- **THEN** the platform SHALL return the original reservation result and SHALL not reserve additional inventory

#### Scenario: Idempotency key is reused for another request
- **WHEN** a client sends different command content using an existing idempotency key
- **THEN** the platform SHALL reject the request without applying either additional state change

### Requirement: Trigger real-time status hooks
The platform SHALL trigger a court status change hook upon successful creation or cancellation of a reservation, providing the updated state of the affected inventory.

#### Scenario: Successful reservation triggers hook
- **WHEN** an authorized client successfully creates a v2 reservation
- **THEN** the platform SHALL dispatch a status update hook for the reserved court-time

