## Purpose

Binds every cashier shift and point-of-sale order to one venue, and limits what each user sees of shifts to their own open shift and the venues they operate.

## ADDED Requirements

### Requirement: Shifts and orders belong to one venue
Opening a shift SHALL bind it to one venue of the organization that the caller is allowed to operate. When the caller operates exactly one venue and none is given, that venue SHALL be used. When the caller operates several venues, a venue SHALL be required; this applies to organization owners as well as staff. A shift request for a venue the caller does not operate SHALL be rejected as forbidden. Every POS order SHALL record the venue of the cashier's open shift. An attached booking SHALL belong to that venue, and an order whose requested venue or attached booking is at another venue SHALL be rejected without any payment, stock or loyalty effect.

#### Scenario: Owner of several venues opens a shift
- **WHEN** an owner of an organization with venues A and B opens a shift without choosing a venue
- **THEN** the request SHALL be rejected with a validation error asking for a venue

#### Scenario: Staff with one venue
- **WHEN** a staff member assigned only to venue A opens a shift without choosing a venue
- **THEN** the shift SHALL be opened at venue A

#### Scenario: Booking at another venue
- **WHEN** a cashier whose shift is at venue A attaches a booking for a court at venue B
- **THEN** the order SHALL be rejected and no payment, stock movement or points SHALL be recorded

### Requirement: Active shift is the caller's own
The active-shift read SHALL return only the open shift opened by the caller in the current organization. When the caller has no open shift, it SHALL return no shift, never another cashier's shift, even for an organization owner.

#### Scenario: Owner without a shift while staff work
- **WHEN** a staff member has an open shift and the owner, who has none, opens the POS screen
- **THEN** the owner SHALL see no active shift and SHALL be offered to open one

#### Scenario: Cashier with an open shift
- **WHEN** a cashier with an open shift at venue A opens the POS screen
- **THEN** that shift, its venue and its running totals SHALL be returned

### Requirement: Shift list limited to operable venues
The shift list SHALL contain only shifts of venues the caller operates: every venue of the organization for an owner, and assigned venues for staff. Shifts recorded before venues became mandatory and having no venue SHALL be visible to organization owners only. Each listed shift SHALL carry its own venue identifier and name, and a shift without a venue SHALL be shown as having none, never labelled or filtered as another venue.

#### Scenario: Staff lists shifts
- **WHEN** a staff member assigned to venue A lists shifts while shifts are open at venues A and B
- **THEN** only venue A's shifts SHALL be returned

#### Scenario: Owner filters by venue
- **WHEN** an owner with shifts at venues A and B selects venue B in the shift list
- **THEN** only venue B's shifts SHALL be shown, each labelled with venue B's name

#### Scenario: Legacy shift without venue
- **WHEN** the list contains a shift that has no venue
- **THEN** it SHALL be labelled as having no venue and SHALL NOT appear under any venue's filter
