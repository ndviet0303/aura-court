## MODIFIED Requirements

### Requirement: Role-based owner operations
Organization owners and authorized employees SHALL access assigned venues and functions including catalog, calendars, employees, POS, memberships and reports through both UI and API. Permissions SHALL be the intersection of active organization membership, venue assignment, the role's fixed permission set and effective feature entitlement. Staff is an employee permission profile, not platform administration. At their assigned venues, staff SHALL view venue and court information, run daily operations, pause a court for maintenance or an incident, restore a court from such a pause, and handle affected bookings. Only the organization owner SHALL create or edit venues and courts, set operating hours, court service schedules and a court's price-table link, change prices, open a venue or court, pause or resume a venue, pause a court for business reasons or restore it from that pause, and close a venue or court. Only the organization owner SHALL manage pricing and promotions for the venues of their organization: list and view price tables, create them, publish their versions, apply, replace or end their court links, retire them and read price coverage; and create, update and stop promotion programs and view their codes, QR codes and usage. Staff SHALL NOT restore a court the owner paused for business reasons and SHALL NOT settle refunds; any refund is agreed and transferred by the owner outside the platform. A denied operation SHALL return 403 without changes. Platform roles MUST NOT be granted through employee invitations. Legacy payouts SHALL be available only to separately authorized finance actors for pre-cutover obligations.

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

#### Scenario: Staff attempt pricing or promotion management
- **WHEN** staff assigned to a venue try to open its price tables, publish a version, apply or retire a table, or create, update or stop a promotion program or open its codes and usage
- **THEN** the platform SHALL answer 403, nothing SHALL change, and the staff interface SHALL NOT offer price or promotion management

#### Scenario: Owner of another organization
- **WHEN** an owner opens or changes a price table or promotion program of a venue in another organization
- **THEN** the platform SHALL answer 403 without revealing or changing it
