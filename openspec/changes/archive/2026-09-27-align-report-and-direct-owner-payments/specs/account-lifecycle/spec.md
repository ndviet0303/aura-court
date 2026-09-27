## ADDED Requirements

### Requirement: Consistent account eligibility at every entry point
Restricted accounts SHALL be denied login via password and linked Google/Facebook identity and SHALL be denied new booking, recurrence, transfer and community mutations through existing sessions. Email verification SHALL be required for customer-originated V1/V2 bookings. A nonempty phone field MUST NOT count as verified; partner initiation/submission SHALL require verified email or actual phone-verification evidence. Staff-assisted walk-ins SHALL record the authorized actor and identified customer under the same documented eligibility policy.

#### Scenario: Restricted existing session
- **WHEN** an account is locked after login and attempts a new booking through either API version
- **THEN** the request SHALL fail without payment, inventory or point effects

#### Scenario: Unverified V2 customer
- **WHEN** an unverified customer submits checkout or a recurring series directly
- **THEN** creation SHALL fail with verification guidance

#### Scenario: Phone merely entered
- **WHEN** an applicant with unverified email enters a phone number without verification evidence
- **THEN** partner submission SHALL remain unavailable

