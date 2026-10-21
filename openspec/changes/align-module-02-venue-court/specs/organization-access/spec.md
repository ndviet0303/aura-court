## ADDED Requirements

### Requirement: Organization-scoped venue and court reads
Reads of an organization's venues and courts through organization operation interfaces SHALL be scoped to the organization the request names and to the caller's current active membership role in it. The organization owner SHALL read every venue of the organization in any activity state, with its courts. Staff SHALL read only the venues currently assigned to them: the venue's name, address, contact details, description, images, amenities, operating hours, dated schedule exceptions and activity state, and for each court its code, sport, description, images, service schedule and operational state. Read access SHALL NOT include adding venues or courts or changing venue information, hours, service schedules or prices. A customer-only member, a member of another organization and an account with no membership SHALL receive 403 for every venue identifier, so the status code does not reveal which organization a venue belongs to. An owner or staff member of the named organization who asks for a venue identifier that does not belong to that organization SHALL receive 404, and staff who ask for an unassigned venue of their own organization SHALL receive 403. Platform administrator rights SHALL NOT stand in for a membership on these reads; platform-wide monitoring SHALL use platform administration reads instead. A restricted account SHALL receive 403. Revoking a staff assignment or membership SHALL take effect from the caller's next request. A denied response SHALL contain no venue or court data.

#### Scenario: Owner reads the organization's venues
- **WHEN** the owner lists the organization's venues or opens any one of them, including a venue that is not yet active
- **THEN** every venue of that organization SHALL be returned with its courts, and no venue of another organization SHALL appear

#### Scenario: Staff read only assigned venues
- **WHEN** staff assigned to venue A of an organization that also has venue B list its venues and open A's operating hours, schedule exceptions and court service schedules
- **THEN** only venue A SHALL be listed, A's hours, exceptions and court schedules SHALL be returned, and every read of venue B SHALL return 403 without B's data

#### Scenario: Customer member or outsider
- **WHEN** a customer-only member of the organization, the owner of another organization or an account with no membership requests the organization's venue list or any venue identifier, existing or not
- **THEN** each request SHALL return 403 without venue data

#### Scenario: Venue of another organization through the member's own organization
- **WHEN** the owner of organization A requests a venue of organization B through organization A
- **THEN** the platform SHALL answer 404 without that venue's data

#### Scenario: Platform administrator on organization reads
- **WHEN** a platform administrator without a membership in the organization requests its venue list or a venue
- **THEN** the platform SHALL answer 403

#### Scenario: Assignment revoked
- **WHEN** the owner removes venue A from a staff member's assignment
- **THEN** the staff member's next read of venue A or its courts SHALL return 403

### Requirement: Role-scoped organization detail
The organization detail SHALL depend on the caller's role. The organization owner SHALL receive the full detail: every venue in any activity state with its courts, and the member list. A platform administrator SHALL also receive the full detail, although platform administration does not stand in for a membership on organization venue and court reads. Staff SHALL receive only the venues currently assigned to them, with their courts, and SHALL NOT receive the member list or other members' personal data. A customer-only member and a non-member SHALL receive 403 without organization data.

#### Scenario: Staff open the organization detail
- **WHEN** staff assigned to venue A open the detail of an organization that also has venue B
- **THEN** the response SHALL contain venue A with its courts and SHALL NOT contain venue B, the member list or any member's email or phone number

#### Scenario: Customer member opens the organization detail
- **WHEN** a customer-only member of the organization opens its detail
- **THEN** the platform SHALL answer 403 without organization data

#### Scenario: Owner and platform administrator open the organization detail
- **WHEN** the owner or a platform administrator opens the organization detail
- **THEN** the response SHALL contain every venue of the organization with its courts and the member list

### Requirement: Venue realtime channel authorization
A subscription to a venue's realtime channel SHALL be granted only to an unrestricted account with a valid session that is the venue organization's owner or staff currently assigned to that venue. A session started before the account's sessions were revoked SHALL be refused with 401. A restricted account, a customer-only member, staff not assigned to that venue, a member of another organization and a platform administrator without a membership SHALL be refused with 403. A refused subscription SHALL receive no venue events.

#### Scenario: Assigned staff subscribe
- **WHEN** staff assigned to venue A subscribe to the realtime channels of venue A and of sibling venue B
- **THEN** the subscription to A SHALL be granted and the subscription to B SHALL be refused

#### Scenario: Restricted account or revoked session
- **WHEN** a staff member assigned to the venue subscribes after the account was locked, or with a session started before the account's sessions were revoked
- **THEN** the locked account SHALL be refused with 403, the revoked session SHALL be refused with 401, and neither SHALL receive venue events

#### Scenario: Non-operators subscribe
- **WHEN** a customer-only member or a platform administrator without a membership subscribes to a venue's realtime channel
- **THEN** the subscription SHALL be refused

## MODIFIED Requirements

### Requirement: Role-based owner operations
Organization owners and authorized employees SHALL access assigned venues and functions including catalog, calendars, pricing, employees, POS, memberships, promotions and reports through both UI and API. Permissions SHALL be the intersection of active organization membership, venue assignment, the role's fixed permission set and effective feature entitlement. Staff is an employee permission profile, not platform administration. At their assigned venues, staff SHALL view venue and court information, run daily operations, pause a court for maintenance or an incident, restore a court from such a pause, and handle affected bookings. Only the organization owner SHALL create or edit venues and courts, set operating hours, court service schedules and a court's price-table link, change prices, open a venue or court, pause or resume a venue, pause a court for business reasons or restore it from that pause, and close a venue or court. Staff SHALL NOT restore a court the owner paused for business reasons and SHALL NOT settle refunds; any refund is agreed and transferred by the owner outside the platform. A denied operation SHALL return 403 without changes. Platform roles MUST NOT be granted through employee invitations. Legacy payouts SHALL be available only to separately authorized finance actors for pre-cutover obligations.

#### Scenario: Venue manager changes venue operations
- **WHEN** a manager operates one assigned venue in a multi-venue organization
- **THEN** permitted actions SHALL work there while sibling venue identifiers and exports are denied

#### Scenario: Owner without global admin
- **WHEN** an approved customer-role owner opens their management screens
- **THEN** authorized organization actions SHALL work without granting platform_admin or legacy admin

#### Scenario: Staff requests payout administration
- **WHEN** a staff member attempts legacy payout or organization administration
- **THEN** the action SHALL be denied without mutations

#### Scenario: Organization switch
- **WHEN** the user switches organization or a grant is revoked
- **THEN** cached UI permissions and direct operations SHALL reflect current grants without hardcoded organization identifiers

#### Scenario: Staff pause and restore a court for maintenance
- **WHEN** staff assigned to a venue pause one of its courts for maintenance or an incident and later restore it
- **THEN** both operations SHALL succeed and record the staff member as actor

#### Scenario: Staff attempt an owner-only operation
- **WHEN** staff try to restore a court the owner paused for business reasons, pause a court for business reasons, pause, resume or close a venue, close a court, open a venue or court, or change hours, service schedules, price-table links or prices
- **THEN** the platform SHALL answer 403 and the venue and court SHALL keep their state and settings
