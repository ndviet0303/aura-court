## MODIFIED Requirements

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

## ADDED Requirements

### Requirement: Catalog and publication contract completeness
Venue creation/update/readiness/review APIs SHALL resolve valid request handlers, preserve all supported fields, enforce organization ownership and version checks, and return documented structures consumed by the actual UI. Public venue, court and sport-zone list/detail/search/cache responses SHALL use the same publication/partner/subscription eligibility without exposing drafts or unreviewed edits. Rollout flags MUST NOT silently admit ineligible new tenants.

#### Scenario: Known draft identifier
- **WHEN** a guest requests a draft court or sport-zone detail by ID
- **THEN** private content SHALL not be returned

#### Scenario: Readiness and owner creation
- **WHEN** an eligible owner creates a draft and evaluates readiness through the UI
- **THEN** requests SHALL reach valid handlers and missing data SHALL be reported rather than an internal class-resolution error

#### Scenario: Subscription expires
- **WHEN** a previously cached public venue loses entitlement
- **THEN** new public availability and booking SHALL be invalidated or re-evaluated without relying on the stale cache

## REMOVED Requirements

### Requirement: Draft venue readiness
**Reason**: Readiness must now require explicit change/refund policy and verified direct owner collection instead of the retired blanket no-change rule.
**Migration**: Re-evaluate new publication revisions with actionable requirements without silently deactivating legacy venues or fabricating policy acceptance.

## ADDED Requirements

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


## REMOVED Requirements

### Requirement: Venue lifecycle and historical policy preservation
**Reason**: The permanent prohibition on changes even for earlier policies is replaced by captured-policy requests with explicit handling of legacy obligations.
**Migration**: Keep venue history and authorization, recheck restoration eligibility and preserve historical money/terms; review unresolved legacy change entitlements before new processing.

## ADDED Requirements

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

