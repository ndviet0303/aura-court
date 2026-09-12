## MODIFIED Requirements

### Requirement: Draft venue readiness
New partner venues SHALL start as nonpublic drafts. Before accepting publication submission, the platform MUST validate approved active partner status, required identity/contact/location/description/images, at least one active court, usable opening/booking windows, valid deterministic pricing, valid collection configuration and current plan/trial entitlement and quotas. Booking cancellation and rescheduling SHALL be unavailable platform-wide; readiness MUST NOT require a cancellation grace period or configurable change/refund policy. Missing requirements SHALL be returned as an actionable checklist. Optional staff and ancillary services MUST NOT block readiness.

#### Scenario: Incomplete venue
- **WHEN** an owner requests publication for a venue lacking prices or an active court
- **THEN** submission SHALL be refused with the missing requirements and the venue SHALL remain nonpublic

#### Scenario: Complete venue is submitted
- **WHEN** an authorized owner submits a venue passing readiness
- **THEN** it SHALL enter `pending_publication` with a recorded configuration version but SHALL NOT yet accept bookings

#### Scenario: No configurable cancellation policy is needed
- **WHEN** an otherwise complete venue has no cancellation grace period or has an obsolete stored value
- **THEN** that obsolete configuration SHALL NOT block readiness or enable cancellation/rescheduling

### Requirement: Venue lifecycle and historical policy preservation
The platform SHALL distinguish draft, pending publication, revision required, active, temporarily closed, suspended, closed and archived venues. Nonoperational venues MUST stop new bookings while preserving history and authorized handling of prior obligations. Restoring operations MUST recheck partner/publication/entitlement readiness. Bookings SHALL retain their captured price and policy snapshots without rewriting history. Retired booking cancellation and rescheduling commands SHALL remain unavailable even when an older snapshot describes them; other supported captured terms SHALL remain unchanged. Setup MUST NOT offer cancellation/rescheduling overrides or payment/refund behavior incompatible with platform contracts.

#### Scenario: Close a venue with outstanding bookings
- **WHEN** an authorized actor closes or archives a venue with existing obligations
- **THEN** new bookings SHALL stop without deleting or silently cancelling historical or future confirmed bookings

#### Scenario: Policy changes after booking
- **WHEN** an operator changes supported policy configuration after a booking was created
- **THEN** the booking SHALL retain its captured policy/price without acquiring new terms, except that retired cancellation/rescheduling commands SHALL no longer be available

#### Scenario: Attempt to restore a removed benefit
- **WHEN** an operator submits a cancellation grace period or cancellation-fee-waiver benefit through an old organization or membership form
- **THEN** the platform SHALL reject the obsolete fields as validation errors without persisting an override or enabling cancellation/rescheduling
