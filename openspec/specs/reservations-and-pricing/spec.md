# Reservations and Pricing Specification

## Purpose

Defines tenant-aware reservation and quote behaviour for the v2 API while preserving
the existing v1 booking contract throughout a controlled migration period.

## Requirements

### Requirement: Versioned reservation API compatibility
The platform SHALL expose new reservation functionality under `/api/v2` and SHALL continue serving the existing `/api/v1` booking interface during the declared migration period, except for the explicitly retired cancellation and rescheduling commands. A legacy booking MUST remain retrievable through its legacy interface even after its inventory and ownership are represented in the v2 domain. Booking creation in both API versions SHALL confirm the reservation immediately upon successful wallet balance deduction instead of entering a `pending_payment` state. POST `/api/v1/bookings/{code}/cancel`, `/api/v1/bookings/{code}/cancel-slots`, `/api/v1/bookings/{code}/reschedule` and `/api/v2/reservations/{reservation}/cancel` SHALL no longer be supported and SHALL return HTTP 404 for an otherwise valid authenticated request without executing a business mutation.

#### Scenario: Legacy client retrieves a historical booking
- **WHEN** a client calls the supported `/api/v1` booking endpoint for a migrated booking
- **THEN** the platform SHALL return the documented v1-compatible representation, including historical cancelled or refunded states

#### Scenario: New client creates a reservation
- **WHEN** an authorized client submits a valid reservation command to `/api/v2` with sufficient wallet balance
- **THEN** the platform SHALL deduct the wallet balance, create a `confirmed` reservation, and return the v2 reservation representation without entering a pending payment state

#### Scenario: New client creates a reservation with insufficient balance
- **WHEN** an authorized client submits a valid reservation command to `/api/v2` with insufficient wallet balance
- **THEN** the platform SHALL reject the reservation with a validation error indicating the shortfall amount, without creating any reservation or slot records

#### Scenario: Stale client invokes a retired command
- **WHEN** an authenticated client posts an otherwise valid request to any retired cancellation or rescheduling URL, including a retry with a previously used idempotency key
- **THEN** the platform SHALL return HTTP 404 without replaying a former success or changing booking, inventory or financial state

### Requirement: Immutable price quotes
The platform SHALL calculate a price quote from active tenant and venue rules using this precedence: holiday or date exception, peak rule, weekly time rule, then base price. The accepted quote MUST snapshot the applied prices and rule version so later rule changes cannot change the checkout amount.

#### Scenario: Exception overrides a weekly price
- **WHEN** a requested slot matches both a date exception and a weekly time rule
- **THEN** the returned quote SHALL use the date exception price

#### Scenario: Price rules change after quote acceptance
- **WHEN** an administrator changes a pricing rule after checkout accepts a quote
- **THEN** the reservation's recorded amount SHALL remain unchanged

### Requirement: Active inventory exclusivity
The platform SHALL permit no more than one active reservation entitlement for a court-time. It MUST return a valid conflict rather than create overlapping active inventory when simultaneous requests compete for the same court-time. Since reservations are confirmed atomically with wallet deduction, slots transition directly to `confirmed` without passing through a `held` intermediate state for new bookings.

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
The platform SHALL trigger a court status change hook upon successful creation of a reservation or automatic expiry that releases held inventory, providing the updated state of the affected inventory. A rejected cancellation or rescheduling request SHALL NOT trigger a court status change hook.

#### Scenario: Successful reservation triggers hook
- **WHEN** an authorized client successfully creates a v2 reservation
- **THEN** the platform SHALL dispatch a status update hook for the reserved court-time

#### Scenario: Unpaid inventory expires
- **WHEN** an unpaid hold expires and releases its court-time
- **THEN** the platform SHALL dispatch a status update hook for the released court-time after commit

#### Scenario: Retired operation does not publish availability
- **WHEN** a cancellation or rescheduling request is rejected
- **THEN** no court status change event SHALL be emitted for that request

### Requirement: Authoritative ordinary checkout and promotion result
Ordinary V1 and V2 checkout SHALL produce equivalent wallet-funded confirmed outcomes under the existing wallet specification. Server-calculated coupon and membership eligibility and permitted combination SHALL be reflected in the accepted price snapshot; frontend totals SHALL NOT authorize discounts. Legacy held records SHALL NOT be confirmed without a recorded valid payment. Specialized split and recurring invoice lifecycles retain their existing explicit rules.

#### Scenario: Tampered discount
- **WHEN** a client submits an unearned discount
- **THEN** checkout rejects or recalculates it without undercharging

#### Scenario: Legacy unpaid hold
- **WHEN** an old client confirms a held ordinary reservation without funds
- **THEN** confirmation fails without granting an active entitlement

### Requirement: Booking changes and released availability
Booking history SHALL show authoritative status after reload. The platform SHALL NOT allow user-initiated cancellation of a whole V1 booking, individual V1 slots or a V2 reservation, or rescheduling of its court/date/time. This rule SHALL apply to existing and new bookings, legacy unpaid holds, all roles and all venue states; historical grace periods or membership benefits SHALL NOT enable retired commands. Rejected attempts MUST preserve booking totals, inventory, entitlements, coupon usage, balances and history, and MUST NOT create replacement bookings or waitlist notifications. Automatic unpaid-hold expiry SHALL remain available and release eligible inventory exactly once without refunding an unpaid booking. Released slots SHALL notify eligible waitlisted users without promising an automatic booking. Specialized recurring invoice overdue release, split-payment expiry and marketplace transfer contracts remain unchanged.

#### Scenario: Whole or partial cancellation is unavailable
- **WHEN** a customer, staff member or administrator attempts to cancel a booking, selected slots or reservation before or after its former grace period
- **THEN** the operation SHALL fail without releasing inventory or changing any associated business or financial record

#### Scenario: Rescheduling is unavailable at any price
- **WHEN** a client requests a replacement court/time at a lower, equal or higher price
- **THEN** the operation SHALL fail without changing the original booking, creating a replacement, or debiting or crediting a wallet

#### Scenario: Reschedule conflict
- **WHEN** a client requests rescheduling to an occupied replacement slot
- **THEN** the retired command SHALL return HTTP 404 without attempting replacement allocation, and the original booking and balance SHALL remain unchanged

#### Scenario: Cancel retry
- **WHEN** a client retries cancellation of a paid booking, including a booking already cancelled before retirement
- **THEN** the retired command SHALL return HTTP 404 without releasing inventory, issuing a refund or changing historical state

#### Scenario: Suspended venue and historical policy do not restore cancellation
- **WHEN** a booking at a suspended venue has a saved cancellation grace period
- **THEN** the booking SHALL remain retrievable but its cancellation and rescheduling commands SHALL remain unavailable

#### Scenario: Legacy unpaid hold is not manually cancelled
- **WHEN** a client requests cancellation of an unexpired legacy unpaid hold
- **THEN** the operation SHALL fail and the hold SHALL remain governed by its existing payment/expiry lifecycle

#### Scenario: Automatic expiry preserves paid bookings
- **WHEN** expiry processing is retried for eligible unpaid holds alongside confirmed paid bookings
- **THEN** only eligible unpaid inventory SHALL be released, without duplicate effects or changes to confirmed paid bookings

#### Scenario: Waitlist release
- **WHEN** a slot with waiting customers becomes available through a supported inventory-release lifecycle
- **THEN** eligible customers SHALL receive a notification and must complete normal checkout
