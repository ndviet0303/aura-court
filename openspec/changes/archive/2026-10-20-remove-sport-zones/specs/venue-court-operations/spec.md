## MODIFIED Requirements

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

## ADDED Requirements

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
