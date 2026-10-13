## MODIFIED Requirements

### Requirement: Catalog and operational calendar
Authorized venue operators SHALL persist identity, address/location, contact details, description, image collection, amenities, sports, court surface/capacity and operational state. Weekly opening intervals, dated exceptions, holidays and maintenance SHALL be persisted by venue/court, timezone-aware and used identically by discovery, quote, booking, recurrence and community inventory. Changes conflicting with confirmed inventory SHALL be rejected with the affected booking references; those bookings SHALL remain valid until they are played or moved to an equivalent court through incident reassignment.

#### Scenario: Closed slot
- **WHEN** an operator closes a court period without existing obligations
- **THEN** all clients and direct booking calls SHALL show/refuse the same closed interval after reload on another device

#### Scenario: Existing bookings
- **WHEN** a proposed schedule change or closure overlaps confirmed bookings or sessions
- **THEN** the change SHALL be rejected with affected references without silently cancelling them

#### Scenario: Catalog persistence
- **WHEN** an owner saves contact information, venue images or court surface/capacity
- **THEN** permitted detail and publication review SHALL retain the same saved values

### Requirement: Report-aligned draft venue readiness
New venues SHALL remain nonpublic drafts until identity/contact/location/description/images, at least one active court, usable calendar/booking windows, deterministic prices, verified owner collection connection, versioned booking policy, approved active partner, valid subscription/trial and quotas are satisfied. The booking policy SHALL define the booking window, minimum and maximum durations, arrival rules, recurring terms and resale terms, and SHALL NOT define cancellation, refund or change terms. The checklist SHALL expose missing requirements; optional employees and ancillary products SHALL not be mandatory.

#### Scenario: Incomplete venue
- **WHEN** a venue lacks a verified receiving connection or booking policy
- **THEN** publication submission SHALL fail with actionable missing fields

#### Scenario: Complete venue is submitted
- **WHEN** an authorized owner submits a complete current configuration
- **THEN** a versioned pending_publication revision SHALL be recorded without prematurely publishing it

#### Scenario: No optional staff
- **WHEN** a complete venue has no employees or POS products
- **THEN** those omissions SHALL NOT block readiness

### Requirement: Unified public and new-booking eligibility
Public discovery, detail, availability and all new-booking entry points SHALL use the same effective eligibility: approved active partner, approved active venue, valid plan/trial, active court with an applicable configured price, and valid operational calendar. Direct requests MUST recheck eligibility at creation time. Suspension, expiry or closure MUST NOT be bypassed by stale UI, cached availability or a legacy activation toggle. Interfaces MUST NOT display a substitute or default price for a court that has no applicable configured price.

#### Scenario: Direct booking on nonpublic venue
- **WHEN** a customer submits a known court identifier belonging to a draft or unapproved venue
- **THEN** no new booking SHALL be created and public APIs SHALL NOT disclose private draft content

#### Scenario: Cached availability after suspension
- **WHEN** an owner is suspended after a customer loaded available slots
- **THEN** subsequent new-booking creation SHALL be refused even if the customer's screen still displays those slots

#### Scenario: Court without a configured price
- **WHEN** an active court of a published venue has no applicable price rule or configured base price
- **THEN** public lists, detail and availability SHALL omit it, new bookings for it SHALL be refused, and no interface SHALL show a substitute price for it

### Requirement: Venue lifecycle and captured policy continuity
The platform SHALL distinguish draft, pending publication, revision required, published/active, temporarily closed, suspended, closed and archived outcomes. Only approved current public versions SHALL be exposed. Suspension/closure/expiry SHALL block new business but preserve historical and future obligations. Restoration SHALL recheck readiness and require platform authority for suspension. Price and policy changes SHALL not rewrite existing booking snapshots; authorized servicing SHALL use the captured policy and record explicit revisions.

#### Scenario: Close a venue with outstanding bookings
- **WHEN** an operator closes a venue with confirmed bookings
- **THEN** new business SHALL stop, authorized servicing of the existing bookings SHALL remain available, and any settlement with affected customers SHALL happen outside the platform without being presented as a platform refund

#### Scenario: Policy changes after booking
- **WHEN** the owner publishes a new booking policy
- **THEN** existing bookings SHALL retain their accepted terms rather than silently inherit the new policy

#### Scenario: Restore suspended venue
- **WHEN** an ordinary owner requests restoration of a platform-suspended venue
- **THEN** the request SHALL be denied until a platform review and fresh eligibility checks succeed
