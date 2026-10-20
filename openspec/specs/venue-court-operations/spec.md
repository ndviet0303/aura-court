## Purpose

Provides an authoritative court catalog and operational calendar so customer availability agrees with venue opening and closure rules.

## Requirements

### Requirement: Catalog and operational calendar
Authorized venue operators SHALL persist identity, address/location, contact details, description, image collection, amenities, sports, court surface/capacity and operational state. Weekly opening intervals, dated exceptions, holidays and maintenance SHALL be persisted by venue/court, timezone-aware and used identically by discovery, quote, booking, recurrence and community inventory. Changes conflicting with confirmed inventory SHALL be rejected with references until handled through an authorized change workflow.

#### Scenario: Closed slot
- **WHEN** an operator closes a court period without existing obligations
- **THEN** all clients and direct booking calls SHALL show/refuse the same closed interval after reload on another device

#### Scenario: Existing bookings
- **WHEN** a proposed schedule change or closure overlaps confirmed bookings or sessions
- **THEN** the change SHALL be rejected with affected references without silently cancelling them

#### Scenario: Catalog persistence
- **WHEN** an owner saves contact information, venue images or court surface/capacity
- **THEN** permitted detail and publication review SHALL retain the same saved values

### Requirement: Catalog and publication contract completeness
Venue creation/update/readiness/review APIs SHALL resolve valid request handlers, preserve all supported fields, enforce organization ownership and version checks, and return documented structures consumed by the actual UI. Public venue and court list/detail/search/cache responses SHALL use the same publication/partner/subscription eligibility without exposing drafts or unreviewed edits. Rollout flags MUST NOT silently admit ineligible new tenants.

#### Scenario: Known draft identifier
- **WHEN** a guest requests a draft court detail by ID
- **THEN** private content SHALL not be returned

#### Scenario: Readiness and owner creation
- **WHEN** an eligible owner creates a draft and evaluates readiness through the UI
- **THEN** requests SHALL reach valid handlers and missing data SHALL be reported rather than an internal class-resolution error

#### Scenario: Subscription expires
- **WHEN** a previously cached public venue loses entitlement
- **THEN** new public availability and booking SHALL be invalidated or re-evaluated without relying on the stale cache

### Requirement: Court lifecycle preservation
Deactivating or removing a court SHALL preserve historical booking and financial references and SHALL prevent new reservations for that court.

#### Scenario: Deactivate court
- **WHEN** an operator deactivates a court with booking history
- **THEN** history remains accessible and new reservations are rejected

### Requirement: Report-aligned draft venue readiness
New venues SHALL remain nonpublic drafts until identity/contact/location/description/images, at least one active court, usable calendar/booking windows, deterministic prices, verified owner collection connection, versioned change/cancellation/refund policy, approved active partner, valid subscription/trial and quotas are satisfied. The checklist SHALL expose missing requirements; optional employees and ancillary products SHALL not be mandatory.

#### Scenario: Incomplete venue
- **WHEN** a venue lacks a verified receiving connection or cancellation policy
- **THEN** publication submission SHALL fail with actionable missing fields

#### Scenario: Complete venue is submitted
- **WHEN** an authorized owner submits a complete current configuration
- **THEN** a versioned pending_publication revision SHALL be recorded without prematurely publishing it

#### Scenario: No optional staff
- **WHEN** a complete venue has no employees or POS products
- **THEN** those omissions SHALL NOT block readiness

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

### Requirement: Venue lifecycle and captured policy continuity
The platform SHALL distinguish draft, pending publication, revision required, published/active, temporarily closed, suspended, closed and archived outcomes. Only approved current public versions SHALL be exposed. Suspension/closure/expiry SHALL block new business but preserve historical and future obligations. Restoration SHALL recheck readiness and require platform authority for suspension. Price and policy changes SHALL not rewrite existing booking snapshots; authorized changes SHALL use the captured policy and record explicit revisions.

