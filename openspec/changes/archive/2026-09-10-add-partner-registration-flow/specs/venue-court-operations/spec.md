## ADDED Requirements

### Requirement: Draft venue readiness
New partner venues SHALL start as nonpublic drafts. Before accepting publication submission, the platform MUST validate approved active partner status, required identity/contact/location/description/images, at least one active court, usable opening/booking windows, valid deterministic pricing, supported booking/change/cancellation policies, valid collection configuration and current plan/trial entitlement and quotas. Missing requirements SHALL be returned as an actionable checklist. Optional staff and ancillary services MUST NOT block readiness.

#### Scenario: Incomplete venue
- **WHEN** an owner requests publication for a venue lacking prices or an active court
- **THEN** submission SHALL be refused with the missing requirements and the venue SHALL remain nonpublic

#### Scenario: Complete venue is submitted
- **WHEN** an authorized owner submits a venue passing readiness
- **THEN** it SHALL enter `pending_publication` with a recorded configuration version but SHALL NOT yet accept bookings

### Requirement: Independent publication review
Only authorized platform reviewers SHALL approve venue publication or record correction/rejection with reasons and owner notification. Approval MUST recheck readiness and the reviewed version; partner approval alone SHALL NOT publish venues. Pending reviewed content SHALL be protected from concurrent modification. Material changes to approved identity/location/policies MUST be reviewed before replacing the public version.

#### Scenario: Subscription expires during publication review
- **WHEN** a reviewer approves a venue whose entitlement expired after submission
- **THEN** publication SHALL fail with an actionable reason and SHALL NOT enable booking

#### Scenario: Correct or reject publication
- **WHEN** a reviewer requests corrections or rejects a submitted venue
- **THEN** the venue SHALL remain nonpublic in `revision_required` with the decision, reason and resubmission eligibility retained

#### Scenario: Stale review
- **WHEN** a review decision references an outdated venue configuration
- **THEN** the platform SHALL reject the decision without publishing unreviewed content

### Requirement: Unified public and new-booking eligibility
Public discovery, detail, availability and all new-booking entry points SHALL use the same effective eligibility: approved active partner, approved active venue, valid plan/trial, active court and valid operational calendar. Direct requests MUST recheck eligibility at creation time. Suspension, expiry or closure MUST NOT be bypassed by stale UI, cached availability or a legacy activation toggle.

#### Scenario: Direct booking on nonpublic venue
- **WHEN** a customer submits a known court identifier belonging to a draft or unapproved venue
- **THEN** no new booking SHALL be created and public APIs SHALL NOT disclose private draft content

#### Scenario: Cached availability after suspension
- **WHEN** an owner is suspended after a customer loaded available slots
- **THEN** subsequent new-booking creation SHALL be refused even if the customer's screen still displays those slots

### Requirement: Venue lifecycle and historical policy preservation
The platform SHALL distinguish draft, pending publication, revision required, active, temporarily closed, suspended, closed and archived venues. Nonoperational venues MUST stop new bookings while preserving history and authorized handling of prior obligations. Restoring operations MUST recheck partner/publication/entitlement readiness. Bookings SHALL retain effective prices and supported policy versions; setup MUST NOT offer payment/refund behavior incompatible with existing platform contracts.

#### Scenario: Close a venue with outstanding bookings
- **WHEN** an authorized actor closes or archives a venue with existing obligations
- **THEN** new bookings SHALL stop without deleting or silently cancelling historical or future confirmed bookings

#### Scenario: Policy changes after booking
- **WHEN** an operator changes supported policy configuration after a booking was created
- **THEN** the booking SHALL retain its captured applicable policy/price and SHALL NOT acquire retrospective terms
