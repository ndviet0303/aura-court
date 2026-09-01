## MODIFIED Requirements

### Requirement: Versioned reservation API compatibility
The platform SHALL expose new reservation functionality under `/api/v2` and SHALL continue serving the existing `/api/v1` booking interface during the declared migration period. A legacy booking MUST remain retrievable through its legacy interface even after its inventory and ownership are represented in the v2 domain. Booking creation in both API versions SHALL confirm the reservation immediately upon successful wallet balance deduction instead of entering a `pending_payment` state.

#### Scenario: Legacy client retrieves a historical booking
- **WHEN** a client calls the supported `/api/v1` booking endpoint for a migrated booking
- **THEN** the platform SHALL return the documented v1-compatible representation

#### Scenario: New client creates a reservation
- **WHEN** an authorized client submits a valid reservation command to `/api/v2` with sufficient wallet balance
- **THEN** the platform SHALL deduct the wallet balance, create a `confirmed` reservation, and return the v2 reservation representation without entering a pending payment state

#### Scenario: New client creates a reservation with insufficient balance
- **WHEN** an authorized client submits a valid reservation command to `/api/v2` with insufficient wallet balance
- **THEN** the platform SHALL reject the reservation with a validation error indicating the shortfall amount, without creating any reservation or slot records

### Requirement: Active inventory exclusivity
The platform SHALL permit no more than one active reservation entitlement for a court-time. It MUST return a valid conflict rather than create overlapping active inventory when simultaneous requests compete for the same court-time. Since reservations are confirmed atomically with wallet deduction, slots transition directly to `confirmed` without passing through a `held` intermediate state for new bookings.

#### Scenario: Concurrent reservation attempts
- **WHEN** 100 requests simultaneously reserve the same available court-time
- **THEN** exactly one request SHALL succeed and every other request SHALL receive a conflict response

## REMOVED Requirements

### Requirement: Payment expiry for pending reservations
**Reason**: Reservations are no longer created in a `pending_payment` state with a 15-minute timeout. Payment is confirmed atomically during reservation creation via wallet deduction. The `payment_expires_at` field, the `ExpirePendingBookings` job, and the frontend countdown timer are no longer needed for new bookings.
**Migration**: Existing `pending_payment` bookings should be expired by the existing job before migration. After migration, the expiry job may be retained temporarily to handle edge cases but will receive no new pending bookings.
