## MODIFIED Requirements

### Requirement: Role-based owner operations
Organization owners and authorized employees SHALL access assigned venues and functions including catalog, calendars, employees, POS, memberships and operational reports through both UI and API. Permissions SHALL be the intersection of active organization membership, venue assignment, the role's fixed permission set and effective feature entitlement. Staff is an employee permission profile, not platform administration. At their assigned venues, staff SHALL view venue and court information, run daily operations, pause a court for maintenance or an incident, restore a court from such a pause, and handle affected bookings. Only the organization owner SHALL create or edit venues and courts, set operating hours, court service schedules and a court's price-table link, change prices, open a venue or court, pause or resume a venue, pause a court for business reasons or restore it from that pause, and close a venue or court. Only the organization owner SHALL manage pricing and promotions for the venues of their organization: list and view price tables, create them, publish their versions, apply, replace or end their court links, retire them and read price coverage; and create, update and stop promotion programs and view their codes, QR codes and usage. Only the organization owner SHALL view, reconcile and export the financial transactions and financial reports of the venues of their organization, and register, renew or terminate their platform packages; staff SHALL NOT access aggregated financial reports or package management. Staff SHALL NOT restore a court the owner paused for business reasons and SHALL NOT settle refunds; any refund is agreed and transferred by the owner outside the platform. A denied operation SHALL return 403 without changes. Platform roles MUST NOT be granted through employee invitations. Legacy payouts SHALL be available only to separately authorized finance actors for pre-cutover obligations.

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

#### Scenario: Staff attempt finance or package management
- **WHEN** staff assigned to a venue open the financial transactions, the financial report or its export, or try to view, register, renew or terminate a venue package
- **THEN** the platform SHALL answer 403, nothing SHALL change, and the staff interface SHALL NOT offer finance or package screens

#### Scenario: Owner of another organization
- **WHEN** an owner opens or changes a price table, promotion program, financial report or package of a venue in another organization
- **THEN** the platform SHALL answer 403 without revealing or changing it

### Requirement: Real staff invitation and permission lifecycle
Invitations SHALL identify organization, venue assignments and expiry, create a pending staff invitation, send a persisted notification and activate membership only after the intended recipient accepts. Invitations SHALL only grant the staff role; ownership comes solely from partner approval. Duplicate accepts SHALL be idempotent; revocation/expiry SHALL prevent activation. Owners SHALL manage staff grants without creating global admins, no platform package SHALL limit the number of staff, and owner memberships SHALL NOT be edited or removed through staff management. API/UI MUST preserve selected venue assignments and audit actor, reason and outcome.

#### Scenario: Invite accepted
- **WHEN** the addressed user accepts a valid invitation twice
- **THEN** exactly one scoped membership SHALL become active and notification/UI SHALL reflect actual delivery and acceptance

#### Scenario: Scope escalation
- **WHEN** an owner tries to invite someone as an organization owner or to grant owner rights through staff management
- **THEN** the request SHALL grant at most the staff role at the selected venues, and no account SHALL acquire organization ownership or global admin rights

#### Scenario: Staff quota and owner rows
- **WHEN** a mutation targets an owner membership through staff management, or an owner invites more staff than any former plan allowed
- **THEN** the first SHALL fail atomically, and the second SHALL follow the invitation rules above without a quota rejection
