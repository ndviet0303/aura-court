## Purpose

Provides an authoritative court catalog and operational calendar so customer availability agrees with venue opening and closure rules.

## Requirements

### Requirement: Catalog and operational calendar
Authorized operators SHALL maintain venue, sport zone, court details, amenities, active state, weekly hours, dated exceptions and maintenance closures. Customer availability and booking validation SHALL use the same effective calendar.

#### Scenario: Closed slot
- **WHEN** an operator closes an unbooked court period
- **THEN** the period is unavailable in search and a direct booking request is rejected

#### Scenario: Existing bookings
- **WHEN** a closure conflicts with confirmed bookings
- **THEN** the operation is rejected with affected booking references instead of silently cancelling them

#### Scenario: Catalog persistence
- **WHEN** an operator updates permitted court details
- **THEN** a refreshed public court view reflects the saved details

### Requirement: Court lifecycle preservation
Deactivating or removing a court SHALL preserve historical booking and financial references and SHALL prevent new reservations for that court.

#### Scenario: Deactivate court
- **WHEN** an operator deactivates a court with booking history
- **THEN** history remains accessible and new reservations are rejected