#### Scenario: Close a venue with outstanding bookings
- **WHEN** an operator closes a venue with confirmed bookings
- **THEN** new business SHALL stop and authorized servicing, change requests and owner refund tracking SHALL remain available

#### Scenario: Policy changes after booking
- **WHEN** the owner publishes a new cancellation policy
- **THEN** existing bookings SHALL retain their accepted terms rather than silently inherit the new policy

#### Scenario: Restore suspended venue
- **WHEN** an ordinary owner requests restoration of a platform-suspended venue
- **THEN** the request SHALL be denied until a platform review and fresh eligibility checks succeed

### Requirement: Area and nearby court discovery
Customer court discovery SHALL group and filter public courts by the venue's area (district, falling back to city) rather than by venue identity, and SHALL show each court's area so same-named courts at different venues stay distinguishable. Venues MAY store a validated latitude/longitude pair, which SHALL be part of the publication review snapshot. On explicit request, the client MAY use the device's position to sort areas and courts nearest first; that position MUST stay on the device, MUST NOT be sent to the server or stored, and venues without coordinates SHALL sort last. Denied or unavailable location SHALL leave discovery usable and explain how to recover.

#### Scenario: Filter by area
- **WHEN** a customer selects an area such as Cầu Giấy
- **THEN** only courts whose venue lies in that area SHALL be offered, each labelled with its area

#### Scenario: Nearest first
- **WHEN** a customer grants location access from the "near me" control
- **THEN** areas and courts SHALL be ordered by distance, the nearest court SHALL be selected and its distance shown, without any request carrying the customer's coordinates

#### Scenario: Location denied
- **WHEN** the browser denies or cannot provide a position
- **THEN** the original ordering SHALL remain and an explanation SHALL be shown

### Requirement: Courts carry their own sport and booking interval
An organization SHALL own venues, and each venue SHALL own courts directly, with no grouping layer between venue and court. Every court SHALL record exactly one sport from the supported list (badminton, football_5v5, football_7v7, tennis, pickleball, basketball, volleyball, table_tennis) and a booking interval length in minutes, which defaults to 60. Authorized operators SHALL choose the sport when creating or editing a court; the court SHALL belong to one active venue of the operator's organization. An unsupported sport SHALL be rejected. Creating a venue SHALL NOT create any court or court grouping implicitly. Availability, quotes, booking and recurring sessions SHALL take the interval length from the court itself. Sport filters on public court and venue lists SHALL match the courts' own sport. The platform SHALL NOT expose sport-zone list, detail or management endpoints.

#### Scenario: Operator creates a court
- **WHEN** an authorized operator creates a court with sport pickleball and no interval length while one of their venues is selected
- **THEN** the court SHALL be stored under that venue with sport pickleball and a 60-minute interval, and SHALL appear under the pickleball filter once it is priced and its venue is eligible

#### Scenario: Unsupported sport
- **WHEN** an operator submits a court with a sport outside the supported list
- **THEN** the request SHALL be rejected with a validation error and no court SHALL be created or changed

#### Scenario: Sport filter on public lists
- **WHEN** a guest lists courts or venues filtered by sport tennis
- **THEN** only courts whose own sport is tennis, and only venues with at least one active tennis court, SHALL be returned

#### Scenario: New venue has no implicit grouping
- **WHEN** an owner creates a venue
- **THEN** the venue SHALL have no courts until the owner adds them, and no sport grouping SHALL be created

#### Scenario: Retired sport-zone endpoints
- **WHEN** any client requests a sport-zone list, detail or management endpoint
- **THEN** the platform SHALL answer 404

#### Scenario: Existing courts keep their sport and interval
- **WHEN** the change is migrated onto existing data
- **THEN** every court SHALL keep the sport and interval length of the grouping it belonged to, courts without a grouping SHALL get badminton and 60 minutes, each court SHALL keep its own active state, and no court's price or interval length SHALL change
