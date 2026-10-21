## MODIFIED Requirements

### Requirement: Courts carry their own sport and booking interval
An organization SHALL own venues, and each venue SHALL own courts directly, with no grouping layer between venue and court. Every court SHALL record exactly one sport from the supported list (badminton, football_5v5, football_7v7, tennis, pickleball, basketball, volleyball, table_tennis) and a booking interval length in minutes, which SHALL be one of 30, 60, 90 or 120 and defaults to 60. The owner SHALL choose the sport when creating a court; the court SHALL belong to one venue of the owner's organization that is not closed, so an inactive venue can receive its first court before it opens, and the new court SHALL start inactive. A court's sport and venue SHALL NOT change after creation: an update that changes either SHALL be rejected with 422 and SHALL change nothing. A court update SHALL change only the code, description and images, plus the retained surface, capacity, booking interval length and amenities. An unsupported sport or interval length SHALL be rejected. Creating a venue SHALL NOT create any court or court grouping implicitly. Recurring sessions SHALL take the interval length from the court itself. Availability, quotes and customer bookings SHALL follow the 30-minute booking grid defined in "Thirty-minute booking interval", whatever the court's interval length. Sport filters on public court and venue lists SHALL match the courts' own sport. The platform SHALL NOT expose sport-zone list, detail or management endpoints.

#### Scenario: Operator creates a court
- **WHEN** the owner creates a court with sport pickleball and no interval length while one of their venues is selected
- **THEN** the court SHALL be stored under that venue with sport pickleball, a 60-minute interval and status inactive, and SHALL appear under the pickleball filter only after it is opened and its venue is eligible

#### Scenario: Court on an inactive or closed venue
- **WHEN** the owner creates a court on an inactive venue, on a closed venue, and on a venue of another organization
- **THEN** the first SHALL be created inactive, the second SHALL be rejected with 422 and the third SHALL be refused with 403

#### Scenario: Sport or venue change
- **WHEN** the owner updates a court with a different sport or a different venue
- **THEN** the request SHALL be rejected with 422 and the court SHALL stay unchanged

#### Scenario: Unsupported sport
- **WHEN** an operator submits a court with a sport outside the supported list
- **THEN** the request SHALL be rejected with a validation error and no court SHALL be created or changed

#### Scenario: Unsupported interval length
- **WHEN** an operator creates or updates a court with a 45-minute interval length
- **THEN** the request SHALL be rejected with 422 and no court SHALL be created or changed

#### Scenario: Interval length does not shape customer bookings
- **WHEN** a court has a 90-minute interval length and a customer requests 15:30–16:30 on it
- **THEN** availability and quotes SHALL offer and price that interval on the 30-minute grid, and recurring sessions on the court SHALL still use 90 minutes

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

#### Scenario: Existing 45-minute courts
- **WHEN** the 30-minute grid is migrated onto a court whose interval length is 45 minutes
- **THEN** its interval length SHALL become 60 minutes, and its existing bookings and recurring occurrences SHALL keep their recorded times
