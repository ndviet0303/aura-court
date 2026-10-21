## ADDED Requirements

### Requirement: Attendance lookup scope
Looking up a booking by its code or booking QR for on-site attendance SHALL return the booking's details only to staff assigned to the booking's venue and to owners of the venue's organization. Any other staff member, including staff of the same organization assigned to other venues, SHALL be refused with 403 without any customer name, phone number, payment or booking detail. The lookup SHALL NOT change any booking, attendance or payment record.

#### Scenario: Assigned staff
- **WHEN** staff assigned to venue A look up the code of a confirmed booking at venue A
- **THEN** the booking's details SHALL be returned and nothing SHALL change

#### Scenario: Staff of another venue
- **WHEN** staff of the same organization assigned only to venue B look up the code of a booking at venue A
- **THEN** the request SHALL be refused with 403 and no customer or booking detail SHALL be returned

#### Scenario: Owner
- **WHEN** an owner of the organization looks up the code of a booking at any of its venues
- **THEN** the booking's details SHALL be returned
